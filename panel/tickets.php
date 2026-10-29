<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Ensure support_tickets table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_user VARCHAR(32) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        admin_reply TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_user (id_user)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Handle admin reply / close
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tid = (int)($_POST['id'] ?? 0);
    if ($tid > 0) {
        if ($action === 'reply') {
            $reply = trim((string)($_POST['reply'] ?? ''));
            if ($reply !== '') {
                try {
                    $st = $pdo->prepare("UPDATE support_tickets SET admin_reply = ?, status = 'answered', updated_at = NOW() WHERE id = ?");
                    $st->execute([$reply, $tid]);
                    // notify user via bot (best-effort)
                    $row = $pdo->prepare("SELECT id_user, subject FROM support_tickets WHERE id = ?");
                    $row->execute([$tid]);
                    $t = $row->fetch(PDO::FETCH_ASSOC);
                    if ($t) {
                        // best-effort telegram notify; ignore failures
                        @file_get_contents('https://api.telegram.org/bot' . (defined('APIKEY') ? APIKEY : '') . '/sendMessage?' . http_build_query(['chat_id' => $t['id_user'], 'text' => "پاسخ پشتیبانی (تیکت #" . $tid . "):\n" . $reply]));
                    }
                } catch (Throwable $e) {}
            }
        } elseif ($action === 'close') {
            try {
                $pdo->prepare("UPDATE support_tickets SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$tid]);
            } catch (Throwable $e) {}
        }
    }
    header('Location: tickets.php?status=' . urlencode($_POST['filter'] ?? 'open'));
    exit;
}

$filter = $_GET['status'] ?? 'open';
$allowed = ['open', 'answered', 'closed', 'all'];
if (!in_array($filter, $allowed, true)) $filter = 'open';

$tickets = [];
try {
    if ($filter === 'all') {
        $tickets = $pdo->query("SELECT * FROM support_tickets ORDER BY id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $st = $pdo->prepare("SELECT * FROM support_tickets WHERE status = ? ORDER BY id DESC LIMIT 200");
        $st->execute([$filter]);
        $tickets = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) { $tickets = []; }

$counts = ['open' => 0, 'answered' => 0, 'closed' => 0];
try {
    foreach (['open','answered','closed'] as $s) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE status = ?");
        $c->execute([$s]);
        $counts[$s] = (int)$c->fetchColumn();
    }
} catch (Throwable $e) {}

$pageTitle = '🎫 پشتیبانی و تیکت‌ها';
$pageLede = 'مدیریت تیکت‌های پشتیبانی کاربران.';
$activeNav = 'tickets';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">🎫 تیکت‌ها (<?= count($tickets) ?>)</div></div></div>
  <div style="display:flex;gap:8px;margin:10px 0;flex-wrap:wrap">
    <?php foreach (['open'=>'باز','answered'=>'پاسخ‌داده‌شده','closed'=>'بسته','all'=>'همه'] as $k=>$lbl): ?>
      <a href="tickets.php?status=<?= $k ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-secondary' ?>"><?= $lbl ?> (<?= $counts[$k] ?? array_sum($counts) ?>)</a>
    <?php endforeach; ?>
  </div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>#</th><th>کاربر</th><th>موضوع</th><th>پیام</th><th>وضعیت</th><th>پاسخ ادمین</th><th>زمان</th><th>عملیات</th></tr></thead>
    <tbody>
      <?php foreach ($tickets as $t): ?>
      <tr>
        <td><?= (int)$t['id'] ?></td>
        <td dir="ltr"><?= htmlspecialchars($t['id_user']) ?></td>
        <td><?= htmlspecialchars($t['subject']) ?></td>
        <td style="max-width:260px;word-break:break-word"><?= htmlspecialchars($t['message']) ?></td>
        <td><?= htmlspecialchars($t['status']) ?></td>
        <td style="max-width:260px;word-break:break-word"><?= htmlspecialchars($t['admin_reply'] ?? '') ?></td>
        <td dir="ltr" style="font-size:.75rem"><?= htmlspecialchars($t['created_at']) ?></td>
        <td>
          <?php if ($t['status'] !== 'closed'): ?>
          <form method="POST" style="display:inline-block;margin:2px">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <input type="text" name="reply" placeholder="پاسخ..." style="width:120px" class="input">
            <button type="submit" class="btn btn-sm btn-primary">پاسخ</button>
          </form>
          <form method="POST" style="display:inline-block;margin:2px">
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
            <button type="submit" class="btn btn-sm">بستن</button>
          </form>
          <?php else: ?>
            <span style="opacity:.6">بسته</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($tickets)): ?>
        <tr><td colspan="8" style="text-align:center;opacity:.6">تیکتی یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>