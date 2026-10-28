<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_auth();
$STYLE_LABELS = ['' => 'پیش‌فرض', 'primary' => 'آبی', 'success' => 'سبز', 'danger' => 'قرمز'];
$STYLE_COLORS = ['primary' => '#3390ec', 'success' => '#4fae4e', 'danger' => '#e17076'];
try {
    foreach (['product', 'category', 'marzban_panel'] as $t) {
        foreach ([['btn_style', 'VARCHAR(20) NULL'], ['btn_emoji_id', 'VARCHAR(32) NULL']] as [$col, $def]) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $st->execute([$t, $col]);
            if ((int) $st->fetchColumn() === 0) {
                $pdo->exec("ALTER TABLE `{$t}` ADD `{$col}` {$def}");
            }
        }
    }
} catch (Exception $e) {
    flash('error', 'خطای دیتابیس: ' . $e->getMessage());
    header('Location: btnstyle.php');
    exit;
}
function nexora_btnstyle_clean_style($v): string
{
    $v = trim((string) $v);
    return in_array($v, ['primary', 'success', 'danger'], true) ? $v : '';
}
function nexora_btnstyle_clean_emoji($v): string
{
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('/^\d{1,32}$/', $v)) return $v;
    if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $v, $m)) return $m[1];
    if (preg_match('/^\{emoji:([^}]+)\}$/u', $v, $m) && function_exists('nexora_emoji_lookup')) {
        $row = nexora_emoji_lookup(trim($m[1]));
        if ($row && !empty($row['custom_emoji_id'])) return (string) $row['custom_emoji_id'];
    }
    return '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '') {
    csrf_check_post();
    $action = $_POST['action'];
    $map = ['product' => ['table' => 'product', 'idcol' => 'id'], 'category' => ['table' => 'category', 'idcol' => 'id'], 'panel' => ['table' => 'marzban_panel', 'idcol' => 'id']];
    if (isset($map[$action])) {
        $id = (int) ($_POST['edit_id'] ?? 0);
        $style = nexora_btnstyle_clean_style($_POST['btn_style'] ?? '');
        $emoji = nexora_btnstyle_clean_emoji($_POST['btn_emoji_id'] ?? '');
        if ($id > 0) {
            try {
                db_query($pdo, "UPDATE `{$map[$action]['table']}` SET btn_style=?, btn_emoji_id=? WHERE `{$map[$action]['idcol']}`=?", [$style !== '' ? $style : null, $emoji !== '' ? $emoji : null, $id]);
                flash('success', 'ذخیره شد.');
            } catch (Exception $e) {
                flash('error', 'خطای دیتابیس: ' . $e->getMessage());
            }
        }
    }
    header('Location: btnstyle.php');
    exit;
}
$products = db_fetchAll($pdo, "SELECT id, name_product, price_product, btn_style, btn_emoji_id FROM product ORDER BY id");
$categories = db_fetchAll($pdo, "SELECT id, remark, btn_style, btn_emoji_id FROM category ORDER BY id");
$panels = db_fetchAll($pdo, "SELECT id, name_panel, btn_style, btn_emoji_id FROM marzban_panel ORDER BY id");
$pageTitle = 'رنگ و ایموجی دکمه‌ها';
$pageLede = 'رنگ و ایموجی پرمیوم دکمه‌های محصول، دسته و پنل در ربات.';
$activeNav = 'btnstyle';
$emojiLibrary = function_exists('nexora_emoji_list') ? nexora_emoji_list() : [];
$msEmojiRows = [];
if (function_exists('nexora_emoji_placeholder')) {
    foreach ($emojiLibrary as $er) {
        $msEmojiRows[] = ['placeholder' => nexora_emoji_placeholder($er), 'label' => trim((string) ($er['emoji_name'] ?? ''))];
    }
}
include __DIR__ . '/inc/layout_head.php';

function nexora_btnstyle_preview($name, $style, $emoji, $STYLE_COLORS): string
{
    $bg = $STYLE_COLORS[$style] ?? 'var(--surface-3)';
    $fg = ($style !== '' && isset($STYLE_COLORS[$style])) ? '#fff' : 'inherit';
    $icon = ($emoji !== '' && $emoji !== null) ? '◆ ' : '';
    return '<span style="display:inline-block;background:' . $bg . ';color:' . $fg . ';border-radius:8px;padding:4px 12px;font-size:.8rem">' . $icon . htmlspecialchars((string) $name) . '</span>';
}

function nexora_btnstyle_style_select($selected): string
{
    $opts = ['' => 'پیش‌فرض', 'primary' => 'آبی', 'success' => 'سبز', 'danger' => 'قرمز'];
    $h = '<select name="btn_style" class="select" style="min-width:90px">';
    foreach ($opts as $v => $l) {
        $sel = ((string) $v === (string) $selected) ? ' selected' : '';
        $h .= '<option value="' . htmlspecialchars($v) . '"' . $sel . '>' . htmlspecialchars($l) . '</option>';
    }
    return $h . '</select>';
}
?>

<?php $flashOk = get_flash('success');
$flashErr = get_flash('error'); ?>
<?php if ($flashOk): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashOk) ?></div></div><?php endif; ?>
<?php if ($flashErr): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashErr) ?></div></div><?php endif; ?>

<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>
<p class="field-hint" style="margin:0 0 14px">روی ایموجی کلیک کنید بعد روی فیلد ایموجی هر ردیف — یا آیدی عددی را دستی بنویسید.</p>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="card-head"><div><div class="card-title">محصولات (<?= count($products) ?>)</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>رنگ</th><th>ایموجی</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($products as $p): ?>
      <tr>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="product">
          <input type="hidden" name="edit_id" value="<?= (int) $p['id'] ?>">
          <td><?= nexora_btnstyle_preview($p['name_product'] ?? '', (string) ($p['btn_style'] ?? ''), (string) ($p['btn_emoji_id'] ?? ''), $STYLE_COLORS) ?></td>
          <td class="cs"><?= htmlspecialchars($p['name_product'] ?? '') ?></td>
          <td><?= nexora_btnstyle_style_select($p['btn_style'] ?? '') ?></td>
          <td><input type="text" name="btn_emoji_id" class="input ms-emoji-field" data-ms-max="1" dir="ltr" style="max-width:170px" placeholder="emoji id" value="<?= htmlspecialchars((string) ($p['btn_emoji_id'] ?? '')) ?>"></td>
          <td><button type="submit" class="btn btn-primary btn-sm">ذخیره</button></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="card-head"><div><div class="card-title">دسته‌ها (<?= count($categories) ?>)</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>رنگ</th><th>ایموجی</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
      <tr>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="category">
          <input type="hidden" name="edit_id" value="<?= (int) $c['id'] ?>">
          <td><?= nexora_btnstyle_preview($c['remark'] ?? '', (string) ($c['btn_style'] ?? ''), (string) ($c['btn_emoji_id'] ?? ''), $STYLE_COLORS) ?></td>
          <td class="cs"><?= htmlspecialchars($c['remark'] ?? '') ?></td>
          <td><?= nexora_btnstyle_style_select($c['btn_style'] ?? '') ?></td>
          <td><input type="text" name="btn_emoji_id" class="input ms-emoji-field" data-ms-max="1" dir="ltr" style="max-width:170px" placeholder="emoji id" value="<?= htmlspecialchars((string) ($c['btn_emoji_id'] ?? '')) ?>"></td>
          <td><button type="submit" class="btn btn-primary btn-sm">ذخیره</button></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="card-head"><div><div class="card-title">پنل‌ها (<?= count($panels) ?>)</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>رنگ</th><th>ایموجی</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($panels as $p): ?>
      <tr>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="panel">
          <input type="hidden" name="edit_id" value="<?= (int) $p['id'] ?>">
          <td><?= nexora_btnstyle_preview($p['name_panel'] ?? '', (string) ($p['btn_style'] ?? ''), (string) ($p['btn_emoji_id'] ?? ''), $STYLE_COLORS) ?></td>
          <td class="cs"><?= htmlspecialchars($p['name_panel'] ?? '') ?></td>
          <td><?= nexora_btnstyle_style_select($p['btn_style'] ?? '') ?></td>
          <td><input type="text" name="btn_emoji_id" class="input ms-emoji-field" data-ms-max="1" dir="ltr" style="max-width:170px" placeholder="emoji id" value="<?= htmlspecialchars((string) ($p['btn_emoji_id'] ?? '')) ?>"></td>
          <td><button type="submit" class="btn btn-primary btn-sm">ذخیره</button></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<script src="js/emoji-insert.js"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
