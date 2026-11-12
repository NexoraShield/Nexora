<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_once __DIR__ . '/../panel_protocol.php';
require_auth();

// A product can define its own inbound(s). When the product has no inbound
// of its own the panel default is used (see panels/Api/Marzban.php).
function nexora_product_inbound_list($panel): array
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
function nexora_product_inbound_value($post): ?string
{
    $list = $post['inbounds_product'] ?? [];
    if (!is_array($list)) {
        $list = ($list === '' || $list === null) ? [] : [$list];
    }
    $list = array_values(array_unique(array_filter(array_map('strval', $list), fn($v) => $v !== '')));
    if (!$list) {
        return null;
    }
    return json_encode(array_values($list));
}
function nexora_product_inbound_field($panels, $selected = [], $prefix = ''): string
{
    $selected = array_map('strval', (array) $selected);
    $h = '<div style="max-height:170px;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:8px">';
    $any = false;
    foreach ($panels as $pl) {
        $list = nexora_product_inbound_list($pl);
        if (!$list) {
            continue;
        }
        $any = true;
        $h .= '<div style="margin-bottom:6px"><b style="font-size:.75rem">' . htmlspecialchars((string) $pl['name_panel']) . '</b>';
        foreach ($list as $inb) {
            $chk = in_array($inb, $selected, true) ? ' checked' : '';
            $h .= '<label style="display:flex;gap:6px;align-items:center;font-size:.78rem;padding:2px 0;cursor:pointer">'
                . '<input type="checkbox" name="inbounds_product[]" value="' . htmlspecialchars($inb) . '"' . $chk . '>'
                . '<span style="direction:ltr">' . htmlspecialchars($inb) . '</span></label>';
        }
        $h .= '</div>';
    }
    if (!$any) {
        $h .= '<div style="font-size:.78rem;color:var(--mute)">برای این پنل هنوز اینباندی تنظیم نشده است. ابتدا از صفحه پنل‌ها گزینه «تنظیم پروتکل و اینباند» را بزنید.</div>';
    }
    return $h . '</div>';
}

// Sets the protocol / inbound of the panel with the data of an account that
// already exists inside that panel. Returns the message for the admin.
function nexora_product_apply_protocol(string $panelName, string $reference): string
{
    $reference = trim($reference);
    if ($reference === '' || $panelName === '' || $panelName === '/all') {
        return '';
    }
    $result = nexora_apply_panel_protocol($GLOBALS['pdo'], $panelName, $reference);
    return ($result['ok'] ? '✅ ' : '⚠️ ') . $result['message'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  csrf_check_post();
  $name = trim($_POST['name_product'] ?? '');
  if ($name === '') {
    flash('error', $textbotlang['panel']['productNameRequired']);
    header('Location: product.php');
    exit;
  }
  if (db_count($pdo, "SELECT COUNT(*) FROM product WHERE name_product = ?", [$name])) {
    flash('error', $textbotlang['panel']['productNameExists']);
    header('Location: product.php');
    exit;
  }
  $code = bin2hex(random_bytes(2));
  try {
    db_query(
      $pdo,
      "INSERT INTO product (name_product,code_product,price_product,Volume_constraint,Service_time,Location,agent,data_limit_reset,note,category,hide_panel,one_buy_status,inbounds) VALUES (?,?,?,?,?,?,?,'no_reset',?,?,'{}','0',?)",
      [$name, $code, (int) ($_POST['price_product'] ?? 0), (int) ($_POST['volume_product'] ?? 0), (int) ($_POST['time_product'] ?? 0), $_POST['namepanel'] ?? '', $_POST['agent_product'] ?? '', $_POST['note_product'] ?? '', $_POST['category_product'] ?? '', nexora_product_inbound_value($_POST)]
    );
    db_query($pdo, "UPDATE product SET display_order = id WHERE id = ?", [$pdo->lastInsertId()]);
    flash('success', $textbotlang['panel']['productAddedPrefix'] . $name . $textbotlang['panel']['productAddedSuffix']);
  } catch (Exception $e) {
    flash('error', $textbotlang['panel']['productDbError'] . $e->getMessage());
  }
  $protocol = nexora_product_apply_protocol($_POST['namepanel'] ?? '', $_POST['protocol_account'] ?? '');
  if ($protocol !== '') {
    flash('success', $protocol);
  }
  header('Location: product.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
  csrf_check_post();
  $pid = (int) ($_POST['edit_id'] ?? 0);
  $name = trim($_POST['name_product'] ?? '');
  if ($pid && $name !== '') {
    try {
      $pdo->beginTransaction();
      db_query(
        $pdo,
        "UPDATE product SET name_product=?,price_product=?,Volume_constraint=?,Service_time=?,Location=?,agent=?,note=?,category=?,inbounds=? WHERE id=?",
        [$name, (int) ($_POST['price_product'] ?? 0), (int) ($_POST['volume_product'] ?? 0), (int) ($_POST['time_product'] ?? 0), $_POST['namepanel'] ?? '', $_POST['agent_product'] ?? '', $_POST['note_product'] ?? '', $_POST['category_product'] ?? '', nexora_product_inbound_value($_POST), $pid]
      );
      if (isset($_POST['position'])) {
        move_display_order($pdo, 'product', $pid, (int) $_POST['position']);
      }
      $pdo->commit();
      flash('success', $textbotlang['panel']['productEdited']);
    } catch (Exception $e) {
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      flash('error', $textbotlang['panel']['productErrorPrefix'] . $e->getMessage());
    }
  }
  $protocol = nexora_product_apply_protocol($_POST['namepanel'] ?? '', $_POST['protocol_account'] ?? '');
  if ($protocol !== '') {
    flash('success', $protocol);
  }
  header('Location: product.php');
  exit;
}

if (isset($_GET['delete'])) {
  csrf_check_get();
  db_query($pdo, "DELETE FROM product WHERE id = ?", [(int) $_GET['delete']]);
  flash('success', $textbotlang['panel']['productDeleted']);
  header('Location: product.php');
  exit;
}

$panels = [];
$categories = [];
try {
  $panels = db_fetchAll($pdo, "SELECT * FROM marzban_panel ORDER BY display_order, id");
  $categories = db_fetchAll($pdo, "SELECT * FROM category ORDER BY display_order, id");
} catch (Exception $e) {
}
// The test services have their own page, they are not products of the shop.
$products = db_fetchAll($pdo, "SELECT * FROM product WHERE 1=1" . panel_product_test_filter($pdo) . " ORDER BY display_order, id");

$pageTitle = $textbotlang['panel']['productsTitle'];
$pageLede = $textbotlang['panel']['productsSubtitle'];
$activeNav = 'product';
include __DIR__ . '/inc/layout_head.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px" class="fade-up">
  <div style="font-size:.85rem;color:var(--mute)"><?= count($products) ?> <?= $textbotlang['panel']['productsHeading'] ?></div>
  <button class="btn btn-primary" onclick="openModal('addModal')"><?= icon('plus', 14) ?> <?= $textbotlang['panel']['productAddProductBtn'] ?></button>
</div>

<div class="card fade-up d1">
  <?php if (empty($products)): ?>
    <div class="empty" style="padding:60px 20px">
      <svg class="ill" viewBox="0 0 200 160" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="40" y="30" width="120" height="100" rx="12" fill="var(--surface-3)" />
        <rect x="56" y="50" width="88" height="12" rx="6" fill="var(--border-strong)" />
        <rect x="56" y="72" width="60" height="8" rx="4" fill="var(--border)" />
        <rect x="56" y="90" width="72" height="8" rx="4" fill="var(--border)" />
        <rect x="56" y="108" width="44" height="8" rx="4" fill="var(--border)" />
        <circle cx="155" cy="125" r="22" fill="var(--accent-s)" stroke="var(--accent)" stroke-width="2" />
        <path d="M147 125h16M155 117v16" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" />
      </svg>
      <p><?= $textbotlang['panel']['productColName'] ?></p>
      <button class="btn btn-primary" style="margin-top:14px" onclick="openModal('addModal')"><?= icon('plus', 14) ?>
        <?= $textbotlang['panel']['productColVolume'] ?></button>
    </div>
  <?php else: ?>
    <div class="toolbar">
      <div class="toolbar-title"><?= $textbotlang['panel']['productColTime'] ?> <small>(<?= count($products) ?>)</small></div>
      <div class="search-box" style="min-width:220px">
        <?= icon('search', 14) ?>
        <input type="text" placeholder="<?= htmlspecialchars($textbotlang['panel']['productSearchPlaceholder']) ?>" data-filter="prodTbl">
        <button type="button" class="search-clear">✕</button>
      </div>
    </div>
    <div class="tbl-wrap">
      <table id="prodTbl" class="tbl-xl">
        <thead>
          <tr>
            <th>#</th>
            <th>نام محصول</th>
            <th>قیمت</th>
            <th>حجم</th>
            <th>مدت</th>
            <th>پنل</th>
            <th>اینباند</th>
            <th>دسته‌بندی</th>
            <th>ایموجی</th>
            <th>کد</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1;
          foreach ($products as $p): ?>
            <tr>
              <td class="cf"><?= $i++ ?></td>
              <td class="cs"><?= function_exists('nexora_emoji_text_to_html') ? nexora_emoji_text_to_html((string) ($p['name_product'] ?? '')) : htmlspecialchars($p['name_product'] ?? '') ?></td>
              <td class="cn cs"><?= number_format((int) ($p['price_product'] ?? 0)) ?> <span class="cf"><?= $textbotlang['panel']['productFieldServiceDays'] ?></span></td>
              <td class="cn"><?= htmlspecialchars($p['Volume_constraint'] ?? '—') ?> <span class="cf">GB</span></td>
              <td class="cn"><?= htmlspecialchars($p['Service_time'] ?? '—') ?> <span class="cf"><?= $textbotlang['panel']['productFieldPriceToman'] ?></span></td>
              <td class="cf"><?= htmlspecialchars(trunc($p['Location'] ?? '—', 16)) ?></td>
              <td class="cm" style="font-size:.72rem"><?php $__ib = json_decode((string) ($p['inbounds'] ?? ''), true); echo is_array($__ib) && $__ib ? htmlspecialchars(implode('، ', array_slice(array_map('strval', $__ib), 0, 2))) : '<span class="cf">پیش‌فرض پنل</span>'; ?></td>
              <td><?php if (!empty($p['category'])): ?><span
                    class="tag tag-info"><?= htmlspecialchars($p['category']) ?></span><?php else: ?><span
                    class="cf">—</span><?php endif; ?></td>
              <td><?= function_exists('nexora_emoji_preview_html') ? (nexora_emoji_preview_html((string) ($p['btn_emoji_id'] ?? '')) ?: '<span class="cf">—</span>') : '<span class="cf">—</span>' ?></td>
              <td class="cm" style="font-size:.72rem"><?= htmlspecialchars($p['code_product'] ?? '') ?></td>
              <td>
                <div style="display:flex;gap:5px">
                  <button class="btn btn-ghost btn-sm btn-icon" title="<?= htmlspecialchars($textbotlang['panel']['productEditBtn']) ?>"
                    onclick="openEditModal(<?= htmlspecialchars(json_encode($p + ['position' => $i - 1]), ENT_QUOTES) ?>)">
                    <?= icon('edit', 13) ?>
                  </button>
                  <a href="product.php?delete=<?= (int) $p['id'] ?>&_csrf=<?= csrf_token() ?>"
                    class="btn btn-no btn-sm btn-icon" title="<?= htmlspecialchars($textbotlang['panel']['productDeleteBtn']) ?>"
                    data-confirm="<?= htmlspecialchars(sprintf($textbotlang['panel']['productConfirmDeleteProduct'], $p['name_product'])) ?>">
                    <?= icon('trash', 13) ?>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal-veil" id="addModal">
  <div class="modal">
    <div class="modal-head">
      <h3><?= $textbotlang['panel']['productFieldProductType'] ?></h3>
      <button class="modal-x" onclick="closeModal('addModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="add">
        <div class="form-grid">
          <div class="field full">
            <label><?= $textbotlang['panel']['productFieldDescription'] ?></label>
            <input type="text" name="name_product" class="input" placeholder="<?= htmlspecialchars($textbotlang['panel']['productNameExample']) ?>" required>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productSaveBtn'] ?></label>
            <input type="number" name="price_product" class="input" placeholder="<?= htmlspecialchars($textbotlang['panel']['productZeroValue']) ?>" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productVolumeGbSuffix'] ?></label>
            <input type="number" name="volume_product" class="input" placeholder="<?= htmlspecialchars($textbotlang['panel']['productFiftyValue']) ?>" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productCancelBtn'] ?></label>
            <input type="number" name="time_product" class="input" placeholder="<?= htmlspecialchars($textbotlang['panel']['productThirtyValue']) ?>" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productFieldLocation'] ?></label>
            <select name="category_product" class="select">
              <option value=""><?= $textbotlang['panel']['productDetailLocation'] ?></option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['remark'] ?? '') ?>">
                  <?= htmlspecialchars($cat['remark'] ?? '') ?>
                 </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productFieldCategory'] ?></label>
            <select name="namepanel" class="select">
              <option value=""><?= $textbotlang['panel']['productFieldNote'] ?></option>
              <?php foreach ($panels as $pl): ?>
                <option value="<?= htmlspecialchars((string) $pl['name_panel']) ?>">
                  <?= htmlspecialchars(stripCustomEmojiTags((string) $pl['name_panel'])) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productColId'] ?></label>
            <select name="agent_product" class="select">
              <option value="f"><?= $textbotlang['panel']['productColType'] ?></option>
              <option value="n"><?= $textbotlang['panel']['productColLocation'] ?></option>
              <option value="n2"><?= $textbotlang['panel']['productColCategory'] ?></option>
            </select>
          </div>
          <div class="field full">
            <label><?= $textbotlang['panel']['productColDescription'] ?></label>
            <input type="text" name="note_product" class="input" placeholder="<?= htmlspecialchars($textbotlang['panel']['productDescriptionOptional']) ?>">
          </div>
          <div class="field full">
            <label>اینباند اختصاصی محصول (اختیاری)</label>
            <?= nexora_product_inbound_field($panels, [], 'add') ?>
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر اینباندی انتخاب نشود، اینباند پیش‌فرض پنل استفاده می‌شود. سرویس‌های این محصول با اینباند ساخته می‌شوند.</small>
          </div>
          <div class="field full">
            <label>نام کاربری یک اکانت داخل همان پنل (برای تنظیم پروتکل و اینباند)</label>
            <input type="text" name="protocol_account" class="input" placeholder="اختیاری — مثلاً نام کاربری یکی از اکانت‌های موجود در پنل">
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر پر شود، پروتکل و اینباند پنل با اطلاعات همین اکانت تنظیم می‌شود.</small>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> <?= $textbotlang['panel']['productColNote'] ?></button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')"><?= $textbotlang['panel']['productColCreatedAt'] ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal-veil" id="editModal">
  <div class="modal">
    <div class="modal-head">
      <h3><?= $textbotlang['panel']['productDetailTitle'] ?></h3>
      <button class="modal-x" onclick="closeModal('editModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="form-grid">
          <div class="field full">
            <label><?= $textbotlang['panel']['productDetailName'] ?></label>
            <input type="text" name="name_product" id="edit_name" class="input" required>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productDetailVolume'] ?></label>
            <input type="number" name="price_product" id="edit_price" class="input" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productVolumeGbSuffix'] ?></label>
            <input type="number" name="volume_product" id="edit_volume" class="input" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productDetailTime'] ?></label>
            <input type="number" name="time_product" id="edit_time" class="input" min="0">
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productFieldPosition'] ?></label>
            <input type="number" name="position" id="edit_position" class="input" min="1" max="<?= count($products) ?>" required>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productDetailPrice'] ?></label>
            <select name="category_product" id="edit_cat" class="select">
              <option value=""><?= $textbotlang['panel']['productDetailLocation'] ?></option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['remark'] ?? '') ?>">
                  <?= htmlspecialchars($cat['remark'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productDetailType'] ?></label>
            <select name="namepanel" id="edit_panel" class="select">
              <option value=""><?= $textbotlang['panel']['productDetailLocation'] ?></option>
              <?php foreach ($panels as $pl): ?>
                <option value="<?= htmlspecialchars((string) $pl['name_panel']) ?>">
                  <?= htmlspecialchars(stripCustomEmojiTags((string) $pl['name_panel'])) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label><?= $textbotlang['panel']['productDetailCategory'] ?></label>
            <select name="agent_product" id="edit_agent" class="select">
              <option value="f"><?= $textbotlang['panel']['productDetailDescription'] ?></option>
              <option value="n"><?= $textbotlang['panel']['productDetailNote'] ?></option>
              <option value="n2"><?= $textbotlang['panel']['productCloseBtn'] ?></option>
            </select>
          </div>
          <div class="field full">
            <label><?= $textbotlang['panel']['productUnlimitedLabel'] ?></label>
            <input type="text" name="note_product" id="edit_note" class="input">
          </div>
          <div class="field full">
            <label>اینباند اختصاصی محصول (اختیاری)</label>
            <div id="edit_inbounds"><?= nexora_product_inbound_field($panels, [], 'edit') ?></div>
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر اینباندی انتخاب نشود، اینباند پیش‌فرض پنل استفاده می‌شود.</small>
          </div>
          <div class="field full">
            <label>نام کاربری یک اکانت داخل همان پنل (برای تنظیم پروتکل و اینباند)</label>
            <input type="text" name="protocol_account" id="edit_protocol" class="input" placeholder="اختیاری — نام کاربری یکی از اکانت‌های موجود در پنل">
            <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">اگر پر شود، پروتکل و اینباند پنل با اطلاعات همین اکانت تنظیم می‌شود.</small>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button type="submit" class="btn btn-primary"><?= icon('check', 13) ?> <?= $textbotlang['panel']['productDayUnit'] ?></button>
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')"><?= $textbotlang['panel']['productTomanUnit'] ?></button>
      </div>
    </form>
  </div>
</div>

<script src="js/product.js"></script>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>