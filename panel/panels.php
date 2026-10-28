<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/bot_emojis.php';
require_auth();
try {
    foreach ([['btn_style', 'VARCHAR(20) NULL'], ['btn_emoji_id', 'VARCHAR(32) NULL']] as [$col, $def]) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'marzban_panel' AND COLUMN_NAME = ?");
        $st->execute([$col]);
        if ((int) $st->fetchColumn() === 0) $pdo->exec("ALTER TABLE `marzban_panel` ADD `{$col}` {$def}");
    }
} catch (Exception $e) {
    flash('error', 'خطای دیتابیس: ' . $e->getMessage());
    header('Location: panels.php');
    exit;
}
function mirza_panels_clean_style($v): string { $v = trim((string) $v); return in_array($v, ['primary', 'success', 'danger'], true) ? $v : ''; }
function mirza_panels_clean_emoji($v): string {
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('/^\d{1,32}$/', $v)) return $v;
    if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $v, $m)) return $m[1];
    if (preg_match('/^\{emoji:([^}]+)\}$/u', $v, $m) && function_exists('mirza_emoji_lookup')) {
        $row = mirza_emoji_lookup(trim($m[1]));
        if ($row && !empty($row['custom_emoji_id'])) return (string) $row['custom_emoji_id'];
    }
    return '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    csrf_check_post();
    $id = (int) ($_POST['edit_id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? ''));
    $agent = trim((string) ($_POST['agent'] ?? ''));
    $limit = trim((string) ($_POST['limit_panel'] ?? ''));
    if ($id > 0) {
        if (!in_array($status, ['active', 'disable'], true)) $status = 'active';
        if ($agent === '') $agent = 'all';
        try {
            db_query($pdo, "UPDATE `marzban_panel` SET status=?, agent=?, limit_panel=?, btn_style=?, btn_emoji_id=? WHERE `id`=?", [
                $status, mb_substr($agent, 0, 200), mb_substr($limit, 0, 100),
                (mirza_panels_clean_style($_POST['btn_style'] ?? '') ?: null),
                (mirza_panels_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null),
                $id,
            ]);
            flash('success', 'ذخیره شد.');
        } catch (Exception $e) { flash('error', 'خطای دیتابیس: ' . $e->getMessage()); }
    }
    header('Location: panels.php');
    exit;
}
$panels = db_fetchAll($pdo, "SELECT id, name_panel, type, status, agent, limit_panel, url_panel, btn_style, btn_emoji_id FROM marzban_panel ORDER BY id");
$pageTitle = 'پنل‌های VPN';
$pageLede = 'ویرایش پنل‌ها: وضعیت، دسترسی، محدودیت، رنگ و ایموجی دکمه در ربات.';
$activeNav = 'panels';
$emojiLibrary = function_exists('mirza_emoji_list') ? mirza_emoji_list() : [];
$msEmojiRows = [];
if (function_exists('mirza_emoji_placeholder')) {
    foreach ($emojiLibrary as $er) {
        $msEmojiRows[] = ['placeholder' => mirza_emoji_placeholder($er), 'label' => trim((string) ($er['emoji_name'] ?? ''))];
    }
}
include __DIR__ . '/inc/layout_head.php';
$STYLE_COLORS = ['primary' => '#3390ec', 'success' => '#4fae4e', 'danger' => '#e17076'];
?>
<?php $flashOk = get_flash('success');
$flashErr = get_flash('error'); ?>
<?php if ($flashOk): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashOk) ?></div></div><?php endif; ?>
<?php if ($flashErr): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashErr) ?></div></div><?php endif; ?>
<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>
<p class="field-hint" style="margin:0 0 14px">روی ایموجی کلیک کنید بعد روی فیلد ایموجی هر ردیف. توجه: نام، نوع و آدرس پنل از اینجا تغییر نمی‌کند (امنیت اتصال).</p>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">پنل‌ها (<?= count($panels) ?>)</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>نوع</th><th>وضعیت</th><th>دسترسی</th><th>محدودیت</th><th>رنگ</th><th>ایموجی</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($panels as $p):
        $st = (string) ($p['btn_style'] ?? '');
        $em = (string) ($p['btn_emoji_id'] ?? '');
        $bg = $STYLE_COLORS[$st] ?? 'var(--surface-3)';
        $fg = isset($STYLE_COLORS[$st]) ? '#fff' : 'inherit';
      ?>
      <tr>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="edit_id" value="<?= (int) $p['id'] ?>">
          <td><span style="display:inline-block;background:<?= $bg ?>;color:<?= $fg ?>;border-radius:8px;padding:4px 12px;font-size:.8rem"><?= ($em !== '' ? '◆ ' : '') . htmlspecialchars($p['name_panel'] ?? '') ?></span></td>
          <td class="cs"><?= htmlspecialchars($p['name_panel'] ?? '') ?><br><small style="opacity:.6"><?= htmlspecialchars($p['type'] ?? '') ?></small></td>
          <td>
            <select name="status" class="select" style="min-width:90px">
              <option value="active" <?= ($p['status'] ?? '') === 'active' ? ' selected' : '' ?>>فعال</option>
              <option value="disable" <?= ($p['status'] ?? '') === 'disable' ? ' selected' : '' ?>>غیرفعال</option>
            </select>
          </td>
          <td><input type="text" name="agent" class="input" dir="ltr" style="max-width:80px" value="<?= htmlspecialchars($p['agent'] ?? 'all') ?>"></td>
          <td><input type="text" name="limit_panel" class="input" dir="ltr" style="max-width:70px" value="<?= htmlspecialchars($p['limit_panel'] ?? '') ?>"></td>
          <td>
            <select name="btn_style" class="select" style="min-width:80px">
              <option value="" <?= $st === '' ? ' selected' : '' ?>>پیش‌فرض</option>
              <option value="primary" <?= $st === 'primary' ? ' selected' : '' ?>>آبی</option>
              <option value="success" <?= $st === 'success' ? ' selected' : '' ?>>سبز</option>
              <option value="danger" <?= $st === 'danger' ? ' selected' : '' ?>>قرمز</option>
            </select>
          </td>
          <td><input type="text" name="btn_emoji_id" class="input ms-emoji-field" data-ms-max="1" dir="ltr" style="max-width:150px" placeholder="emoji id" value="<?= htmlspecialchars($em) ?>"></td>
          <td><button type="submit" class="btn btn-primary btn-sm">ذخیره</button></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<script src="js/emoji-insert.js"></script>
<?php include __DIR__ . '/inc/layout_head.php' === false ? '' : ''; ?>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
