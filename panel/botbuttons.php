<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_auth();

$STYLE_COLORS = ['primary' => '#3b82f6', 'success' => '#22c55e', 'danger' => '#ef4444'];

// All keyboard button labels from lang (keyboard group)
$keyboardTexts = $textbotlang['keyboard'] ?? [];
$btnKeys = array_keys($keyboardTexts);

function nexora_bb_clean_style($v): string
{
    $v = trim((string) $v);
    return in_array($v, ['primary', 'success', 'danger'], true) ? $v : '';
}
function nexora_bb_clean_emoji($v): string
{
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('/^\d{1,32}$/', $v)) return $v;
    if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $v, $m)) return $m[1];
    if (preg_match('/^\{emoji:([^}]+)\}$/u', $v)) return $v;
    return '';
}

// Load saved emoji + style from PaySetting
$savedEmoji = [];
$savedStyle = [];
try {
    foreach (db_fetchAll($pdo, "SELECT NamePay, ValuePay FROM PaySetting WHERE NamePay LIKE 'nx_bb_emoji_%' OR NamePay LIKE 'nx_bb_style_%'") as $r) {
        $name = (string) $r['NamePay'];
        if (str_starts_with($name, 'nx_bb_emoji_')) {
            $savedEmoji[substr($name, 12)] = (string) ($r['ValuePay'] ?? '');
        } elseif (str_starts_with($name, 'nx_bb_style_')) {
            $savedStyle[substr($name, 12)] = (string) ($r['ValuePay'] ?? '');
        }
    }
} catch (Exception $e) {
    $savedEmoji = [];
    $savedStyle = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $key = (string) ($_POST['btn_key'] ?? '');
    if (in_array($key, $btnKeys, true)) {
        $emoji = nexora_bb_clean_emoji($_POST['btn_emoji'] ?? '');
        $style = nexora_bb_clean_style($_POST['btn_style'] ?? '');
        try {
            foreach (['emoji' => $emoji, 'style' => $style] as $field => $val) {
                $name = 'nx_bb_' . $field . '_' . $key;
                $exists = db_count($pdo, "SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?", [$name]);
                if ($val !== '') {
                    if ($exists > 0) {
                        db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $name]);
                    } else {
                        db_query($pdo, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)", [$name, $val]);
                    }
                } elseif ($exists > 0) {
                    db_query($pdo, "DELETE FROM PaySetting WHERE NamePay = ?", [$name]);
                }
            }
            flash('success', 'ذخیره شد.');
        } catch (Exception $e) {
            flash('error', 'خطای دیتابیس: ' . $e->getMessage());
        }
    }
    header('Location: botbuttons.php');
    exit;
}

$pageTitle = 'دکمه‌های ربات (متن، رنگ، ایموجی)';
$pageLede = 'ویرایش رنگ و ایموجی پرمیوم برای تمام کلیدهای کیبورد ربات. برای ویرایش متن کلیدها از «متن‌های ربات» استفاده کنید.';
$activeNav = 'botbuttons';
$emojiLibrary = function_exists('nexora_emoji_list') ? nexora_emoji_list() : [];
include __DIR__ . '/inc/layout_head.php';

$flashOk = get_flash('success');
$flashErr = get_flash('error');
if ($flashOk) { echo '<div class="card fade-up" style="margin-bottom:14px"><div class="card-body">' . htmlspecialchars($flashOk) . '</div></div>'; }
if ($flashErr) { echo '<div class="card fade-up" style="margin-bottom:14px"><div class="card-body">' . htmlspecialchars($flashErr) . '</div></div>'; }
?>

<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>
<p class="field-hint" style="margin:0 0 14px">روی ایموجی کلیک کنید سپس روی فیلد ایموجی هر کلید. برای ویرایش متن: <a href="bottext.php">متن‌های ربات</a></p>

<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">کلیدهای کیبورد ربات</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>کلید</th><th>رنگ</th><th>ایموجی</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($btnKeys as $key):
        $label = $keyboardTexts[$key];
        $curEmoji = $savedEmoji[$key] ?? '';
        $curStyle = $savedStyle[$key] ?? '';
        $preview = is_string($curEmoji) && preg_match('/^\d{1,32}$/', $curEmoji) ? '◆ ' : '';
      ?>
      <tr>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="btn_key" value="<?= htmlspecialchars($key) ?>">
          <td class="cs"><?= $preview . htmlspecialchars((string) $label) ?> <span class="cm" dir="ltr" style="font-size:.7rem">(<?= htmlspecialchars($key) ?>)</span></td>
          <td>
            <select name="btn_style" class="select" style="min-width:90px">
              <option value="" <?= $curStyle === '' ? 'selected' : '' ?>>پیش‌فرض</option>
              <option value="primary" <?= $curStyle === 'primary' ? 'selected' : '' ?>>آبی</option>
              <option value="success" <?= $curStyle === 'success' ? 'selected' : '' ?>>سبز</option>
              <option value="danger" <?= $curStyle === 'danger' ? 'selected' : '' ?>>قرمز</option>
            </select>
          </td>
          <td><input type="text" name="btn_emoji" class="input ms-emoji-field" data-ms-max="1" dir="ltr" style="max-width:150px" placeholder="emoji id" value="<?= htmlspecialchars($curEmoji) ?>"></td>
          <td><button type="submit" class="btn btn-primary btn-sm">ذخیره</button></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<script src="js/emoji-insert.js"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
