<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_once __DIR__ . '/../panel_protocol.php';
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
// The name of the panel without the premium emoji tag (only for the display)
function nexora_testservice_panel_label(string $name): string
{
    $clean = trim((string) preg_replace('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', '$1', $name));
    return $clean !== '' ? $clean : $name;
}
// The inbounds of a panel
function nexora_testservice_inbounds(array $panel): array
{
    $raw = json_decode((string) ($panel['inbounds'] ?? ''), true);
    $out = [];
    if (is_array($raw)) {
        foreach ($raw as $v) {
            if (is_scalar($v) && (string) $v !== '') {
                $out[] = (string) $v;
            }
        }
    }
    return array_values(array_unique($out));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '') {
    csrf_check_post();
    $action = (string) $_POST['action'];
    $protocolMessage = '';
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name_product'] ?? '');
        $panel = trim($_POST['namepanel'] ?? '');
        $reference = trim($_POST['protocol_account'] ?? '');
        if ($name === '') {
            flash('error', 'نام سرویس تست را وارد کنید.');
        } elseif ($panel === '') {
            flash('error', 'پنل را انتخاب کنید.');
        } else {
            try {
                $inbounds = trim((string) ($_POST['inbounds'] ?? ''));
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
                            $panel,
                            'f',
                            (string) ($_POST['note_product'] ?? ''),
                            '',
                            nexora_testservice_clean_style($_POST['btn_style'] ?? '') ?: null,
                            nexora_testservice_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
                            $inbounds === '' ? null : $inbounds,
                        ]
                    );
                    $newId = (int) $pdo->lastInsertId();
                    db_query($pdo, "UPDATE product SET display_order = id WHERE id = ?", [$newId, $newId]);
                    flash('success', 'سرویس تست اضافه شد.');
                } else {
                    $id = (int) ($_POST['edit_id'] ?? 0);
                    db_query(
                        $pdo,
                        "UPDATE product SET name_product=?,Volume_constraint=?,Service_time=?,Location=?,note=?,status_product=?,is_test=1,btn_style=?,btn_emoji_id=?,inbounds=? WHERE id=?",
                        [
                            $name,
                            (int) ($_POST['volume_product'] ?? 0),
                            (int) ($_POST['time_product'] ?? 0),
                            $panel,
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
                }
                // The protocol and the inbound of the panel are taken from an
                // account that already exists inside that panel - the same
                // way as the bot does it.
                if ($reference !== '') {
                    $protocol = nexora_apply_panel_protocol($pdo, $panel, $reference);
                    $protocolMessage = ($protocol['ok'] ? '✅ ' : '⚠️ ') . $protocol['message'];
                }
            } catch (Exception $e) {
                flash('error', 'خطای دیتابیس: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        db_query($pdo, "DELETE FROM product WHERE id = ? AND is_test = 1", [(int) ($_POST['edit_id'] ?? 0)]);
        flash('success', 'سرویس تست حذف شد.');
    }
    if ($protocolMessage !== '') {
        flash('success', $protocolMessage);
    }
    header('Location: testservice.php');
    exit;
}

$panels = [];
try {
    $panels = db_fetchAll($pdo, "SELECT id, name_panel, inbounds FROM marzban_panel ORDER BY display_order, id");
} catch (Exception $e) {
}
$items = db_fetchAll($pdo, "SELECT * FROM product WHERE is_test = 1 ORDER BY display_order, id");
$colors = [];
foreach (nexora_color_palette() as $slug => $info) {
    if ($slug !== '') $colors[$slug] = $info['hex'];
}
// the inbounds of every panel (for the form)
$panelInbounds = [];
foreach ($panels as $pn) {
    $panelInbounds[(string) $pn['name_panel']] = nexora_testservice_inbounds($pn);
}

$pageTitle = 'سرویس تست';
$pageLede = 'سرویس‌های تست را با نام دلخواه، حجم، مدت، پنل و اینباند اختصاصی تعریف کنید. اگر پنل هنوز پروتکل و اینباند ندارد، نام کاربری یکی از اکانت‌های موجود همان پنل را بنویسید تا با آن تنظیم شود.';
$activeNav = 'testservice';
include __DIR__ . '/inc/layout_head.php';
?>

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
          <td><?= htmlspecialchars(nexora_testservice_panel_label((string) ($it['Location'] ?? ''))) ?></td>
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

<?php
// the form of the inbounds (shared by the add and the edit modal)
$tsInboundField = function (string $id, array $selected = []) use ($panelInbounds) {
    $h = '<select name="inbounds" id="' . htmlspecialchars($id) . '" class="select" onchange="tsFilterInbounds(this)">';
    $h .= '<option value="">پیش‌فرض پنل</option>';
    foreach ($panelInbounds as $panelName => $list) {
        foreach ($list as $inb) {
            $sel = in_array((string) $inb, array_map('strval', $selected), true) ? ' selected' : '';
            $h .= '<option value="' . htmlspecialchars((string) $inb) . '" data-panel="' . htmlspecialchars((string) $panelName) . '"' . $sel . '>'
                . htmlspecialchars((string) $inb) . '</option>';
        }
    }
    return $h . '</select>';
};
?>

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
            <select name="namepanel" id="ts_add_panel" class="select" required onchange="tsFilterInbounds(document.getElementById('ts_add_inbounds'))">
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($panels as $pn): ?>
                <option value="<?= htmlspecialchars((string) $pn['name_panel']) ?>"><?= htmlspecialchars(nexora_testservice_panel_label((string) $pn['name_panel'])) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>نام کاربری یک اکانت داخل همین پنل (برای تنظیم پروتکل و اینباند)</span>
            <input class="input" name="protocol_account" placeholder="مثلاً نام کاربری یکی از اکانت‌های موجود در پنل">
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">دقیقاً مثل «تنظیم پروتکل و اینباند» در ربات: با این اکانت، پروتکل و اینباند پنل تنظیم می‌شود.</small>
          </label>
          <label class="field"><span>اینباند اختصاصی (اختیاری)</span>
            <?= $tsInboundField('ts_add_inbounds') ?>
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر خالی بماند، اینباند پیش‌فرض پنل استفاده می‌شود.</small>
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
            <select name="namepanel" id="ts_edit_panel" class="select" required onchange="tsFilterInbounds(document.getElementById('ts_edit_inbounds'))">
              <?php foreach ($panels as $pn): ?>
                <option value="<?= htmlspecialchars((string) $pn['name_panel']) ?>"><?= htmlspecialchars(nexora_testservice_panel_label((string) $pn['name_panel'])) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>نام کاربری یک اکانت داخل همین پنل (برای تنظیم پروتکل و اینباند)</span>
            <input class="input" name="protocol_account" id="ts_edit_protocol" placeholder="اختیاری — مثلاً نام کاربری یکی از اکانت‌های موجود در پنل">
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر پر شود، پروتکل و اینباند پنل با اطلاعات همین اکانت تنظیم می‌شود.</small>
          </label>
          <label class="field"><span>اینباند اختصاصی (اختیاری)</span>
            <?= $tsInboundField('ts_edit_inbounds') ?>
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
// Only the inbounds of the selected panel are offered.
window.tsFilterInbounds = function (select) {
    if (!select) return;
    var panelSelect = null;
    if (select.id === 'ts_add_inbounds') {
        panelSelect = document.getElementById('ts_add_panel');
    } else if (select.id === 'ts_edit_inbounds') {
        panelSelect = document.getElementById('ts_edit_panel');
    }
    var panel = panelSelect ? panelSelect.value : '';
    var keep = select.value;
    for (var i = 0; i < select.options.length; i++) {
        var opt = select.options[i];
        if (!opt.value) continue;
        var owner = opt.getAttribute('data-panel') || '';
        if (owner && panel && owner !== panel) {
            opt.hidden = true;
            opt.disabled = true;
        } else {
            opt.hidden = false;
            opt.disabled = false;
        }
    }
    if (keep && select.selectedIndex >= 0 && select.options[select.selectedIndex].disabled) {
        select.selectedIndex = 0;
    }
};
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
    document.getElementById('ts_edit_protocol').value = '';
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
        window.tsFilterInbounds(inb);
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
