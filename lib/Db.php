<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * SQLite storage. One file, no server to configure — the whole point is
 * that this drops onto a NAS next to the rest of the app.
 */
final class Db
{
    private static ?PDO $pdo = null;

    /**
     * Database file path.
     *
     * The filename carries a random token generated on first run. The
     * .htaccess deny rule is the primary protection, but it only works on
     * Apache with AllowOverride on — on nginx it is ignored silently. An
     * unguessable filename means that a misconfigured server still does not
     * hand the whole user table to anyone who requests the obvious path.
     * The token lives in a .php file, which any web server executes rather
     * than dumps.
     */
    private static ?string $pathCache = null;

    public static function path(): string
    {
        if (self::$pathCache !== null) {
            return self::$pathCache;
        }

        // The data directory can be redirected with H3D_DATA_DIR, so tests and
        // one-off scripts can run against a throwaway database instead of the
        // real one. Production never sets it, so nothing changes there.
        $override = getenv('H3D_DATA_DIR');
        $dir = ($override !== false && $override !== '') ? $override : dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $confFile = $dir . '/instance.php';

        if (is_file($confFile)) {
            $token = (string) (require $confFile);

            if (!preg_match('/^[0-9a-f]{16}$/', $token)) {
                throw new RuntimeException('data/instance.php is corrupt; the database name cannot be resolved.');
            }
        } else {
            // Migrate an existing install that still uses the fixed name.
            $legacy = $dir . '/h3d.sqlite';
            $token  = bin2hex(random_bytes(8));

            file_put_contents(
                $confFile,
                "<?php\n" .
                "// Instance token: part of the database filename.\n" .
                "// Losing this file orphans the database.\n" .
                "if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }\n" .
                "return " . var_export($token, true) . ";\n",
                LOCK_EX
            );
            @chmod($confFile, 0640);

            if (is_file($legacy)) {
                foreach (['', '-wal', '-shm'] as $suffix) {
                    if (is_file($legacy . $suffix)) {
                        @rename($legacy . $suffix, $dir . '/h3d-' . $token . '.sqlite' . $suffix);
                    }
                }
            }
        }

        return self::$pathCache = $dir . '/h3d-' . $token . '.sqlite';
    }

    /**
     * Drops the connection and the cached path, so the next call reopens
     * from scratch. Needed when the file itself is about to be deleted:
     * SQLite keeps the handle valid on an unlinked file, which would leave
     * writes going into a database nobody can see.
     */
    public static function close(): void
    {
        self::$pdo = null;
        self::$pathCache = null;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dir = dirname(self::path());
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create the data directory.');
        }

        $pdo = new PDO('sqlite:' . self::path(), null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 20,
        ]);

        // WAL keeps readers from blocking the writer, and a busy timeout
        // means concurrent exports wait instead of failing outright.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 20000');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');

        self::$pdo = $pdo;
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_meta (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        )');

        $version = (int) (self::meta($pdo, 'version') ?? '0');

        if ($version < 1) {
            $pdo->exec('
            CREATE TABLE IF NOT EXISTS settings (
                key   TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                email         TEXT    NOT NULL UNIQUE,
                pass_hash     TEXT    NOT NULL,
                credits       INTEGER NOT NULL DEFAULT 0,
                is_admin      INTEGER NOT NULL DEFAULT 0,
                is_blocked    INTEGER NOT NULL DEFAULT 0,
                created_at    INTEGER NOT NULL,
                last_login_at INTEGER
            );

            -- Append-only history. users.credits is the working balance and
            -- is only ever changed in the same transaction as a ledger row,
            -- so the two can be cross-checked from the admin panel.
            CREATE TABLE IF NOT EXISTS ledger (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                delta         INTEGER NOT NULL,
                balance_after INTEGER NOT NULL,
                reason        TEXT    NOT NULL,
                ref           TEXT,
                created_at    INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_ledger_user ON ledger(user_id, id DESC);

            CREATE TABLE IF NOT EXISTS codes (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                code       TEXT    NOT NULL UNIQUE,
                credits    INTEGER NOT NULL,
                max_uses   INTEGER NOT NULL DEFAULT 1,
                uses       INTEGER NOT NULL DEFAULT 0,
                expires_at INTEGER,
                active     INTEGER NOT NULL DEFAULT 1,
                note       TEXT,
                created_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS code_uses (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                code_id    INTEGER NOT NULL REFERENCES codes(id) ON DELETE CASCADE,
                user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                created_at INTEGER NOT NULL,
                UNIQUE(code_id, user_id)
            );

            CREATE TABLE IF NOT EXISTS exports (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER REFERENCES users(id) ON DELETE SET NULL,
                ip_hash       TEXT    NOT NULL,
                format        TEXT    NOT NULL,
                boxes         INTEGER NOT NULL,
                credits_spent INTEGER NOT NULL DEFAULT 0,
                created_at    INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_exports_user ON exports(user_id, created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_exports_ip   ON exports(ip_hash, created_at DESC);

            CREATE TABLE IF NOT EXISTS packages (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL,
                credits     INTEGER NOT NULL,
                price_cents INTEGER NOT NULL,
                active      INTEGER NOT NULL DEFAULT 1,
                sort        INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE IF NOT EXISTS orders (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                package_id  INTEGER,
                credits     INTEGER NOT NULL,
                price_cents INTEGER NOT NULL,
                status      TEXT    NOT NULL DEFAULT "pending",
                reference   TEXT    NOT NULL,
                created_at  INTEGER NOT NULL,
                paid_at     INTEGER
            );

            CREATE TABLE IF NOT EXISTS verification_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_hash TEXT NOT NULL,
                email_hash TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_verification_requests_ip ON verification_requests(ip_hash, created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_verification_requests_email ON verification_requests(email_hash, created_at DESC);

            CREATE TABLE IF NOT EXISTS login_attempts (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_hash    TEXT NOT NULL,
                email      TEXT,
                ok         INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_attempts ON login_attempts(ip_hash, created_at DESC);
            ');

            self::setMeta($pdo, 'version', '1');
            $version = 1;
        }

        if ($version < 2) {
            // Daily credit top-up. The date of the last grant is stored per
            // user so the grant can be applied lazily on the next visit
            // instead of needing a cron job on the NAS.
            $pdo->exec("ALTER TABLE users ADD COLUMN daily_granted_on TEXT NOT NULL DEFAULT ''");

            self::setMeta($pdo, 'version', '2');
            $version = 2;
        }

        if ($version < 3) {
            // Email verification. The account works immediately after
            // registration; verification is what releases the welcome
            // credits, so an unverified address costs nothing to keep.
            $pdo->exec("ALTER TABLE users ADD COLUMN verified_at INTEGER");
            $pdo->exec("ALTER TABLE users ADD COLUMN verify_token TEXT");
            $pdo->exec("ALTER TABLE users ADD COLUMN verify_sent_at INTEGER");
            $pdo->exec("ALTER TABLE users ADD COLUMN verify_bonus_at INTEGER");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_verify_token ON users(verify_token)");

            // Cancelling keeps the row and records why.
            $pdo->exec("ALTER TABLE orders ADD COLUMN cancelled_at INTEGER");
            $pdo->exec("ALTER TABLE orders ADD COLUMN admin_note TEXT NOT NULL DEFAULT ''");

            // Outgoing mail log, so a delivery problem is visible instead of
            // silently swallowed.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS mail_log (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    recipient  TEXT NOT NULL,
                    subject    TEXT NOT NULL,
                    kind       TEXT NOT NULL,
                    ok         INTEGER NOT NULL,
                    error      TEXT,
                    created_at INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_mail_log ON mail_log(created_at DESC);
            ");

            // Existing accounts predate verification; treat them as verified
            // rather than retroactively locking their credits.
            $pdo->exec('UPDATE users SET verified_at = created_at WHERE verified_at IS NULL');

            self::setMeta($pdo, 'version', '3');
            $version = 3;
        }

        if ($version < 4) {
            // Readable form of a code, kept alongside the normalised key so
            // the panel can show what was actually printed on the card.
            $pdo->exec("ALTER TABLE codes ADD COLUMN label TEXT NOT NULL DEFAULT ''");
            $pdo->exec("UPDATE codes SET label = code WHERE label = ''");

            self::setMeta($pdo, 'version', '4');
            $version = 4;
        }

        if ($version < 5) {
            // How many times one account may redeem the same code. 1 keeps
            // the old behaviour; a higher number suits a code meant to be
            // used repeatedly by the same person.
            $pdo->exec("ALTER TABLE codes ADD COLUMN uses_per_account INTEGER NOT NULL DEFAULT 1");

            // The unique(code_id, user_id) index on code_uses is what limited
            // it to one, so the table has to be rebuilt without it.
            $pdo->exec("
                CREATE TABLE code_uses_new (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    code_id    INTEGER NOT NULL REFERENCES codes(id) ON DELETE CASCADE,
                    user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    created_at INTEGER NOT NULL
                );
                INSERT INTO code_uses_new (id, code_id, user_id, created_at)
                    SELECT id, code_id, user_id, created_at FROM code_uses;
                DROP TABLE code_uses;
                ALTER TABLE code_uses_new RENAME TO code_uses;
                CREATE INDEX IF NOT EXISTS idx_code_uses ON code_uses(code_id, user_id);
            ");

            self::setMeta($pdo, 'version', '5');
            $version = 5;
        }

        if ($version < 6) {
            // Per-order secret so the status page can be opened straight from
            // an email without signing in. The reference alone is only 24
            // bits of randomness — fine as a payment reference, far too
            // guessable to be the only thing protecting a page.
            $pdo->exec("ALTER TABLE orders ADD COLUMN access_token TEXT NOT NULL DEFAULT ''");

            $rows = $pdo->query('SELECT id FROM orders')->fetchAll(PDO::FETCH_COLUMN);
            $up = $pdo->prepare('UPDATE orders SET access_token = ? WHERE id = ?');
            foreach ($rows as $id) {
                $up->execute([bin2hex(random_bytes(16)), (int) $id]);
            }

            self::setMeta($pdo, 'version', '6');
            $version = 6;
        }

        if ($version < 7) {
            // Packages get the same kind of limits codes already had.
            $pdo->exec("ALTER TABLE packages ADD COLUMN max_uses INTEGER NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE packages ADD COLUMN uses_per_account INTEGER NOT NULL DEFAULT 0");

            // Price history, so the lowest price of the last 30 days can be
            // shown next to a discount. Required by the EU price-indication
            // rules and impossible to reconstruct after the fact, so it has
            // to be recorded as prices change.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS price_history (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    package_id  INTEGER NOT NULL REFERENCES packages(id) ON DELETE CASCADE,
                    price_cents INTEGER NOT NULL,
                    created_at  INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_price_hist ON price_history(package_id, created_at DESC);
            ");

            // Seed with what each package costs today, so the window starts
            // from something true rather than from nothing.
            $rows = $pdo->query('SELECT id, price_cents FROM packages')->fetchAll();
            $ins = $pdo->prepare('INSERT INTO price_history (package_id, price_cents, created_at) VALUES (?, ?, ?)');
            foreach ($rows as $r) {
                $ins->execute([(int) $r['id'], (int) $r['price_cents'], time()]);
            }

            self::setMeta($pdo, 'version', '7');
            $version = 7;
        }

        if ($version < 8) {
            // Credits become a count of tenths so half a credit can exist.
            // Every stored amount is scaled once, here; from this point on
            // the integers in these columns mean tenths, not credits.
            foreach ([
                'UPDATE users    SET credits = credits * 10',
                'UPDATE ledger   SET delta = delta * 10, balance_after = balance_after * 10',
                'UPDATE codes    SET credits = credits * 10',
                'UPDATE packages SET credits = credits * 10',
                'UPDATE orders   SET credits = credits * 10',
            ] as $sql) {
                $pdo->exec($sql);
            }

            // The same for the credit amounts kept in settings.
            $st = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
            $up = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)
                                 ON CONFLICT(key) DO UPDATE SET value = excluded.value');
            foreach (['credit_cost', 'signup_bonus', 'daily_credits', 'daily_credits_cap'] as $k) {
                $st->execute([$k]);
                $v = $st->fetchColumn();
                if ($v !== false) {
                    $up->execute([$k, (string) ((int) $v * 10)]);
                }
            }
            // discount_percent moves to tenths of a percent as well.
            $st->execute(['discount_percent']);
            $v = $st->fetchColumn();
            if ($v !== false) {
                $up->execute(['discount_percent', (string) ((int) $v * 10)]);
            }

            self::setMeta($pdo, 'version', '8');
            $version = 8;
        }

        if ($version < 9) {
            // Payment methods become rows instead of a single PayPal setting,
            // so several can be offered and reordered without a code change.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS payment_methods (
                    id      INTEGER PRIMARY KEY AUTOINCREMENT,
                    kind    TEXT    NOT NULL DEFAULT 'other',
                    label   TEXT    NOT NULL,
                    target  TEXT    NOT NULL DEFAULT '',
                    note    TEXT    NOT NULL DEFAULT '',
                    active  INTEGER NOT NULL DEFAULT 1,
                    sort    INTEGER NOT NULL DEFAULT 0
                );
                CREATE INDEX IF NOT EXISTS idx_pay_sort ON payment_methods(sort, id);
            ");

            // Carry the existing PayPal configuration over as the first row
            // rather than making the admin set it up again.
            $get = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
            $val = static function (string $k) use ($get): string {
                $get->execute([$k]);
                $v = $get->fetchColumn();
                return $v === false ? '' : trim((string) $v);
            };

            $user = $val('paypal_user');
            $link = $val('paypal_link');
            $note = $val('paypal_note');

            if ($user !== '' || $link !== '') {
                $pdo->prepare(
                    'INSERT INTO payment_methods (kind, label, target, note, active, sort)
                     VALUES (?, ?, ?, ?, 1, 0)'
                )->execute([
                    $link !== '' ? 'link' : 'paypal',
                    'PayPal',
                    $link !== '' ? $link : $user,
                    $note,
                ]);
            }

            self::setMeta($pdo, 'version', '9');
            $version = 9;
        }

        if ($version < 10) {
            // A proper numeric variable symbol. Deriving one from the
            // reference produced nonsense - the digits of "H3D-A1B2C3" are
            // "3123", which matches nothing and means nothing. This is the
            // number payments are reconciled by, so it gets its own column.
            $pdo->exec("ALTER TABLE orders ADD COLUMN vs TEXT NOT NULL DEFAULT ''");
            $pdo->exec("UPDATE orders SET vs = printf('%06d', id) WHERE vs = ''");

            self::setMeta($pdo, 'version', '10');
            $version = 10;
        }

        if ($version < 11) {
            // Currencies become rows with a code, a symbol and a rate, and
            // the code is picked from that list instead of typed. A free
            // text field let "EUR" be entered as a euro sign, which the
            // sanitiser then stripped to nothing and quietly replaced with
            // the CZK fallback - so a shop switched to euros kept sending
            // people PayPal links in crowns.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS currencies (
                    code     TEXT PRIMARY KEY,
                    symbol   TEXT NOT NULL DEFAULT '',
                    rate_ppm INTEGER NOT NULL DEFAULT 1000000,
                    decimals INTEGER NOT NULL DEFAULT 2,
                    active   INTEGER NOT NULL DEFAULT 1,
                    sort     INTEGER NOT NULL DEFAULT 0
                );
            ");

            // An order records the currency it was placed in, so changing
            // the shop currency later never relabels old money.
            $pdo->exec("ALTER TABLE orders ADD COLUMN currency TEXT NOT NULL DEFAULT ''");

            // A method can be limited to certain currencies; empty is all.
            $pdo->exec("ALTER TABLE payment_methods ADD COLUMN currencies TEXT NOT NULL DEFAULT ''");

            // Seed the base currency from whatever the setting held, keeping
            // only what looks like a code.
            $st = $pdo->prepare('SELECT value FROM settings WHERE key = ?');
            $st->execute(['currency']);
            $raw  = (string) ($st->fetchColumn() ?: 'CZK');
            $code = preg_replace('/[^A-Z]/', '', strtoupper($raw)) ?? '';
            $code = strlen($code) === 3 ? $code : 'CZK';

            $pdo->prepare('INSERT OR IGNORE INTO currencies (code, symbol, rate_ppm, decimals, active, sort)
                           VALUES (?, ?, 1000000, 2, 1, 0)')
                ->execute([$code, $code === 'CZK' ? 'Kč' : ($code === 'EUR' ? '€' : $code)]);

            $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)
                           ON CONFLICT(key) DO UPDATE SET value = excluded.value')
                ->execute(['currency', $code]);

            $pdo->prepare('UPDATE orders SET currency = ? WHERE currency = \'\'')->execute([$code]);

            self::setMeta($pdo, 'version', '11');
            $version = 11;
        }

        if ($version < 12) {
            // Rates flip to the direction people actually read them: how
            // much base currency one unit costs. "1 EUR = 24.50 CZK" is
            // something you can copy off a rate board; 0.0408 is a number
            // you have to work out first and cannot sanity check.
            $pdo->exec('ALTER TABLE currencies RENAME COLUMN rate_ppm TO per_unit_ppm');

            // old value was units-per-base, so the new one is its reciprocal.
            $pdo->exec('UPDATE currencies
                        SET per_unit_ppm = CAST(1000000000000 / per_unit_ppm AS INTEGER)
                        WHERE per_unit_ppm > 0');
            $pdo->exec('UPDATE currencies SET per_unit_ppm = 1000000 WHERE per_unit_ppm <= 0');

            self::setMeta($pdo, 'version', '12');
            $version = 12;
        }

        if ($version < 13) {
            // The shipped payment instructions were English text stored in a
            // setting, so a Czech reader saw an English paragraph nobody had
            // chosen. Clearing it where it still matches the old default
            // hands those installs the translated wording; anything an admin
            // actually wrote is left alone.
            $pdo->prepare('UPDATE settings SET value = \'\' WHERE key = ? AND value = ?')
                ->execute([
                    'purchase_instructions',
                    'Orders are confirmed manually. Send the amount with the reference '
                    . 'shown on your order and the credits are added once the payment arrives.',
                ]);

            self::setMeta($pdo, 'version', '13');
            $version = 13;
        }

        if ($version < 14) {
            // Three roles instead of one flag. is_admin stays and is kept in
            // step, because a boolean column is what the older queries and
            // any backup script expect; role is what the code reads.
            $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'");
            $pdo->exec("UPDATE users SET role = 'admin' WHERE is_admin = 1");

            // Whether to ask before spending a credit becomes the account
            // holder's choice. NULL means they have not chosen, so the
            // shop-wide default applies.
            $pdo->exec('ALTER TABLE users ADD COLUMN confirm_spend INTEGER DEFAULT NULL');

            self::setMeta($pdo, 'version', '14');
            $version = 14;
        }

        if ($version < 15) {
            // An order can now be taken in hand before it is paid. The
            // timestamp is what the expiry counts from, so it lives with the
            // order rather than being guessed from the log.
            $pdo->exec("ALTER TABLE orders ADD COLUMN accepted_at INTEGER DEFAULT NULL");

            self::setMeta($pdo, 'version', '15');
            $version = 15;
        }

        if ($version < 16) {
            // Forgotten passwords, and a flag for a password that was handed
            // out rather than chosen - one the holder has to replace before
            // the account is theirs again.
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_token TEXT DEFAULT NULL');
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_sent_at INTEGER DEFAULT NULL');
            $pdo->exec('ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0');

            self::setMeta($pdo, 'version', '16');
            $version = 16;
        }

        if ($version < 17) {
            // Time-based packages. A package may grant a number of days of
            // unlimited exports (a week, a month, a year) instead of, or as
            // well as, credits. Days rather than months so a short free trial
            // is expressible too. The length is snapshotted onto the order so
            // editing the package later never rewrites what was sold, and the
            // buyer's cover runs until subscription_until. "featured" lifts a
            // package into the highlighted slot in the shop.
            $pdo->exec("ALTER TABLE packages ADD COLUMN sub_days INTEGER NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE packages ADD COLUMN featured INTEGER NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE orders   ADD COLUMN sub_days INTEGER NOT NULL DEFAULT 0");
            $pdo->exec("ALTER TABLE users    ADD COLUMN subscription_until INTEGER DEFAULT NULL");

            self::setMeta($pdo, 'version', '17');
            $version = 17;
        }

        if ($version < 18) {
            // v17 shipped briefly measuring time-based packages in months, in a
            // "sub_months" column, before the model settled on days and gained
            // "featured". A database that ran that early shape is stuck at
            // version 17 without the current columns, so this step adds each one
            // only where it is missing - covering the early-v17, the final-v17
            // and the fresh-install cases without a duplicate-column error.
            $hasCol = static function (PDO $pdo, string $table, string $col): bool {
                $cols = $pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_COLUMN, 1);
                return in_array($col, $cols, true);
            };
            $addCol = static function (PDO $pdo, string $table, string $col, string $def) use ($hasCol): void {
                if (!$hasCol($pdo, $table, $col)) {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN $col $def");
                }
            };

            $addCol($pdo, 'packages', 'sub_days',           'INTEGER NOT NULL DEFAULT 0');
            $addCol($pdo, 'packages', 'featured',           'INTEGER NOT NULL DEFAULT 0');
            $addCol($pdo, 'orders',   'sub_days',           'INTEGER NOT NULL DEFAULT 0');
            $addCol($pdo, 'users',    'subscription_until', 'INTEGER DEFAULT NULL');

            // Carry any length set under the old month-based column across, so
            // a package or order sold as a subscription does not quietly revert
            // to a plain one. 30 days to the month is close enough here.
            if ($hasCol($pdo, 'packages', 'sub_months')) {
                $pdo->exec('UPDATE packages SET sub_days = sub_months * 30 WHERE sub_days = 0 AND sub_months > 0');
            }
            if ($hasCol($pdo, 'orders', 'sub_months')) {
                $pdo->exec('UPDATE orders SET sub_days = sub_months * 30 WHERE sub_days = 0 AND sub_months > 0');
            }

            self::setMeta($pdo, 'version', '18');
            $version = 18;
        }

        if ($version < 19) {
            // Codes can grant days of unlimited access (a time plan), not only
            // credits - for a "share us on Facebook, get a free week" campaign.
            $cols = $pdo->query('PRAGMA table_info(codes)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('sub_days', $cols, true)) {
                $pdo->exec('ALTER TABLE codes ADD COLUMN sub_days INTEGER NOT NULL DEFAULT 0');
            }

            self::setMeta($pdo, 'version', '19');
            $version = 19;
        }

        if ($version < 20) {
            // Preferred language per account, so e-mails go out in the reader's
            // language rather than one fixed one. NULL = follow the site default.
            $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('lang', $cols, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN lang TEXT DEFAULT NULL');
            }

            self::setMeta($pdo, 'version', '20');
            $version = 20;
        }

        if ($version < 21) {
            // Per-account studio preferences (print area, drawer size, grid…)
            // stored as a small JSON blob so the workspace comes back the way
            // the user left it after they sign in again.
            $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('prefs', $cols, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN prefs TEXT NOT NULL DEFAULT ''");
            }

            self::setMeta($pdo, 'version', '21');
            $version = 21;
        }

        if ($version < 22) {
            // Optional display name, shown on support messages. Not an
            // identifier: signing in is by e-mail address only, and two
            // members may carry the same name. Stored as typed.
            $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('nickname', $cols, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN nickname TEXT NOT NULL DEFAULT ''");
            }

            self::setMeta($pdo, 'version', '22');
            $version = 22;
        }

        if ($version < 23) {
            // Changing the account e-mail is confirmed from the new address, so
            // the pending value and its token wait here until the link is used.
            $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            foreach ([
                'pending_email'      => "ALTER TABLE users ADD COLUMN pending_email TEXT NOT NULL DEFAULT ''",
                'email_change_token' => "ALTER TABLE users ADD COLUMN email_change_token TEXT NOT NULL DEFAULT ''",
                'email_change_at'    => 'ALTER TABLE users ADD COLUMN email_change_at INTEGER',
            ] as $col => $sql) {
                if (!in_array($col, $cols, true)) {
                    $pdo->exec($sql);
                }
            }

            self::setMeta($pdo, 'version', '23');
            $version = 23;
        }

        if ($version < 24) {
            // Referral program: everyone gets a personal invite code, a new
            // account remembers who invited it, and the one-off reward is
            // flagged so it can never be paid twice.
            $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            foreach ([
                'ref_code'     => "ALTER TABLE users ADD COLUMN ref_code TEXT NOT NULL DEFAULT ''",
                'referred_by'  => 'ALTER TABLE users ADD COLUMN referred_by INTEGER',
                'ref_rewarded' => 'ALTER TABLE users ADD COLUMN ref_rewarded INTEGER NOT NULL DEFAULT 0',
            ] as $col => $sql) {
                if (!in_array($col, $cols, true)) {
                    $pdo->exec($sql);
                }
            }

            self::setMeta($pdo, 'version', '24');
            $version = 24;
        }

        if ($version < 25) {
            // Brute-force guard for code redemption: every attempt is logged,
            // and a block row freezes the feature for that account for a day.
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS code_attempts (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id    INTEGER NOT NULL,
                    is_block   INTEGER NOT NULL DEFAULT 0,
                    created_at INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_code_attempts
                    ON code_attempts(user_id, created_at DESC);
            ');

            self::setMeta($pdo, 'version', '25');
            $version = 25;
        }

        if ($version < 26) {
            // A time package can start now or tomorrow - the buyer's choice,
            // stored with the order and honoured when it is fulfilled.
            $cols = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('sub_start', $cols, true)) {
                $pdo->exec("ALTER TABLE orders ADD COLUMN sub_start TEXT NOT NULL DEFAULT ''");
            }

            self::setMeta($pdo, 'version', '26');
            $version = 26;
        }

        if ($version < 27) {
            // A finished refund records how much went back, where and when -
            // the refund receipt is built from these, not from prose notes.
            $cols = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
            foreach ([
                'refund_cents' => 'ALTER TABLE orders ADD COLUMN refund_cents INTEGER',
                'refund_dest'  => "ALTER TABLE orders ADD COLUMN refund_dest TEXT NOT NULL DEFAULT ''",
                'refunded_at'  => 'ALTER TABLE orders ADD COLUMN refunded_at INTEGER',
            ] as $col => $sql) {
                if (!in_array($col, $cols, true)) {
                    $pdo->exec($sql);
                }
            }

            self::setMeta($pdo, 'version', '27');
            $version = 27;
        }

        if ($version < 28) {
            // sub_padding: extra seconds a deferred start added before the
            // paid days began (start "tomorrow" = wait until midnight). A
            // refund reclaims the padding too, otherwise a cancelled plan
            // stays "active" for the leftover gap and confuses everyone.
            // sub_expiry_notified: which expiry the user was mailed about,
            // so the "your plan ended" mail goes out exactly once per plan.
            $cols = $pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('sub_padding', $cols, true)) {
                $pdo->exec('ALTER TABLE orders ADD COLUMN sub_padding INTEGER NOT NULL DEFAULT 0');
            }
            $ucols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('sub_expiry_notified', $ucols, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN sub_expiry_notified INTEGER');
            }

            self::setMeta($pdo, 'version', '28');
            $version = 28;
        }

        if ($version < 29) {
            // Who did what in the admin panel. The per-user history says a
            // plan was cancelled; this says which administrator cancelled
            // it - the question that only comes up once there are two of
            // them, and by then it is too late to start recording.
            $pdo->exec('
                CREATE TABLE IF NOT EXISTS admin_log (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    admin_id   INTEGER NOT NULL,
                    admin_name TEXT NOT NULL DEFAULT \'\',
                    action     TEXT NOT NULL,
                    target     TEXT NOT NULL DEFAULT \'\',
                    detail     TEXT NOT NULL DEFAULT \'\',
                    ok         INTEGER NOT NULL DEFAULT 1,
                    created_at INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_admin_log_time
                    ON admin_log(created_at DESC);
                CREATE INDEX IF NOT EXISTS idx_admin_log_admin
                    ON admin_log(admin_id, created_at DESC);
            ');

            self::setMeta($pdo, 'version', '29');
            $version = 29;
        }

        if ($version < 30) {
            // A time package is now bought first and started later, by the
            // customer, from their account. Paying for a week and having it
            // burn down while an operator was asleep - or while the buyer
            // was on holiday - was the whole problem.
            //
            // NULL means "paid but not started yet"; older orders are
            // stamped with their payment time, because those did start the
            // moment they were settled.
            $pdo->exec('ALTER TABLE orders ADD COLUMN activated_at INTEGER');
            $pdo->exec("UPDATE orders SET activated_at = COALESCE(paid_at, created_at)
                        WHERE sub_days > 0 AND status IN ('paid', 'refund', 'refunded')");

            self::setMeta($pdo, 'version', '30');
            $version = 30;
        }

        if ($version < 31) {
            /*
             * What the sign-up bonus was when this account was created.
             *
             * The bonus was read from the settings at the moment somebody
             * clicked the confirmation link, so changing it in the panel
             * silently rewrote a promise already sent by e-mail: register on
             * Monday with "30 credits and 7 free days" in your inbox, confirm
             * on Wednesday after the operator lowered it, and get whatever
             * Wednesday said.
             *
             * NULL means "made before this column existed" - those accounts
             * fall back to the current settings, which is what they would
             * have got anyway.
             */
            $pdo->exec('ALTER TABLE users ADD COLUMN signup_bonus INTEGER');
            $pdo->exec('ALTER TABLE users ADD COLUMN signup_bonus_days INTEGER');

            self::setMeta($pdo, 'version', '31');
            $version = 31;
        }

        if ($version < 32) {
            // Messages sent by signed-in users to the administrator.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_messages (
                    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id               INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    subject               TEXT NOT NULL,
                    body                  TEXT NOT NULL,
                    contact_consent      INTEGER NOT NULL DEFAULT 0,
                    consent_at            INTEGER,
                    created_at            INTEGER NOT NULL,
                    read_at               INTEGER,
                    read_by               INTEGER REFERENCES users(id) ON DELETE SET NULL
                );
                CREATE INDEX IF NOT EXISTS idx_user_messages_time
                    ON user_messages(created_at DESC);
                CREATE INDEX IF NOT EXISTS idx_user_messages_user
                    ON user_messages(user_id, created_at DESC);
                CREATE INDEX IF NOT EXISTS idx_user_messages_unread
                    ON user_messages(read_at, created_at DESC);
            " );

            self::setMeta($pdo, 'version', '32');
            $version = 32;
        }

        if ($version < 33) {
            // Support messages are a conversation, not a one-way contact
            // form.  Keeping the reply on the original message preserves all
            // existing tickets while giving both sides one shared thread.
            $cols = $pdo->query('PRAGMA table_info(user_messages)')->fetchAll(PDO::FETCH_COLUMN, 1);
            foreach ([
                'admin_reply' => "ALTER TABLE user_messages ADD COLUMN admin_reply TEXT NOT NULL DEFAULT ''",
                'replied_at'  => 'ALTER TABLE user_messages ADD COLUMN replied_at INTEGER',
                'replied_by'  => 'ALTER TABLE user_messages ADD COLUMN replied_by INTEGER',
                'user_read_at'=> 'ALTER TABLE user_messages ADD COLUMN user_read_at INTEGER',
            ] as $col => $sql) {
                if (!in_array($col, $cols, true)) {
                    $pdo->exec($sql);
                }
            }
            self::setMeta($pdo, 'version', '33');
            $version = 33;
        }

        if ($version < 34) {
            // A support conversation has an explicit end.  Until staff closes
            // it, the customer sees the thread rather than another compose
            // form, which keeps one issue in one place.
            $cols = $pdo->query('PRAGMA table_info(user_messages)')->fetchAll(PDO::FETCH_COLUMN, 1);
            foreach ([
                'closed_at' => 'ALTER TABLE user_messages ADD COLUMN closed_at INTEGER',
                'closed_by' => 'ALTER TABLE user_messages ADD COLUMN closed_by INTEGER',
            ] as $col => $sql) {
                if (!in_array($col, $cols, true)) {
                    $pdo->exec($sql);
                }
            }
            self::setMeta($pdo, 'version', '34');
            $version = 34;
        }

        if ($version < 35) {
            // Stored only with a support request, for staff context.
            $cols = $pdo->query('PRAGMA table_info(user_messages)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('ip_address', $cols, true)) {
                $pdo->exec("ALTER TABLE user_messages ADD COLUMN ip_address TEXT NOT NULL DEFAULT ''");
            }
            self::setMeta($pdo, 'version', '35');
            $version = 35;
        }

        if ($version < 36) {
            // The support inbox and the reply badge are opened often. These
            // indexes avoid full-history scans once a live installation has
            // accumulated messages and failed-login records.
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_users_nickname_ci ON users(LOWER(nickname));');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_open_user ON user_messages(user_id, closed_at, created_at DESC);');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_state ON user_messages(closed_at, read_at, created_at DESC);');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_messages_replies ON user_messages(user_id, replied_at, user_read_at);');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attempts_identifier ON login_attempts(email, created_at DESC);');
            self::setMeta($pdo, 'version', '36');
            $version = 36;
        }

        if ($version < 37) {
            // A reply used to overwrite the previous reply on the ticket.
            // Keep the small ticket row for its current state, but store the
            // actual conversation as append-only posts so neither side can
            // accidentally edit a message that has already been sent.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_message_posts (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    message_id  INTEGER NOT NULL REFERENCES user_messages(id) ON DELETE CASCADE,
                    author_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
                    author_kind TEXT NOT NULL CHECK(author_kind IN ('user', 'staff')),
                    body        TEXT NOT NULL,
                    created_at  INTEGER NOT NULL,
                    read_at     INTEGER
                );
                CREATE INDEX IF NOT EXISTS idx_message_posts_thread
                    ON user_message_posts(message_id, id);
                CREATE INDEX IF NOT EXISTS idx_message_posts_unread
                    ON user_message_posts(author_kind, read_at, message_id);
            ");
            // Preserve the single legacy response as the first staff post.
            $pdo->exec("
                INSERT INTO user_message_posts (message_id, author_id, author_kind, body, created_at, read_at)
                SELECT id, replied_by, 'staff', admin_reply, COALESCE(replied_at, created_at), user_read_at
                FROM user_messages
                WHERE TRIM(COALESCE(admin_reply, '')) <> ''
                  AND NOT EXISTS (
                      SELECT 1 FROM user_message_posts p
                      WHERE p.message_id = user_messages.id AND p.author_kind = 'staff'
                  );
            ");
            self::setMeta($pdo, 'version', '37');
            $version = 37;
        }


        if ($version < 41) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS support_feedback (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    message_id  INTEGER REFERENCES user_messages(id) ON DELETE SET NULL,
                    rating      TEXT NOT NULL CHECK(rating IN ('good','partial','bad')),
                    comment     TEXT NOT NULL DEFAULT '',
                    created_at  INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_support_feedback_user
                    ON support_feedback(user_id, created_at DESC);
            ");
            self::setMeta($pdo, 'version', '41');
            $version = 41;
        }

        if ($version < 42) {
            $cols = $pdo->query('PRAGMA table_info(support_feedback)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('message_id', $cols, true)) {
                $pdo->exec('ALTER TABLE support_feedback ADD COLUMN message_id INTEGER REFERENCES user_messages(id) ON DELETE SET NULL');
            }
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_support_feedback_message ON support_feedback(message_id)');
            self::setMeta($pdo, 'version', '42');
            $version = 42;
        }

        // Share-link storage must exist even on installations whose schema
        // version was already advanced past the original share migration.
        // Older builds placed this migration after version 40, which meant
        // an existing database at v40 could skip table creation entirely.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS shared_projects (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                share_key       TEXT NOT NULL UNIQUE,
                user_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                project_json    TEXT NOT NULL,
                created_at      INTEGER NOT NULL,
                last_access_at  INTEGER
            );
            CREATE INDEX IF NOT EXISTS idx_shared_projects_user
                ON shared_projects(user_id, created_at DESC);
        " );

        if ($version < 39) {
            // Each signed-in user remembers which What's New revision they
            // have actually dismissed. The value is a DB field, not a cookie,
            // so it follows the account across browsers and devices.
            $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('news_seen_version', $cols, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN news_seen_version INTEGER NOT NULL DEFAULT 0');
            }
            self::setMeta($pdo, 'version', '39');
            $version = 39;
        }

        if ($version < 40) {
            // Keep a timestamped history of the daily-credit rate. This is
            // required for retroactive catch-up: if the allowance changes
            // while an account is away, each missing calendar day must use
            // the rate that was effective at the start of that day.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS daily_credit_rates (
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    credits       INTEGER NOT NULL,
                    effective_at  INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_daily_credit_rates_effective
                    ON daily_credit_rates(effective_at);
            ");
            $st = $pdo->query('SELECT COUNT(*) FROM daily_credit_rates');
            if ((int) $st->fetchColumn() === 0) {
                $pdo->prepare(
                    'INSERT INTO daily_credit_rates (credits, effective_at) VALUES (?, ?)'
                )->execute([Cred::parse(Settings::get('daily_credits')), time()]);
            }
            self::setMeta($pdo, 'version', '40');
            $version = 40;
        }

        if ($version < 43) {
            // Admin-only title for support conversations. The original
            // subject remains unchanged for the customer-facing flow.
            $cols = $pdo->query('PRAGMA table_info(user_messages)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('admin_title', $cols, true)) {
                $pdo->exec("ALTER TABLE user_messages ADD COLUMN admin_title TEXT NOT NULL DEFAULT ''");
            }
            self::setMeta($pdo, 'version', '43');
            $version = 43;
        }

        if ($version < 44) {
            // Persistent 30-day sign-in. Only a SHA-256 hash of the opaque
            // browser token is stored, and multiple devices can have their
            // own token at the same time.
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS remember_tokens (
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    token_hash    TEXT NOT NULL UNIQUE,
                    created_at    INTEGER NOT NULL,
                    expires_at    INTEGER NOT NULL,
                    last_used_at  INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_remember_tokens_user
                    ON remember_tokens(user_id);
                CREATE INDEX IF NOT EXISTS idx_remember_tokens_expiry
                    ON remember_tokens(expires_at);
            " );
            self::setMeta($pdo, 'version', '44');
            $version = 44;
        }

        if ($version < 45) {
            // Unverified accounts have a strict lifecycle: 24 hours to confirm,
            // then 24 hours disabled before the account is removed. The
            // timestamps are separate so the cleanup can distinguish the two
            // stages without guessing from created_at.
            $cols = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_COLUMN, 1);
            if (!in_array('verify_expires_at', $cols, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN verify_expires_at INTEGER');
            }
            if (!in_array('verify_blocked_at', $cols, true)) {
                $pdo->exec('ALTER TABLE users ADD COLUMN verify_blocked_at INTEGER');
            }
            $pdo->exec("UPDATE users SET verify_expires_at = created_at + 86400 WHERE verified_at IS NULL AND verify_expires_at IS NULL");
            self::setMeta($pdo, 'version', '45');
            $version = 45;
        }

        if ($version < 46) {
            // Public verification resend protection was added to the fresh-install
            // schema long ago, but existing databases that had already passed v1
            // never received the table because it lived only in the initial CREATE
            // TABLE block. That made the public resend endpoint throw before it
            // ever reached SMTP, while the admin SMTP/verify test continued to work.
            $pdo->exec("CREATE TABLE IF NOT EXISTS verification_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_hash TEXT NOT NULL,
                email_hash TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )");
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_verification_requests_ip ON verification_requests(ip_hash, created_at DESC)');
            $pdo->exec('CREATE INDEX IF NOT EXISTS idx_verification_requests_email ON verification_requests(email_hash, created_at DESC)');

            self::setMeta($pdo, 'version', '46');
            $version = 46;
        }

    }

    private static function meta(PDO $pdo, string $k): ?string
    {
        $st = $pdo->prepare('SELECT value FROM schema_meta WHERE key = ?');
        $st->execute([$k]);
        $v = $st->fetchColumn();
        return $v === false ? null : (string) $v;
    }

    private static function setMeta(PDO $pdo, string $k, string $v): void
    {
        $st = $pdo->prepare('INSERT INTO schema_meta (key, value) VALUES (?, ?)
                             ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        $st->execute([$k, $v]);
    }

    /** True when SQLite refused a write because somebody else was writing. */
    private static function isBusy(Throwable $e): bool
    {
        $m = $e->getMessage();
        return stripos($m, 'database is locked') !== false
            || stripos($m, 'database table is locked') !== false
            || stripos($m, 'database is busy') !== false;
    }

    /**
     * Runs a write, and tries again while SQLite says it is busy.
     *
     * One writer at a time is the deal with SQLite, and busy_timeout makes a
     * writer wait its turn - except in the case that actually bites here. In
     * WAL mode a connection that has read something is holding a snapshot of
     * the database; if anybody commits before it writes, the write is
     * refused AT ONCE, with no waiting, because waiting could deadlock.
     *
     * The snapshot is held by a live statement handle with rows still
     * unread - the ordinary "prepare, execute, fetch one row" that PHP code
     * is made of. So retrying on the same connection is pointless: it holds
     * the same stale snapshot and fails again, which is exactly what a load
     * test with eight processes showed (dozens of dropped exports and
     * redeemed codes, and retries that never once succeeded).
     *
     * Dropping the connection drops the snapshot with it, and SQLite hands
     * out a fresh one on reconnect. Reopening a local file is cheap; losing
     * somebody's export is not.
     *
     * The closure must be safe to run twice - it only ever re-runs after a
     * failure that committed nothing.
     */
    public static function write(callable $fn, int $tries = 8)
    {
        $waitUs = 3000;

        for ($attempt = 1; ; $attempt++) {
            try {
                return $fn(self::pdo());
            } catch (Throwable $e) {
                if ($attempt >= $tries || !self::isBusy($e)) {
                    throw $e;
                }
                // Fresh connection, fresh snapshot, then a short wait with
                // jitter so the losers of a race do not all come back at the
                // same instant and collide again.
                self::close();
                usleep($waitUs + random_int(0, $waitUs));
                $waitUs = min($waitUs * 2, 120000);
            }
        }
    }

    /**
     * Run a closure inside an immediate transaction (write lock upfront).
     *
     * Retried as a whole on a busy database: BEGIN IMMEDIATE takes the write
     * lock before anything is read, so a retry starts from a clean slate.
     */
    public static function transact(callable $fn)
    {
        return self::write(static function (PDO $pdo) use ($fn) {
            $pdo->exec('BEGIN IMMEDIATE');
            try {
                $result = $fn($pdo);
                $pdo->exec('COMMIT');
                return $result;
            } catch (Throwable $e) {
                // A failed BEGIN never gets here; anything later must undo
                // itself before the retry, or the second attempt would find
                // a transaction already open.
                try { $pdo->exec('ROLLBACK'); } catch (Throwable $ignored) {}
                throw $e;
            }
        });
    }
}
