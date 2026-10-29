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
    if (is_array($setting) && !empty($setting['idadmin'])) $adminChatId = (int) $setting['idadmin'];
} catch (Throwable $e) {}
if ($adminChatId <= 0) {
    try {
        $admins = select("admin", "*", null, null, "selectall");
        if (is_array($admins) && !empty($admins)) {
            foreach ($admins as $a) { if (!empty($a['id_admin'])) { $adminChatId = (int) $a['id_admin']; break; } }
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

$fileName = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$filePath = $backupDir . '/' . $fileName;

$ok = false;
// Try mysqldump
$cmd = 'mysqldump -h ' . escapeshellarg($dbHost) . ' -u ' . escapeshellarg($dbUser);
if ($dbPass !== '') $cmd .= ' -p' . escapeshellarg($dbPass);
$cmd .= ' ' . escapeshellarg($dbName) . ' > ' . escapeshellarg($filePath) . ' 2>/dev/null';
@exec($cmd, $out, $rc);
if ($rc === 0 && is_file($filePath) && filesize($filePath) > 100) {
    $ok = true;
} else {
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
        @file_put_contents($filePath, $sql, LOCK_EX);
        if (is_file($filePath) && filesize($filePath) > 100) $ok = true;
    } catch (Throwable $e) {
        @file_put_contents(__DIR__ . '/../error_log', "backup error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

// Send to admin
if ($ok && $adminChatId > 0) {
    try {
        sendDocument($adminChatId, $filePath, "📦 Nexora auto-backup " . date('Y-m-d H:i:s') . "\nSize: " . round(filesize($filePath)/1024, 1) . " KB");
    } catch (Throwable $e) {
        // fallback: message
        try { sendmessage($adminChatId, "📦 Backup ready: " . $fileName . " (" . round(filesize($filePath)/1024,1) . " KB) on server.", null, 'HTML'); } catch (Throwable $e2) {}
    }
}

// Cleanup old backups (keep last 7)
$files = glob($backupDir . '/backup_*.sql');
if (is_array($files) && count($files) > 7) {
    usort($files, fn($a,$b) => filemtime($a) <=> filemtime($b));
    foreach (array_slice($files, 0, count($files) - 7) as $f) @unlink($f);
}

echo $ok ? "OK $fileName\n" : "FAIL\n";
