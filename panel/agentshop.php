<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_auth();

const NEXORA_AGENT_SHOP_MARKER = 'agentshop';

function nexora_agentshop_clean_style($v): string
{
    $v = trim((string) $v);
    return in_array($v, nexora_color_slugs(), true) ? $v : '';
}
function nexora_agentshop_clean_emoji($v): string
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
function nexora_agentshop_style_select($selected): string
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
function nexora_agentshop_preview($name, $style, $emoji, $colors): string
{
    $bg = $colors[$style] ?? 'var(--surface-3)';
    $fg = ($style !== '' && isset($colors[$style])) ? '#fff' : 'inherit';
    $icon = ($emoji !== '' && $emoji !== null) ? '◆ ' : '';
    return '<span style="display:inline-block;background:' . $bg . ';color:' . $fg . ';border-radius:8px;padding:4px 12px;font-size:.8rem">' . $icon . htmlspecialchars((string) $name) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    csrf_check_post();
    $name = trim($_POST['name_product'] ?? '');
    if ($name === '') {
        flash('error', 'نام محصول را وارد کنید.');
        header('Location: agentshop.php');
        exit;
    }
    try {
        $code = bin2hex(random_bytes(2));
        db_query(
            $pdo,
            "INSERT INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status,status_product,btn_style,btn_emoji_id) VALUES (?,?,?,?,?,?,?,'no_reset',?,?,'{}','0','active',?,?)",
            [
                $name,
                $code,
                (int) ($_POST['price_product'] ?? 0),
                (int) ($_POST['volume_product'] ?? 0),
                (int) ($_POST['time_product'] ?? 0),
                (string) ($_POST['namepanel'] ?? ''),
                NEXORA_AGENT_SHOP_MARKER,
                (string) ($_POST['note_product'] ?? ''),
                (string) ($_POST['category_product'] ?? ''),
                nexora_agentshop_clean_style($_POST['btn_style'] ?? '') ?: null,
                nexora_agentshop_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
            ]
        );
        db_query($pdo, "UPDATE product SET display_order = id WHERE id = ?", [$pdo->lastInsertId()]);
        flash('success', 'محصول فروشگاه نمایندگی اضافه شد.');
    } catch (Exception $e) {
        flash('error', 'خطای دیتابیس: ' . $e->getMessage());
    }
    header('Location: agentshop.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    csrf_check_post();
    $id = (int) ($_POST['edit_id'] ?? 0);
    $name = trim($_POST['name_product'] ?? '');
    if ($id && $name !== '') {
        try {
            db_query(
                $pdo,
                "UPDATE product SET name_product=?,price_product=?,Volume_constraint=?,Service_time=?,Location=?,note=?,status_product=?,btn_style=?,btn_emoji_id=? WHERE id=? AND agent=?",
                [
                    $name,
                    (int) ($_POST['price_product'] ?? 0),
                    (int) ($_POST['volume_product'] ?? 0),
                    (int) ($_POST['time_product'] ?? 0),
                    (string) ($_POST['namepanel'] ?? ''),
                    (string) ($_POST['note_product'] ?? ''),
                    ($_POST['status_product'] ?? 'active') === 'hidden' ? 'hidden' : 'active',
                    nexora_agentshop_clean_style($_POST['btn_style'] ?? '') ?: null,
                    nexora_agentshop_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
                    $id,
                    NEXORA_AGENT_SHOP_MARKER,
                ]
            );
            if (isset($_POST['position'])) {
                move_display_order($pdo, 'product', $id, (int) $_POST['position']);
            }
            flash('success', 'محصول بروزرسانی شد.');
        } catch (Exception $e) {
            flash('error', 'خطای دیتابیس: ' . $e->getMessage());
        }
    }
    header('Location: agentshop.php');
    exit;
}

if (isset($_GET['delete'])) {
    csrf_check_get();
    db_query($pdo, "DELETE FROM product WHERE id = ? AND agent = ?", [(int) $_GET['delete'], NEXORA_AGENT_SHOP_MARKER]);
    flash('success', 'محصول حذف شد.');
    header('Location: agentshop.php');
    exit;
}

$panels = [];
$categories = [];
try {
    $panels = db_fetchAll($pdo, "SELECT id, name_panel FROM marzban_panel ORDER BY display_order, id");
    $categories = db_fetchAll($pdo, "SELECT id, remark FROM category ORDER BY display_order, id");
} catch (Exception $e) {
}
$items = db_fetchAll($pdo, "SELECT * FROM product WHERE agent = ? ORDER BY display_order, id", [NEXORA_AGENT_SHOP_MARKER]);

$STYLE_COLORS = [];
foreach (nexora_color_palette() as $slug => $info) {
    if ($slug !== '') $STYLE_COLORS[$slug] = $info['hex'];
}

$pageTitle = 'فروشگاه پنل نمایندگی';
$pageLede = 'محصولات مخصوص فروش پنل نمایندگی را با قیمت، توضیحات و رنگ دکمه تعریف کنید. این محصولات فقط از کلید «خرید پنل نمایندگی» در منوی اصلی ربات نمایش داده می‌شوند.';
$activeNav = 'agentshop';
include __DIR__ . '/inc/layout_head.php';
?>

<?php $flashOk = get_flash('success');
$flashErr = get_flash('error'); ?>
<?php if ($flashOk): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashOk) ?></div></div><?php endif; ?>
<?php if ($flashErr): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashErr) ?></div></div><?php endif; ?>

<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px" class="fade-up">
  <div style="font-size:.85rem;color:var(--mute)"><?= count($items) ?> محصول در فروشگاه نمایندگی</div>
  <button class="btn btn-primary" onclick="openModal('addModal')"><?= icon('plus', 14) ?> افزودن محصول</button>
</div>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>قیمت</th><th>حجم</th><th>زمان</th><th>پنل</th><th>توضیحات</th><th>رنگ</th><th>ایموجی</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="11" style="text-align:center;color:var(--mute);padding:22px">هنوز محصولی برای فروش پنل نمایندگی تعریف نشده است.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $it): ?>
        <tr>
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" value="<?= (int) $it['id'] ?>">
            <td><?= nexora_agentshop_preview($it['name_product'] ?? '', (string) ($it['btn_style'] ?? ''), (string) ($it['btn_emoji_id'] ?? ''), $STYLE_COLORS) ?></td>
            <td><input class="input" name="name_product" value="<?= htmlspecialchars((string) ($it['name_product'] ?? '')) ?>"></td>
            <td><input class="input" type="number" name="price_product" value="<?= (int) ($it['price_product'] ?? 0) ?>"></td>
            <td><input class="input" type="number" name="volume_product" value="<?= (int) ($it['Volume_constraint'] ?? 0) ?>"></td>
            <td><input class="input" type="number" name="time_product" value="<?= (int) ($it['Service_time'] ?? 0) ?>"></td>
            <td>
              <select name="namepanel" class="select" style="min-width:120px">
                <?php foreach ($panels as $pn): ?>
                  <option value="<?= htmlspecialchars((string) $pn['name_panel']) ?>" <?= ((string) ($it['Location'] ?? '') === (string) $pn['name_panel']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $pn['name_panel']) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><textarea class="input" name="note_product" rows="2" style="min-width:220px"><?= htmlspecialchars((string) ($it['note'] ?? '')) ?></textarea></td>
            <td><?= nexora_agentshop_style_select((string) ($it['btn_style'] ?? '')) ?></td>
            <td><input class="input" name="btn_emoji_id" value="<?= htmlspecialchars((string) ($it['btn_emoji_id'] ?? '')) ?>" placeholder="ایموجی"></td>
            <td>
              <select name="status_product" class="select">
                <option value="active" <?= ($it['status_product'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option>
                <option value="hidden" <?= ($it['status_product'] ?? '') === 'hidden' ? 'selected' : '' ?>>پنهان</option>
              </select>
            </td>
            <td style="white-space:nowrap">
              <input class="input" type="number" name="position" value="<?= (int) ($it['display_order'] ?? 0) ?>" style="width:70px" title="ترتیب نمایش">
              <button class="btn btn-primary btn-sm" type="submit"><?= icon('check', 13) ?> ذخیره</button>
              <a class="btn btn-no btn-sm" href="agentshop.php?delete=<?= (int) $it['id'] ?>&_csrf=<?= csrf_token() ?>" data-confirm="این محصول حذف شود؟">حذف</a>
            </td>
          </form>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal" id="addModal">
  <div class="modal-card">
    <div class="modal-head"><div class="modal-title">افزودن محصول فروشگاه نمایندگی</div><button class="btn btn-no" onclick="closeModal('addModal')">✕</button></div>
    <form method="POST" class="modal-body">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <label class="field"><span>نام محصول</span><input class="input" name="name_product" required></label>
        <label class="field"><span>قیمت (تومان)</span><input class="input" type="number" name="price_product" value="0" required></label>
        <label class="field"><span>حجم (گیگابایت)</span><input class="input" type="number" name="volume_product" value="0" required></label>
        <label class="field"><span>مدت (روز)</span><input class="input" type="number" name="time_product" value="0" required></label>
        <label class="field"><span>پنل</span>
          <select name="namepanel" class="select" required>
            <?php foreach ($panels as $pn): ?>
              <option value="<?= htmlspecialchars((string) $pn['name_panel']) ?>"><?= htmlspecialchars((string) $pn['name_panel']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field"><span>دسته‌بندی (اختیاری)</span>
          <select name="category_product" class="select">
            <option value="">—</option>
            <?php foreach ($categories as $ct): ?>
              <option value="<?= htmlspecialchars((string) $ct['remark']) ?>"><?= htmlspecialchars((string) $ct['remark']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field"><span>رنگ دکمه</span><?= nexora_agentshop_style_select('') ?></label>
        <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" placeholder="آیدی عددی یا {emoji:نام}"></label>
        <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="note_product" rows="3" placeholder="توضیح محصول، مزیت‌ها، شرایط استفاده و ..."></textarea></label>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit">افزودن محصول</button>
        <button class="btn btn-no" type="button" onclick="closeModal('addModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
