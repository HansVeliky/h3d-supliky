<?php
declare(strict_types=1);

if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

final class UserMessages
{
    public static function create(int $userId, string $subject, string $body): int
    {
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || mb_strlen($subject) > 160) { throw new InvalidArgumentException('Předmět je povinný a může mít nejvýše 160 znaků.'); }
        if ($body === '' || mb_strlen($body) > 10000) { throw new InvalidArgumentException('Zpráva je povinná a může mít nejvýše 10 000 znaků.'); }
        if (self::activeForUser($userId) !== null) {
            throw new RuntimeException('Nejdřív dokonči otevřenou konverzaci s administrací.');
        }
        $now=time();
        $ip = substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
        $st=Db::pdo()->prepare('INSERT INTO user_messages (user_id, subject, body, contact_consent, consent_at, created_at, ip_address) VALUES (?, ?, ?, 1, ?, ?, ?)');
        $st->execute([$userId,$subject,$body,$now,$now,$ip]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function unreadCount(): int
    { return (int)Db::pdo()->query('SELECT COUNT(*) FROM user_messages WHERE read_at IS NULL')->fetchColumn(); }

    /**
     * Conversations waiting for an answer - the number on the badge.
     *
     * Counts THREADS, not messages, and only those where the last word is the
     * customer's: either the opening message was never read, or somebody has
     * written again since. Five messages in one conversation is one thing to
     * deal with, and the old count (every unread row) said five.
     *
     * A closed conversation never counts, however it ended.
     */
    public static function waitingCount(): int
    {
        return (int) Db::pdo()->query(
            "SELECT COUNT(*) FROM user_messages m
              WHERE m.closed_at IS NULL
                AND (m.read_at IS NULL
                     OR EXISTS (SELECT 1 FROM user_message_posts p
                                 WHERE p.message_id = m.id
                                   AND p.author_kind = 'user'
                                   AND p.read_at IS NULL))"
        )->fetchColumn();
    }

    /** Notifications are only answers from staff the customer has not opened. */
    public static function unreadReplyCount(int $userId): int
    {
        $st = Db::pdo()->prepare("SELECT COUNT(*) FROM user_message_posts p
            JOIN user_messages m ON m.id = p.message_id
            WHERE m.user_id = ? AND p.author_kind = 'staff' AND p.read_at IS NULL");
        $st->execute([$userId]);
        return (int) $st->fetchColumn();
    }

    /** @return ?array<string,mixed> */
    public static function activeForUser(int $userId): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM user_messages WHERE user_id = ? AND closed_at IS NULL ORDER BY created_at DESC, id DESC LIMIT 1');
        $st->execute([$userId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Whether this user has another active support conversation besides the supplied ticket. */
    public static function hasOtherActiveForUser(int $userId, int $excludeMessageId = 0): bool
    {
        $st = Db::pdo()->prepare('SELECT 1 FROM user_messages WHERE user_id = ? AND closed_at IS NULL AND id <> ? LIMIT 1');
        $st->execute([$userId, $excludeMessageId]);
        return (bool)$st->fetchColumn();
    }

    public static function markRepliesRead(int $userId): void
    {
        $now = time();
        Db::pdo()->prepare("UPDATE user_message_posts SET read_at = ?
            WHERE author_kind = 'staff' AND read_at IS NULL AND message_id IN
                (SELECT id FROM user_messages WHERE user_id = ?)")
            ->execute([$now, $userId]);
        // Compatibility marker for the existing ticket list and old backups.
        Db::pdo()->prepare('UPDATE user_messages SET user_read_at = ?
            WHERE user_id = ? AND replied_at IS NOT NULL AND user_read_at IS NULL')
            ->execute([$now, $userId]);
    }

    /** The customer may close only their own still-active conversation. */
    public static function closeByUser(int $userId, int $messageId): bool
    {
        $st = Db::pdo()->prepare('UPDATE user_messages SET closed_at = ?, closed_by = NULL
            WHERE id = ? AND user_id = ? AND closed_at IS NULL');
        $st->execute([time(), $messageId, $userId]);
        return $st->rowCount() === 1;
    }

    /** Adds one immutable staff post to an open customer conversation. */
    /** Updates the internal title visible only to administrators. */
    public static function updateAdminTitle(int $messageId, string $title): bool
    {
        $title = trim($title);
        if ($title === '') {
            $title = 'Konverzace s uživatelem';
        }
        if (mb_strlen($title) > 160) {
            $title = mb_substr($title, 0, 157) . '…';
        }
        $st = Db::pdo()->prepare('UPDATE user_messages SET admin_title = ? WHERE id = ?');
        $st->execute([$title, $messageId]);
        return $st->rowCount() === 1;
    }

    public static function addStaffReply(int $messageId, int $staffId, string $body): bool
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 10000) {
            throw new InvalidArgumentException('Odpověď je povinná a může mít nejvýše 10 000 znaků.');
        }
        $pdo = Db::pdo();
        $now = time();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('UPDATE user_messages
                SET admin_reply = ?, replied_at = ?, replied_by = ?, user_read_at = NULL,
                    read_at = COALESCE(read_at, ?)
                WHERE id = ? AND closed_at IS NULL');
            $st->execute([$body, $now, $staffId, $now, $messageId]);
            if ($st->rowCount() !== 1) {
                $pdo->rollBack();
                return false;
            }
            $st = $pdo->prepare("INSERT INTO user_message_posts
                (message_id, author_id, author_kind, body, created_at, read_at)
                VALUES (?, ?, 'staff', ?, ?, NULL)");
            $st->execute([$messageId, $staffId, $body, $now]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }

    /** Adds a customer post to their own still-open conversation. */
    public static function addUserReply(int $userId, int $messageId, string $body): bool
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 10000) {
            throw new InvalidArgumentException('Odpověď je povinná a může mít nejvýše 10 000 znaků.');
        }

        $pdo = Db::pdo();
        $now = time();
        $pdo->beginTransaction();
        try {
            // The flyout can stay open while an administrator or another tab
            // changes the ticket. Never trust an old DOM id when a current
            // active conversation exists for this user.
            $st = $pdo->prepare(
                'SELECT id FROM user_messages
                 WHERE id = ? AND user_id = ? AND closed_at IS NULL
                 LIMIT 1'
            );
            $st->execute([$messageId, $userId]);
            $resolvedId = (int)($st->fetchColumn() ?: 0);

            if ($resolvedId <= 0) {
                $st = $pdo->prepare(
                    'SELECT id FROM user_messages
                     WHERE user_id = ? AND closed_at IS NULL
                     ORDER BY created_at DESC, id DESC
                     LIMIT 1'
                );
                $st->execute([$userId]);
                $resolvedId = (int)($st->fetchColumn() ?: 0);
            }

            if ($resolvedId <= 0) {
                $pdo->rollBack();
                return false;
            }

            $messageId = $resolvedId;

            // A user may send at most five consecutive messages. A staff reply
            // resets the streak, so the next batch of five becomes available.
            $st = $pdo->prepare("SELECT author_kind FROM user_message_posts WHERE message_id = ? ORDER BY id DESC LIMIT 5");
            $st->execute([$messageId]);
            $recentKinds = $st->fetchAll(PDO::FETCH_COLUMN);
            $consecutive = 0;
            foreach ($recentKinds as $kind) {
                if ((string)$kind !== 'user') break;
                $consecutive++;
            }
            if ($consecutive >= 5) {
                $pdo->rollBack();
                throw new RuntimeException('Dosáhl jsi limitu 5 zpráv. Počkej prosím na odpověď živého kolegy.');
            }

            $st = $pdo->prepare(
                "INSERT INTO user_message_posts
                 (message_id, author_id, author_kind, body, created_at, read_at)
                 VALUES (?, ?, 'user', ?, ?, NULL)"
            );
            $st->execute([$messageId, $userId, $body, $now]);

            // Keep the legacy ticket fields in sync with the thread.
            $pdo->prepare(
                'UPDATE user_messages
                 SET user_read_at = NULL
                 WHERE id = ? AND user_id = ? AND closed_at IS NULL'
            )->execute([$messageId, $userId]);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function postsFor(int $messageId): array
    {
        $st = Db::pdo()->prepare("SELECT p.*, COALESCE(u.nickname, u.email, 'Administrace') AS author_name
            FROM user_message_posts p
            LEFT JOIN users u ON u.id = p.author_id
            WHERE p.message_id = ?
            ORDER BY p.id ASC");
        $st->execute([$messageId]);
        return $st->fetchAll();
    }

    /**
     * @param array<int,int> $messageIds
     * @return array<int,array<int,array<string,mixed>>> posts keyed by ticket id
     */
    public static function postsForMessages(array $messageIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $messageIds), static fn (int $id): bool => $id > 0)));
        if (!$ids) { return []; }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $st = Db::pdo()->prepare("SELECT p.*, COALESCE(u.nickname, u.email, 'Administrace') AS author_name
            FROM user_message_posts p
            LEFT JOIN users u ON u.id = p.author_id
            WHERE p.message_id IN ($marks)
            ORDER BY p.message_id ASC, p.id ASC");
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll() as $post) {
            $out[(int) $post['message_id']][] = $post;
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public static function forUser(int $userId): array
    {
        $st = Db::pdo()->prepare('SELECT * FROM user_messages WHERE user_id = ? ORDER BY created_at DESC, id DESC');
        $st->execute([$userId]);
        return $st->fetchAll();
    }
}
