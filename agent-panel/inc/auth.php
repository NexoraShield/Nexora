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
