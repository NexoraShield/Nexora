<?php
// ---------------------------------------------------------------------------
// The payment page of the web application.
//
// The customer buys something in the web application and the wallet of his
// account is not enough: he comes here, picks one of the gateways of the bot
// and pays. The money is collected by the bot itself, so the order is the
// normal order of the bot.
//
// The page is rendered by the server, so it works without the telegram web
// app and it looks the same everywhere.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../gateways.php';
$textbotlang = languagechange();
date_default_timezone_set('Asia/Tehran');

$invoiceId = trim((string) ($_GET['invoice'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$order = null;
$user = null;
$error = '';
if ($invoiceId === '' || $token === '') {
    $error = 'لینک پرداخت ناقص است. لطفاً دوباره از داخل ربات یا برنامه تلگرام تلاش کنید.';
} else {
    try {
        $st = $pdo->prepare("SELECT * FROM invoice WHERE id_invoice = ? AND id_user = (SELECT id FROM `user` WHERE token = ? LIMIT 1) LIMIT 1");
        $st->execute([$invoiceId, $token]);
        $order = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$order) {
            $error = 'این فاکتور پیدا نشد.';
        } elseif ((string) $order['Status'] !== 'unpaid') {
            $error = 'این فاکتور قبلاً پرداخت شده است یا منقضی شده است.';
        } else {
            $ust = $pdo->prepare("SELECT * FROM `user` WHERE token = ? LIMIT 1");
            $ust->execute([$token]);
            $user = $ust->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$user) {
                $error = 'نشست شما پیدا نشد. لطفاً دوباره وارد شوید.';
            }
        }
    } catch (Throwable $e) {
        error_log('app/pay.php: ' . $e->getMessage());
        $error = 'خطایی رخ داد. لطفاً دوباره تلاش کنید.';
    }
}

$amount = (float) ($order['payable_amount'] ?? 0);
if ($amount <= 0 && $order) {
    $amount = (float) ($order['price_product'] ?? 0);
}
$gateways = [];
if ($order && $user) {
    foreach (nexora_available_gateways(['cardpayment' => $user['cardpayment'] ?? 1]) as $gateway) {
        $gateways[] = $gateway;
    }
}
if ($order && $user && !$gateways) {
    $error = 'در حال حاضر هیچ درگاه پرداختی فعال نیست. لطفاً با پشتیبانی ربات در ارتباط باشید.';
}

$botUsername = (string) ($usernamebot ?? '');
$productName = (string) ($order['name_product'] ?? '');
$productName = trim(preg_replace('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', '$1', $productName) ?? $productName);
$panelName = (string) ($order['Service_location'] ?? '');
$panelName = trim(preg_replace('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', '$1', $panelName) ?? $panelName);
$palette = function_exists('nexora_color_palette') ? nexora_color_palette() : [];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0d1120">
<title>پرداخت سفارش | نکسورا</title>
<link rel="icon" href="/panel/assets/favicon.ico">
<style>
  :root{--gold:#f0c465;--gold2:#cf9226;--text:#eef2ff;--mute:#a9b2cf;--line:rgba(255,255,255,.09)}
  *{box-sizing:border-box}
  html,body{margin:0;padding:0;min-height:100vh;color:var(--text);
    font-family:Vazir,ui-sans-serif,system-ui,Tahoma,sans-serif;
    background-image:
      radial-gradient(900px 520px at 10% -10%, rgba(124,92,255,.30), transparent 60%),
      radial-gradient(760px 480px at 92% 2%, rgba(240,196,101,.16), transparent 58%),
      linear-gradient(180deg,#121731,#0b0f1c);
    background-attachment:fixed}
  body::before{content:"";position:fixed;inset:0;pointer-events:none;
    background:linear-gradient(115deg,transparent 32%,rgba(240,196,101,.06) 48%,transparent 64%);
    background-size:250% 250%;animation:shine 13s ease-in-out infinite;z-index:0}
  @keyframes shine{0%,100%{background-position:120% 0}50%{background-position:-20% 0}}
  .wrap{position:relative;z-index:1;max-width:520px;margin:0 auto;padding:26px 16px 44px}
  .card{background:rgba(23,28,50,.72);backdrop-filter:blur(16px) saturate(150%);
    -webkit-backdrop-filter:blur(16px) saturate(150%);border:1px solid var(--line);border-radius:22px;
    padding:22px 18px;margin-bottom:16px;
    box-shadow:0 22px 50px -26px rgba(0,0,0,.9), inset 0 1px 0 rgba(255,255,255,.07)}
  h1{margin:0 0 6px;font-size:20px;letter-spacing:-.01em;text-shadow:0 2px 16px rgba(0,0,0,.5)}
  .sub{margin:0 0 18px;color:var(--mute);font-size:13px;line-height:1.9}
  .row{display:flex;justify-content:space-between;gap:12px;padding:11px 0;border-bottom:1px dashed rgba(255,255,255,.07);font-size:13.5px}
  .row:last-child{border-bottom:0}
  .row span{color:var(--mute)}
  .row b{font-weight:700;text-align:left}
  .amount{font-size:26px;font-weight:800;background:linear-gradient(135deg,#f7d17a,#cf9226);
    -webkit-background-clip:text;background-clip:text;color:transparent;text-align:center;margin:6px 0 2px}
  .gw{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;
    padding:14px 16px;margin-top:10px;border-radius:16px;cursor:pointer;text-decoration:none;
    background:rgba(255,255,255,.045);border:1px solid var(--line);color:var(--text);font-size:14px;font-weight:600;
    transition:transform .16s ease,box-shadow .22s ease,background-color .22s ease}
  .gw:hover{transform:translateY(-2px);background:rgba(255,255,255,.08);
    box-shadow:0 14px 30px -16px rgba(0,0,0,.9),0 0 0 1px rgba(240,196,101,.28)}
  .gw:active{transform:scale(.985)}
  .gw .ico{width:40px;height:40px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:19px;flex:0 0 auto}
  .gw .go{color:var(--mute);font-size:12px;font-weight:400}
  .err{border-color:rgba(255,120,120,.35);background:rgba(120,30,30,.28)}
  .err h1{color:#ffb4b4}
  .note{margin-top:14px;font-size:11.5px;color:var(--mute);line-height:2;text-align:center}
  .badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:11px;
    background:rgba(240,196,101,.14);border:1px solid rgba(240,196,101,.3);color:#f6d79b;margin-bottom:12px}
  ::selection{background:rgba(240,196,101,.42)}
</style>
</head>
<body>
<div class="wrap">
<?php if ($error !== ''): ?>
  <div class="card err">
    <h1>پرداخت انجام نشد</h1>
    <p class="sub"><?= htmlspecialchars($error) ?></p>
    <p class="note">اگر مبلغی از حساب شما کم شده است با پشتیبانی ربات در ارتباط باشید.</p>
  </div>
<?php else: ?>
  <div class="card">
    <span class="badge">🛒 فاکتور <?= htmlspecialchars((string) $order['id_invoice']) ?></span>
    <h1>پرداخت سفارش</h1>
    <p class="sub">یکی از درگاه‌های زیر را انتخاب کنید تا مبلغ را بپردازید. سفارش شما بعد از تایید پرداخت فعال می‌شود.</p>
    <div class="amount"><?= number_format((float) $amount) ?> تومان</div>
    <div class="row"><span>محصول</span><b><?= htmlspecialchars($productName) ?></b></div>
    <?php if ($panelName !== ''): ?><div class="row"><span>پنل</span><b><?= htmlspecialchars($panelName) ?></b></div><?php endif; ?>
    <div class="row"><span>شماره فاکتور</span><b><?= htmlspecialchars((string) $order['id_invoice']) ?></b></div>
  </div>
  <div class="card">
    <h1 style="font-size:16px">درگاه‌های پرداخت</h1>
    <p class="sub" style="margin-bottom:6px">مبلغ پرداختی شما</p>
    <?php foreach ($gateways as $gateway):
        $style = (string) ($gateway['style'] ?? '');
        $hex = $palette[$style]['hex'] ?? '#8b93b8';
    ?>
      <a class="gw" href="https://t.me/<?= htmlspecialchars($botUsername) ?>?start=paygw_<?= htmlspecialchars((string) $gateway['key']) ?>_<?= htmlspecialchars((string) $order['id_invoice']) ?>" target="_blank" rel="noopener">
        <span style="display:flex;align-items:center;gap:12px">
          <span class="ico" style="background:<?= htmlspecialchars($hex) ?>22;border:1px solid <?= htmlspecialchars($hex) ?>55"><?= htmlspecialchars((string) ($gateway['icon'] ?? '')) ?></span>
          <span><?= htmlspecialchars((string) $gateway['label']) ?></span>
        </span>
        <span class="go">پرداخت ←</span>
      </a>
    <?php endforeach; ?>
    <p class="note">پس از پرداخت، تایید درگاه به صورت خودکار انجام می‌شود و سرویس شما فعال می‌گردد.</p>
  </div>
<?php endif; ?>
</div>
</body>
</html>
