<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../jdf.php';

$setting = select("setting", "*");
$textbotlang = languagechange();

 // Admin chat id: prefer setting admin, fallback to first admin row
$adminChatId = 0;
try {
    if (is_array($setting) && !empty($setting['idadmin'])) $adminChatId = (string) $setting['idadmin'];
    // Allow explicit backup group/chat override
    if (empty($adminChatId) && is_array($setting) && !empty($setting['backup_chat_id'])) $adminChatId = (string) $setting['backup_chat_id'];
    if (empty($adminChatId) && is_array($setting) && !empty($setting['Channel_Report'])) $adminChatId = (string) $setting['Channel_Report'];
} catch (Throwable $e) {}
if (empty($adminChatId)) {
    try {
        $admins = select("admin", "*", null, null, "selectall");
        if (is_array($admins) && !empty($admins)) {
            foreach ($admins as $a) { if (!empty($a['id_admin'])) { $adminChatId = (string) $a['id_admin']; break; } }
        }
    } catch (Throwable $e) {}
}

// Backup directory
$backupDir = __DIR__ . '/../backup';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

global $dbhost, $dbname, $usernamedb, $passworddb;
$dbName = $dbname ?? 'nexorabot';
$dbUser = $usernamedb ?? 'root';
$dbPass = $passworddb ?? '';
$dbHost = $dbhost ?? 'localhost';


// detect mysqldump
$mysqldump = null;
foreach (['mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $c) {
    $rc = 0; $o = [];
    @exec('command -v ' . escapeshellarg($c) . ' 2>/dev/null', $o, $rc);
    if ($rc === 0 && !empty($o)) { $mysqldump = trim($o[0]); break; }
}
// also try direct
if ($mysqldump === null && is_file('/usr/bin/mysqldump')) $mysqldump = '/usr/bin/mysqldump';

$useGzip = (bool) @exec('command -v gzip 2>/dev/null');
$ext = $useGzip ? '.sql.gz' : '.sql';
$fileName = 'backup_' . date('Y-m-d_H-i-s') . $ext;
$filePath = $backupDir . '/' . $fileName;

$ok = false;
if ($mysqldump !== null) {
    $cmd = escapeshellarg($mysqldump) . ' -h ' . escapeshellarg($dbHost) . ' -u ' . escapeshellarg($dbUser);
    if ($dbPass !== '') $cmd .= ' -p' . escapeshellarg($dbPass);
    $cmd .= ' ' . escapeshellarg($dbName);
    if ($useGzip) $cmd .= ' | gzip > ' . escapeshellarg($filePath) . ' 2>/dev/null';
    else $cmd .= ' > ' . escapeshellarg($filePath) . ' 2>/dev/null';
    @exec($cmd, $out, $rc);
    if ($rc === 0 && is_file($filePath) && filesize($filePath) > 100) {
        $ok = true;
    }
}
if (!$ok) {
    // Fallback: PHP dump
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $sql = "-- Nexora auto backup " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n";
        foreach ($tables as $t) {
            $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_ASSOC);
            $sql .= "DROP TABLE IF EXISTS `$t`;\n" . ($create['Create Table'] ?? '') . ";\n";
            $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $vals = [];
                foreach ($row as $v) {
                    if ($v === null) $vals[] = 'NULL';
                    else $vals[] = $pdo->quote((string) $v);
                }
                $sql .= "INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n";
            }
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $data = $useGzip ? gzencode($sql, 9) : $sql;
        @file_put_contents($filePath, $data, LOCK_EX);
        if (is_file($filePath) && filesize($filePath) > 100) $ok = true;
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/../error_log', "backup error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Send to admin / backup group (supports negative supergroup chat ids)
if ($ok && $adminChatId !== '' && $adminChatId !== '0') {
    try {
        $resp = sendDocument($adminChatId, $filePath, "📦 Nexora auto-backup " . date('Y-m-d H:i:s') . "\nSize: " . round(filesize($filePath)/1024, 1) . " KB");
        if (is_array($resp) && !empty($resp['ok']) && $resp['ok'] !== true) {
            @file_put_contents(__DIR__ . '/../error_log', "backup send failed: " . json_encode($resp) . "\n", FILE_APPEND);
        }
    } catch (Throwable $e) {
        // fallback: message
        try { sendmessage($adminChatId, "📦 Backup ready: " . $fileName . " (" . round(filesize($filePath)/1024,1) . " KB) on server.", null, 'HTML'); } catch (Throwable $e2) {}
    }
}

// Cleanup old backups (keep last 7)
$files = array_merge(glob($backupDir . '/backup_*.sql') ?: [], glob($backupDir . '/backup_*.sql.gz') ?: []);
if (is_array($files) && count($files) > 7) {
    usort($files, fn($a,$b) => filemtime($a) <=> filemtime($b));
    foreach (array_slice($files, 0, count($files) - 7) as $f) @unlink($f);
}

echo $ok ? "OK $fileName\n" : "FAIL\n";
