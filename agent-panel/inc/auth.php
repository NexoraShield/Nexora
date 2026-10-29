<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/config.php';
if (!function_exists('nexora_agent_ensure_table')) {
    function nexora_agent_ensure_table(): void
    {
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) return;
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_auth (id_user VARCHAR(32) NOT NULL PRIMARY KEY, pass_hash VARCHAR(255) NOT NULL, created_at INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (Throwable $e) { error_log('nexora_agent_ensure_table: ' . $e->getMessage()); }
    }
}
if (!function_exists('nexora_agent_current')) {
    function nexora_agent_current(): ?array
    {
        global $pdo;
        if (empty($_SESSION['nexora_agent_id'])) return null;
        try {
            $stmt = $pdo->prepare('SELECT * FROM `user` WHERE id = ? LIMIT 1');
            $stmt->execute([(string) $_SESSION['nexora_agent_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($user) ? $user : null;
        } catch (Throwable $e) { return null; }
    }
}
if (!function_exists('nexora_agent_require_login')) {
    function nexora_agent_require_login(): array
    {
        $user = nexora_agent_current();
        if ($user === null) { header('Location: login.php'); exit; }
        return $user;
    }
}
nexora_agent_ensure_table();
if (!function_exists('nexora_agent_ensure_login_table')) {
    function nexora_agent_ensure_login_table(): void
    {
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) return;
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS agent_login_attempts (ip VARCHAR(64) NOT NULL PRIMARY KEY, attempts INT UNSIGNED NOT NULL DEFAULT 0, first_attempt INT UNSIGNED NOT NULL DEFAULT 0, last_attempt INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (Throwable $e) { error_log('nexora_agent_ensure_login_table: ' . $e->getMessage()); }
    }
}
if (!function_exists('nexora_agent_login_throttle')) {
    // Brute-force protection for the agent login form.
    // Returns array(allowed:bool, locked:bool, retry:int seconds until unlock, msg:string)
    function nexora_agent_login_throttle(): array
    {
        global $pdo;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $maxAttempts = (defined('NEXORA_AGENT_LOGIN_MAX') ? (int) NEXORA_AGENT_LOGIN_MAX : 5);
        $lockSeconds = (defined('NEXORA_AGENT_LOGIN_LOCK') ? (int) NEXORA_AGENT_LOGIN_LOCK : 300);
        $now = time();
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return ['allowed' => true, 'locked' => false, 'retry' => 0, 'msg' => ''];
        }
        try {
            nexora_agent_ensure_login_table();
            $tx = false;
            try { $pdo->beginTransaction(); $tx = true; } catch (Throwable $e) {}
            $stmt = $pdo->prepare('SELECT attempts, first_attempt, last_attempt FROM agent_login_attempts WHERE ip = ? LIMIT 1');
            $stmt->execute([$ip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $attempts = (int) ($row['attempts'] ?? 0);
            $first = (int) ($row['first_attempt'] ?? 0);
            $last = (int) ($row['last_attempt'] ?? 0);
            // locked?
            if ($attempts >= $maxAttempts && ($now - $last) < $lockSeconds) {
                if ($tx) try { $pdo->rollBack(); } catch (Throwable $e) {}
                $retry = max(1, $lockSeconds - (int) ($now - $last));
                return ['allowed' => false, 'locked' => true, 'retry' => $retry, 'msg' => 'پنل قفل شد. ' . strtr((string) $retry, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']) . ' ثانیه دیگر تلاش کنید.'];
            }
            // window expired -> reset
            if (($now - (int) $first) > $lockSeconds || ($now - (int) $last) >= $lockSeconds) {
                $attempts = 0; $first = $now;
            }
            if ($tx) try { $pdo->commit(); } catch (Throwable $e) {}
            return ['allowed' => true, 'locked' => false, 'retry' => 0, 'msg' => ''];
        } catch (Throwable $e) {
            if ($tx) try { $pdo->rollBack(); } catch (Throwable $e) {}
            return ['allowed' => true, 'locked' => false, 'retry' => 0, 'msg' => ''];
        }
    }
}
if (!function_exists('nexora_agent_login_fail')) {
    function nexora_agent_login_fail(): void
    {
        global $pdo;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $now = time();
        $maxAttempts = (defined('NEXORA_AGENT_LOGIN_MAX') ? (int) NEXORA_AGENT_LOGIN_MAX : 5);
        $lockSeconds = (defined('NEXORA_AGENT_LOGIN_LOCK') ? (int) NEXORA_AGENT_LOGIN_LOCK : 300);
        if (!isset($pdo) || !($pdo instanceof PDO)) return;
        try {
            nexora_agent_ensure_login_table();
            $stmt = $pdo->prepare('SELECT attempts, first_attempt, last_attempt FROM agent_login_attempts WHERE ip = ? LIMIT 1');
            $stmt->execute([$ip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $prevAttempts = (int) ($row['attempts'] ?? 0);
            $prevFirst = (int) ($row['first_attempt'] ?? 0);
            $prevLast = (int) ($row['last_attempt'] ?? 0);
            if ($prevAttempts <= 0 || ($now - $prevLast) >= $lockSeconds) {
                // no record yet, or previous window/lockout expired: start a fresh window
                $attempts = 1;
                $first = $now;
            } else {
                $attempts = $prevAttempts + 1;
                $first = $prevFirst > 0 ? $prevFirst : $now;
            }
            $last = $now;
            $stmt = $pdo->prepare('INSERT INTO agent_login_attempts (ip, attempts, first_attempt, last_attempt) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), first_attempt = VALUES(first_attempt), last_attempt = VALUES(last_attempt)');
            $stmt->execute([$ip, $attempts, $first, $last]);
        } catch (Throwable $e) { error_log('nexora_agent_login_fail: ' . $e->getMessage()); }
    }
}
if (!function_exists('nexora_agent_login_reset')) {
    function nexora_agent_login_reset(): void
    {
        global $pdo;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!isset($pdo) || !($pdo instanceof PDO)) return;
        try {
            $stmt = $pdo->prepare('DELETE FROM agent_login_attempts WHERE ip = ?');
            $stmt->execute([$ip]);
        } catch (Throwable $e) { error_log('nexora_agent_login_reset: ' . $e->getMessage()); }
    }
}
