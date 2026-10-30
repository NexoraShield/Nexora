<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable') {
    csrf_check_post();
    $id = (int) ($_POST['edit_id'] ?? 0);
    if ($id > 0) {
        try {
            db_query($pdo, "UPDATE `marzban_panel` SET status='disable' WHERE `id`=?", [$id]);
            flash('success', 'پنل غیرفعال شد.');
        } catch (Exception $e) {
            flash('error', 'خطای دیتابیس: ' . $e->getMessage());
        }
    }
    header('Location: health.php');
    exit;
}
function nexora_panel_probe(string $url): array
{
    $url = trim($url);
    if ($url === '') return ['ok' => false, 'ms' => 0, 'info' => 'بدون آدرس'];
    if (!preg_match('#^https?://#i', $url)) $url = 'http://' . $url;
    $t0 = microtime(true);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $ms = (int) round((microtime(true) - $t0) * 1000);
    if ($err !== '') return ['ok' => false, 'ms' => $ms, 'info' => 'عدم دسترسی'];
    return ['ok' => $code > 0 && $code < 500, 'ms' => $ms, 'info' => 'HTTP ' . $code];
}
$panels = [];
try { $panels = db_fetchAll($pdo, "SELECT id, name_panel, type, url_panel, status FROM marzban_panel ORDER BY id"); } catch (Exception $e) { $panels = []; }
$pageTitle = 'سلامت پنل‌ها';
$pageLede = 'بررسی دسترسی پنل‌ها + غیرفعال‌سازی پنل خراب';
$activeNav = 'health';
include __DIR__ . '/inc/layout_head.php';
$flashOk = get_flash('success');
$flashErr = get_flash('error');
if ($flashOk) { echo '<div class="card fade-up" style="margin-bottom:14px"><div class="card-body">' . htmlspecialchars($flashOk) . '</div></div>'; }
if ($flashErr) { echo '<div class="card fade-up" style="margin-bottom:14px"><div class="card-body">' . htmlspecialchars($flashErr) . '</div></div>'; }
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">وضعیت پنل‌ها (<?= count($panels) ?>)</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>نام</th><th>نوع</th><th>وضعیت</th><th>پاسخ</th><th>پینگ</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($panels as $p):
        $probe = nexora_panel_probe((string) ($p['url_panel'] ?? ''));
        $isActive = (($p['status'] ?? '') === 'active');
      ?>
      <tr>
        <td class="cs"><?= htmlspecialchars(stripCustomEmojiTags($p['name_panel']) ?? '') ?></td>
        <td><?= htmlspecialchars($p['type'] ?? '') ?></td>
        <td><?= $isActive ? '<span class="tag tag-ok">فعال</span>' : '<span class="tag tag-no">غیرفعال</span>' ?></td>
        <td><?= $probe['ok'] ? '<span class="tag tag-ok">سالم</span>' : '<span class="tag tag-no">' . htmlspecialchars($probe['info']) . '</span>' ?></td>
        <td class="cf"><?= $probe['ms'] ?>ms</td>
        <td>
          <?php if ($isActive && !$probe['ok']): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('این پنل غیرفعال شود؟')">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="disable">
            <input type="hidden" name="edit_id" value="<?= (int) $p['id'] ?>">
            <button type="submit" class="btn btn-no btn-sm">غیرفعال</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
