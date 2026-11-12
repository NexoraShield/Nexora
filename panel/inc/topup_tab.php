<?php
// Shared "panel top up" tab for the agent shop and the agent panel shop.
// The including page must define: $topupShop ('agentshop' | 'agentpanelshop')
// and $topupReturn (the page to come back to).
if (!isset($topupShop) || !isset($topupReturn)) {
    return;
}
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
            try {
                if ($action === 'topup_add') {
                    db_query(
                        $pdo,
                        "INSERT INTO panel_topup_product (shop,name,price,credit,description,btn_style,btn_emoji_id,status_product) VALUES (?,?,?,?,?,?,?,'active')",
                        [$topupShop, $name, $price, $credit, $description, $style !== '' ? $style : null, $emoji !== '' ? $emoji : null]
                    );
                    $newId = (int) $pdo->lastInsertId();
                    db_query($pdo, "UPDATE panel_topup_product SET display_order = ? WHERE id = ?", [$newId, $newId]);
                    flash('success', 'گزینه شارژ اضافه شد.');
                } else {
                    $id = (int) ($_POST['edit_id'] ?? 0);
                    db_query(
                        $pdo,
                        "UPDATE panel_topup_product SET name=?,price=?,credit=?,description=?,status_product=?,btn_style=?,btn_emoji_id=? WHERE id=? AND shop=?",
                        [
                            $name,
                            $price,
                            $credit,
                            $description,
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
    <thead><tr><th>پیش‌نمایش</th><th>نام</th><th>مبلغ</th><th>اعتبار</th><th>توضیحات</th><th>رنگ</th><th>ایموجی</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
      <?php if (!$topupItems): ?>
        <tr><td colspan="9" style="text-align:center;color:var(--mute);padding:22px">هنوز گزینه‌ای برای شارژ تعریف نشده است.</td></tr>
      <?php endif; ?>
      <?php foreach ($topupItems as $item): ?>
        <tr>
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="topup_edit">
            <input type="hidden" name="edit_id" value="<?= (int) $item['id'] ?>">
            <td><?= nexora_agentshop_preview($item['name'] ?? '', (string) ($item['btn_style'] ?? ''), (string) ($item['btn_emoji_id'] ?? ''), $topupColors) ?></td>
            <td><input class="input" name="topup_name" value="<?= htmlspecialchars((string) ($item['name'] ?? '')) ?>"></td>
            <td><input class="input" type="number" name="topup_price" value="<?= (int) ($item['price'] ?? 0) ?>"></td>
            <td><input class="input" type="number" name="topup_credit" value="<?= (int) ($item['credit'] ?? 0) ?>"></td>
            <td><textarea class="input" name="topup_description" rows="2" style="min-width:220px"><?= htmlspecialchars((string) ($item['description'] ?? '')) ?></textarea></td>
            <td><?= nexora_agentshop_style_select((string) ($item['btn_style'] ?? '')) ?></td>
            <td><input class="input" name="btn_emoji_id" value="<?= htmlspecialchars((string) ($item['btn_emoji_id'] ?? '')) ?>" placeholder="ایموجی"></td>
            <td>
              <select name="status_product" class="select">
                <option value="active" <?= ($item['status_product'] ?? 'active') === 'active' ? 'selected' : '' ?>>فعال</option>
                <option value="hidden" <?= ($item['status_product'] ?? '') === 'hidden' ? 'selected' : '' ?>>پنهان</option>
              </select>
            </td>
            <td style="white-space:nowrap">
              <button class="btn btn-primary btn-sm" type="submit"><?= icon('check', 13) ?> ذخیره</button>
            </td>
          </form>
          <form method="POST" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="topup_delete">
            <input type="hidden" name="edit_id" value="<?= (int) $item['id'] ?>">
            <button class="btn btn-no btn-sm" type="submit" data-confirm="این گزینه حذف شود؟">حذف</button>
          </form>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal" id="addTopupModal">
  <div class="modal-card">
    <div class="modal-head"><div class="modal-title">افزودن گزینه شارژ</div><button class="btn btn-no" onclick="closeModal('addTopupModal')">✕</button></div>
    <form method="POST" class="modal-body">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="topup_add">
      <div class="form-grid">
        <label class="field"><span>نام گزینه (مثلاً: شارژ یک میلیونی)</span><input class="input" name="topup_name" required></label>
        <label class="field"><span>مبلغ پرداختی (تومان)</span><input class="input" type="number" name="topup_price" value="0" required></label>
        <label class="field"><span>اعتبار داخل پنل (تومان)</span><input class="input" type="number" name="topup_credit" value="1000000" required></label>
        <label class="field"><span>رنگ دکمه</span><?= nexora_agentshop_style_select('') ?></label>
        <label class="field"><span>ایموجی پرمیوم</span><input class="input" name="btn_emoji_id" placeholder="آیدی عددی یا {emoji:نام}"></label>
        <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="topup_description" rows="3" placeholder="مثلاً: یک میلیون تومان اعتبار داخل پنل نمایندگی"></textarea></label>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit">افزودن گزینه</button>
        <button class="btn btn-no" type="button" onclick="closeModal('addTopupModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>
