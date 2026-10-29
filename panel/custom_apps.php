<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// Ensure custom_apps table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_apps (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(128) NOT NULL,
        platform VARCHAR(64) NULL,
        download_link VARCHAR(512) NULL,
        tutorial TEXT NULL,
        icon VARCHAR(64) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// Handle add/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $platform = trim((string)($_POST['platform'] ?? ''));
        $link = trim((string)($_POST['download_link'] ?? ''));
        $tutorial = trim((string)($_POST['tutorial'] ?? ''));
        $icon = trim((string)($_POST['icon'] ?? ''));
        if ($name !== '') {
            try {
                $st = $pdo->prepare("INSERT INTO custom_apps (name, platform, download_link, tutorial, icon, sort_order, status) VALUES (?,?,?,?,?,?, 'active')");
                $st->execute([$name, $platform, $link, $tutorial, $icon, (int)($_POST['sort_order'] ?? 0)]);
            } catch (Throwable $e) {}
        }
    } elseif ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { try { $pdo->prepare("DELETE FROM custom_apps WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    } elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { try { $pdo->prepare("UPDATE custom_apps SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    }
    header('Location: custom_apps.php');
    exit;
}

$apps = [];
try { $apps = $pdo->query("SELECT * FROM custom_apps ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable $e) { $apps = []; }

$pageTitle = '📱 برنامه اختصاصی و آموزش اتصال';
$pageLede = 'مدیریت برنامه‌های اختصاصی و آموزش‌های اتصال برای کاربران.';
$activeNav = 'custom_apps';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">📱 برنامه‌های اختصاصی (<?= count($apps) ?>)</div></div></div>
  <div class="card-body">
    <form method="POST" class="form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="add">
      <input type="text" name="name" placeholder="نام برنامه *" class="input" required>
      <input type="text" name="platform" placeholder="پلتفرم (Android/iOS/Windows)" class="input">
      <input type="text" name="download_link" placeholder="لینک دانلود" class="input">
      <input type="text" name="icon" placeholder="آیکون/emoji" class="input">
      <input type="number" name="sort_order" placeholder="ترتیب" class="input" value="0">
      <textarea name="tutorial" placeholder="آموزش اتصال (HTML مجاز)" class="input" style="grid-column:1/-1;min-height:80px"></textarea>
      <button type="submit" class="btn btn-primary" style="grid-column:1/-1">➕ افزودن</button>
    </form>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>#</th><th>نام</th><th>پلتفرم</th><th>لینک</th><th>آموزش</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
        <?php foreach ($apps as $a): ?>
        <tr>
          <td><?= (int)$a['id'] ?></td>
          <td><?= htmlspecialchars($a['name']) ?> <?= htmlspecialchars($a['icon'] ?? '') ?></td>
          <td><?= htmlspecialchars($a['platform'] ?? '') ?></td>
          <td style="max-width:160px;word-break:break-all"><a href="<?= htmlspecialchars($a['download_link'] ?? '') ?>" target="_blank">دانلود</a></td>
          <td style="max-width:200px;word-break:break-word;font-size:.8rem"><?= htmlspecialchars(mb_substr($a['tutorial'] ?? '',0,80)) ?></td>
          <td><?= htmlspecialchars($a['status']) ?></td>
          <td>
            <form method="POST" style="display:inline"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button type="submit" class="btn btn-sm">تغییر وضعیت</button></form>
            <form method="POST" style="display:inline" onsubmit="return confirm('حذف؟')"><input type="hidden" name="_csrf" value="<?= csrf_token() ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button type="submit" class="btn btn-sm">حذف</button></form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($apps)): ?>
        <tr><td colspan="7" style="text-align:center;opacity:.6">برنامه‌ای یافت نشد.</td></tr>
        <?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>