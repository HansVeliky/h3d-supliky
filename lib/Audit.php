<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * A per-account activity timeline for the admin.
 *
 * Nothing new is stored: the history is stitched together from the records
 * that already exist - the ledger (credit movements), redeemed codes, and the
 * lifecycle timestamps on orders - so an administrator can see how an account
 * got to where it is and check a customer's story against it.
 *
 * Each event is ['at' => unix, 'kind' => string, 'text' => string, 'detail' => string].
 */
final class Audit
{
    /** @return list<array{at:int,kind:string,text:string,detail:string}> */
    public static function forUser(int $userId, string $lang = 'cs'): array
    {
        $pdo    = Db::pdo();
        $events = [];
        $cs     = $lang !== 'en';
        $t = static fn(string $en, string $cz): string => $cs ? $cz : $en;

        // ---- messages to the administrator ----
        try {
            $st = $pdo->prepare(
                'SELECT id, subject, created_at, read_at, closed_at
                 FROM user_messages WHERE user_id = ? ORDER BY created_at'
            );
            $st->execute([$userId]);
            foreach ($st->fetchAll() as $m) {
                $status = $m['closed_at'] !== null
                    ? $t('conversation closed', 'konverzace uzavřena')
                    : (!empty($m['read_at'])
                        ? $t('read by administrator', 'přečteno administrátorem')
                        : $t('not read yet', 'zatím nepřečteno'));

                $events[] = [
                    'at' => (int) $m['created_at'],
                    'kind' => $m['closed_at'] !== null ? 'message-closed' : 'message',
                    'text' => $t('Support message: ', 'Zpráva administrátorovi: ') . (string) $m['subject'],
                    'detail' => $status,
                ];

                if ($m['closed_at'] !== null) {
                    $events[] = [
                        'at' => (int) $m['closed_at'],
                        'kind' => 'message-closed',
                        'text' => $t('Conversation closed: ', 'Konverzace uzavřena: ') . (string) $m['subject'],
                        'detail' => $t(
                            'open the conversation from the Messages tab for the full thread',
                            'celou konverzaci otevřeš v kartě Konverzace přes Informace'
                        ),
                    ];
                }
            }
        } catch (Throwable $e) {}

        // ---- credit movements, minus the code ones (shown from code_uses) ----
        try {
            $st = $pdo->prepare(
                "SELECT delta, balance_after, reason, ref, created_at
                 FROM ledger WHERE user_id = ? AND reason != 'code' ORDER BY created_at"
            );
            $st->execute([$userId]);
            $whyMap = $cs ? [
                'order' => 'za objednávku', 'admin' => 'ručně adminem',
                'admin_bulk' => 'hromadně adminem', 'signup' => 'bonus za registraci',
                'daily' => 'denní kredity', 'anonymised' => 'anonymizace účtu',
                'referral' => 'doporučení kamaráda',
            ] : [
                'order' => 'for an order', 'admin' => 'by an admin',
                'admin_bulk' => 'bulk by an admin', 'signup' => 'signup bonus',
                'daily' => 'daily credits', 'anonymised' => 'account anonymised',
                'referral' => 'friend referral',
            ];
            foreach ($st->fetchAll() as $r) {
                $delta  = (int) $r['delta'];
                $reason = (string) $r['reason'];
                $ref    = ref_label($r['ref'] ?? null);
                $ref    = $ref === '-' ? '' : $ref;

                // Ledger repairs are administrative interventions, not a
                // real customer credit grant. Keep the complete diagnostic
                // note in the intervention protocol so an administrator can
                // later see why the correction was made.
                if ($reason === 'ledger_repair') {
                    $events[] = [
                        'at'     => (int) $r['created_at'],
                        'kind'   => 'ledger-repair',
                        'text'   => $t('Ledger repaired by administrator', 'Účetní kniha opravena administrátorem'),
                        'detail' => (string) ($r['ref'] ?? ''),
                    ];
                    continue;
                }

                // Subscription changes ride the ledger as zero-credit rows;
                // "Added 0 credits" would bury what actually happened.
                if ($reason === 'sub_cancel' || $reason === 'sub_set') {
                    $events[] = [
                        'at'     => (int) $r['created_at'],
                        'kind'   => 'order',
                        'text'   => $reason === 'sub_cancel'
                            ? $t('Subscription cancelled by admin', 'Předplatné zrušeno adminem')
                            : $t('Subscription set by admin', 'Předplatné nastaveno adminem'),
                        'detail' => $ref,
                    ];
                    continue;
                }

                $why = $whyMap[$reason] ?? $reason;
                if ($reason === 'daily' && preg_match('/^(\d{4}-\d{2}-\d{2})$/', (string) ($r['ref'] ?? ''), $dm)) {
                    $ref = $t('allocation day ', 'denní přidělení ') . $dm[1] . ($ref !== '' ? ' · ' . $ref : '');
                }

                $events[] = [
                    'at'   => (int) $r['created_at'],
                    'kind' => $delta >= 0 ? 'credit-up' : 'credit-down',
                    'text' => ($delta >= 0 ? $t('Added ', 'Připsáno ') : $t('Removed ', 'Odečteno '))
                            . Cred::fmtCs(abs($delta)) . $t(' credits (', ' kreditů (') . $why . ')',
                    'detail' => $t('balance ', 'zůstatek ') . Cred::fmtCs((int) $r['balance_after'])
                              . ($ref !== '' ? ' · ' . $ref : ''),
                ];
            }
        } catch (Throwable $e) {}

        // ---- redeemed codes (carry both credits and any days) ----
        try {
            $st = $pdo->prepare(
                'SELECT cu.created_at, c.label, c.code, c.credits, c.sub_days
                 FROM code_uses cu JOIN codes c ON c.id = cu.code_id
                 WHERE cu.user_id = ?'
            );
            $st->execute([$userId]);
            foreach ($st->fetchAll() as $r) {
                $bits = [];
                if ((int) $r['credits'] !== 0) {
                    $bits[] = Cred::fmtCs((int) $r['credits']) . $t(' credits', ' kreditů');
                }
                if ((int) ($r['sub_days'] ?? 0) > 0) {
                    $bits[] = Orders::durationLabel((int) $r['sub_days'], $cs ? 'cs' : 'en') . $t(' unlimited', ' neomezeně');
                }
                $events[] = [
                    'at'     => (int) $r['created_at'],
                    'kind'   => 'code',
                    'text'   => $t('Redeemed code ', 'Uplatnil kód ') . (trim((string) ($r['label'] ?? '')) !== '' ? $r['label'] : $r['code']),
                    'detail' => $bits ? '+ ' . implode(' + ', $bits) : '',
                ];
            }
        } catch (Throwable $e) {}

        // ---- order lifecycle ----
        try {
            $st = $pdo->prepare(
                'SELECT reference, price_cents, currency, credits, sub_days,
                        created_at, accepted_at, paid_at, cancelled_at
                 FROM orders WHERE user_id = ?'
            );
            $st->execute([$userId]);
            foreach ($st->fetchAll() as $o) {
                $ref    = (string) $o['reference'];
                $amount = money((int) $o['price_cents'], $o['currency'] ?? null);

                $reward = [];
                if ((int) $o['credits'] !== 0) {
                    $reward[] = Cred::fmtCs((int) $o['credits']) . $t(' credits', ' kreditů');
                }
                if ((int) ($o['sub_days'] ?? 0) > 0) {
                    $reward[] = Orders::durationLabel((int) $o['sub_days'], $cs ? 'cs' : 'en') . $t(' unlimited', ' neomezeně');
                }
                $rewardText = $reward ? implode(' + ', $reward) : '';

                $events[] = ['at' => (int) $o['created_at'], 'kind' => 'order',
                    'text' => $t('Placed order ', 'Vytvořil objednávku ') . $ref, 'detail' => $amount];

                if (!empty($o['accepted_at'])) {
                    $events[] = ['at' => (int) $o['accepted_at'], 'kind' => 'order',
                        'text' => $t('Order ', 'Objednávka ') . $ref . $t(' accepted for processing', ' přijata ke zpracování'), 'detail' => ''];
                }
                if (!empty($o['paid_at'])) {
                    $events[] = ['at' => (int) $o['paid_at'], 'kind' => 'order-paid',
                        'text' => $t('Order ', 'Objednávka ') . $ref . $t(' paid', ' zaplacena'), 'detail' => $rewardText];
                }
                if (!empty($o['cancelled_at'])) {
                    $events[] = ['at' => (int) $o['cancelled_at'], 'kind' => 'order-cancel',
                        'text' => $t('Order ', 'Objednávka ') . $ref . $t(' cancelled', ' zrušena'), 'detail' => ''];
                }
            }
        } catch (Throwable $e) {}

        usort($events, static fn($a, $b) => $b['at'] <=> $a['at']);

        return $events;
    }
}
