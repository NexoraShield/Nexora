<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) {
    require_once $__nexoraStyleLib;
}
require_auth();

$STYLE_COLORS = ['primary' => '#3b82f6', 'success' => '#22c55e', 'danger' => '#ef4444'];

// Build a comprehensive list of "button" entries from multiple lang groups.
// Each entry: storage key (sanitized, used for PaySetting nx_bb_*) + dotted path + default text.
$buttonEntries = [];
foreach (($textbotlang['keyboard'] ?? []) as $k => $v) {
    if (!is_string($v) || $v === '') continue;
    $buttonEntries[] = ['storage' => (string) $k, 'path' => 'keyboard.' . $k, 'default' => $v, 'group' => 'keyboard'];
}
if (isset($textbotlang['users']['backbtn']) && is_string($textbotlang['users']['backbtn'])) {
    $buttonEntries[] = ['storage' => 'users_backbtn', 'path' => 'users.backbtn', 'default' => $textbotlang['users']['backbtn'], 'group' => 'users'];
}
foreach (['backAdminBtn', 'backMenuBtn', 'backToShopMenu'] as $ak) {
    if (isset($textbotlang['Admin'][$ak]) && is_string($textbotlang['Admin'][$ak]) && $textbotlang['Admin'][$ak] !== '') {
        $buttonEntries[] = ['storage' => 'Admin_' . $ak, 'path' => 'Admin.' . $ak, 'default' => $textbotlang['Admin'][$ak], 'group' => 'Admin'];
    }
}
$mainMenuMap = [
    'sell' => 'text_sell', 'extend' => 'text_extend', 'userTest' => 'text_usertest',
    'wheelLuck' => 'text_wheel_luck', 'purchasedServices' => 'text_Purchased_services',
    'accountWallet' => 'accountwallet', 'affiliates' => 'text_affiliates',
    'tariffList' => 'text_Tariff_list', 'support' => 'text_support', 'help' => 'text_help',
    'agentPanel' => 'text_agentpanel', 'requestAgent' => 'text_requestagent',
];
foreach ($mainMenuMap as $sub => $storage) {
    if (isset($textbotlang['textbot'][$sub]) && is_string($textbotlang['textbot'][$sub]) && $textbotlang['textbot'][$sub] !== '') {
        $buttonEntries[] = ['storage' => $storage, 'path' => 'textbot.' . $sub, 'default' => $textbotlang['textbot'][$sub], 'group' => 'textbot'];
    }
}
$storageKeys = array_column($buttonEntries, 'storage');
$keyboardTexts = $textbotlang['keyboard'] ?? [];


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

$overrideFile = dirname(__DIR__) . '/lang/override/fa.php';
$overrideTexts = is_file($overrideFile) ? include $overrideFile : [];
if (!is_array($overrideTexts)) $overrideTexts = [];
function nexora_bb_get_override(array $overrides, string $path): ?string {
    $node = $overrides;
    foreach (explode('.', $path) as $seg) {
        if (!is_array($node) || !array_key_exists($seg, $node)) return null;
        $node = $node[$seg];
    }
    return is_string($node) ? $node : null;
}
function nexora_bb_set_override(array &$overrides, string $path, $value): void {
    $segments = explode('.', $path);
    $last = array_pop($segments);
    $node = &$overrides;
    foreach ($segments as $seg) {
        if (!isset($node[$seg]) || !is_array($node[$seg])) $node[$seg] = [];
        $node = &$node[$seg];
    }
    if ($value === null) {
        unset($node[$last]);
    } else {
        $node[$last] = $value;
    }
    unset($node);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $key = (string) ($_POST['btn_key'] ?? '');
    $idx = array_search($key, $storageKeys, true);
    if ($idx !== false) {
        $entry = $buttonEntries[$idx];
        $emoji = nexora_bb_clean_emoji($_POST['btn_emoji'] ?? '');
        $style = nexora_bb_clean_style($_POST['btn_style'] ?? '');
        $text  = isset($_POST['btn_text']) ? (string) $_POST['btn_text'] : null;
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
        if (is_string($text)) {
            $text = str_replace("\r\n", "\n", $text);
            $default = $entry['default'];
            if (trim($text) === '' || $text === $default) {
                nexora_bb_set_override($overrideTexts, $entry['path'], null);
            } else {
                nexora_bb_set_override($overrideTexts, $entry['path'], $text);
            }
            try {
                $clean = array_filter($overrideTexts, fn($v) => !(is_array($v) && empty($v)));
                if ($clean) {
                    $overrideDir = dirname($overrideFile);
                    if (!is_dir($overrideDir)) @mkdir($overrideDir, 0755, true);
                    @file_put_contents($overrideFile, "<?php\n\nreturn " . var_export($clean, true) . ";\n", LOCK_EX);
                } else {
                    if (is_file($overrideFile)) @unlink($overrideFile);
                }
                if (function_exists('opcache_invalidate')) opcache_invalidate($overrideFile, true);
            } catch (Throwable $e) {
            }
        }
    }
    header('Location: botbuttons.php');
    exit;
}

$pageTitle = 'دکمه‌های ربات (متن، رنگ، ایموجی)';
$pageLede = 'ویرایش متن، رنگ و ایموجی پرمیوم برای تمام کلیدهای کیبورد ربات (شامل «بازگشت» و دکمه‌های منو).';
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
    <thead><tr><th>کلید</th><th>متن</th><th>رنگ</th><th>ایموجی</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($buttonEntries as $entry):
        $storage = $entry['storage'];
        $label = $entry['default'];
        $curEmoji = $savedEmoji[$storage] ?? '';
        $curStyle = $savedStyle[$storage] ?? '';
        $curText = nexora_bb_get_override($overrideTexts, $entry['path']);
        if ($curText === null) $curText = $entry['default'];
        $preview = is_string($curEmoji) && preg_match('/^\d{1,32}$/', $curEmoji) ? '◆ ' : '';
      ?>
      <tr>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="btn_key" value="<?= htmlspecialchars($storage) ?>">
          <td class="cs"><?= $preview . htmlspecialchars((string) $label) ?> <span class="cm" dir="ltr" style="font-size:.7rem">(<?= htmlspecialchars($entry['path']) ?>)</span></td>
          <td><textarea name="btn_text" class="textarea ms-emoji-field" data-ms-max="3" dir="auto" rows="2" style="min-width:160px"><?= htmlspecialchars($curText) ?></textarea></td>
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
