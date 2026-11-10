<?php
// process_queue.php - retry pending operations (e.g. create_user on downed servers)
// Usage: php BOTDIR/cronbot/process_queue.php [BOT_DIR]
$target = $argv[1] ?? '';
$target = rtrim($target, "/\\");
if ($target === '' || !is_dir($target)) {
    // auto-detect
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
$ManagePanel = null;
if (is_file($target . '/panels.php')) { require $target . '/panels.php'; }
if (!isset($ManagePanel) || !($ManagePanel instanceof ManagePanel)) { $ManagePanel = new ManagePanel(); }

$maxAttempts = 5;
$processed = 0; $done = 0; $failed = 0;
try {
    $stmt = $pdo->prepare("SELECT * FROM op_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 20");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($items as $q) {
        $processed++;
        $id = (int)$q['id'];
        $pdo->prepare("UPDATE op_queue SET status='processing', attempts=attempts+1, updated_at=NOW() WHERE id=?")->execute([$id]);
        $payload = json_decode($q['payload'] ?? '', true) ?: [];
        $ok = false; $err = '';
        try {
            if ($q['op_type'] === 'create_user') {
                $dataoutput = $ManagePanel->createUser($payload['panel'] ?? '', $payload['code_product'] ?? '', $payload['username'] ?? '', $payload['datac'] ?? []);
                if (is_array($dataoutput) && !empty($dataoutput['username'])) {
                    $ok = true;
                    if (!empty($q['id_invoice'])) {
                        $pdo->prepare("UPDATE invoice SET Status='active' WHERE id_invoice=? AND Status<>'active'")->execute([$q['id_invoice']]);
                    }
                } else {
                    $err = json_encode($dataoutput['msg'] ?? $dataoutput ?? 'unknown');
                }
            } else {
                $err = 'unsupported op_type: ' . $q['op_type'];
            }
        } catch (Throwable $e) { $err = $e->getMessage(); }
        if ($ok) {
            $pdo->prepare("UPDATE op_queue SET status='done', last_error=NULL, updated_at=NOW() WHERE id=?")->execute([$id]);
            $done++;
        } else {
            $attempts = (int)$q['attempts'] + 1;
            if ($attempts >= $maxAttempts) {
                $pdo->prepare("UPDATE op_queue SET status='failed', last_error=?, updated_at=NOW() WHERE id=?")->execute([$err, $id]);
                $failed++;
            } else {
                $pdo->prepare("UPDATE op_queue SET status='pending', last_error=?, updated_at=NOW() WHERE id=?")->execute([$err, $id]);
            }
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "process_queue failed: " . $e->getMessage() . "\n");
    exit(1);
}
echo "Processed: $processed, Done: $done, Failed: $failed\n";