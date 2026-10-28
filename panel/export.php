<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();
if (isset($_GET['dl'])) {
    $kind = $_GET['dl'];
    csrf_check_get();
    if ($kind === 'users' || $kind === 'invoices') {
        try {
            if ($kind === 'users') {
                $rows = db_fetchAll($pdo, "SELECT id, username, Balance, agent, number FROM `user` ORDER BY id");
                $head = ['id', 'username', 'Balance', 'agent', 'number'];
            } else {
                $rows = db_fetchAll($pdo, "SELECT id_invoice, id_user, username, name_product, price_product, Status, time_sell FROM invoice ORDER BY time_sell DESC LIMIT 5000");
                $head = ['id_invoice', 'id_user', 'username', 'name_product', 'price_product', 'Status', 'time_sell'];
            }
        } catch (Exception $e) {
            die('DB error: ' . htmlspecialchars($e->getMessage()));
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nexora-' . $kind . '-' . date('Ymd-His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $head);
        foreach ($rows as $r) {
            $line = [];
            foreach ($head as $h) {
                $line[] = isset($r[$h]) ? (string) $r[$h] : '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }
}
$pageTitle = 'خروجی اکسل';
$pageLede = 'دانلود CSV کاربران و فاکتورها (سازگار با Excel)';
$activeNav = 'export';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">خروجی گرفتن</div></div></div>
  <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn btn-primary" href="export.php?dl=users&_csrf=<?= csrf_token() ?>">دانلود کاربران (CSV)</a>
    <a class="btn btn-primary" href="export.php?dl=invoices&_csrf=<?= csrf_token() ?>">دانلود فاکتورها (CSV)</a>
  </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
