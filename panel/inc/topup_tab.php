<?php
// Shared "panel top up" tab for the agent shop and the agent panel shop.
// The including page must define: $topupShop ('agentshop' | 'agentpanelshop')
// and $topupReturn (the page to come back to).
if (!isset($topupShop) || !isset($topupReturn)) {
    return;
}
require_once __DIR__ . '/shop_helpers.php';
$topupShop = nexora_topup_shop_key($topupShop);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '') {
    csrf_check_post();
    $action = (string) $_POST['action'];
    if ($action === 'topup_button') {
        nexora_topup_set_button($topupShop, (string) ($_POST['topup_label'] ?? ''), (string) ($_POST['topup_style'] ?? ''));
        flash('success', 'نام و رنگ دکمه شارژ ذخیره شد.');
    } elseif ($action === 'topup_add' || $action === 'topup_edit') {
        $name = trim($_POST['topup_name'] ?? '');
        if ($name === '') {
            flash('error', 'نام گزینه شارژ را وارد کنید.');
        } else {
            $style = nexora_agentshop_clean_style($_POST['btn_style'] ?? '');
            $emoji = nexora_agentshop_clean_emoji($_POST['btn_emoji_id'] ?? '');
            $price = (int) ($_POST['topup_price'] ?? 0);
            $credit = (int) ($_POST['topup_credit'] ?? 0);
            $description = (string) ($_POST['topup_description'] ?? '');
            $unit = trim((string) ($_POST['topup_unit'] ?? ''));
            try {
                if ($action === 'topup_add') {
                    db_query(
                        $pdo,
                        "INSERT INTO panel_topup_product (shop,name,price,credit,description,unit,btn_style,btn_emoji_id,status_product) VALUES (?,?,?,?,?,?,?,?,'active')",
                        [$topupShop, $name, $price, $credit, $description, $unit !== '' ? $unit : null, $style !== '' ? $style : null, $emoji !== '' ? $emoji : null]
                    );
                    $newId = (int) $pdo->lastInsertId();
                    db_query($pdo, "UPDATE panel_topup_product SET display_order = ? WHERE id = ?", [$newId, $newId]);
                    flash('success', 'گزینه شارژ اضافه شد.');
                } else {
                    $id = (int) ($_POST['edit_id'] ?? 0);
                    db_query(
                        $pdo,
                        "UPDATE panel_topup_product SET name=?,price=?,credit=?,description=?,unit=?,status_product=?,btn_style=?,btn_emoji_id=? WHERE id=? AND shop=?",
                        [
                            $name,
                            $price,
                            $credit,
                            $description,
                            $unit !== '' ? $unit : null,
                            ($_POST['status_product'] ?? 'active') === 'hidden' ? 'hidden' : 'active',
                            $style !== '' ? $style : null,
                            $emoji !== '' ? $emoji : null,
                            $id,
                            $topupShop,
                        ]
                    );
                    flash('success', 'گزینه شارژ بروزرسانی شد.');
                }
            } catch (Exception $e) {
                flash('error', 'خطای دیتابیس: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'topup_delete') {
        db_query($pdo, "DELETE FROM panel_topup_product WHERE id = ? AND shop = ?", [(int) ($_POST['edit_id'] ?? 0), $topupShop]);
        flash('success', 'گزینه شارژ حذف شد.');
    }
    header('Location: ' . $topupReturn . '?tab=topup');
    exit;
}

$topupItems = nexora_topup_products($topupShop, false);
$topupButton = nexora_topup_button($topupShop);
$topupColors = [];
foreach (nexora_color_palette() as $slug => $info) {
    if ($slug !== '') {
        $topupColors[$slug] = $info['hex'];
    }
}
?>
<div class="card fade-up" style="margin-bottom:18px">
  <div class="card-head"><div><div class="card-title">🎨 دکمه «شارژ پنل»</div>
    <div class="card-subtitle">نام و رنگ این دکمه در منوی فروشگاه نمایش داده می‌شود. می‌توانید ایموجی پرمیوم هم روی نام بگذارید (مثلاً: 💳 شارژ پنل).</div></div></div>
  <form method="POST" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="topup_button">
    <div class="field" style="min-width:260px">
      <label>نام دکمه</label>
      <input class="input" name="topup_label" value="<?= htmlspecialchars((string) $topupButton['label']) ?>">
    </div>
    <div class="field" style="min-width:160px">
      <label>رنگ</label>
      <select name="topup_style" class="select">
        <?php foreach (nexora_color_palette() as $slug => $info): $label = $info['label'] ?? $slug; if ($slug === '') $label = 'پیش‌فرض'; ?>
          <option value="<?= htmlspecialchars($slug) ?>" <?= ((string) $slug === (string) $topupButton['style']) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('check', 13) ?> ذخیره</button>
  </form>
</div>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="card-head"><div><div class="card-title">💳 گزینه‌های شارژ</div>
    <div class="card-subtitle">هر گزینه یک مبلغ پرداختی و یک اعتبار داخل پنل دارد. پس از پرداخت، اعتبار به کیف پول مشتری اضافه می‌شود.</div></div>
    <div><button class="btn btn-primary" onclick="openModal('addTopupModal')"><?= icon('plus', 14) ?> افزودن گزینه</button></div>
  </div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>مبلغ</th><th>اعتبار</th><th>واحد</th><th>توضیحات</th><th>رنگ</th><th>ایموجی</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
      <?php if (!$topupItems): ?>
        <tr><td colspan="10" style="text-align:center;color:var(--mute);padding:22px">هنوز گزینه‌ای برای شارژ تعریف نشده است.</td></tr>
      <?php endif; ?>
      <?php foreach ($topupItems as $item): ?>
        <tr>
          <td><?= nexora_agentshop_preview($item['name'] ?? '', (string) ($item['btn_style'] ?? ''), (string) ($item['btn_emoji_id'] ?? ''), $topupColors) ?></td>
          <td><?= htmlspecialchars((string) ($item['name'] ?? '')) ?></td>
          <td><?= number_format((float) ($item['price'] ?? 0)) ?></td>
          <td><?= number_format((float) ($item['credit'] ?? 0)) ?></td>
          <td><?= htmlspecialchars((string) ($item['unit'] ?? '')) ?></td>
          <td style="min-width:220px"><?= nl2br(htmlspecialchars((string) ($item['description'] ?? ''))) ?></td>
          <td><span class="pill"><?= htmlspecialchars((string) ($item['btn_style'] ?? 'پیش‌فرض')) ?></span></td>
          <td><?= ($item['btn_emoji_id'] ?? '') !== '' ? '💎' : '—' ?></td>
          <td><?= ($item['status_product'] ?? 'active') === 'active' ? '✅ فعال' : '⛔️ پنهان' ?></td>
          <td style="white-space:nowrap">
            <button class="btn btn-primary btn-sm" type="button" onclick='openEditTopup(<?= json_encode([
                'id' => (int) $item['id'],
                'topup_name' => (string) ($item['name'] ?? ''),
                'topup_price' => (float) ($item['price'] ?? 0),
                'topup_credit' => (float) ($item['credit'] ?? 0),
                'topup_unit' => (string) ($item['unit'] ?? ''),
                'topup_description' => (string) ($item['description'] ?? ''),
                'btn_style' => (string) ($item['btn_style'] ?? ''),
                'btn_emoji_id' => (string) ($item['btn_emoji_id'] ?? ''),
                'status_product' => (string) ($item['status_product'] ?? 'active'),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ ویرایش</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="topup_delete">
              <input type="hidden" name="edit_id" value="<?= (int) $item['id'] ?>">
              <button class="btn btn-no btn-sm" type="submit" data-confirm="این گزینه حذف شود؟">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal-veil" id="addTopupModal">
  <div class="modal">
    <div class="modal-head">
      <h3>افزودن گزینه شارژ</h3>
      <button class="modal-x" onclick="closeModal('addTopupModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="topup_add">
        <div class="form-grid">
          <label class="field"><span>نام گزینه (مثلاً: شارژ یک میلیونی)</span><input class="input" name="topup_name" required></label>
          <label class="field"><span>مبلغ پرداختی (تومان)</span><input class="input" type="number" name="topup_price" value="0" required></label>
          <label class="field"><span>اعتبار داخل پنل (تومان)</span><input class="input" type="number" name="topup_credit" value="1000000" required></label>
          <label class="field"><span>واحد (مثال: GB یا Toman)</span><input class="input" name="topup_unit" placeholder="اختیاری"></label>
          <label class="field"><span>رنگ دکمه</span><?= nexora_agentshop_style_select('') ?></label>
          <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" placeholder="آیدی عددی یا {emoji:نام}"></label>
          <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="topup_description" rows="3" placeholder="مثلاً: یک میلیون تومان اعتبار داخل پنل نمایندگی"></textarea></label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit">افزودن گزینه</button>
        <button class="btn btn-ghost" type="button" onclick="closeModal('addTopupModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-veil" id="editTopupModal">
  <div class="modal">
    <div class="modal-head">
      <h3>ویرایش گزینه شارژ</h3>
      <button class="modal-x" onclick="closeModal('editTopupModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="topup_edit">
        <input type="hidden" name="edit_id" id="tu_edit_id">
        <div class="form-grid">
          <label class="field"><span>نام گزینه</span><input class="input" name="topup_name" id="tu_edit_name" required></label>
          <label class="field"><span>مبلغ پرداختی (تومان)</span><input class="input" type="number" name="topup_price" id="tu_edit_price" value="0" required></label>
          <label class="field"><span>اعتبار داخل پنل (تومان)</span><input class="input" type="number" name="topup_credit" id="tu_edit_credit" value="0" required></label>
          <label class="field"><span>واحد</span><input class="input" name="topup_unit" id="tu_edit_unit" placeholder="اختیاری"></label>
          <label class="field"><span>رنگ دکمه</span><?= nexora_agentshop_style_select('', 'tu_edit_style') ?></label>
          <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" id="tu_edit_emoji" placeholder="آیدی عددی یا {emoji:نام}"></label>
          <label class="field"><span>وضعیت</span>
            <select name="status_product" id="tu_edit_status" class="select">
              <option value="active">فعال</option>
              <option value="hidden">پنهان</option>
            </select>
          </label>
          <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="topup_description" id="tu_edit_description" rows="3"></textarea></label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit"><?= icon('check', 13) ?> ذخیره</button>
        <button class="btn btn-ghost" type="button" onclick="closeModal('editTopupModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<script>
window.openEditTopup = function (p) {
    p = p || {};
    document.getElementById('tu_edit_id').value = p.id || '';
    document.getElementById('tu_edit_name').value = p.topup_name || '';
    document.getElementById('tu_edit_price').value = p.topup_price || 0;
    document.getElementById('tu_edit_credit').value = p.topup_credit || 0;
    document.getElementById('tu_edit_unit').value = p.topup_unit || '';
    document.getElementById('tu_edit_description').value = p.topup_description || '';
    document.getElementById('tu_edit_emoji').value = p.btn_emoji_id || '';
    document.getElementById('tu_edit_status').value = p.status_product || 'active';
    var st = document.getElementById('tu_edit_style');
    if (st) {
        for (var i = 0; i < st.options.length; i++) {
            st.options[i].selected = st.options[i].value === (p.btn_style || '');
        }
    }
    openModal('editTopupModal');
};
</script>
