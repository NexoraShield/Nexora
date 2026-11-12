<?php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

require __DIR__ . '/../../config.php';
require __DIR__ . '/../../function.php';

date_default_timezone_set('Asia/Tehran');

// Panel UI language strings (loaded from lang/fa.php via languagechange())
$textbotlang = languagechange();

function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch(PDO $pdo, string $sql, array $params = []): ?array
{
    return db_query($pdo, $sql, $params)->fetch() ?: null;
}

function db_fetchAll(PDO $pdo, string $sql, array $params = []): array
{
    return db_query($pdo, $sql, $params)->fetchAll();
}

function db_count(PDO $pdo, string $sql, array $params = []): int
{
    return (int) db_query($pdo, $sql, $params)->fetchColumn();
}

function move_display_order(PDO $pdo, string $table, int $id, int $position): void
{
    $current = $pdo->query("SELECT id, display_order FROM `$table` ORDER BY display_order, id FOR UPDATE")->fetchAll(PDO::FETCH_KEY_PAIR);
    $ids = array_keys($current);
    $from = array_search($id, $ids, true);
    $to = max(0, min(count($ids) - 1, $position - 1));
    if ($from === false || $from === $to) {
        return;
    }
    array_splice($ids, $from, 1);
    array_splice($ids, $to, 0, [$id]);
    $stmt = $pdo->prepare("UPDATE `$table` SET display_order = ? WHERE id = ?");
    foreach ($ids as $index => $rowId) {
        if ((int) $current[$rowId] !== $index + 1) {
            $stmt->execute([$index + 1, $rowId]);
        }
    }
}
function require_auth(): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    global $pdo;
    if (empty($_SESSION['admin_user'])) {
        header('Location: login.php');
        exit;
    }
    try {
        $admin = db_fetch($pdo, "SELECT id_admin, rule FROM admin WHERE username = ?", [$_SESSION['admin_user']]);
        if (!$admin || $admin['rule'] !== 'administrator') {
            session_destroy();
            header('Location: login.php');
            exit;
        }
    } catch (Exception $e) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check_value(string $token): bool
{
    return hash_equals($_SESSION['csrf'] ?? '', $token);
}

function csrf_check_post(): void
{
    global $textbotlang;
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function csrf_check_get(): void
{
    global $textbotlang;
    $token = $_GET['_csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die($textbotlang['panel']['configInvalidRequest']);
    }
}

function flash(string $key, string $msg): void
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $_SESSION["flash_{$key}"] = $msg;
}

function get_flash(string $key): ?string
{
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    $msg = $_SESSION["flash_{$key}"] ?? null;
    unset($_SESSION["flash_{$key}"]);
    return $msg;
}

function trunc(string $str, int $max = 30): string
{
    return mb_strlen($str, 'UTF-8') > $max
        ? mb_substr($str, 0, $max, 'UTF-8') . '…'
        : $str;
}

function safe_date($ts, string $fmt = 'Y/m/d'): string
{
    if (!$ts)
        return '—';
    if (!is_numeric($ts))
        return htmlspecialchars((string) $ts);
    return date($fmt, (int) $ts);
}
function check_login_rate(string $ip): bool
{
    $file = sys_get_temp_dir() . '/panel_login_' . md5($ip);
    $data = @json_decode(@file_get_contents($file) ?: '{}', true) ?: [];
    $now = time();
    $data = array_filter($data, fn($t) => ($now - $t) < 900);
    if (count($data) >= 10)
        return false;
    $data[] = $now;
    @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    return true;
}

function clear_login_rate(string $ip): void
{
    @unlink(sys_get_temp_dir() . '/panel_login_' . md5($ip));
}

/**
 * The columns of a table. A column that the database does not have is simply
 * left out, so a page still works when the migration has not run yet.
 */
function panel_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    $key = strtolower($table);
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $columns = [];
    try {
        $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $st->execute([$table]);
        $columns = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $columns = [];
    }
    $cache[$key] = $columns;
    return $columns;
}

/**
 * Every panel of the installation.
 *
 * The list is built from the columns the database really has, so a missing
 * column can never empty the list (which made the panel of the product form
 * impossible to pick).
 */
function panel_load_panels(PDO $pdo): array
{
    $columns = panel_table_columns($pdo, 'marzban_panel');
    if (!$columns) {
        return [];
    }
    $wanted = ['id', 'name_panel', 'type', 'status', 'inbounds', 'proxies', 'display_order'];
    $selected = array_values(array_intersect($wanted, $columns));
    if (!$selected) {
        return [];
    }
    $order = in_array('display_order', $columns, true) ? ' ORDER BY display_order, id' : ' ORDER BY id';
    try {
        return db_fetchAll($pdo, "SELECT " . implode(', ', $selected) . " FROM marzban_panel" . $order);
    } catch (Throwable $e) {
        error_log('panel_load_panels: ' . $e->getMessage());
        return [];
    }
}

/**
 * The test services have their own page, so they are left out of the product
 * list. A database that does not know the column yet simply shows everything.
 */
function panel_product_test_filter(PDO $pdo): string
{
    static $filter = null;
    if ($filter !== null) {
        return $filter;
    }
    $columns = array_map('strtolower', panel_table_columns($pdo, 'product'));
    $filter = in_array('is_test', $columns, true) ? ' AND is_test = 0' : '';
    return $filter;
}

/**
 * The name of a panel without its premium emoji tag (only for the display).
 */
function panel_plain_name(string $name): string
{
    $clean = trim((string) preg_replace('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', '$1', $name));
    return $clean !== '' ? $clean : $name;
}

function user_role_label(string $agent): string
{    global $textbotlang;
    return match ($agent) {
        'n' => $textbotlang['panel']['configRoleN'],
        'n2' => $textbotlang['panel']['configRoleN2'],
        'all' => $textbotlang['panel']['configRoleAll'],
        default => $textbotlang['panel']['configRoleDefault'],
    };
}

function user_role_tag(string $agent): string
{
    return match ($agent) {
        'f' => 'tag-info',
        'n' => 'tag-info',
        'n2' => 'tag-warn',
        'all' => 'tag-ok',
        default => 'tag-plain',
    };
}
