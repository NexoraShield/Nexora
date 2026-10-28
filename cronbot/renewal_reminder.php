<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../jdf.php';
$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$textbotlang = languagechange();

// Send renewal reminders 3 days and 1 day before expiry
$days = [3, 1];
foreach ($days as $day) {
    $targetDate = date('Y/m/d', strtotime("+$day days"));
    $stmt = $pdo->prepare("SELECT * FROM service_other WHERE status = 'active' AND expire_date LIKE ?");
    $stmt->execute(["%$targetDate%"]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($services as $svc) {
        $user = select("user", "*", "id", $svc['id_user'], "select");
        if (!$user) continue;
        $lang = $user['lang'] ?? 'fa';
        // Send reminder
        $msg = "⏰ یادآوری: سرویس شما " . $day . " روز دیگر منقضی می‌شود.\nبرای تمدید به بات مراجعه کنید.";
        sendmessage($svc['id_user'], $msg, null, 'HTML');
    }
}
echo "Renewal reminders sent.\n";