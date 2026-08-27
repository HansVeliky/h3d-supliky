<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Credits
{
    /**
     * Adds (or removes) credits and records the movement.
     *
     * Runs inside an immediate transaction and re-reads the balance there,
     * so two exports firing at once cannot both pass a balance check and
     * spend the same credit. Refuses to take a balance below zero.
     *
     * @return int the new balance
     * @throws RuntimeException when the balance would go negative
     */
    public static function apply(int $userId, int $delta, string $reason, ?string $ref = null): int
    {
        return Db::transact(function (PDO $pdo) use ($userId, $delta, $reason, $ref) {
            $st = $pdo->prepare('SELECT credits FROM users WHERE id = ?');
            $st->execute([$userId]);
            $current = $st->fetchColumn();

            if ($current === false) {
                throw new RuntimeException('Unknown user.');
            }

            $balance = (int) $current + $delta;
            if ($balance < 0) {
                throw new RuntimeException('Not enough credits.');
            }

            $pdo->prepare('UPDATE users SET credits = ? WHERE id = ?')
                ->execute([$balance, $userId]);

            $pdo->prepare(
                'INSERT INTO ledger (user_id, delta, balance_after, reason, ref, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$userId, $delta, $balance, $reason, $ref, time()]);

            return $balance;
        });
    }

    public static function balance(int $userId): int
    {
        $st = Db::pdo()->prepare('SELECT credits FROM users WHERE id = ?');
        $st->execute([$userId]);
        return (int) $st->fetchColumn();
    }

    public static function history(int $userId, int $limit = 50): array
    {
        $st = Db::pdo()->prepare(
            'SELECT * FROM ledger WHERE user_id = ? ORDER BY id DESC LIMIT ?'
        );
        $st->execute([$userId, $limit]);
        return $st->fetchAll();
    }

    /**
     * Cross-check: the working balance on each user must equal the sum of
     * that user's ledger. Any row returned here is a bug worth knowing about.
     */
    public static function audit(): array
    {
        // There are two separate checks here. The first compares the cached
        // account balance with the sum of all credit movements. The second
        // walks the immutable history and checks that every balance_after is
        // actually previous_balance + delta. This catches old overdraw/clamp
        // bugs even after the account's current balance has been repaired.
        $pdo = Db::pdo();
        $users = $pdo->query(
            'SELECT u.id, u.email, u.credits, COALESCE(l.total, 0) AS ledger_sum
             FROM users u
             LEFT JOIN (
                 SELECT user_id, SUM(delta) AS total FROM ledger GROUP BY user_id
             ) l ON l.user_id = u.id'
        )->fetchAll();

        $out = [];
        $st = $pdo->prepare(
            'SELECT id, delta, balance_after, reason, ref, created_at
             FROM ledger WHERE user_id = ? ORDER BY id'
        );

        foreach ($users as $u) {
            $st->execute([(int) $u['id']]);
            $rows = $st->fetchAll();
            $previous = null;
            $chainErrors = [];

            foreach ($rows as $r) {
                // ledger_repair is an accounting correction, not a customer
                // movement. It deliberately fixes the total without pretending
                // that the customer's cached balance moved again.
                if ((string) $r['reason'] === 'ledger_repair') {
                    continue;
                }

                $delta = (int) $r['delta'];
                $actual = (int) $r['balance_after'];
                $expected = $previous === null ? $delta : $previous + $delta;

                if ($actual !== $expected) {
                    $chainErrors[] = [
                        'id' => (int) $r['id'],
                        'delta' => $delta,
                        'expected' => $expected,
                        'actual' => $actual,
                        'reason' => (string) $r['reason'],
                        'ref' => (string) ($r['ref'] ?? ''),
                    ];
                }
                if ($actual < 0) {
                    $chainErrors[] = [
                        'id' => (int) $r['id'],
                        'delta' => $delta,
                        'expected' => $expected,
                        'actual' => $actual,
                        'reason' => (string) $r['reason'],
                        'ref' => (string) ($r['ref'] ?? ''),
                        'negative' => true,
                    ];
                }
                $previous = $actual;
            }

            $mismatch = (int) $u['credits'] - (int) $u['ledger_sum'];
            if ($mismatch !== 0 || $chainErrors) {
                $first = $chainErrors[0] ?? null;
                $out[] = [
                    'id' => (int) $u['id'],
                    'email' => (string) $u['email'],
                    'credits' => (int) $u['credits'],
                    'ledger_sum' => (int) $u['ledger_sum'],
                    'mismatch' => $mismatch,
                    'chain_errors' => count($chainErrors),
                    'first_chain_error' => $first,
                ];
            }
        }

        return $out;
    }

    /**
     * Adds one transparent correction row when a legacy write left the
     * balance cache and its append-only ledger out of sync. It never edits or
     * deletes history, so the original event remains auditable.
     *
     * @return int correction applied to the ledger; zero when already sound
     */
    public static function reconcile(int $userId): int
    {
        return Db::transact(static function (PDO $pdo) use ($userId): int {
            $st = $pdo->prepare('SELECT email, credits FROM users WHERE id = ?');
            $st->execute([$userId]);
            $user = $st->fetch();
            if (!$user) {
                throw new RuntimeException('Unknown user.');
            }

            $sum = $pdo->prepare('SELECT COALESCE(SUM(delta), 0) FROM ledger WHERE user_id = ?');
            $sum->execute([$userId]);
            $ledgerSum = (int) $sum->fetchColumn();
            $balance = (int) $user['credits'];
            $delta = $balance - $ledgerSum;
            if ($delta === 0) {
                return 0;
            }

            // Capture the first historical inconsistency so the correction is
            // self-explanatory in the account intervention log. In particular,
            // this documents the old pattern where a negative code could try
            // to remove more credits than the account actually had, while the
            // balance was clamped to zero.
            $rows = $pdo->prepare(
                'SELECT id, delta, balance_after, reason, ref FROM ledger
                 WHERE user_id = ? ORDER BY id'
            );
            $rows->execute([$userId]);
            $previous = null;
            $problem = null;
            foreach ($rows->fetchAll() as $r) {
                if ((string) $r['reason'] === 'ledger_repair') continue;
                $expected = $previous === null
                    ? (int) $r['delta']
                    : $previous + (int) $r['delta'];
                $actual = (int) $r['balance_after'];
                if ($problem === null && $actual !== $expected) {
                    $problem = 'řádek #' . (int) $r['id']
                        . ': pohyb ' . Cred::fmtCs((int) $r['delta'])
                        . ', očekávaný zůstatek ' . Cred::fmtCs($expected)
                        . ', zapsaný zůstatek ' . Cred::fmtCs($actual)
                        . ', důvod ' . (string) $r['reason'];
                    if ((int) $r['delta'] < 0 && $expected < 0 && $actual === 0) {
                        $problem .= '; historicky se odečítalo více kreditů, než účet měl, zůstatek byl oříznut na 0, ale do účetní knihy se zapsal celý požadovaný záporný pohyb';
                    }
                }
                $previous = $actual;
            }

            $ref = 'oprava účetní knihy adminem; rozdíl '
                . ($delta >= 0 ? '+' : '-') . Cred::fmtCs(abs($delta))
                . ' kreditu; zůstatek účtu ' . Cred::fmtCs($balance)
                . '; součet pohybů před opravou ' . Cred::fmtCs($ledgerSum)
                . ($problem !== null ? '; zjištěná historická chyba: ' . $problem : '');

            $pdo->prepare(
                'INSERT INTO ledger (user_id, delta, balance_after, reason, ref, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$userId, $delta, $balance, 'ledger_repair', $ref, time()]);

            return $delta;
        });
    }

}
