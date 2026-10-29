<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Ensure audit table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS AuditLog (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_user VARCHAR(64) NULL,
        action VARCHAR(128) NOT NULL,
        detail TEXT NULL,
        ip VARCHAR(64) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Log helper (global)
if (!function_exists('audit_log')) {
    function audit_log($action, $detail = '') {
        global $pdo;
        try {
            $admin = $_SESSION['admin_user'] ?? 'unknown';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $st = $pdo->prepare("INSERT INTO AuditLog (admin_user, action, detail, ip) VALUES (?,?,?,?)");
            $st->execute([$admin, $action, mb_substr((string)$detail,0,2000), $ip]);
        } catch (Throwable $e) {}
    }
}

// Auto-log page view
audit_log('view_audit', '');

$logs = [];
try {
    $logs = db_fetchAll($pdo, "SELECT * FROM AuditLog ORDER BY id DESC LIMIT 200");
} catch (Throwable $e) { $logs = []; }

$pageTitle = '📜 گزارش فعالیت (Audit Log)';
$pageLede = 'ثبت و مشاهدهٔ تمام اقدامات انجام‌شده در پنل مدیریت.';
$activeNav = 'audit';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">گزارش فعالیت (<?= count($logs) ?>)</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>#</th><th>ادمین</th><th>اقدام</th><th>جزئیات</th><th>IP</th><th>زمان</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= (int)$l['id'] ?></td>
        <td><?= htmlspecialchars($l['admin_user'] ?? '') ?></td>
        <td><?= htmlspecialchars($l['action'] ?? '') ?></td>
        <td style="max-width:300px;word-break:break-word"><?= htmlspecialchars($l['detail'] ?? '') ?></td>
        <td dir="ltr"><?= htmlspecialchars($l['ip'] ?? '') ?></td>
        <td dir="ltr"><?= htmlspecialchars($l['created_at'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
