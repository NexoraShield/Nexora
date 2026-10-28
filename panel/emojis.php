<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__mirzaStyleLib = __DIR__ . '/../mirza_style.php';
if (is_file($__mirzaStyleLib)) {
    require_once $__mirzaStyleLib;
}
require_auth();
if (!function_exists('mirza_ensure_emoji_table') || !function_exists('mirza_emoji_list')) {
    die('mirza_style.php is missing — reinstall the Style update.');
}
mirza_ensure_emoji_table();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim((string) ($_POST['emoji_name'] ?? ''));
        $cid = trim((string) ($_POST['custom_emoji_id'] ?? ''));
        if (preg_match('/emoji-id\s*=\s*"(\d{1,32})"/i', $cid, $m)) $cid = $m[1];
        $utf8 = trim((string) ($_POST['emoji_utf8'] ?? ''));
        $res = mirza_emoji_save($name, $cid, $utf8, $_SESSION['admin_user'] ?? null);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'ایموجی ذخیره شد: {emoji:' . $res['slug'] . '}' : ($res['error'] ?? 'خطا'));
        header('Location: emojis.php');
        exit;
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $done = ($id > 0) && mirza_emoji_delete($id);
        flash($done ? 'success' : 'error', $done ? 'ایموجی حذف شد.' : 'حذف انجام نشد.');
        header('Location: emojis.php');
        exit;
    }
    if ($action === 'rename') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['emoji_name'] ?? ''));
        flash(mirza_emoji_rename($id, $name) ? 'success' : 'error', mirza_emoji_rename($id, $name) ? 'نام به‌روز شد.' : 'تغییر نام انجام نشد.');
        header('Location: emojis.php');
        exit;
    }
    if ($action === 'save_all') {
        $names = $_POST['names'] ?? [];
        $saved = 0;
        if (is_array($names)) {
            foreach ($names as $id => $name) {
                if (mirza_emoji_rename((int) $id, trim((string) $name))) $saved++;
            }
        }
        flash('success', $saved > 0 ? "نام {$saved} ایموجی ذخیره شد." : 'تغییری ذخیره نشد.');
        header('Location: emojis.php');
        exit;
    }
}
$emojiLibrary = mirza_emoji_list();
$pageTitle = 'کتابخانه ایموجی پرمیوم';
$pageLede = 'ذخیره، نام‌گذاری و کپی کد برای دکمه‌های ربات';
$activeNav = 'emojis';
$msEmojiRows = [];
if (function_exists('mirza_emoji_placeholder')) {
    foreach ($emojiLibrary as $er) {
        $msEmojiRows[] = ['placeholder' => mirza_emoji_placeholder($er), 'label' => trim((string) ($er['emoji_name'] ?? ''))];
    }
}
include __DIR__ . '/inc/layout_head.php';
?>
<?php $flashOk = get_flash('success');
$flashErr = get_flash('error'); ?>
<?php if ($flashOk): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashOk) ?></div></div><?php endif; ?>
<?php if ($flashErr): ?><div class="card fade-up" style="margin-bottom:14px"><div class="card-body"><?= htmlspecialchars($flashErr) ?></div></div><?php endif; ?>

<div class="card fade-up" style="margin-bottom:18px">
  <div class="card-head"><div><div class="card-title">راهنما</div><div class="card-subtitle">آیدی عددی ایموجی پرمیوم را از کتابخانه ویرا کپی کنید یا تگ کامل <code dir="ltr">&lt;tg-emoji&gt;</code> را بچسبانید</div></div></div>
  <div class="card-body"><p style="font-size:.85rem">در دکمه‌ها فقط <b>یک</b> ایموجی (محدودیت تلگرام). کد <code dir="ltr">{emoji:slug}</code> را می‌توانید مستقیم در نام محصول/دسته هم بنویسید.</p></div>
</div>

<div class="card fade-up d1" style="margin-bottom:18px">
  <div class="card-head"><div><div class="card-title">افزودن ایموجی</div></div></div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="add">
      <div class="form-grid">
        <div class="field">
          <label>نام (انگلیسی، مثلا kharid)</label>
          <input type="text" name="emoji_name" class="input" dir="ltr" required>
        </div>
        <div class="field">
          <label>شناسه عددی تلگرام</label>
          <input type="text" name="custom_emoji_id" class="input" dir="ltr" placeholder="5377..." required>
        </div>
        <div class="field">
          <label>کاراکتر جایگزین (اختیاری)</label>
          <input type="text" name="emoji_utf8" class="input" placeholder="🔥">
        </div>
      </div>
      <div class="modal-foot" style="padding:12px 0 0">
        <button type="submit" class="btn btn-primary"><?= icon('plus', 13) ?> ذخیره</button>
      </div>
    </form>
  </div>
</div>

<form method="post" class="card fade-up d1">
  <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
  <input type="hidden" name="action" value="save_all">
  <div class="card-head">
    <div class="card-title">ایموجی‌های ذخیره‌شده (<?= count($emojiLibrary) ?>)</div>
    <?php if ($emojiLibrary !== []): ?>
      <button type="submit" class="btn btn-primary btn-sm">ذخیره همهٔ نام‌ها</button>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0">
    <?php if ($emojiLibrary === []): ?>
      <p class="field-hint" style="padding:18px">هنوز ایموجی ذخیره نشده. با <code>/savemoji</code> در ربات شروع کنید.</p>
    <?php else: ?>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr><th>نام نمایشی</th><th>slug</th><th>پیش‌نمایش</th><th>کد در دکمه</th><th>شناسه تلگرام</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($emojiLibrary as $row):
              $placeholder = mirza_emoji_placeholder($row);
              ?>
              <tr>
                <td><input type="text" name="names[<?= (int) $row['id'] ?>]" class="input input-sm" style="max-width:150px" value="<?= htmlspecialchars($row['emoji_name']) ?>" required></td>
                <td><code dir="ltr"><?= htmlspecialchars((string) ($row['emoji_slug'] ?? '')) ?></code></td>
                <td style="font-size:1.35rem;text-align:center"><?= htmlspecialchars($row['emoji_utf8'] ?? '—') ?></td>
                <td><code dir="ltr"><?= htmlspecialchars($placeholder) ?></code>
                  <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($placeholder, ENT_QUOTES) ?>')">کپی</button>
                </td>
                <td><code dir="ltr"><?= htmlspecialchars($row['custom_emoji_id']) ?></code></td>
                <td>
                  <button type="button" class="btn btn-ghost btn-sm" onclick="var f=document.getElementById('em-del-<?= (int) $row['id'] ?>');if(confirm('حذف شود؟'))f.submit();">حذف</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</form>

<?php foreach ($emojiLibrary as $row): ?>
  <form method="post" id="em-del-<?= (int) $row['id'] ?>" style="display:none">
    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
  </form>
<?php endforeach; ?>

<?php include __DIR__ . '/inc/emoji_picker_bar.php'; ?>
<script src="js/emoji-insert.js"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
