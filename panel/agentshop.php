<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_once __DIR__ . '/../panel_protocol.php';
require_once __DIR__ . '/inc/shop_helpers.php';
require_auth();

const NEXORA_AGENT_SHOP_MARKER = 'agentshop';

$shopTab = (isset($_GET['tab']) && $_GET['tab'] === 'topup') ? 'topup' : 'products';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '') {
    csrf_check_post();
    $action = (string) $_POST['action'];
    $protocolMessage = '';
    if ($action === 'add' || $action === 'edit') {
        $name = trim($_POST['name_product'] ?? '');
        $panel = trim($_POST['namepanel'] ?? '');
        $reference = trim($_POST['protocol_account'] ?? '');
        if ($name === '') {
            flash('error', 'نام محصول را وارد کنید.');
        } else {
            try {
                if ($action === 'add') {
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
                            $panel,
                            NEXORA_AGENT_SHOP_MARKER,
                            (string) ($_POST['note_product'] ?? ''),
                            (string) ($_POST['category_product'] ?? ''),
                            nexora_agentshop_clean_style($_POST['btn_style'] ?? '') ?: null,
                            nexora_agentshop_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
                        ]
                    );
                    db_query($pdo, "UPDATE product SET display_order = id WHERE id = ?", [$pdo->lastInsertId()]);
                    flash('success', 'محصول فروشگاه نمایندگی اضافه شد.');
                } else {
                    $id = (int) ($_POST['edit_id'] ?? 0);
                    db_query(
                        $pdo,
                        "UPDATE product SET name_product=?,price_product=?,Volume_constraint=?,Service_time=?,Location=?,note=?,category=?,status_product=?,btn_style=?,btn_emoji_id=? WHERE id=? AND agent=?",
                        [
                            $name,
                            (int) ($_POST['price_product'] ?? 0),
                            (int) ($_POST['volume_product'] ?? 0),
                            (int) ($_POST['time_product'] ?? 0),
                            $panel,
                            (string) ($_POST['note_product'] ?? ''),
                            (string) ($_POST['category_product'] ?? ''),
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
                }
                // the protocol / inbound of the panel is taken from an account
                // that already exists inside that panel
                if ($reference !== '') {
                    $protocol = nexora_apply_panel_protocol($pdo, $panel, $reference);
                    $protocolMessage = $protocol['ok']
                        ? '✅ ' . $protocol['message']
                        : '⚠️ ' . $protocol['message'];
                }
            } catch (Exception $e) {
                flash('error', 'خطای دیتابیس: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        db_query($pdo, "DELETE FROM product WHERE id = ? AND agent = ?", [(int) ($_POST['edit_id'] ?? 0), NEXORA_AGENT_SHOP_MARKER]);
        flash('success', 'محصول حذف شد.');
    }
    if ($protocolMessage !== '') {
        flash($protocolMessage === '' ? 'success' : 'success', $protocolMessage);
    }
    header('Location: agentshop.php?tab=' . ($shopTab === 'topup' ? 'topup' : 'products'));
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

<div class="card fade-up" style="margin-bottom:14px"><div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap">
  <a class="btn <?= $shopTab === 'products' ? 'btn-primary' : 'btn-ghost' ?>" href="agentshop.php?tab=products">🛒 محصولات فروشگاه</a>
  <a class="btn <?= $shopTab === 'topup' ? 'btn-primary' : 'btn-ghost' ?>" href="agentshop.php?tab=topup">💳 شارژ پنل</a>
</div></div>

<?php if ($shopTab === 'topup'):
    $topupShop = 'agentshop';
    $topupReturn = 'agentshop.php';
    include __DIR__ . '/inc/topup_tab.php';
else: ?>
<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px" class="fade-up">
  <div style="font-size:.85rem;color:var(--mute)"><?= count($items) ?> محصول در فروشگاه نمایندگی</div>
  <button class="btn btn-primary" onclick="openModal('addModal')"><?= icon('plus', 14) ?> افزودن محصول</button>
</div>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>قیمت</th><th>حجم</th><th>زمان</th><th>پنل</th><th>توضیحات</th><th>رنگ</th><th>ایموجی</th><th>وضعیت</th><th>ترتیب</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="12" style="text-align:center;color:var(--mute);padding:22px">هنوز محصولی برای فروش پنل نمایندگی تعریف نشده است.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= nexora_agentshop_preview($it['name_product'] ?? '', (string) ($it['btn_style'] ?? ''), (string) ($it['btn_emoji_id'] ?? ''), $STYLE_COLORS) ?></td>
          <td><?= htmlspecialchars((string) ($it['name_product'] ?? '')) ?></td>
          <td><?= number_format((float) ($it['price_product'] ?? 0)) ?></td>
          <td><?= (int) ($it['Volume_constraint'] ?? 0) ?> GB</td>
          <td><?= (int) ($it['Service_time'] ?? 0) ?> روز</td>
          <td><?= htmlspecialchars((string) ($it['Location'] ?? '')) ?></td>
          <td style="min-width:220px"><?= nl2br(htmlspecialchars((string) ($it['note'] ?? ''))) ?></td>
          <td><span class="pill"><?= htmlspecialchars((string) ($it['btn_style'] ?? 'پیش‌فرض')) ?></span></td>
          <td><?= ($it['btn_emoji_id'] ?? '') !== '' ? '💎' : '—' ?></td>
          <td><?= ($it['status_product'] ?? 'active') === 'active' ? '✅ فعال' : '⛔️ پنهان' ?></td>
          <td><?= (int) ($it['display_order'] ?? 0) ?></td>
          <td style="white-space:nowrap">
            <button class="btn btn-primary btn-sm" type="button" onclick='openEditModal(<?= json_encode([
                'id' => (int) $it['id'],
                'name_product' => (string) ($it['name_product'] ?? ''),
                'price_product' => (int) ($it['price_product'] ?? 0),
                'volume_product' => (int) ($it['Volume_constraint'] ?? 0),
                'time_product' => (int) ($it['Service_time'] ?? 0),
                'namepanel' => (string) ($it['Location'] ?? ''),
                'note_product' => (string) ($it['note'] ?? ''),
                'category_product' => (string) ($it['category'] ?? ''),
                'btn_style' => (string) ($it['btn_style'] ?? ''),
                'btn_emoji_id' => (string) ($it['btn_emoji_id'] ?? ''),
                'status_product' => (string) ($it['status_product'] ?? 'active'),
                'position' => (int) ($it['display_order'] ?? 0),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ ویرایش</button>
            <a class="btn btn-no btn-sm" href="agentshop.php?delete=<?= (int) $it['id'] ?>&_csrf=<?= csrf_token() ?>" data-confirm="این محصول حذف شود؟">حذف</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal-veil" id="addModal">
  <div class="modal">
    <div class="modal-head">
      <h3>افزودن محصول فروشگاه نمایندگی</h3>
      <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
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
          <label class="field" style="grid-column:1/-1"><span>نام کاربری یک اکانت داخل همان پنل (برای تنظیم پروتکل و اینباند)</span>
            <input class="input" name="protocol_account" placeholder="اختیاری — مثلاً نام کاربری یکی از اکانت‌های موجود در پنل">
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر پر شود، پروتکل و اینباند پنل با اطلاعات همین اکانت تنظیم می‌شود.</small>
          </label>
          <label class="field"><span>رنگ دکمه</span><?= nexora_agentshop_style_select('') ?></label>
          <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" placeholder="آیدی عددی یا {emoji:نام}"></label>
          <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="note_product" rows="3" placeholder="توضیح محصول، مزیت‌ها، شرایط استفاده و ..."></textarea></label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit">افزودن محصول</button>
        <button class="btn btn-ghost" type="button" onclick="closeModal('addModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-veil" id="editModal">
  <div class="modal">
    <div class="modal-head">
      <h3>ویرایش محصول فروشگاه نمایندگی</h3>
      <button class="modal-x" onclick="closeModal('editModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="form-grid">
          <label class="field"><span>نام محصول</span><input class="input" name="name_product" id="edit_name" required></label>
          <label class="field"><span>قیمت (تومان)</span><input class="input" type="number" name="price_product" id="edit_price" value="0" required></label>
          <label class="field"><span>حجم (گیگابایت)</span><input class="input" type="number" name="volume_product" id="edit_volume" value="0" required></label>
          <label class="field"><span>مدت (روز)</span><input class="input" type="number" name="time_product" id="edit_time" value="0" required></label>
          <label class="field"><span>پنل</span>
            <select name="namepanel" id="edit_panel" class="select" required>
              <?php foreach ($panels as $pn): ?>
                <option value="<?= htmlspecialchars((string) $pn['name_panel']) ?>"><?= htmlspecialchars((string) $pn['name_panel']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>دسته‌بندی</span>
            <select name="category_product" id="edit_category" class="select">
              <option value="">—</option>
              <?php foreach ($categories as $ct): ?>
                <option value="<?= htmlspecialchars((string) $ct['remark']) ?>"><?= htmlspecialchars((string) $ct['remark']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field" style="grid-column:1/-1"><span>نام کاربری یک اکانت داخل همان پنل (برای تنظیم پروتکل و اینباند)</span>
            <input class="input" name="protocol_account" id="edit_protocol" placeholder="اختیاری — نام کاربری یکی از اکانت‌های موجود در پنل">
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر پر شود، پروتکل و اینباند پنل با اطلاعات همین اکانت تنظیم می‌شود.</small>
          </label>
          <label class="field"><span>رنگ دکمه</span><?= nexora_agentshop_style_select('', 'edit_btn_style') ?></label>
          <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" id="edit_emoji" placeholder="آیدی عددی یا {emoji:نام}"></label>
          <label class="field"><span>وضعیت</span>
            <select name="status_product" id="edit_status" class="select">
              <option value="active">فعال</option>
              <option value="hidden">پنهان</option>
            </select>
          </label>
          <label class="field"><span>ترتیب نمایش</span><input class="input" type="number" name="position" id="edit_position" value="0"></label>
          <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="note_product" id="edit_note" rows="3"></textarea></label>
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
window.openEditModal = function (p) {
    p = p || {};
    document.getElementById('edit_id').value = p.id || '';
    document.getElementById('edit_name').value = p.name_product || '';
    document.getElementById('edit_price').value = p.price_product || 0;
    document.getElementById('edit_volume').value = p.volume_product || 0;
    document.getElementById('edit_time').value = p.time_product || 0;
    document.getElementById('edit_note').value = p.note_product || '';
    document.getElementById('edit_emoji').value = p.btn_emoji_id || '';
    document.getElementById('edit_protocol').value = '';
    document.getElementById('edit_position').value = p.position || 0;
    document.getElementById('edit_status').value = p.status_product || 'active';
    var sel = document.getElementById('edit_panel');
    if (sel) {
        for (var i = 0; i < sel.options.length; i++) {
            sel.options[i].selected = sel.options[i].value === (p.namepanel || '');
        }
    }
    var cat = document.getElementById('edit_category');
    if (cat) {
        for (var j = 0; j < cat.options.length; j++) {
            cat.options[j].selected = cat.options[j].value === (p.category_product || '');
        }
    }
    var st = document.getElementById('edit_btn_style');
    if (st) {
        for (var k = 0; k < st.options.length; k++) {
            st.options[k].selected = st.options[k].value === (p.btn_style || '');
        }
    }
    openModal('editModal');
};
</script>
<?php endif; ?>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
