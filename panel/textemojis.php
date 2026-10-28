<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__mirzaStyleLib = __DIR__ . '/../mirza_style.php';
if (is_file($__mirzaStyleLib)) {
    require_once $__mirzaStyleLib;
}
require_auth();

$MENU_KEYS = [
    'text_sell' => ['textbot', 'sell'],
    'text_extend' => ['textbot', 'extend'],
    'text_usertest' => ['textbot', 'userTest'],
    'text_wheel_luck' => ['textbot', 'wheelLuck'],
    'text_Purchased_services' => ['textbot', 'purchasedServices'],
    'accountwallet' => ['textbot', 'accountWallet'],
    'text_affiliates' => ['textbot', 'affiliates'],
    'text_Tariff_list' => ['textbot', 'tariffList'],
    'text_support' => ['textbot', 'support'],
    'text_help' => ['textbot', 'help'],
    'text_agentpanel' => ['textbot', 'agentPanel'],
    'text_requestagent' => ['textbot', 'requestAgent'],
];

function mirza_textemojis_clean($v): string
{
    $v = trim((string) $v);
    if ($v === '') return '';
    if (preg_match('/^\d{1,32}$/', $v)) return $v;
    if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $v, $m)) return $m[1];
    if (preg_match('/^\{emoji:([^}]+)\}$/u', $v)) return $v;
    return '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    csrf_check_post();
    $key = (string) ($_POST['btn_key'] ?? '');
    $val = mirza_textemojis_clean($_POST['btn_emoji'] ?? '');
    if (isset($MENU_KEYS[$key])) {
        try {
            $name = 'nx_emoji_' . $key;
            $exists = db_count($pdo, "SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?", [$name]);
            if ($exists > 0) {
                db_query($pdo, "UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?", [$val, $name]);
            } elseif ($val !== '') {
                db_query($pdo, "INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)", [$name, $val]);
            }
            flash('success', 'ذخیره شد.');
        } catch (Exception $e) {
            flash('error', 'خطای دیتابیس: ' . $e->getMessage());
        }
    }
    header('Location: textemojis.php');
    exit;
}

$saved = [];
try {
    foreach (db_fetchAll($pdo, "SELECT NamePay, ValuePay FROM PaySetting WHERE NamePay LIKE 'nx\\_emoji\\_%' ESCAPE '\\'") as $r) {
        $saved[substr((string) $r['NamePay'], 9)] = (string) ($r['ValuePay'] ?? '');
    }
} catch (Exception $e) {
    $saved = [];
}

$pageTitle = 'ایموجی دکمه‌های اصلی';
$pageLede = 'ایموجی پرمیوم برای دکمه‌های صفحه اصلی ربات (منوی اینلاین: آیکون · منوی معمولی: کاراکتر)';
$activeNav = 'textemojis';
$emojiLibrary = function_exists('mirza_emoji_list') ? mirza_emoji_list() : [];
$msEmojiRows = [];
if (function_exists('mirza_emoji_placeholder')) {
    foreach ($emojiLibrary as $er) {
        $msEmojiRows[] = ['placeholder' => mirza_emoji_placeholder($er), 'label' => trim((string) ($er['emoji_name'] ?? ''))];
    }
}
include __DIR__ . '/inc/layout_head.php';

$flashOk = get_flash('success');
$flashErr = get_flash('error');
if ($flashOk) { echo '<div class="card fade-up" style="margin-bottom:14px"><div class="card-body">' . htmlspecialchars($flashOk) . '</div></div>'; }
if ($flashErr) { echo '<div class="card fade-up" style="margin-bottom:14px"><div class="card-body">' . htmlspecialchars($flashErr) . '</div></div>'; }
?>

<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>
<p class="field-hint" style="margin:0 0 14px">روی ایموجی کلیک کنید بعد روی فیلد ایموجی هر دکمه. برای بقیه متن‌ها: <a href="bottext.php">متن‌های ربات</a></p>

<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">دکمه‌های صفحه اصلی</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>دکمه</th><th>ایموجی</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($MENU_KEYS as $key => [$grp, $sub]):
        $label = $textbotlang[$grp][$sub] ?? $key;
        $cur = $saved[$key] ?? '';
        $preview = is_string($cur) && preg_match('/^\d{1,32}$/', $cur) ? '◆ ' : '';
      ?>
      <tr>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="btn_key" value="<?= htmlspecialchars($key) ?>">
          <td class="cs"><?= $preview . htmlspecialchars((string) $label) ?></td>
          <td><input type="text" name="btn_emoji" class="input ms-emoji-field" data-ms-max="1" dir="ltr" style="max-width:170px" placeholder="emoji id" value="<?= htmlspecialchars($cur) ?>"></td>
          <td><button type="submit" class="btn btn-primary btn-sm">ذخیره</button></td>
        </form>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<script src="js/emoji-insert.js"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
