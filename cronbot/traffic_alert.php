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

// Alert users at 80% and 90% traffic usage
$stmt = $pdo->prepare("SELECT * FROM service_other WHERE status = 'active'");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($services as $svc) {
    $used = (float) ($svc['used_traffic'] ?? 0);
    $limit = (float) ($svc['data_limit'] ?? 0);
    if ($limit <= 0) continue;
    $pct = ($used / $limit) * 100;
    if ($pct >= 90 && $pct < 95) {
        $msg = "⚠️ هشدار: " . round($pct) . "% از حجم ترافیک شما استفاده شده است.\nلطفاً نسبت به تمدید یا افزایش حجم اقدام کنید.";
        sendmessage($svc['id_user'], $msg, null, 'HTML');
    } elseif ($pct >= 80 && $pct < 85) {
        $msg = "📊 اطلاع: " . round($pct) . "% از حجم ترافیک شما استفاده شده است.";
        sendmessage($svc['id_user'], $msg, null, 'HTML');
    }
}
echo "Traffic alerts sent.\n";