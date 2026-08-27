<?php
declare(strict_types=1);

/**
 * "Your subscription expired" mail, sent after the fact.
 *
 * There is no cron here: any page load may notice that a plan crossed its end
 * time and hasn't been told about it yet. users.sub_expiry_notified remembers
 * WHICH end the mail covered, so each run of a plan produces exactly one mail
 * - a renewed plan clears the marker and can expire (and be announced) again.
 * An admin cancel sets subscription_until to NULL, which never matches here;
 * that path has its own "cancelled" mail.
 */
final class SubExpiry
{
    /** How often the sweep may actually touch the database. */
    private const EVERY = 300;

    /**
     * How many mails one page load may send.
     *
     * Sending happens inline, and an SMTP server that is merely slow costs
     * the whole timeout per message. Twenty of those turned an ordinary page
     * load into a request nobody waits out; the rest go with the next sweep.
     */
    private const BATCH = 5;

    public static function tick(): void
    {
        try {
            // Every page load ran this query, including the studio's frequent
            // preference saves - a plan expiring is not urgent enough for
            // that, so a stamp file keeps it to one sweep per EVERY seconds.
            $stamp = dirname(Db::path()) . '/.sub-expiry';
            $last  = @filemtime($stamp);
            if ($last !== false && time() - $last < self::EVERY) {
                return;
            }
            @touch($stamp);

            $pdo = Db::pdo();
            $st  = $pdo->prepare(
                'SELECT * FROM users
                 WHERE subscription_until IS NOT NULL
                   AND subscription_until <= ?
                   AND (sub_expiry_notified IS NULL OR sub_expiry_notified <> subscription_until)
                 LIMIT ' . self::BATCH
            );
            $st->execute([time()]);
            $due = $st->fetchAll();

            foreach ($due as $u) {
                $until = (int) $u['subscription_until'];

                // Marked first, even with the mailer off - enabling mail later
                // must not flush a backlog of months-old expiries.
                $pdo->prepare('UPDATE users SET sub_expiry_notified = ? WHERE id = ?')
                    ->execute([$until, (int) $u['id']]);

                if (Mailer::enabled() && trim((string) $u['email']) !== '') {
                    Notify::$lang = Auth::userLang($u);
                    Mailer::notify(
                        (string) $u['email'],
                        Notify::subscriptionExpired($until, originUrl() . '/account.php'),
                        'subscription_expired'
                    );
                }
            }
        } catch (Throwable $e) {
            // A missed notification beats a broken page; the next request
            // will try again.
        }
    }
}
