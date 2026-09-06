<?php
declare(strict_types=1);

namespace Grippartner;

use PDO;

final class RateLimiter
{
    public static function tooMany(string $key, int $maxHits, int $windowSeconds): bool
    {
        $pdo = Database::pdo();
        $fullKey = substr(hash('sha256', $key), 0, 64);
        $now = new \DateTimeImmutable('now');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, hits, window_start FROM rate_limits WHERE rate_key = ? FOR UPDATE');
            $stmt->execute([$fullKey]);
            $row = $stmt->fetch();

            if (!$row) {
                $ins = $pdo->prepare('INSERT INTO rate_limits (rate_key, hits, window_start) VALUES (?, 1, ?)');
                $ins->execute([$fullKey, $now->format('Y-m-d H:i:s')]);
                $pdo->commit();
                return false;
            }

            $windowStart = new \DateTimeImmutable((string) $row['window_start']);
            $elapsed = $now->getTimestamp() - $windowStart->getTimestamp();

            if ($elapsed > $windowSeconds) {
                $upd = $pdo->prepare('UPDATE rate_limits SET hits = 1, window_start = ? WHERE id = ?');
                $upd->execute([$now->format('Y-m-d H:i:s'), $row['id']]);
                $pdo->commit();
                return false;
            }

            if ((int) $row['hits'] >= $maxHits) {
                $pdo->commit();
                return true;
            }

            $upd = $pdo->prepare('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?');
            $upd->execute([$row['id']]);
            $pdo->commit();
            return false;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Fail open but log — prefer availability over hard lockout on DB hiccup
            Logger::error('RateLimiter error', ['message' => $e->getMessage()]);
            return false;
        }
    }
}
