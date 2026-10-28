<?php
chdir(__DIR__);
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
$textbotlang = languagechange();
$stateFile = __DIR__ . '/nudge_unpaid.json';
$reminded = [];
if (is_file($stateFile)) {
    $reminded = json_decode(@file_get_contents($stateFile) ?: '[]', true);
    if (!is_array($reminded)) $reminded = [];
}
try {
    $stmt = $pdo->query("SELECT id_order, id_user, price FROM Payment_report WHERE payment_Status != 'paid' AND payment_Status != 'expire' ORDER BY id_order DESC LIMIT 200");
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    error_log('nudge_unpaid query: ' . $e->getMessage());
    exit;
}
$sent = 0;
foreach ($rows as $r) {
    $order = (string) ($r['id_order'] ?? '');
    $uid = (string) ($r['id_user'] ?? '');
    if ($order === '' || $uid === '' || isset($reminded[$order])) continue;
    if ($sent >= 20) break;
    $price = number_format((float) ($r['price'] ?? 0));
    $msg = "⏳ فاکتور پرداخت‌نشده دارید.\n💰 مبلغ: {$price} تومان\nکد پیگیری: <code>{$order}</code>\nبرای پرداخت از بخش کیف پول اقدام کنید.";
    try {
        sendmessage($uid, $msg, null, 'HTML');
        $reminded[$order] = time();
        $sent++;
    } catch (Throwable $e) { error_log('nudge_unpaid send: ' . $e->getMessage()); }
}
$cut = time() - 7 * 86400;
foreach ($reminded as $k => $t) { if ((int) $t < $cut) unset($reminded[$k]); }
@file_put_contents($stateFile, json_encode($reminded), LOCK_EX);
echo "nudged: {$sent}\n";
