<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Toggle maintenance flag in setting table (column maintenance_mode if exists)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $val = ($_POST['mode'] ?? 'off') === 'on' ? 'on' : 'off';
    try {
        // ensure column
        $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='setting' AND COLUMN_NAME='maintenance_mode'");
        $st->execute();
        if ((int)$st->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `setting` ADD `maintenance_mode` VARCHAR(8) NULL DEFAULT 'off'");
        }
        $pdo->prepare("UPDATE setting SET maintenance_mode=?")->execute([$val]);
        flash('success', $val === 'on' ? 'حالت تعمیرات فعال شد.' : 'حالت تعمیرات غیرفعال شد.');
    } catch (Throwable $e) {
        flash('error', 'خطا: ' . $e->getMessage());
    }
    header('Location: maintenance.php');
    exit;
}

$mode = 'off';
try {
    $st = $pdo->prepare("SELECT maintenance_mode FROM setting LIMIT 1");
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['maintenance_mode'])) $mode = $row['maintenance_mode'];
} catch (Throwable $e) { $mode = 'off'; }

$pageTitle = '🛠️ حالت تعمیرات';
$pageLede = 'فعال/غیرفعال کردن حالت تعمیرات (Maintenance Mode) برای ربات.';
$activeNav = 'maintenance';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">حالت تعمیرات</div></div></div>
  <div class="card-body">
    <p>وضعیت فعلی: <b><?= $mode === 'on' ? '🔴 فعال' : '🟢 غیرفعال' ?></b></p>
    <form method="POST" style="margin-top:12px">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="mode" value="<?= $mode === 'on' ? 'off' : 'on' ?>">
      <button type="submit" class="btn <?= $mode === 'on' ? 'btn-primary' : 'btn-danger' ?>">
        <?= $mode === 'on' ? '🟢 غیرفعال کردن' : '🔴 فعال کردن' ?>
      </button>
    </form>
    <p style="margin-top:14px;opacity:.7;font-size:.85rem">
      در حالت تعمیرات، ربات پیام «در حال تعمیرات» نمایش می‌دهد.
    </p>
  </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
