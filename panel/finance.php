<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../nexora_style.php';
require_once __DIR__ . '/../gateways.php';
require_auth();

nexora_ensure_emoji_table();

$gateways = nexora_payment_gateways();
$palette = nexora_color_palette();

/**
 * Writes one PaySetting row.
 */
function finance_save_setting(PDO $pdo, string $name, string $value): void
{
    $chk = $pdo->prepare("SELECT COUNT(*) FROM PaySetting WHERE NamePay = ?");
    $chk->execute([$name]);
    if ((int) $chk->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO PaySetting (NamePay, ValuePay) VALUES (?, ?)")->execute([$name, $value]);
    } else {
        $pdo->prepare("UPDATE PaySetting SET ValuePay = ? WHERE NamePay = ?")->execute([$value, $name]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check_post();
    $action = (string) $_POST['action'];
    $key = trim((string) ($_POST['gateway'] ?? ''));
    if (!isset($gateways[$key])) {
        flash('error', 'درگاه نامعتبر است.');
        header('Location: finance.php');
        exit;
    }
    $gateway = $gateways[$key];

    if ($action === 'toggle') {
        $current = (string) getPaySettingValue($gateway['setting'], $gateway['off']);
        $isOn = nexora_pay_switch_is_on($current, $gateway['on']);
        finance_save_setting($pdo, $gateway['setting'], $isOn ? $gateway['off'] : $gateway['on']);
        flash('success', ($isOn ? 'درگاه «' : 'درگاه «') . $gateway['label'] . '» ' . ($isOn ? 'خاموش' : 'روشن') . ' شد.');
    } elseif ($action === 'save') {
        // the colour of the gateway (also the colour of its payment button)
        $style = trim((string) ($_POST['gw_style'] ?? ''));
        if ($style !== '' && in_array($style, nexora_color_slugs(), true)) {
            finance_save_setting($pdo, 'nx_gw_style_' . $key, $style);
        }
        // the on / off switch
        $status = trim((string) ($_POST['gw_status'] ?? ''));
        if ($status === 'on' || $status === 'off') {
            finance_save_setting($pdo, $gateway['setting'], $status === 'on' ? $gateway['on'] : $gateway['off']);
        }
        // the settings of the gateway
        foreach ($gateway['fields'] as $field) {
            $name = (string) $field['name'];
            if (!array_key_exists($name, $_POST)) {
                continue;
            }
            finance_save_setting($pdo, $name, trim((string) $_POST[$name]));
        }
        flash('success', 'تنظیمات درگاه «' . $gateway['label'] . '» ذخیره شد.');
    }
    if (function_exists('clearSelectCache')) {
        clearSelectCache('PaySetting');
    }
    // The persistent file cache of the table has to go as well, otherwise the
    // bot keeps the old value for up to five minutes.
    $pcDir = __DIR__ . '/../storage/cache';
    foreach ((array) glob($pcDir . '/pc_PaySetting_*.json') as $pcFile) {
        @unlink((string) $pcFile);
    }
    header('Location: finance.php');
    exit;
}

// The colour of every gateway: the one that was picked in the panel, otherwise
// the default of the gateway.
$colors = nexora_gateway_color_map();

$pageTitle = '💰 مالی و درگاه‌های پرداخت';
$pageLede = 'همه درگاه‌های پرداخت را اینجا مدیریت کنید: روشن و خاموش کردن، انتخاب رنگ دکمه پرداخت و تنظیم کلیدهای هر درگاه.';
$activeNav = 'finance';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="page-header">
    <h1>💰 مالی و درگاه‌های پرداخت</h1>
    <p class="page-lede"><?= $pageLede ?></p>
</div>

<?php
$enabled = 0;
foreach ($gateways as $g) {
    if (nexora_pay_switch_is_on(getPaySettingValue($g['setting'], $g['off']), $g['on'])) {
        $enabled++;
    }
}
?>
<div class="stats" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="stat success">
        <div class="stat-label">درگاه‌های فعال</div>
        <div class="stat-num"><?= $enabled ?><small> از <?= count($gateways) ?></small></div>
        <div class="stat-meta">در صفحه پرداخت مشتری دیده می‌شوند</div>
    </div>
    <div class="stat">
        <div class="stat-label">کل درگاه‌ها</div>
        <div class="stat-num"><?= count($gateways) ?></div>
        <div class="stat-meta">همه با روشن و خاموش شدن</div>
    </div>
    <div class="stat warn">
        <div class="stat-label">تراکنش‌های پرداخت</div>
        <div class="stat-num">
            <?php
            try {
                echo number_format((int) db_query($pdo, "SELECT COUNT(*) FROM Payment_report")->fetchColumn());
            } catch (Exception $e) {
                echo '—';
            }
            ?>
        </div>
        <div class="stat-meta"><a href="payment.php" class="btn-link">مشاهده تراکنش‌ها</a></div>
    </div>
</div>

<?php $i = 0; foreach ($gateways as $key => $gateway):
    $style = (string) ($colors[$key] ?? ($gateway['style'] ?? ''));
    $hex = $palette[$style]['hex'] ?? '#888888';
    $label = $palette[$style]['label'] ?? $style;
    $native = nexora_btn_style($style);
    $isOn = nexora_pay_switch_is_on(getPaySettingValue($gateway['setting'], $gateway['off']), $gateway['on']);
    $i++;
?>
<div class="card fade-up d<?= min($i, 4) ?>" style="margin-bottom:16px;border-right:3px solid <?= $hex ?>">
    <div class="card-head">
        <div>
            <div class="card-title">
                <span style="display:inline-block;background:<?= $hex ?>;color:#fff;border-radius:8px;padding:3px 12px;font-size:.8rem;margin-left:8px"><?= $gateway['icon'] ?> <?= htmlspecialchars($gateway['label']) ?></span>
                <?= $isOn ? '<span class="tag tag-ok">فعال</span>' : '<span class="tag tag-plain">غیرفعال</span>' ?>
            </div>
            <div class="card-subtitle">رنگ دکمه پرداخت: <strong style="color:<?= $hex ?>"><?= htmlspecialchars($label) ?></strong> — همین رنگ روی دکمه پرداخت در ربات اعمال می‌شود.</div>
        </div>
        <form method="POST" style="display:inline">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="gateway" value="<?= htmlspecialchars($key) ?>">
            <button type="submit" class="btn <?= $isOn ? 'btn-no' : 'btn-primary' ?> btn-sm"><?= $isOn ? '⛔️ خاموش کردن' : '✅ روشن کردن' ?></button>
        </form>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="gateway" value="<?= htmlspecialchars($key) ?>">
            <div class="form-grid">
                <label class="field">
                    <span>وضعیت</span>
                    <select name="gw_status" class="select">
                        <option value="on" <?= $isOn ? 'selected' : '' ?>>فعال</option>
                        <option value="off" <?= $isOn ? '' : 'selected' ?>>غیرفعال</option>
                    </select>
                </label>
                <label class="field">
                    <span>رنگ دکمه پرداخت</span>
                    <select name="gw_style" class="select">
                        <?php foreach ($palette as $slug => $info): ?>
                            <option value="<?= htmlspecialchars($slug) ?>" <?= $style === (string) $slug ? 'selected' : '' ?>>
                                <?= htmlspecialchars($slug === '' ? 'پیش‌فرض' : ($info['label'] ?? $slug)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="display:block;color:var(--mute);font-size:.72rem;margin-top:5px">پیش‌فرض این درگاه: <?= htmlspecialchars($palette[$gateway['style']]['label'] ?? $gateway['style']) ?></small>
                </label>
                <?php foreach ($gateway['fields'] as $field):
                    $value = (string) getPaySettingValue($field['name'], '');
                    $isPaymentKey = stripos((string) $field['label'], 'کلید') !== false;
                ?>
                    <label class="field" style="<?= $isPaymentKey ? 'border-right:2px solid ' . $hex . ';border-radius:8px;padding-right:10px' : '' ?>">
                        <span><?= htmlspecialchars($field['label']) ?></span>
                        <input class="input" type="text" name="<?= htmlspecialchars($field['name']) ?>" dir="ltr"
                            value="<?= htmlspecialchars($value) ?>"
                            <?= $isPaymentKey ? 'style="border-color:' . $hex . '"' : '' ?>>
                    </label>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:14px">
                <button type="submit" class="btn btn-primary">💾 ذخیره تنظیمات <?= htmlspecialchars($gateway['label']) ?></button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<div class="card fade-up">
    <div class="card-head">
        <div><div class="card-title">📊 تراکنش‌های پرداخت</div></div>
    </div>
    <div class="card-body">
        <p>مشاهدهٔ تمام تراکنش‌های پرداخت در بخش پرداخت‌ها.</p>
        <a href="payment.php" class="btn btn-ghost">💳 مشاهده تراکنش‌ها</a>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>
