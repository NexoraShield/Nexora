<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_auth();

function nexora_agentpanel_clean_style($v): string
{
    $v = trim((string) $v);
    return in_array($v, nexora_color_slugs(), true) ? $v : '';
}
function nexora_agentpanel_clean_emoji($v): string
{
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('/^\d{1,32}$/', $v)) return $v;
    if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $v, $m)) return $m[1];
    if (preg_match('/^\{emoji:([^}]+)\}$/u', $v) && function_exists('nexora_emoji_lookup')) {
        $row = nexora_emoji_lookup(trim($v));
        if ($row && !empty($row['custom_emoji_id'])) return (string) $row['custom_emoji_id'];
    }
    return '';
}
function nexora_agentpanel_style_select($selected): string
{
    $h = '<select name="btn_style" class="select" style="min-width:90px">';
    foreach (nexora_color_palette() as $slug => $info) {
        $label = $info['label'] ?? $slug;
        if ($slug === '') $label = 'پیش‌فرض';
        $sel = ((string) $slug === (string) $selected) ? ' selected' : '';
        $h .= '<option value="' . htmlspecialchars($slug) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    return $h . '</select>';
}
function nexora_agentpanel_preview($name, $style, $emoji, $colors): string
{
    $bg = $colors[$style] ?? 'var(--surface-3)';
    $fg = ($style !== '' && isset($colors[$style])) ? '#fff' : 'inherit';
    $icon = ($emoji !== '' && $emoji !== null) ? '◆ ' : '';
    return '<span style="display:inline-block;background:' . $bg . ';color:' . $fg . ';border-radius:8px;padding:4px 12px;font-size:.8rem">' . $icon . htmlspecialchars((string) $name) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check_post();
    $name = trim($_POST['name_panel'] ?? '');
    if ($name === '') {
        flash('error', 'نام پنل را وارد کنید.');
        header('Location: agentpanel.php');
        exit;
    }
    try {
        db_query(
            $pdo,
            "INSERT INTO agent_panel_product (name,price,credit,description,btn_style,btn_emoji_id,status_product) VALUES (?,?,?,?,?,?,'active')",
            [
                $name,
                (int) ($_POST['price'] ?? 0),
                (int) ($_POST['credit'] ?? 0),
                (string) ($_POST['description'] ?? ''),
                nexora_agentpanel_clean_style($_POST['btn_style'] ?? '') ?: null,
                nexora_agentpanel_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
            ]
        );
        $newId = (int) $pdo->lastInsertId();
        db_query($pdo, "UPDATE agent_panel_product SET display_order = ? WHERE id = ?", [$newId, $newId]);
        flash('success', 'پنل نمایندگی اضافه شد.');
    } catch (Exception $e) {
        flash('error', 'خطای دیتابیس: ' . $e->getMessage());
    }
    header('Location: agentpanel.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    csrf_check_post();
    $id = (int) ($_POST['edit_id'] ?? 0);
    $name = trim($_POST['name_panel'] ?? '');
    if ($id && $name !== '') {
        try {
            db_query(
                $pdo,
                "UPDATE agent_panel_product SET name=?,price=?,credit=?,description=?,status_product=?,btn_style=?,btn_emoji_id=? WHERE id=?",
                [
                    $name,
                    (int) ($_POST['price'] ?? 0),
                    (int) ($_POST['credit'] ?? 0),
                    (string) ($_POST['description'] ?? ''),
                    ($_POST['status_product'] ?? 'active') === 'hidden' ? 'hidden' : 'active',
                    nexora_agentpanel_clean_style($_POST['btn_style'] ?? '') ?: null,
                    nexora_agentpanel_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
                    $id,
                ]
            );
            if (isset($_POST['position'])) {
                move_display_order($pdo, 'agent_panel_product', $id, (int) $_POST['position']);
            }
            flash('success', 'پنل نمایندگی بروزرسانی شد.');
        } catch (Exception $e) {
            flash('error', 'خطای دیتابیس: ' . $e->getMessage());
        }
    }
    header('Location: agentpanel.php');
    exit;
}

if (isset($_GET['delete'])) {
    csrf_check_get();
    db_query($pdo, "DELETE FROM agent_panel_product WHERE id = ?", [(int) $_GET['delete']]);
    flash('success', 'پنل نمایندگی حذف شد.');
    header('Location: agentpanel.php');
    exit;
}

$items = db_fetchAll($pdo, "SELECT * FROM agent_panel_product ORDER BY display_order, id");

$STYLE_COLORS = [];
foreach (nexora_color_palette() as $slug => $info) {
    if ($slug !== '') $STYLE_COLORS[$slug] = $info['hex'];
}

$pageTitle = 'فروشگاه پنل نمایندگی';
$pageLede = 'پنل‌هایی که نماینده می‌تواند برای خودش بخرد. بعد از پرداخت، نام کاربری، رمز و لینک پنل برای مشتری ارسال می‌شود و اعتبار تعیین‌شده به کیف پول او داخل پنل اضافه می‌گردد.';
$activeNav = 'agentpanel';
include __DIR__ . '/inc/layout_head.php';
?>

<?php $flashOk = get_flash('success');
$flashErr = get_flash('error'); ?>
<?php if ($flashOk): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashOk) ?></div></div><?php endif; ?>
<?php if ($flashErr): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashErr) ?></div></div><?php endif; ?>

<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px" class="fade-up">
  <div style="font-size:.85rem;color:var(--mute)"><?= count($items) ?> پنل در فروشگاه نمایندگی</div>
  <button class="btn btn-primary" onclick="openModal('addModal')"><?= icon('plus', 14) ?> افزودن پنل</button>
</div>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>قیمت</th><th>اعتبار پنل</th><th>توضیحات</th><th>رنگ</th><th>ایموجی</th><th>وضعیت</th><th>ترتیب</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="10" style="text-align:center;color:var(--mute);padding:22px">هنوز پنلی برای فروش به نمایندگان تعریف نشده است.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $it): ?>
        <tr>
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" value="<?= (int) $it['id'] ?>">
            <td><?= nexora_agentpanel_preview($it['name'] ?? '', (string) ($it['btn_style'] ?? ''), (string) ($it['btn_emoji_id'] ?? ''), $STYLE_COLORS) ?></td>
            <td><input class="input" name="name_panel" value="<?= htmlspecialchars((string) ($it['name'] ?? '')) ?>"></td>
            <td><input class="input" type="number" name="price" value="<?= (int) ($it['price'] ?? 0) ?>"></td>
            <td><input class="input" type="number" name="credit" value="<?= (int) ($it['credit'] ?? 0) ?>"></td>
            <td><textarea class="input" name="description" rows="2" style="min-width:240px"><?= htmlspecialchars((string) ($it['description'] ?? '')) ?></textarea></td>
            <td><?= nexora_agentpanel_style_select((string) ($it['btn_style'] ?? '')) ?></td>
            <td><input class="input" name="btn_emoji_id" value="<?= htmlspecialchars((string) ($it['btn_emoji_id'] ?? '')) ?>" placeholder="ایموجی"></td>
            <td>
              <select name="status_product" class="select">
                <option value="active" <?= ($it['status_product'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option>
                <option value="hidden" <?= ($it['status_product'] ?? '') === 'hidden' ? 'selected' : '' ?>>پنهان</option>
              </select>
            </td>
            <td><input class="input" type="number" name="position" value="<?= (int) ($it['display_order'] ?? 0) ?>" style="width:70px" title="ترتیب نمایش"></td>
            <td style="white-space:nowrap">
              <button class="btn btn-primary btn-sm" type="submit"><?= icon('check', 13) ?> ذخیره</button>
              <a class="btn btn-no btn-sm" href="agentpanel.php?delete=<?= (int) $it['id'] ?>&_csrf=<?= csrf_token() ?>" data-confirm="این پنل حذف شود؟">حذف</a>
            </td>
          </form>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal" id="addModal">
  <div class="modal-card">
    <div class="modal-head"><div class="modal-title">افزودن پنل نمایندگی</div><button class="btn btn-no" onclick="closeModal('addModal')">✕</button></div>
    <form method="POST" class="modal-body">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <label class="field"><span>نام پنل (مثال: پنل ۱ میلیونی)</span><input class="input" name="name_panel" required></label>
        <label class="field"><span>قیمت (تومان)</span><input class="input" type="number" name="price" value="0" required></label>
        <label class="field"><span>اعتبار داخل پنل (تومان)</span><input class="input" type="number" name="credit" value="1000000" required></label>
        <label class="field"><span>رنگ دکمه</span><?= nexora_agentpanel_style_select('') ?></label>
        <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" placeholder="آیدی عددی یا {emoji:نام}"></label>
        <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="description" rows="3" placeholder="مزیت‌های این پنل، تعداد کاربر، مدت اعتبار و ..."></textarea></label>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit">افزودن پنل</button>
        <button class="btn btn-no" type="button" onclick="closeModal('addModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
