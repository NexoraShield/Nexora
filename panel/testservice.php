<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_once __DIR__ . '/../function.php';
require_auth();

function nexora_testservice_clean_style($v): string
{
    $v = trim((string) $v);
    return in_array($v, nexora_color_slugs(), true) ? $v : '';
}
function nexora_testservice_clean_emoji($v): string
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
function nexora_testservice_style_select($selected, string $name = 'btn_style'): string
{
    $h = '<select name="' . htmlspecialchars($name) . '" class="select" style="min-width:90px">';
    foreach (nexora_color_palette() as $slug => $info) {
        $label = $info['label'] ?? $slug;
        if ($slug === '') $label = 'پیش‌فرض';
        $sel = ((string) $slug === (string) $selected) ? ' selected' : '';
        $h .= '<option value="' . htmlspecialchars($slug) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    return $h . '</select>';
}
function nexora_testservice_preview($name, $style, $emoji, $colors): string
{
    $bg = $colors[$style] ?? 'var(--surface-3)';
    $fg = ($style !== '' && isset($colors[$style])) ? '#fff' : 'inherit';
    $icon = ($emoji !== '' && $emoji !== null) ? '◆ ' : '';
    return '<span style="display:inline-block;background:' . $bg . ';color:' . $fg . ';border-radius:8px;padding:4px 12px;font-size:.8rem">' . $icon . htmlspecialchars((string) $name) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '') {
    csrf_check_post();
    $action = (string) $_POST['action'];
    $name = trim($_POST['name_product'] ?? '');
    if (($action === 'add' || $action === 'edit') && $name === '') {
        flash('error', 'نام سرویس تست را وارد کنید.');
    } else {
        try {
            if ($action === 'add') {
                $code = bin2hex(random_bytes(2));
                db_query(
                    $pdo,
                    "INSERT INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status,status_product,is_test,btn_style,btn_emoji_id,inbounds) VALUES (?,?,?,?,?,?,?,'no_reset',?,?,'{}','0','active',1,?,?,?)",
                    [
                        $name,
                        $code,
                        0,
                        (int) ($_POST['volume_product'] ?? 0),
                        (int) ($_POST['time_product'] ?? 0),
                        (string) ($_POST['namepanel'] ?? ''),
                        'f',
                        (string) ($_POST['note_product'] ?? ''),
                        '',
                        nexora_testservice_clean_style($_POST['btn_style'] ?? '') ?: null,
                        nexora_testservice_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
                        trim((string) ($_POST['inbounds'] ?? '')) === '' ? null : (string) ($_POST['inbounds'] ?? ''),
                    ]
                );
                $newId = (int) $pdo->lastInsertId();
                db_query($pdo, "UPDATE product SET display_order = id WHERE id = ?", [$newId, $newId]);
                flash('success', 'سرویس تست اضافه شد.');
            } elseif ($action === 'edit') {
                $id = (int) ($_POST['edit_id'] ?? 0);
                $inbounds = trim((string) ($_POST['inbounds'] ?? ''));
                db_query(
                    $pdo,
                    "UPDATE product SET name_product=?,Volume_constraint=?,Service_time=?,Location=?,note=?,status_product=?,is_test=1,btn_style=?,btn_emoji_id=?,inbounds=? WHERE id=?",
                    [
                        $name,
                        (int) ($_POST['volume_product'] ?? 0),
                        (int) ($_POST['time_product'] ?? 0),
                        (string) ($_POST['namepanel'] ?? ''),
                        (string) ($_POST['note_product'] ?? ''),
                        ($_POST['status_product'] ?? 'active') === 'hidden' ? 'hidden' : 'active',
                        nexora_testservice_clean_style($_POST['btn_style'] ?? '') ?: null,
                        nexora_testservice_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
                        $inbounds === '' ? null : $inbounds,
                        $id,
                    ]
                );
                if (isset($_POST['position'])) {
                    move_display_order($pdo, 'product', $id, (int) $_POST['position']);
                }
                flash('success', 'سرویس تست بروزرسانی شد.');
            } elseif ($action === 'delete') {
                db_query($pdo, "DELETE FROM product WHERE id = ? AND is_test = 1", [(int) ($_POST['edit_id'] ?? 0)]);
                flash('success', 'سرویس تست حذف شد.');
            }
        } catch (Exception $e) {
            flash('error', 'خطای دیتابیس: ' . $e->getMessage());
        }
    }
    header('Location: testservice.php');
    exit;
}

$panels = [];
try {
    $panels = db_fetchAll($pdo, "SELECT id, name_panel, inbounds FROM marzban_panel ORDER BY display_order, id");
} catch (Exception $e) {
}
$items = [];
try {
    $items = db_fetchAll($pdo, "SELECT * FROM product WHERE is_test = 1 ORDER BY display_order, id");
} catch (Exception $e) {
}
$colors = [];
foreach (nexora_color_palette() as $slug => $info) {
    if ($slug !== '') $colors[$slug] = $info['hex'];
}

$pageTitle = 'سرویس تست';
$pageLede = 'سرویس‌های تست را با نام دلخواه، حجم، مدت، پنل و اینباند اختصاصی تعریف کنید. وقتی مشتری کلید «اکانت تست» را بزند، این سرویس‌ها به او نمایش داده می‌شوند.';
$activeNav = 'testservice';
include __DIR__ . '/inc/layout_head.php';
?>

<?php $flashOk = get_flash('success');
$flashErr = get_flash('error'); ?>
<?php if ($flashOk): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashOk) ?></div></div><?php endif; ?>
<?php if ($flashErr): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashErr) ?></div></div><?php endif; ?>

<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px" class="fade-up">
  <div style="font-size:.85rem;color:var(--mute)"><?= count($items) ?> سرویس تست</div>
  <button class="btn btn-primary" onclick="openModal('addModal')"><?= icon('plus', 14) ?> افزودن سرویس تست</button>
</div>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>حجم (GB)</th><th>مدت (ساعت)</th><th>پنل</th><th>اینباند</th><th>توضیحات</th><th>رنگ</th><th>ایموجی</th><th>وضعیت</th><th>ترتیب</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="12" style="text-align:center;color:var(--mute);padding:22px">هنوز سرویس تستی تعریف نشده است. تا وقتی سرویس تست تعریف نکنید، سرویس تست قبلی (بر اساس تنظیمات هر پنل) مثل قبل کار می‌کند.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $it):
        $inbounds = json_decode((string) ($it['inbounds'] ?? ''), true);
        $inboundText = is_array($inbounds) && $inbounds ? implode('، ', array_map('strval', $inbounds)) : 'پیش‌فرض پنل';
      ?>
        <tr>
          <td><?= nexora_testservice_preview($it['name_product'] ?? '', (string) ($it['btn_style'] ?? ''), (string) ($it['btn_emoji_id'] ?? ''), $colors) ?></td>
          <td><?= htmlspecialchars((string) ($it['name_product'] ?? '')) ?></td>
          <td><?= (int) ($it['Volume_constraint'] ?? 0) ?></td>
          <td><?= (int) ($it['Service_time'] ?? 0) ?></td>
          <td><?= htmlspecialchars((string) ($it['Location'] ?? '')) ?></td>
          <td style="min-width:160px"><?= htmlspecialchars($inboundText) ?></td>
          <td style="min-width:200px"><?= nl2br(htmlspecialchars((string) ($it['note'] ?? ''))) ?></td>
          <td><span class="pill"><?= htmlspecialchars((string) ($it['btn_style'] ?? 'پیش‌فرض')) ?></span></td>
          <td><?= ($it['btn_emoji_id'] ?? '') !== '' ? '💎' : '—' ?></td>
          <td><?= ($it['status_product'] ?? 'active') === 'active' ? '✅ فعال' : '⛔️ پنهان' ?></td>
          <td><?= (int) ($it['display_order'] ?? 0) ?></td>
          <td style="white-space:nowrap">
            <button class="btn btn-primary btn-sm" type="button" onclick='openEditTest(<?= json_encode([
                'id' => (int) $it['id'],
                'name_product' => (string) ($it['name_product'] ?? ''),
                'volume_product' => (int) ($it['Volume_constraint'] ?? 0),
                'time_product' => (int) ($it['Service_time'] ?? 0),
                'namepanel' => (string) ($it['Location'] ?? ''),
                'note_product' => (string) ($it['note'] ?? ''),
                'btn_style' => (string) ($it['btn_style'] ?? ''),
                'btn_emoji_id' => (string) ($it['btn_emoji_id'] ?? ''),
                'status_product' => (string) ($it['status_product'] ?? 'active'),
                'position' => (int) ($it['display_order'] ?? 0),
                'inbounds' => is_array($inbounds) ? $inbounds : [],
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ ویرایش</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="edit_id" value="<?= (int) $it['id'] ?>">
              <button class="btn btn-no btn-sm" type="submit" data-confirm="این سرویس تست حذف شود؟">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal-veil" id="addModal">
  <div class="modal">
    <div class="modal-head">
      <h3>افزودن سرویس تست</h3>
      <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
          <label class="field"><span>نام سرویس تست</span><input class="input" name="name_product" required></label>
          <label class="field"><span>حجم (گیگابایت)</span><input class="input" type="number" name="volume_product" value="1" required></label>
          <label class="field"><span>مدت (ساعت)</span><input class="input" type="number" name="time_product" value="1" required></label>
          <label class="field"><span>پنل</span>
            <select name="namepanel" class="select" required>
              <?php foreach ($panels as $pn): ?>
                <option value="<?= htmlspecialchars((string) $pn['name_panel']) ?>"><?= htmlspecialchars((string) $pn['name_panel']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>اینباند اختصاصی</span>
            <select name="inbounds" class="select">
              <option value="">پیش‌فرض پنل</option>
              <?php foreach ($panels as $pn):
                $raw = json_decode((string) ($pn['inbounds'] ?? ''), true);
                if (!is_array($raw)) continue;
                foreach ($raw as $inb):
                  if (!is_scalar($inb) || (string) $inb === '') continue; ?>
                <option value="<?= htmlspecialchars((string) $inb) ?>"><?= htmlspecialchars((string) $pn['name_panel']) ?> → <?= htmlspecialchars((string) $inb) ?></option>
              <?php endforeach; endforeach; ?>
            </select>
          </label>
          <label class="field"><span>رنگ دکمه</span><?= nexora_testservice_style_select('') ?></label>
          <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" placeholder="آیدی عددی یا {emoji:نام}"></label>
          <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="note_product" rows="2"></textarea></label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit">افزودن سرویس تست</button>
        <button class="btn btn-ghost" type="button" onclick="closeModal('addModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-veil" id="editModal">
  <div class="modal">
    <div class="modal-head">
      <h3>ویرایش سرویس تست</h3>
      <button class="modal-x" onclick="closeModal('editModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="edit_id" id="ts_edit_id">
        <div class="form-grid">
          <label class="field"><span>نام سرویس تست</span><input class="input" name="name_product" id="ts_edit_name" required></label>
          <label class="field"><span>حجم (گیگابایت)</span><input class="input" type="number" name="volume_product" id="ts_edit_volume" value="0" required></label>
          <label class="field"><span>مدت (ساعت)</span><input class="input" type="number" name="time_product" id="ts_edit_time" value="0" required></label>
          <label class="field"><span>پنل</span>
            <select name="namepanel" id="ts_edit_panel" class="select" required>
              <?php foreach ($panels as $pn): ?>
                <option value="<?= htmlspecialchars((string) $pn['name_panel']) ?>"><?= htmlspecialchars((string) $pn['name_panel']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>اینباند اختصاصی</span>
            <select name="inbounds" id="ts_edit_inbounds" class="select">
              <option value="">پیش‌فرض پنل</option>
              <?php foreach ($panels as $pn):
                $raw = json_decode((string) ($pn['inbounds'] ?? ''), true);
                if (!is_array($raw)) continue;
                foreach ($raw as $inb):
                  if (!is_scalar($inb) || (string) $inb === '') continue; ?>
                <option value="<?= htmlspecialchars((string) $inb) ?>"><?= htmlspecialchars((string) $pn['name_panel']) ?> → <?= htmlspecialchars((string) $inb) ?></option>
              <?php endforeach; endforeach; ?>
            </select>
          </label>
          <label class="field"><span>رنگ دکمه</span><?= nexora_testservice_style_select('', 'ts_edit_style') ?></label>
          <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" id="ts_edit_emoji" placeholder="آیدی عددی یا {emoji:نام}"></label>
          <label class="field"><span>وضعیت</span>
            <select name="status_product" id="ts_edit_status" class="select">
              <option value="active">فعال</option>
              <option value="hidden">پنهان</option>
            </select>
          </label>
          <label class="field"><span>ترتیب نمایش</span><input class="input" type="number" name="position" id="ts_edit_position" value="0"></label>
          <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="note_product" id="ts_edit_note" rows="2"></textarea></label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit"><?= icon('check', 13) ?> ذخیره</button>
        <button class="btn btn-ghost" type="button" onclick="closeModal('editModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<script>
window.openEditTest = function (p) {
    p = p || {};
    document.getElementById('ts_edit_id').value = p.id || '';
    document.getElementById('ts_edit_name').value = p.name_product || '';
    document.getElementById('ts_edit_volume').value = p.volume_product || 0;
    document.getElementById('ts_edit_time').value = p.time_product || 0;
    document.getElementById('ts_edit_note').value = p.note_product || '';
    document.getElementById('ts_edit_emoji').value = p.btn_emoji_id || '';
    document.getElementById('ts_edit_position').value = p.position || 0;
    document.getElementById('ts_edit_status').value = p.status_product || 'active';
    var sel = document.getElementById('ts_edit_panel');
    if (sel) {
        for (var i = 0; i < sel.options.length; i++) {
            sel.options[i].selected = sel.options[i].value === (p.namepanel || '');
        }
    }
    var inb = document.getElementById('ts_edit_inbounds');
    if (inb) {
        var wanted = (p.inbounds || []).map(function (v) { return String(v); });
        for (var j = 0; j < inb.options.length; j++) {
            inb.options[j].selected = wanted.indexOf(inb.options[j].value) !== -1;
        }
    }
    var st = document.getElementById('ts_edit_style');
    if (st) {
        for (var k = 0; k < st.options.length; k++) {
            st.options[k].selected = st.options[k].value === (p.btn_style || '');
        }
    }
    openModal('editModal');
};
</script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
