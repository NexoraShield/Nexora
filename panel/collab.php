<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Ensure collab_notes table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS collab_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_user VARCHAR(64) NULL,
        title VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        pinned TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        $title = trim((string)($_POST['title'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $pinned = isset($_POST['pinned']) ? 1 : 0;
        if ($title !== '' && $body !== '') {
            try {
                $admin = $_SESSION['admin_user'] ?? 'admin';
                $st = $pdo->prepare("INSERT INTO collab_notes (admin_user, title, body, pinned) VALUES (?,?,?,?)");
                $st->execute([$admin, $title, $body, $pinned]);
            } catch (Throwable $e) {}
        }
    } elseif ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { try { $pdo->prepare("DELETE FROM collab_notes WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    } elseif ($act === 'pin') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { try { $pdo->prepare("UPDATE collab_notes SET pinned = 1 - pinned WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    }
    header('Location: collab.php');
    exit;
}

$notes = [];
try { $notes = $pdo->query("SELECT * FROM collab_notes ORDER BY pinned DESC, id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) { $notes = []; }

$pageTitle = '🤝 همکاری (Collaboration Board)';
$pageLede = 'یادداشت‌ها و اعلان‌های مشترک بین ادمین‌ها.';
$activeNav = 'collab';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">🤝 همکاری (<?= count($notes) ?>)</div></div></div>
  <div class="card-body">
    <form method="POST" style="display:grid;grid-template-columns:1fr;gap:10px;margin-bottom:16px">
      <input type="hidden" name="action" value="add">
      <input type="text" name="title" placeholder="عنوان یادداشت *" class="input" required>
      <textarea name="body" placeholder="متن یادداشت..." class="input" style="min-height:80px" required></textarea>
      <label><input type="checkbox" name="pinned" value="1"> پین کردن (نمایش در بالا)</label>
      <button type="submit" class="btn btn-primary">➕ افزودن یادداشت</button>
    </form>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>#</th><th>عنوان</th><th>متن</th><th>ادمین</th><th>زمان</th><th>عملیات</th></tr></thead>
      <tbody>
        <?php foreach ($notes as $n): ?>
        <tr>
          <td><?= (int)$n['id'] ?> <?= $n['pinned'] ? '📌' : '' ?></td>
          <td><?= htmlspecialchars($n['title']) ?></td>
          <td style="max-width:300px;word-break:break-word"><?= nl2br(htmlspecialchars($n['body'])) ?></td>
          <td><?= htmlspecialchars($n['admin_user'] ?? '') ?></td>
          <td dir="ltr" style="font-size:.75rem"><?= htmlspecialchars($n['created_at']) ?></td>
          <td>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="pin"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button type="submit" class="btn btn-sm"><?= $n['pinned'] ? 'Unpin' : 'Pin' ?></button></form>
            <form method="POST" style="display:inline" onsubmit="return confirm('حذف؟')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button type="submit" class="btn btn-sm">حذف</button></form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($notes)): ?>
        <tr><td colspan="6" style="text-align:center;opacity:.6">یادداشتی یافت نشد.</td></tr>
        <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>