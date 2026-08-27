<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Clearing test data.
 *
 * Split into scopes rather than one big button, because "reset" almost never
 * means the same thing twice: usually you want the orders gone but the
 * packages kept, or the whole thing back to a blank install. Each scope says
 * exactly what it removes and what it leaves alone.
 *
 * Nothing here is undoable, so the panel takes a backup first and every call
 * is guarded by the caller re-entering their password.
 */
final class Reset
{
    /** scope => [label, description] */
    public const SCOPES = [
        'chats' => [
            'Aktivní chaty',
            'Smaže pouze aktuálně otevřené chaty s podporou. Archivované konverzace zůstanou beze změny.',
        ],
        'conversations' => [
            'Veškeré konverzace',
            'Smaže všechny chaty a jejich kompletní přepisy z databáze včetně zpráv, odpovědí a hodnocení.',
        ],
        'activity' => [
            'Aktivita',
            'Historie exportů, log e-mailů a pokusy o přihlášení. Kredity ani objednávky se nemění.',
        ],
        'commerce' => [
            'Objednávky a kredity',
            'Objednávky, pohyby kreditů, uplatnění kódů a historie cen. Zůstatky se vynulují. Účty zůstanou.',
        ],
        'users' => [
            'Uživatelé',
            'Všechny účty kromě tvého, se vším, co k nim patří.',
        ],
        'catalog' => [
            'Katalog',
            'Balíčky, kódy a platební metody. Měny a nastavení zůstanou.',
        ],
        'settings' => [
            'Nastavení',
            'Vrátí všechna nastavení na výchozí hodnoty, včetně SMTP a slevy.',
        ],
    ];

    /**
     * Runs the selected scopes.
     *
     * @param string[] $scopes
     * @return array<string,string> what each scope did
     */
    public static function run(array $scopes, int $keepUserId): array
    {
        $pdo = Db::pdo();
        $done = [];

        foreach ($scopes as $scope) {
            switch ($scope) {
                case 'chats':
                    $pdo->beginTransaction();
                    try {
                        $ids = $pdo->query("SELECT id FROM user_messages WHERE closed_at IS NULL")->fetchAll(PDO::FETCH_COLUMN);
                        $nMessages = count($ids);
                        $nPosts = 0;
                        $nFeedback = 0;
                        if ($ids) {
                            $marks = implode(',', array_fill(0, count($ids), '?'));
                            $st = $pdo->prepare("SELECT COUNT(*) FROM user_message_posts WHERE message_id IN ($marks)");
                            $st->execute($ids);
                            $nPosts = (int) $st->fetchColumn();
                            $st = $pdo->prepare("SELECT COUNT(*) FROM support_feedback WHERE message_id IN ($marks)");
                            $st->execute($ids);
                            $nFeedback = (int) $st->fetchColumn();
                            $st = $pdo->prepare("DELETE FROM support_feedback WHERE message_id IN ($marks)");
                            $st->execute($ids);
                            $st = $pdo->prepare("DELETE FROM user_message_posts WHERE message_id IN ($marks)");
                            $st->execute($ids);
                            $st = $pdo->prepare("DELETE FROM user_messages WHERE id IN ($marks)");
                            $st->execute($ids);
                        }
                        $pdo->commit();
                        $done[$scope] = $nMessages . ' aktivních chatů, ' . $nPosts . ' zpráv a ' . $nFeedback . ' hodnocení smazáno';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $e;
                    }
                    break;

                case 'conversations':
                    $pdo->beginTransaction();
                    try {
                        $nMessages = (int) $pdo->query('SELECT COUNT(*) FROM user_messages')->fetchColumn();
                        $nPosts = (int) $pdo->query('SELECT COUNT(*) FROM user_message_posts')->fetchColumn();
                        $nFeedback = (int) $pdo->query('SELECT COUNT(*) FROM support_feedback')->fetchColumn();
                        $pdo->exec('DELETE FROM support_feedback');
                        $pdo->exec('DELETE FROM user_message_posts');
                        $pdo->exec('DELETE FROM user_messages');
                        $pdo->commit();
                        $done[$scope] = $nMessages . ' konverzací, ' . $nPosts . ' zpráv a ' . $nFeedback . ' hodnocení smazáno';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        throw $e;
                    }
                    break;
                case 'activity':
                    $n = self::wipe($pdo, ['exports', 'mail_log', 'login_attempts']);
                    $done[$scope] = "$n řádků smazáno";
                    break;

                case 'commerce':
                    $n = self::wipe($pdo, ['orders', 'ledger', 'code_uses', 'price_history']);
                    // Balances live on the user row as a cache of the ledger,
                    // so clearing the ledger without clearing them would
                    // leave the audit permanently complaining.
                    $pdo->exec('UPDATE users SET credits = 0');
                    $pdo->exec('UPDATE codes SET uses = 0');
                    $done[$scope] = "$n řádků smazáno, zůstatky vynulovány";
                    break;

                case 'users':
                    $st = $pdo->prepare('DELETE FROM users WHERE id != ?');
                    $st->execute([$keepUserId]);
                    $done[$scope] = $st->rowCount() . ' účtů smazáno';
                    break;

                case 'catalog':
                    $n = self::wipe($pdo, ['packages', 'codes', 'code_uses', 'payment_methods', 'price_history']);
                    $done[$scope] = "$n řádků smazáno";
                    break;

                case 'settings':
                    $pdo->exec('DELETE FROM settings');
                    Settings::forget();
                    $done[$scope] = 'vráceno na výchozí';
                    break;
            }
        }

        return $done;
    }

    /** @param string[] $tables */
    private static function wipe(PDO $pdo, array $tables): int
    {
        $total = 0;
        foreach ($tables as $t) {
            try {
                $total += (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
                $pdo->exec("DELETE FROM $t");
            } catch (Throwable $e) {
                // A table that does not exist yet is not a failure.
                error_log('[h3d] reset skipped ' . $t . ': ' . $e->getMessage());
            }
        }
        return $total;
    }

    /**
     * Removes the database entirely, so the next request rebuilds it from
     * scratch and the first registration becomes the administrator again.
     *
     * The instance token goes with it: keeping it would leave the new
     * database under the old unguessable name, which is harmless but
     * confusing when comparing backups.
     */
    public static function wipeEverything(): void
    {
        $path = Db::path();
        Db::close();

        foreach (['', '-wal', '-shm'] as $suffix) {
            if (is_file($path . $suffix)) {
                @unlink($path . $suffix);
            }
        }

        $token = dirname($path) . '/instance.php';
        if (is_file($token)) {
            @unlink($token);
        }
    }

    /** Row counts, so the panel can say what is about to disappear. */
    public static function counts(): array
    {
        $tables = ['users', 'orders', 'ledger', 'exports', 'codes', 'code_uses',
                   'packages', 'payment_methods', 'currencies', 'mail_log', 'price_history',
                   'user_messages', 'user_message_posts', 'support_feedback'];
        $out = [];
        foreach ($tables as $t) {
            try {
                $out[$t] = (int) Db::pdo()->query("SELECT COUNT(*) FROM $t")->fetchColumn();
            } catch (Throwable $e) {
                $out[$t] = 0;
            }
        }
        return $out;
    }
}
