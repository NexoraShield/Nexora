<?php
// check_volumes.php - check service volumes/time and warn users nearing expiry
$target = $argv[1] ?? '';
$target = rtrim($target, "/\\");
if ($target === '' || !is_dir($target)) {
    foreach (['/var/www/html/nexorabot','/var/www/html/nexorabotconfig','/var/www/nexorabot','/var/www/html/bot'] as $c) {
        if (is_dir($c) && is_file($c . '/config.php')) { $target = $c; break; }
    }
}
if ($target === '' || !is_dir($target)) { fwrite(STDERR, "Target directory not found.\n"); exit(1); }
$configFile = $target . '/config.php';
$helperFile = dirname(__DIR__) . '/nexora_style.php';
if (!is_file($configFile)) { fwrite(STDERR, "config.php not found.\n"); exit(1); }
if (!is_file($helperFile)) { fwrite(STDERR, "nexora_style.php not found.\n"); exit(1); }
require $configFile;
require $helperFile;
if (!isset($pdo) || !($pdo instanceof PDO)) { fwrite(STDERR, "DB connection not available.\n"); exit(1); }
$textbotlang = function_exists('languagechange') ? languagechange() : [];
$now = time();
$warned = 0;
try {
    $stmt = $pdo->query("SELECT * FROM invoice WHERE status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold'");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($items as $inv) {
        $ts = (int)($inv['time_sell'] ?? 0);
        $days = (int)($inv['Service_time'] ?? 0);
        if ($days <= 0 || $ts <= 0) continue;
        $expiry = $ts + $days * 86400;
        $remaining = $expiry - $now;
        if ($remaining > 0 && $remaining < 3 * 86400 && ($inv['Status'] ?? '') !== 'sendedwarn') {
            // send warning (best-effort)
            $msg = "⚠️ سرویس شما ({$inv['name_product']}) تا " . ceil($remaining/86400) . " روز دیگر منقضی می‌شود.";
            // use telegram if available
            if (function_exists('sendmessage')) { @sendmessage($inv['id_user'], $msg, null, 'HTML'); }
            $pdo->prepare("UPDATE invoice SET Status='sendedwarn' WHERE id_invoice=?")->execute([$inv['id_invoice']]);
            $warned++;
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "check_volumes failed: " . $e->getMessage() . "\n");
    exit(1);
}
echo "Checked volumes. Warnings sent: $warned\n";