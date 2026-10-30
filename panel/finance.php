<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../nexora_style.php';
require_auth();

nexora_ensure_emoji_table();

// Handle FrenzyEx settings save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gateway']) && $_POST['gateway'] === 'frenzyex') {
    csrf_check_post();
    $fields = ['frenzyexstatus', 'frenzyex_api_key', 'frenzyex_base_url', 'frenzyex_callback_secret', 'cashbackfrenzyex', 'minbalancefrenzyex', 'maxbalancefrenzyex'];
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        $chk = $pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?");
        $chk->execute([$f]);
        if ((int)$chk->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)")->execute([$f, $val]);
        } else {
            $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?")->execute([$val, $f]);
        }
    }
    if (function_exists('clearSelectCache')) clearSelectCache('PaySetting');
    flash('success', 'تنظیمات FrenzyEx ذخیره شد.');
    header('Location: finance.php');
    exit;
}

// Load FrenzyEx settings
$fx = [];
foreach (['frenzyexstatus','frenzyex_api_key','frenzyex_base_url','frenzyex_callback_secret','cashbackfrenzyex','minbalancefrenzyex','maxbalancefrenzyex'] as $k) {
    $fx[$k] = getPaySettingValue($k, '');
}

$pageTitle = '💰 مالی و درگاه‌های پرداخت';
$pageLede = 'مدیریت درگاه‌های پرداخت و تنظیمات مالی ربات.';
$activeNav = 'finance';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="page-header">
    <h1>💰 مالی و درگاه‌های پرداخت</h1>
    <p class="page-lede"><?= $pageLede ?></p>
</div>

<div class="card fade-up d1" style="margin-bottom:20px">
    <div class="card-head">
        <div><div class="card-title">💳 FrenzyEx</div></div>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="gateway" value="frenzyex">
            <div class="field">
                <label>وضعیت</label>
                <select name="frenzyexstatus" class="input">
                    <option value="onfrenzyex" <?= $fx['frenzyexstatus'] === 'onfrenzyex' ? 'selected' : '' ?>>فعال</option>
                    <option value="offfrenzyex" <?= $fx['frenzyexstatus'] !== 'onfrenzyex' ? 'selected' : '' ?>>غیرفعال</option>
                </select>
            </div>
            <div class="field">
                <label>کلید API</label>
                <input type="text" name="frenzyex_api_key" class="input" value="<?= htmlspecialchars($fx['frenzyex_api_key']) ?>" placeholder="API Key">
            </div>
            <div class="field">
                <label>آدرس پایه (Base URL)</label>
                <input type="text" name="frenzyex_base_url" class="input" value="<?= htmlspecialchars($fx['frenzyex_base_url'] ?: 'https://frenzy.fastsnap.info') ?>" placeholder="https://frenzy.fastsnap.info">
            </div>
            <div class="field">
                <label>سکرت کال‌بک</label>
                <input type="text" name="frenzyex_callback_secret" class="input" value="<?= htmlspecialchars($fx['frenzyex_callback_secret']) ?>" placeholder="Callback Secret">
            </div>
            <div class="field">
                <label>کش‌بک (%)</label>
                <input type="text" name="cashbackfrenzyex" class="input" value="<?= htmlspecialchars($fx['cashbackfrenzyex'] ?: '0') ?>">
            </div>
            <div class="field">
                <label>حداقل مبلغ (تومان)</label>
                <input type="text" name="minbalancefrenzyex" class="input" value="<?= htmlspecialchars($fx['minbalancefrenzyex'] ?: '20000') ?>">
            </div>
            <div class="field">
                <label>حداکثر مبلغ (تومان)</label>
                <input type="text" name="maxbalancefrenzyex" class="input" value="<?= htmlspecialchars($fx['maxbalancefrenzyex'] ?: '1000000') ?>">
            </div>
            <div style="margin-top:12px">
                <button type="submit" class="btn btn-primary">💾 ذخیره تنظیمات FrenzyEx</button>
            </div>
        </form>
    </div>
</div>

<div class="card fade-up d2">
    <div class="card-head">
        <div><div class="card-title">📊 تراکنش‌های پرداخت</div></div>
    </div>
    <div class="card-body">
        <p>مشاهدهٔ تمام تراکنش‌های پرداخت در بخش پرداخت‌ها.</p>
        <a href="payment.php" class="btn btn-ghost">💳 مشاهده تراکنش‌ها</a>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>