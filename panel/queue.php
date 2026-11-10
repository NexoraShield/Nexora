<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Ensure op_queue table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS op_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_user VARCHAR(32) NOT NULL,
        id_invoice VARCHAR(64) NULL,
        op_type VARCHAR(32) NOT NULL,
        payload TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_user (id_user)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Handle retry / clear
if (isset($_GET['action'])) {
    $act = $_GET['action'];
    $qid = (int)($_GET['id'] ?? 0);
    try {
        if ($act === 'retry' && $qid > 0) {
            $pdo->prepare("UPDATE op_queue SET status='pending', attempts=0, last_error=NULL, updated_at=NOW() WHERE id=?")->execute([$qid]);
        } elseif ($act === 'clear_done') {
            $pdo->exec("DELETE FROM op_queue WHERE status IN ('done','failed')");
        } elseif ($act === 'delete' && $qid > 0) {
            $pdo->prepare("DELETE FROM op_queue WHERE id=?")->execute([$qid]);
        }
    } catch (Throwable $e) {}
    header('Location: queue.php');
    exit;
}

$filter = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'processing', 'done', 'failed', 'all'];
if (!in_array($filter, $allowed, true)) $filter = 'pending';

$items = [];
try {
    if ($filter === 'all') {
        $items = $pdo->query("SELECT * FROM op_queue ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $st = $pdo->prepare("SELECT * FROM op_queue WHERE status = ? ORDER BY id DESC LIMIT 200");
        $st->execute([$filter]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) { $items = []; }

$counts = [];
foreach (['pending','processing','done','failed'] as $s) {
    try { $c = $pdo->prepare("SELECT COUNT(*) FROM op_queue WHERE status=?"); $c->execute([$s]); $counts[$s]=(int)$c->fetchColumn(); } catch (Throwable $e) { $counts[$s]=0; }
}

$pageTitle = '📋 صف عملیات‌های ناقص (Queue)';
$pageLede = 'مدیریت عملیات‌های انجام‌نشده (مثلاً ایجاد سرویس روی سرور قطع‌شده).';
$activeNav = 'queue';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">📋 صف عملیات (<?= count($items) ?>)</div></div></div>
  <div style="display:flex;gap:8px;margin:10px 0;flex-wrap:wrap">
    <?php foreach (['pending'=>'در انتظار','processing'=>'در حال انجام','done'=>'انجام‌شده','failed'=>'ناموفق','all'=>'همه'] as $k=>$lbl): ?>
      <a href="queue.php?status=<?= $k ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-secondary' ?>"><?= $lbl ?> (<?= $counts[$k] ?? 0 ?>)</a>
    <?php endforeach; ?>
    <a href="queue.php?action=clear_done" class="btn btn-sm" onclick="return confirm('پاک کردن done/failed؟')">پاکسازی done/failed</a>
  </div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>#</th><th>کاربر</th><th>فاکتور</th><th>نوع عملیات</th><th>وضعیت</th><th>تلاش</th><th>خطا</th><th>زمان</th><th>عملیات</th></tr></thead>
    <tbody>
      <?php foreach ($items as $q): ?>
      <tr>
        <td><?= (int)$q['id'] ?></td>
        <td dir="ltr"><?= htmlspecialchars($q['id_user']) ?></td>
        <td dir="ltr"><?= htmlspecialchars($q['id_invoice'] ?? '') ?></td>
        <td><?= htmlspecialchars($q['op_type']) ?></td>
        <td><?= htmlspecialchars($q['status']) ?></td>
        <td><?= (int)$q['attempts'] ?></td>
        <td style="max-width:200px;word-break:break-word;font-size:.75rem"><?= htmlspecialchars($q['last_error'] ?? '') ?></td>
        <td dir="ltr" style="font-size:.75rem"><?= htmlspecialchars($q['created_at']) ?></td>
        <td>
          <?php if ($q['status'] !== 'done'): ?>
            <a href="queue.php?action=retry&id=<?= (int)$q['id'] ?>" class="btn btn-sm btn-primary">تلاش مجدد</a>
          <?php endif; ?>
          <a href="queue.php?action=delete&id=<?= (int)$q['id'] ?>" class="btn btn-sm" onclick="return confirm('حذف؟')">حذف</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <tr><td colspan="9" style="text-align:center;opacity:.6">موردی یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table></div>
  <p style="margin-top:10px;opacity:.7;font-size:.85rem">Cron پیشنهادی: <code>*/10 * * * * php BOTDIR/cronbot/process_queue.php</code></p>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>