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

// Find unpaid invoices older than 24h and remind once
try {
    $stmt = $pdo->prepare("SELECT * FROM Invoice WHERE status = 'unpaid' AND created_at < (NOW() - INTERVAL 24 HOUR) AND (reminded IS NULL OR reminded = 0) LIMIT 100");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // fallback column names
    $rows = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM Invoice WHERE status = 'unpaid' LIMIT 100");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e2) {
        $rows = [];
    }
}

foreach ($rows as $inv) {
    $uid = (int) ($inv['id_user'] ?? $inv['user_id'] ?? 0);
    if ($uid <= 0) continue;
    $user = select("user", "*", "id", $uid, "select");
    $chatId = $user['id'] ?? $uid;
    $amount = $inv['price'] ?? $inv['amount'] ?? '';
    $invId = $inv['id'] ?? $inv['invoice_id'] ?? '';
    $msg = "⏰ یادآوری پرداخت\n\nفاکتور #" . $invId . "\nمبلغ: " . $amount . "\n\nبرای پرداخت به ربات مراجعه کنید.";
    try {
        sendmessage($chatId, $msg, null, 'HTML');
    } catch (Throwable $e) {}
    try {
        $pdo->prepare("UPDATE Invoice SET reminded = 1 WHERE id = ?")->execute([$invId]);
    } catch (Throwable $e) {}
}

echo "Done: " . count($rows) . "\n";
