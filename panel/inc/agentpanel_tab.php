<?php
// The "فروش پنل به نمایندگان" section: the packages an agent can buy.
// The including page must define $panelReturn (the page to come back to).
if (!isset($panelReturn)) {
    $panelReturn = 'agents.php';
}
$panelTab = $panelReturn . '?tab=panels';

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
    if (preg_match('/^\{emoji:([^}]+)\}$/u', $v, $m) && function_exists('nexora_emoji_lookup')) {
        $row = nexora_emoji_lookup(trim($m[1]));
        if ($row && !empty($row['custom_emoji_id'])) return (string) $row['custom_emoji_id'];
    }
    return '';
}
function nexora_agentpanel_style_select($selected, string $name = 'btn_style', string $id = ''): string
{
    $h = '<select name="' . htmlspecialchars($name) . '"'
        . ($id !== '' ? ' id="' . htmlspecialchars($id) . '"' : '')
        . ' class="select" style="min-width:90px">';
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
    $label = function_exists('nexora_emoji_text_to_html')
        ? nexora_emoji_text_to_html((string) $name)
        : htmlspecialchars((string) $name);
    return '<span style="display:inline-block;background:' . $bg . ';color:' . $fg . ';border-radius:8px;padding:4px 12px;font-size:.8rem">' . $icon . $label . '</span>';
}

// The panels that can be picked for the agent panel are the ones the
// administrator defined. The panels of the type "nexora_agent" (the ones that
// are built for the agent panel itself) come first, the other panels of the
// administrator follow. The list is built before the form is handled, so the
// dropdown is filled on a normal page load as well and not only after a form
// was submitted.
$agentPanels = [];
$otherPanels = [];
foreach (panel_load_panels($pdo) as $panelRow) {
    $panelName = (string) ($panelRow['name_panel'] ?? '');
    if ($panelName === '') {
        continue;
    }
    if ((string) ($panelRow['type'] ?? '') === 'nexora_agent') {
        $agentPanels[] = $panelName;
    } else {
        $otherPanels[] = $panelName;
    }
}
$agentPanels = array_merge($agentPanels, $otherPanels);

// The table of the packages of the reseller panel. A database that does not
// know it yet must not turn this tab into a fatal error.
$agentPanelProductReady = panel_table_exists($pdo, 'agent_panel_product');
if (!$agentPanelProductReady) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS agent_panel_product (id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, name varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL, price varchar(2000) NULL, credit varchar(2000) NULL, description TEXT NULL, btn_style VARCHAR(20) NULL, btn_emoji_id VARCHAR(32) NULL, status_product varchar(20) NOT NULL DEFAULT 'active', display_order INT NOT NULL DEFAULT 0, panel_name varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $agentPanelProductReady = true;
    } catch (Throwable $e) {
        error_log('agentpanel_tab (table): ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '') {
    csrf_check_post();
    $action = (string) $_POST['action'];
    if ($action === 'ap_add' || $action === 'ap_edit') {
        $name = trim($_POST['name_panel'] ?? '');
        $price = (int) ($_POST['price'] ?? 0);
        $credit = (int) ($_POST['credit'] ?? 0);
        // The price of a package is the credit it puts into the wallet of the
        // customer. When no price is typed, or when the administrator asks for
        // it, the credit is used.
        if ($price <= 0 && $credit > 0) {
            $price = $credit;
        }
        if ((string) ($_POST['price_same_credit'] ?? '0') === '1') {
            $price = $credit;
        }
        if ($name === '') {
            flash('error', 'نام پنل را وارد کنید.');
        } elseif (!$agentPanelProductReady) {
            flash('error', 'جدول پنل‌های نمایندگی در دیتابیس نیست. ابتدا اسکریپت نصب/به‌روزرسانی را اجرا کنید.');
        } else {
            try {
                if ($action === 'ap_add') {
                    db_query(
                        $pdo,
                        "INSERT INTO agent_panel_product (name,price,credit,description,btn_style,btn_emoji_id,panel_name,status_product) VALUES (?,?,?,?,?,?,?,'active')",
                        [
                            $name,
                            $price,
                            $credit,
                            (string) ($_POST['description'] ?? ''),
                            nexora_agentpanel_clean_style($_POST['btn_style'] ?? '') ?: null,
                            nexora_agentpanel_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
                            (string) ($_POST['panel_name'] ?? ''),
                        ]
                    );
                    $newId = (int) $pdo->lastInsertId();
                    db_query($pdo, "UPDATE agent_panel_product SET display_order = ? WHERE id = ?", [$newId, $newId]);
                    flash('success', 'پنل نمایندگی اضافه شد.');
                } else {
                    $id = (int) ($_POST['edit_id'] ?? 0);
                    db_query(
                        $pdo,
                        "UPDATE agent_panel_product SET name=?,price=?,credit=?,description=?,status_product=?,btn_style=?,btn_emoji_id=?,panel_name=? WHERE id=?",
                        [
                            $name,
                            $price,
                            $credit,
                            (string) ($_POST['description'] ?? ''),
                            ($_POST['status_product'] ?? 'active') === 'hidden' ? 'hidden' : 'active',
                            nexora_agentpanel_clean_style($_POST['btn_style'] ?? '') ?: null,
                            nexora_agentpanel_clean_emoji($_POST['btn_emoji_id'] ?? '') ?: null,
                            (string) ($_POST['panel_name'] ?? ''),
                            $id,
                        ]
                    );
                    if (isset($_POST['position'])) {
                        try {
                            move_display_order($pdo, 'agent_panel_product', $id, (int) $_POST['position']);
                        } catch (Throwable $e) {
                            error_log('agentpanel_tab (order): ' . $e->getMessage());
                        }
                    }
                    flash('success', 'پنل نمایندگی بروزرسانی شد.');
                }
            } catch (Throwable $e) {
                flash('error', 'خطای دیتابیس: ' . $e->getMessage());
            }
        }
        header('Location: ' . $panelTab);
        exit;
    }
    if ($action === 'ap_delete') {
        try {
            db_query($pdo, "DELETE FROM agent_panel_product WHERE id = ?", [(int) ($_POST['edit_id'] ?? 0)]);
            flash('success', 'پنل نمایندگی حذف شد.');
        } catch (Throwable $e) {
            flash('error', 'حذف پنل انجام نشد: ' . $e->getMessage());
        }
        header('Location: ' . $panelTab);
        exit;
    }
}

if (isset($_GET['delete'])) {
    csrf_check_get();
    try {
        db_query($pdo, "DELETE FROM agent_panel_product WHERE id = ?", [(int) $_GET['delete']]);
        flash('success', 'پنل نمایندگی حذف شد.');
    } catch (Throwable $e) {
        flash('error', 'حذف پنل انجام نشد: ' . $e->getMessage());
    }
    header('Location: ' . $panelTab);
    exit;
}

$items = [];
if ($agentPanelProductReady) {
    try {
        $items = db_fetchAll($pdo, "SELECT * FROM agent_panel_product ORDER BY display_order, id");
    } catch (Throwable $e) {
        error_log('agentpanel_tab (list): ' . $e->getMessage());
        $items = [];
    }
}

$STYLE_COLORS = [];
foreach (nexora_color_palette() as $slug => $info) {
    if ($slug !== '') $STYLE_COLORS[$slug] = $info['hex'];
}
?>

<?php include __DIR__ . '/emoji_picker_bar.php'; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px" class="fade-up">
  <div style="font-size:.85rem;color:var(--mute)"><?= count($items) ?> پنل در فروشگاه نمایندگی</div>
  <button class="btn btn-primary" onclick="openModal('addAgentPanelModal')"><?= icon('plus', 14) ?> افزودن پنل</button>
</div>

<div class="card fade-up" style="margin-bottom:18px">
  <div class="card-body" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="font-size:.85rem;color:var(--mute)">پنل‌هایی که به مشتری‌ها تحویل داده‌اید (اعتبار، رمز و حذف پنل) در تب «پنل‌های تحویل‌شده» هستند.</div>
    <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars((string) $panelReturn) ?>?tab=accounts">📦 پنل‌های تحویل‌شده</a>
  </div>
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
          <td><?= nexora_agentpanel_preview($it['name'] ?? '', (string) ($it['btn_style'] ?? ''), (string) ($it['btn_emoji_id'] ?? ''), $STYLE_COLORS) ?></td>
          <td><?= htmlspecialchars((string) ($it['name'] ?? '')) ?></td>
          <td><?= number_format((float) ($it['price'] ?? 0)) ?></td>
          <td><?= number_format((float) ($it['credit'] ?? 0)) ?></td>
          <td style="min-width:240px"><?= nl2br(htmlspecialchars((string) ($it['description'] ?? ''))) ?></td>
          <td><span class="pill"><?= htmlspecialchars((string) ($it['btn_style'] ?? 'پیش‌فرض')) ?></span></td>
          <td><?= nexora_emoji_preview_html((string) ($it['btn_emoji_id'] ?? '')) ?: '<span class="cf">—</span>' ?></td>
          <td><?= ($it['status_product'] ?? 'active') === 'active' ? '✅ فعال' : '⛔️ پنهان' ?></td>
          <td><?= (int) ($it['display_order'] ?? 0) ?></td>
          <td style="white-space:nowrap">
            <button class="btn btn-primary btn-sm" type="button" onclick='openEditAgentPanel(<?= json_encode([
                'id' => (int) $it['id'],
                'name' => (string) ($it['name'] ?? ''),
                'panel_name' => (string) ($it['panel_name'] ?? ''),
                'price' => (int) ($it['price'] ?? 0),
                'credit' => (int) ($it['credit'] ?? 0),
                'description' => (string) ($it['description'] ?? ''),
                'btn_style' => (string) ($it['btn_style'] ?? ''),
                'btn_emoji_id' => (string) ($it['btn_emoji_id'] ?? ''),
                'status_product' => (string) ($it['status_product'] ?? 'active'),
                'position' => (int) ($it['display_order'] ?? 0),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ ویرایش</button>
            <a class="btn btn-no btn-sm" href="<?= htmlspecialchars($panelTab) ?>&delete=<?= (int) $it['id'] ?>&_csrf=<?= csrf_token() ?>" data-confirm="این پنل حذف شود؟">حذف</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal-veil" id="addAgentPanelModal">
  <div class="modal">
    <div class="modal-head">
      <h3>افزودن پنل نمایندگی</h3>
      <button class="modal-x" onclick="closeModal('addAgentPanelModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="ap_add">
        <div class="form-grid">
          <label class="field"><span>نام پنل (مثال: پنل ۱ میلیونی)</span><input class="input" name="name_panel" required></label>
          <label class="field"><span>پنل (پنلی که بمشتری تحویل داده می‌شود)</span>
            <select name="panel_name" class="select">
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($agentPanels as $agentPanelName): ?>
                <option value="<?= htmlspecialchars((string) $agentPanelName) ?>"><?= htmlspecialchars(panel_plain_name((string) $agentPanelName)) ?></option>
              <?php endforeach; ?>
            </select>
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">پنل‌هایی که در ربات با کلید «افزودن پنل» و نوع «اجنت پنل» ساخته‌اید اینجا نمایش داده می‌شوند.</small>
          </label>
          <label class="field"><span>قیمت (تومان)</span><input class="input" type="number" name="price" value="0" required><small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر خالی بماند، قیمت برابر با اعتبار پنل ذخیره می‌شود.</small></label>
          <label class="field"><span>اعتبار داخل پنل (تومان)</span><input class="input" type="number" name="credit" value="1000000" required></label>
          <label class="field"><span>رنگ دکمه</span><?= nexora_agentpanel_style_select('') ?></label>
          <label class="field"><span>ایموجی پرمیوم</span><input class="input ms-emoji-field" data-ms-max="1" name="btn_emoji_id" placeholder="آیدی عددی یا {emoji:نام}"></label>
          <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="description" rows="3" placeholder="مزیت‌های این پنل، تعداد کاربر، مدت اعتبار و ..."></textarea></label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit">افزودن پنل</button>
        <button class="btn btn-ghost" type="button" onclick="closeModal('addAgentPanelModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-veil" id="editAgentPanelModal">
  <div class="modal">
    <div class="modal-head">
      <h3>ویرایش پنل نمایندگی</h3>
      <button class="modal-x" onclick="closeModal('editAgentPanelModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="ap_edit">
        <input type="hidden" name="edit_id" id="ap_edit_id">
        <div class="form-grid">
          <label class="field"><span>نام پنل</span><input class="input" name="name_panel" id="ap_edit_name" required></label>
          <label class="field"><span>پنل</span>
            <select name="panel_name" id="ap_edit_panel" class="select">
              <option value="">— انتخاب کنید —</option>
              <?php foreach ($agentPanels as $agentPanelName): ?>
                <option value="<?= htmlspecialchars((string) $agentPanelName) ?>"><?= htmlspecialchars(panel_plain_name((string) $agentPanelName)) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>قیمت (تومان)</span><input class="input" type="number" name="price" id="ap_edit_price" value="0" required></label>
          <label class="field"><span>اعتبار داخل پنل (تومان)</span><input class="input" type="number" name="credit" id="ap_edit_credit" value="0" required></label>
          <label class="field"><span>رنگ دکمه</span><?= nexora_agentpanel_style_select('', 'btn_style', 'ap_edit_style') ?></label>
          <label class="field"><span>ایموجی پرمیوم</span><input class="input ms-emoji-field" data-ms-max="1" name="btn_emoji_id" id="ap_edit_emoji" placeholder="آیدی عددی یا {emoji:نام}"></label>
          <label class="field"><span>وضعیت</span>
            <select name="status_product" id="ap_edit_status" class="select">
              <option value="active">فعال</option>
              <option value="hidden">پنهان</option>
            </select>
          </label>
          <label class="field"><span>ترتیب نمایش</span><input class="input" type="number" name="position" id="ap_edit_position" value="0"></label>
          <label class="field" style="grid-column:1/-1"><span>توضیحات (برای مشتری)</span><textarea class="input" name="description" id="ap_edit_description" rows="3"></textarea></label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit"><?= icon('check', 13) ?> ذخیره</button>
        <button class="btn btn-ghost" type="button" onclick="closeModal('editAgentPanelModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<script>
window.openEditAgentPanel = function (p) {
    p = p || {};
    document.getElementById('ap_edit_id').value = p.id || '';
    document.getElementById('ap_edit_name').value = p.name || '';
    var apPanel = document.getElementById('ap_edit_panel');
    if (apPanel) {
        apPanel.value = p.panel_name || '';
    }
    document.getElementById('ap_edit_price').value = p.price || 0;
    document.getElementById('ap_edit_credit').value = p.credit || 0;
    document.getElementById('ap_edit_description').value = p.description || '';
    document.getElementById('ap_edit_emoji').value = p.btn_emoji_id || '';
    document.getElementById('ap_edit_position').value = p.position || 0;
    document.getElementById('ap_edit_status').value = p.status_product || 'active';
    var st = document.getElementById('ap_edit_style');
    if (st) {
        for (var i = 0; i < st.options.length; i++) {
            st.options[i].selected = st.options[i].value === (p.btn_style || '');
        }
    }
    openModal('editAgentPanelModal');
};
</script>
