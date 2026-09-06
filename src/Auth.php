<?php
declare(strict_types=1);

namespace Grippartner;

final class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['admin_id']) && !empty($_SESSION['admin_username']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    }

    public static function username(): string
    {
        return (string) ($_SESSION['admin_username'] ?? '');
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/admin/book/login.php');
        }
    }

    public static function attempt(string $username, string $password): bool
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return false;
        }
        if (RateLimiter::tooMany('admin_login:' . client_ip(), 8, 900)) {
            return false;
        }

        // Zelfde credentials als nieuwsbrief-admin (config), plus DB-admins.
        $configUser = trim(Config::string('ADMIN_USERNAME', ''));
        $configPass = (string) Config::get('ADMIN_PASSWORD', '');
        if (
            $configUser !== ''
            && $configPass !== ''
            && hash_equals($configUser, $username)
            && hash_equals($configPass, $password)
        ) {
            self::ensureConfigAdmin($configUser, $configPass);
            $stmt = Database::pdo()->prepare('SELECT id, username FROM admins WHERE username = ? LIMIT 1');
            $stmt->execute([$configUser]);
            $row = $stmt->fetch();
            if ($row) {
                Security::rotateSession();
                $_SESSION['admin_id'] = (int) $row['id'];
                $_SESSION['admin_username'] = (string) $row['username'];
                Database::pdo()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')
                    ->execute([(int) $row['id']]);
                return true;
            }
        }

        $stmt = Database::pdo()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return false;
        }

        Security::rotateSession();
        $_SESSION['admin_id'] = (int) $row['id'];
        $_SESSION['admin_username'] = (string) $row['username'];

        Database::pdo()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
        return true;
    }

    /** Zorg dat config-admin ook in `admins` staat (zelfde login als nieuwsbrief). */
    private static function ensureConfigAdmin(string $username, string $password): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $id = $stmt->fetchColumn();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($id) {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, (int) $id]);
            return;
        }
        $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)')->execute([$username, $hash]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    public static function createAdmin(string $username, string $password): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = Database::pdo()->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
        $stmt->execute([trim($username), $hash]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function adminCount(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    }
}
