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

// Check panel health
$stmt = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active'");
$stmt->execute();
$panels = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($panels as $panel) {
    try {
        $start = microtime(true);
        // The panel drivers no longer expose testConnection(); the panel API
        // returns an access token when the panel answers.
        $check = function_exists('token_panel') ? token_panel($panel['code_panel'], false) : null;
        $reachable = is_array($check) && isset($check['access_token']);
        $elapsed = round((microtime(true) - $start) * 1000);
        if (!$reachable) {
            error_log("[health] Panel " . $panel['name_panel'] . " is unreachable");
            // Notify admin
            $adminId = $setting['adminnumber'] ?? '';
            if ($adminId) {
                $msg = "⚠️ پنل " . $panel['name_panel'] . " غیرقابل دسترس است!";
                sendmessage($adminId, $msg, null, 'HTML');
            }
        }
    } catch (Throwable $e) {
        error_log("[health] Panel " . $panel['name_panel'] . " error: " . $e->getMessage());
    }
}
echo "Health check done.\n";