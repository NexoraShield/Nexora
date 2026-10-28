<?php
require __DIR__ . '/../config.php';
if (!isset($pdo) || !($pdo instanceof PDO)) { fwrite(STDERR, "No DB connection from config.php\n"); exit(1); }
$indexes = [
    ['invoice', 'idx_invoice_user_status', '(id_user, Status)'],
    ['invoice', 'idx_invoice_location', '(Service_location)'],
    ['invoice', 'idx_invoice_time', '(time_sell)'],
    ['Payment_report', 'idx_pay_user_status', '(id_user, payment_Status)'],
    ['Payment_report', 'idx_pay_method_status', '(Payment_Method, payment_Status)'],
    ['Payment_report', 'idx_pay_order', '(id_order)'],
    ['marzban_panel', 'idx_panel_status_agent', '(status, agent)'],
    ['product', 'idx_product_loc_agent', '(Location, agent)'],
    ['product', 'idx_product_code', '(code_product)'],
    ['user', 'idx_user_agent', '(agent)'],
    ['user', 'idx_user_verify', '(verify)'],
];
$done = 0; $skipped = 0;
foreach ($indexes as [$table, $name, $cols]) {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
        $st->execute([$table, $name]);
        if ((int) $st->fetchColumn() > 0) { $skipped++; continue; }
        $t = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $t->execute([$table]);
        if ((int) $t->fetchColumn() === 0) { $skipped++; continue; }
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$name}` {$cols}");
        echo "added {$table}.{$name}\n";
        $done++;
    } catch (Throwable $e) {
        echo "skip {$table}.{$name}: " . $e->getMessage() . "\n";
        $skipped++;
    }
}
echo "done: {$done} added, {$skipped} skipped\n";
