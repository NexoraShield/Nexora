<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// List recent backup files (created by cronbot/backup.php or manual)
$backupDir = dirname(__DIR__) . '/backups';
$backups = [];
if (is_dir($backupDir)) {
    foreach (glob($backupDir . '/*') as $f) {
        if (is_file($f)) {
            $backups[] = ['name' => basename($f), 'size' => filesize($f), 'mtime' => filemtime($f)];
        }
    }
    usort($backups, function ($a, $b) { return $b['mtime'] <=> $a['mtime']; });
    $backups = array_slice($backups, 0, 50);
}

// Trigger manual backup (best-effort, runs backup script)
$triggered = false;
if (isset($_GET['run']) && $_GET['run'] === '1') {
    $script = dirname(__DIR__) . '/cronbot/backup.php';
    if (is_file($script)) {
        // run in background-ish via php cli
        $cmd = 'php ' . escapeshellarg($script) . ' > /dev/null 2>&1 &';
        @shell_exec($cmd);
        $triggered = true;
    }
}

$pageTitle = '💾 پشتیبان‌گیری (Backup)';
$pageLede = 'پشتیبان‌گیری از دیتابیس ربات و پنل‌ها.';
$activeNav = 'backup';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">💾 پشتیبان‌گیری</div></div></div>
  <div class="card-body">
    <?php if ($triggered): ?>
      <p style="color:#4fae4e">✅ درخواست پشتیبان‌گیری ارسال شد. پس از چند ثانیه صفحه را رفرش کنید.</p>
    <?php endif; ?>
    <p>پوشهٔ بکاپ: <code><?= htmlspecialchars($backupDir) ?></code></p>
    <a href="backup.php?run=1" class="btn btn-primary" style="margin:8px 0">▶ اجرای پشتیبان‌گیری دستی</a>
    <h4 style="margin-top:16px">بکاپ‌های اخیر (<?= count($backups) ?>)</h4>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>#</th><th>نام فایل</th><th>حجم</th><th>زمان</th></tr></thead>
      <tbody>
        <?php foreach ($backups as $i => $b): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($b['name']) ?></td>
          <td><?= number_format($b['size']) ?> B</td>
          <td dir="ltr"><?= date('Y-m-d H:i:s', $b['mtime']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($backups)): ?>
        <tr><td colspan="4" style="text-align:center;opacity:.6">بکاپی یافت نشد.</td></tr>
        <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>