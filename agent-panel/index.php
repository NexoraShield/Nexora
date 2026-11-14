<?php
require_once __DIR__ . '/inc/auth.php';
// The panel needs the helpers of the bot: the account of a product is made
// exactly like a purchase of the bot.
require_once dirname(__DIR__) . '/function.php';
require_once dirname(__DIR__) . '/panels.php';
require_once dirname(__DIR__) . '/botapi.php';
require_once __DIR__ . '/inc/shop.php';
$ManagePanel = new ManagePanel();
$setting = select("setting", "*", null, null, "select");
$me = nexora_agent_require_login();
$myId = (string) $me['id'];
// The helpers of the bot work with the id of the user: the panel speaks the
// language of the agent.
$from_id = $myId;
$textbotlang = languagechange();

$views = ['dash', 'reports', 'users', 'shop', 'ops', 'api', 'settings'];
$view = isset($_GET['view']) ? (string) $_GET['view'] : 'dash';
if (!in_array($view, $views, true)) {
    $view = 'dash';
}

$themes = ['teal' => 'فیروزه روز', 'blue' => 'دریا آبی', 'purple' => 'بنفش رویا', 'green' => 'زمرد سبز', 'orange' => 'غروب گرم'];
$theme = isset($_COOKIE['nx_agent_theme']) && isset($themes[$_COOKIE['nx_agent_theme']]) ? $_COOKIE['nx_agent_theme'] : 'teal';
$dark = isset($_COOKIE['nx_agent_dark']) && $_COOKIE['nx_agent_dark'] === '1';

if (isset($_GET['theme']) && isset($themes[$_GET['theme']])) {
    setcookie('nx_agent_theme', (string) $_GET['theme'], time() + 31536000, '/');
    header('Location: index.php?view=settings');
    exit;
}
if (isset($_GET['dark'])) {
    setcookie('nx_agent_dark', $_GET['dark'] === '1' ? '1' : '0', time() + 31536000, '/');
    header('Location: index.php?view=settings');
    exit;
}

// ---------------------------------------------------------------- data
$credit = (float) ($me['Balance'] ?? 0);
// The prices of the ready products are 10% below the prices of the bot.
// When the owner gives this agent a special discount, that one is used.
$percent = 10.0;
$agentDiscount = (float) ($me['pricediscount'] ?? 0);
if ($agentDiscount > 0) {
    $percent = $agentDiscount;
}

$subs = [];
try {
    $stmt = $pdo->prepare('SELECT id, username, Balance, register, agent FROM `user` WHERE affiliates = ? ORDER BY id DESC LIMIT 500');
    $stmt->execute([$myId]);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $subs = [];
}
$subIds = array_map('strval', array_column($subs, 'id'));

$services = [];
$revenue = 0.0;
$activeCount = 0;
if ($subIds) {
    $ph = implode(',', array_fill(0, count($subIds), '?'));
    try {
        $stmt = $pdo->prepare("SELECT id_invoice, id_user, username, Service_location, name_product, price_product, Volume, Service_time, Status, time_sell, note FROM invoice WHERE id_user IN ($ph) ORDER BY time_sell DESC LIMIT 1000");
        $stmt->execute($subIds);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $services = [];
    }
    foreach ($services as $svc) {
        if (in_array((string) $svc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
            $activeCount++;
            $revenue += (float) $svc['price_product'];
        }
    }
}

$volumeGb = 0.0;
foreach ($services as $svc) {
    if (in_array((string) $svc['Status'], ['active', 'end_of_time', 'end_of_volume', 'sendedwarn', 'send_on_hold'], true)) {
        $volumeGb += (float) $svc['Volume'];
    }
}

// the products the agent can sell (only the products of the bot itself)
$products = nx_agent_products($pdo);
$agentPrice = function ($price) use ($percent) {
    return max(0, (int) round(((float) $price) * (100 - $percent) / 100));
};

// The categories of the products: the shop of the agent is grouped the same
// way the site of Nexora groups its services.
$categories = [];
foreach ($products as $pr) {
    $cat = trim((string) ($pr['category'] ?? ''));
    if ($cat === '') {
        continue;
    }
    if (!in_array($cat, $categories, true)) {
        $categories[] = $cat;
    }
}
natcasesort($categories);
$categories = array_values($categories);
$activeCategory = trim((string) ($_GET['cat'] ?? ''));
if ($activeCategory !== '' && !in_array($activeCategory, $categories, true)) {
    $activeCategory = '';
}
$visibleProducts = $products;
if ($activeCategory !== '') {
    $visibleProducts = array_values(array_filter($products, function ($pr) use ($activeCategory) {
        return trim((string) ($pr['category'] ?? '')) === $activeCategory;
    }));
}

// A purchase of the agent: the wallet of the agent pays for the service of one
// of his customers. The agent writes the name of his customer, never a
// telegram id: a random suffix is added to the name of the account.
$shopFlash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'buy') {
    if (!nx_agent_csrf_check()) {
        $shopFlash = ['type' => 'error', 'msg' => 'درخواست نامعتبر بود. دوباره تلاش کنید.', 'detail' => []];
    } else {
        $buyResult = nx_agent_buy_product(
            $me,
            (int) ($_POST['product_id'] ?? 0),
            trim((string) ($_POST['username'] ?? '')),
            $percent
        );
        $shopFlash = ['type' => $buyResult['ok'] ? 'success' : 'error', 'msg' => $buyResult['msg'], 'detail' => $buyResult['detail'] ?? []];
        if ($buyResult['ok']) {
            $credit = max(0, (float) $credit - (float) ($buyResult['detail']['price'] ?? 0));
        }
    }
}

// the activity of the last 14 days
$chart = [];
$maxChart = 1;
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y/m/d', strtotime("-$i days"));
    $chart[$day] = 0;
}
foreach ($services as $svc) {
    $day = substr((string) $svc['time_sell'], 0, 10);
    if (isset($chart[$day])) {
        $chart[$day] += (float) $svc['price_product'];
    }
}
foreach ($chart as $v) {
    if ($v > $maxChart) $maxChart = $v;
}

// the api token of the agent (stable, derived)
$apiToken = 'nx_' . substr(hash_hmac('sha256', 'agent-api:' . $myId, (string) $APIKEY), 0, 40);

$navItems = [
    'dash' => ['📊', 'داشبورد'],
    'reports' => ['📈', 'گزارش‌ها و آمار'],
    'users' => ['👥', 'مدیریت کاربران'],
    'shop' => ['📦', 'محصولات آماده'],
    'ops' => ['🧾', 'لاگ عملیات'],
    'api' => ['🔌', 'مستندات API'],
    'settings' => ['⚙️', 'تنظیمات'],
];
function nx_num($v)
{
    return number_format((float) $v);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>پنل نمایندگی | نکسورا</title>
<link rel="icon" href="assets/nexora-logo-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800;900&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Estedad:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="reseller-panel.css?v=10">
<link rel="stylesheet" href="beauty.css?v=12">
<link rel="stylesheet" href="mobile.css?v=13">
</head>
<body data-theme="<?= htmlspecialchars($theme) ?>"<?= $dark ? ' data-dark="1"' : '' ?>>
<div class="rs-app" id="rs-app">
  <aside class="rs-side">
    <div class="rs-brand"><img src="assets/nexora-logo-icon.png" alt=""><div><b id="rs-name"><?= htmlspecialchars((string) ($me['username'] ?? $myId)) ?></b><small>پنل نمایندگی</small></div></div>
    <?php foreach ($navItems as $key => [$icon, $label]): ?>
      <a href="index.php?view=<?= htmlspecialchars($key) ?>" class="rs-nav <?= $view === $key ? 'active' : '' ?>">
        <span class="ico"><?= $icon ?></span><?= htmlspecialchars($label) ?>
      </a>
    <?php endforeach; ?>
    <div class="sp"></div>
    <a href="logout.php" class="rs-nav" id="rs-logout"><span class="ico">⏻</span>خروج از حساب</a>
  </aside>
  <main class="rs-main">

    <?php if ($view === 'dash'): ?>
    <section id="v-dash">
      <div class="rs-head"><div><h1>داشبورد</h1><p>خلاصه وضعیت پنل</p></div><div class="grow"></div></div>
      <div class="rs-stats">
        <div class="stat"><span class="ic">💰</span><div><small>موجودی کیف پول</small><b><?= nx_num($credit) ?></b><div class="cap">اعتبار قابل استفاده (تومان)</div></div></div>
        <div class="stat"><span class="ic">👥</span><div><small>کاربران</small><b><?= count($subs) ?></b><div class="cap">بدون سقف کاربر</div></div></div>
        <div class="stat"><span class="ic">📦</span><div><small>حجم ساخته‌شده</small><b><?= nx_num($volumeGb) ?></b><div class="cap">مجموع سرویس‌ها (GB)</div></div></div>
      </div>
      <div class="card"><div class="rs-head"><div><h2>📈 نمودار فعالیت</h2><p class="sub">فروش ۱۴ روز اخیر</p></div><div class="grow"></div></div>
        <div class="bars">
          <?php foreach ($chart as $day => $value): $h = $maxChart > 0 ? (int) round($value / $maxChart * 100) : 0; ?>
            <div class="bar"><i style="height:<?= max(3, $h) ?>%"></i><span><?= htmlspecialchars(substr($day, 5)) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="grid-2">
        <div class="card"><h2>🧾 آخرین سرویس‌ها</h2><p class="sub">۱۰ سرویس آخر کاربران شما</p>
          <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>کاربر</th><th>محصول</th><th>مبلغ</th><th>وضعیت</th></tr></thead><tbody>
          <?php foreach (array_slice($services, 0, 10) as $svc): ?>
            <tr><td class="mono"><?= htmlspecialchars((string) $svc['username']) ?></td><td><?= htmlspecialchars((string) $svc['name_product']) ?></td><td class="mono"><?= nx_num($svc['price_product']) ?></td>
            <td><span class="pill <?= in_array((string) $svc['Status'], ['active', 'end_of_time', 'end_of_volume'], true) ? 'p-green' : 'p-gray' ?>"><?= htmlspecialchars((string) $svc['Status']) ?></span></td></tr>
          <?php endforeach; ?>
          <?php if (!$services): ?><tr><td colspan="4" style="color:var(--muted)">هنوز سرویسی ثبت نشده است.</td></tr><?php endif; ?>
          </tbody></table></div>
        </div>
        <div class="card"><h2>ℹ️ اطلاعات پنل</h2>
          <div class="kv"><span>شناسه نماینده</span><b class="mono"><?= htmlspecialchars($myId) ?></b></div>
          <div class="kv"><span>نام کاربری</span><b><?= htmlspecialchars((string) ($me['username'] ?? '-')) ?></b></div>
          <div class="kv"><span>پورسنت فروش</span><b><?= nx_num($percent) ?>٪</b></div>
          <div class="kv"><span>تعداد کاربران</span><b><?= count($subs) ?></b></div>
          <div class="kv"><span>سرویس‌های فعال</span><b><?= $activeCount ?></b></div>
        </div>
      </div>
    </section>

    <?php elseif ($view === 'reports'): ?>
    <section id="v-reports">
      <div class="rs-head"><div><h1>گزارش‌ها و آمار</h1><p>روند فروش و درآمد پنل</p></div><div class="grow"></div></div>
      <div class="kpi-grid">
        <div class="kpi"><small>درآمد کل</small><b><?= nx_num($revenue) ?></b><span class="gr up">تومان</span></div>
        <div class="kpi"><small>تعداد سفارش</small><b><?= count($services) ?></b><span class="gr down">سرویس</span></div>
        <div class="kpi"><small>میانگین هر سفارش</small><b><?= nx_num(count($services) ? $revenue / count($services) : 0) ?></b><span class="gr up">تومان</span></div>
        <div class="kpi"><small>سرویس فعال</small><b><?= $activeCount ?></b><span class="gr down">سرویس</span></div>
      </div>
      <div class="grid-2">
        <div class="card"><h2>📈 فروش بر اساس روز</h2><p class="sub">۱۴ روز اخیر</p>
          <div class="bars">
            <?php foreach ($chart as $day => $value): $h = $maxChart > 0 ? (int) round($value / $maxChart * 100) : 0; ?>
              <div class="bar"><i style="height:<?= max(3, $h) ?>%"></i><span><?= htmlspecialchars(substr($day, 5)) ?></span></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card"><h2>🏆 پرفروش‌ترین محصول‌ها</h2><p class="sub">بر اساس تعداد سرویس</p>
          <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>محصول</th><th>تعداد</th><th>درآمد</th></tr></thead><tbody>
          <?php
          $byProduct = [];
          foreach ($services as $svc) {
              $key = (string) $svc['name_product'];
              if (!isset($byProduct[$key])) $byProduct[$key] = ['count' => 0, 'revenue' => 0.0];
              $byProduct[$key]['count']++;
              $byProduct[$key]['revenue'] += (float) $svc['price_product'];
          }
          arsort($byProduct);
          $i = 0;
          foreach ($byProduct as $name => $data):
              if ($i++ >= 8) break; ?>
            <tr><td><?= htmlspecialchars($name) ?></td><td class="mono"><?= (int) $data['count'] ?></td><td class="mono"><?= nx_num($data['revenue']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$byProduct): ?><tr><td colspan="3" style="color:var(--muted)">داده‌ای برای نمایش نیست.</td></tr><?php endif; ?>
          </tbody></table></div>
        </div>
      </div>
      <div class="card"><h2>📍 فروش به تفکیک لوکیشن</h2>
        <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>لوکیشن</th><th>تعداد</th><th>درآمد</th></tr></thead><tbody>
        <?php
        $byLocation = [];
        foreach ($services as $svc) {
            $key = (string) $svc['Service_location'];
            if (!isset($byLocation[$key])) $byLocation[$key] = ['count' => 0, 'revenue' => 0.0];
            $byLocation[$key]['count']++;
            $byLocation[$key]['revenue'] += (float) $svc['price_product'];
        }
        arsort($byLocation);
        foreach ($byLocation as $name => $data): ?>
          <tr><td><?= htmlspecialchars($name) ?></td><td class="mono"><?= (int) $data['count'] ?></td><td class="mono"><?= nx_num($data['revenue']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$byLocation): ?><tr><td colspan="3" style="color:var(--muted)">داده‌ای برای نمایش نیست.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
    </section>

    <?php elseif ($view === 'users'): ?>
    <section id="v-users">
      <div class="rs-head"><div><h1>مدیریت کاربران</h1><p>کاربرانی که با پنل شما سرویس گرفته‌اند</p></div><div class="grow"></div></div>
      <div class="card">
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>نام کاربری</th><th>سرویس‌ها</th><th>موجودی</th><th>تعداد سرویس</th><th>ثبت</th></tr></thead><tbody>
        <?php
        $subServiceCount = [];
        $subServiceNames = [];
        foreach ($services as $svc) {
            $uid = (string) $svc['id_user'];
            $subServiceCount[$uid] = ($subServiceCount[$uid] ?? 0) + 1;
            $subServiceNames[$uid][] = (string) $svc['username'];
        }
        foreach ($subs as $sub):
            $subId = (string) $sub['id'];
            $subNames = array_values(array_unique(array_filter($subServiceNames[$subId] ?? []))); ?>
          <tr>
            <td>
              <b><?= htmlspecialchars((string) ($sub['username'] ?? '')) ?></b>
              <div style="font-size:.7rem;color:var(--mute)" dir="ltr"><?= htmlspecialchars($subId) ?></div>
            </td>
            <td class="mono">
              <?php if ($subNames): ?>
                <?php foreach ($subNames as $subName): ?>
                  <span class="nx-chip"><?= htmlspecialchars($subName) ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span style="color:var(--muted)">—</span>
              <?php endif; ?>
            </td>
            <td class="mono"><?= nx_num($sub['Balance'] ?? 0) ?></td>
            <td class="mono"><?= (int) ($subServiceCount[$subId] ?? 0) ?></td>
            <td class="mono"><?= htmlspecialchars((string) ($sub['register'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$subs): ?><tr><td colspan="5" style="color:var(--muted)">هنوز کاربری با پنل شما ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
    </section>

    <?php elseif ($view === 'shop'): ?>
    <section id="v-shop">
      <div class="rs-head"><div><h1>📦 محصولات آماده</h1><p>بسته‌های آماده با تخفیف نماینده — قیمت‌ها برای شما <?= nx_num($percent) ?>٪ تخفیف دارند</p></div><div class="grow"></div></div>

      <?php if ($shopFlash): ?>
        <div class="nx-flash nx-flash-<?= htmlspecialchars((string) $shopFlash['type']) ?>">
          <b><?= $shopFlash['type'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars((string) $shopFlash['msg']) ?></b>
          <?php if (!empty($shopFlash['detail']['username'])): ?>
            <?php
            $flashLink = (string) ($shopFlash['detail']['link'] ?? '');
            if ($flashLink === '') {
                $flashLink = (string) ($shopFlash['detail']['subscription_url'] ?? '');
            }
            $flashConfigs = [];
            foreach ((array) ($shopFlash['detail']['configs'] ?? []) as $flashConfig) {
                $flashConfig = trim((string) $flashConfig);
                if ($flashConfig !== '') {
                    $flashConfigs[] = $flashConfig;
                }
            }
            ?>
            <div class="nx-flash-detail">
              <div class="kv"><span>نام کاربری سرویس</span><b class="mono"><?= htmlspecialchars((string) $shopFlash['detail']['username']) ?></b></div>
              <div class="kv"><span>نام مشتری</span><b><?= htmlspecialchars((string) ($shopFlash['detail']['customer'] ?? '')) ?></b></div>
              <?php if ($flashLink !== ''): ?>
                <div class="kv"><span>لینک اتصال</span><b class="mono" style="word-break:break-all"><?= htmlspecialchars($flashLink) ?></b></div>
              <?php endif; ?>
              <?php if ($flashConfigs): ?>
                <div class="nx-flash-configs">
                  <span class="nx-flash-label">کانفیگ‌ها (کپی کنید و به مشتری بدهید)</span>
                  <?php foreach ($flashConfigs as $flashConfig): ?>
                    <code class="nx-flash-config"><?= htmlspecialchars($flashConfig) ?></code>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <div class="kv"><span>محصول</span><b><?= htmlspecialchars((string) ($shopFlash['detail']['product'] ?? '')) ?></b></div>
              <div class="kv"><span>لوکیشن</span><b><?= htmlspecialchars((string) ($shopFlash['detail']['panel'] ?? '')) ?></b></div>
              <div class="kv"><span>مبلغ پرداختی</span><b class="mono"><?= nx_num((float) ($shopFlash['detail']['price'] ?? 0)) ?> تومان</b></div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!$products): ?>
        <div class="card"><p class="sub">محصولی برای فروش تعریف نشده است.</p></div>
      <?php else: ?>
        <div class="shop-cats">
          <a class="shop-cat <?= $activeCategory === '' ? 'active' : '' ?>" href="index.php?view=shop">✨ همه</a>
          <?php $palette = nx_agent_palette(); foreach ($categories as $catIndex => $cat): ?>
            <?php $acc = $palette[$catIndex % count($palette)]; ?>
            <a class="shop-cat <?= $activeCategory === $cat ? 'active' : '' ?>" style="--lc:<?= $acc['c'] ?>;--lcrgb:<?= $acc['rgb'] ?>;--lc2:<?= $acc['c2'] ?>" href="index.php?view=shop&amp;cat=<?= urlencode($cat) ?>"><span class="c"><?= $acc['e'] ?></span><?= htmlspecialchars($cat) ?></a>
          <?php endforeach; ?>
        </div>

        <div class="shop-grid nx-shop-grid" id="shop-list">
          <?php $palette = nx_agent_palette(); foreach ($visibleProducts as $index => $pr): ?>
            <?php
            $cat = trim((string) ($pr['category'] ?? ''));
            $catIndex = $cat !== '' ? array_search($cat, $categories, true) : 0;
            $acc = $palette[(int) $catIndex % count($palette)];
            $nameHtml = nx_agent_emoji_html((string) $pr['name_product']);
            $emoji = nx_agent_first_emoji((string) $pr['name_product']);
            $emojiImg = $emoji['id'] !== '' ? nx_agent_emoji_image($pdo, $emoji['id']) : nx_agent_emoji_image($pdo, (string) ($pr['btn_emoji_id'] ?? ''));
            $price = $agentPrice($pr['price_product']);
            $days = (int) ($pr['Service_time'] ?? 0);
            ?>
            <div class="shop-card nx-in" style="--lc:<?= $acc['c'] ?>;--lcrgb:<?= $acc['rgb'] ?>;--lc2:<?= $acc['c2'] ?>;animation-delay:<?= round($index * 0.06, 2) ?>s">
              <div class="shop-top">
                <span class="shop-emoji">
                  <?php if ($emojiImg !== ''): ?>
                    <img src="<?= htmlspecialchars($emojiImg) ?>" alt="<?= htmlspecialchars($emoji['char']) ?>" loading="lazy">
                  <?php else: ?>
                    <?= $acc['e'] ?>
                  <?php endif; ?>
                </span>
                <?php if ($percent > 0): ?><span class="off">٪<?= nx_num($percent) ?> تخفیف</span><?php endif; ?>
              </div>
              <b><?= $nameHtml ?></b>
              <div class="vol">
                📍 <?= htmlspecialchars((string) ($pr['Location'] ?? '')) ?> •
                💾 <?= htmlspecialchars(nx_agent_volume_text($pr['Volume_constraint'] ?? 0)) ?> •
                ⏳ <?= $days > 0 ? htmlspecialchars($days . ' روز') : 'نامحدود' ?>
              </div>
              <div class="pr">
                <?php if ($percent > 0): ?><s><?= nx_num((float) $pr['price_product']) ?> تومان</s><?php endif; ?>
                <b><?= nx_num($price) ?> تومان</b>
              </div>
              <button class="rs-btn lux" type="button"
                onclick="nxOpenBuy(<?= htmlspecialchars(json_encode([
                  'id' => (int) $pr['id'],
                  'name' => nx_agent_plain_name((string) $pr['name_product']),
                  'price' => $price,
                  'panel' => (string) ($pr['Location'] ?? ''),
                ], JSON_UNESCAPED_UNICODE)) ?>)">🛒 خرید</button>
            </div>
          <?php endforeach; ?>
          <?php if (!$visibleProducts): ?>
            <div class="card"><p class="sub">محصولی در این دسته‌بندی نیست.</p></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($products): ?>
      <div class="modal-bk" id="nx-buy-modal" hidden>
        <div class="modal" onclick="event.stopPropagation()">
          <h2>🛒 خرید محصول</h2>
          <p class="sub" id="nx-buy-title"></p>
          <form method="POST" action="index.php?view=shop">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(nx_agent_csrf_token()) ?>">
            <input type="hidden" name="action" value="buy">
            <input type="hidden" name="product_id" id="nx-buy-id" value="">
            <label><span>نام کاربری مشتری</span><input type="text" name="username" id="nx-buy-username" dir="ltr" placeholder="مثلاً ali" autocomplete="off" spellcheck="false" required></label>
            <small class="nx-hint">یک بخش تصادفی خودکار به نام اضافه می‌شود (مثلاً <span class="mono">ali_a1b2c3</span>) تا نام تکراری نشود. همین نام در «مدیریت کاربران» ذخیره می‌شود.</small>
            <div class="kv"><span>قیمت شما</span><b id="nx-buy-price">—</b></div>
            <div class="modal-foot">
              <button class="rs-btn lux" type="submit">تأیید و خرید</button>
              <button class="rs-btn ghost" type="button" onclick="nxCloseBuy()">انصراف</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </section>

    <?php elseif ($view === 'ops'): ?>
    <section id="v-ops">
      <div class="rs-head"><div><h1>لاگ عملیات</h1><p>سرویس‌های ثبت‌شده کاربران شما</p></div><div class="grow"></div></div>
      <div class="rs-stats">
        <div class="stat"><span class="ic">🧾</span><div><small>تعداد عملیات</small><b><?= count($services) ?></b></div></div>
        <div class="stat"><span class="ic">💸</span><div><small>مجموع فروش</small><b><?= nx_num($revenue) ?></b></div></div>
        <div class="stat"><span class="ic">💰</span><div><small>موجودی فعلی</small><b><?= nx_num($credit) ?></b></div></div>
      </div>
      <div class="card">
        <div style="overflow-x:auto"><table class="tbl"><thead><tr><th>کاربر</th><th>محصول</th><th>لوکیشن</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th></tr></thead><tbody>
        <?php foreach (array_slice($services, 0, 200) as $svc): ?>
          <tr>
            <td class="mono"><?= htmlspecialchars((string) $svc['username']) ?></td>
            <td><?= htmlspecialchars((string) $svc['name_product']) ?></td>
            <td><?= htmlspecialchars((string) $svc['Service_location']) ?></td>
            <td class="mono"><?= nx_num($svc['price_product']) ?></td>
            <td><span class="pill <?= in_array((string) $svc['Status'], ['active', 'end_of_time', 'end_of_volume'], true) ? 'p-green' : 'p-gray' ?>"><?= htmlspecialchars((string) $svc['Status']) ?></span></td>
            <td class="mono"><?= htmlspecialchars((string) $svc['time_sell']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$services): ?><tr><td colspan="6" style="color:var(--muted)">رویدادی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
    </section>

    <?php elseif ($view === 'api'): ?>
    <section id="v-api">
      <div class="rs-head"><div><h1>مستندات API</h1><p>اتصال ربات یا سایت خودت به پنل نمایندگی.</p></div></div>
      <div class="card"><h2>توکن شخصی</h2><p class="sub">با این توکن از طرف پنلت درخواست بفرست. آن را محرمانه نگه دار.</p>
        <div class="filters"><input id="api-token" type="text" readonly dir="ltr" value="<?= htmlspecialchars($apiToken) ?>"></div>
      </div>
      <div class="card"><h2>ساخت کاربر</h2><pre class="api">POST <?= htmlspecialchars((string) ($domainhosts ?? '')) ?>/api/user.php
Token: <?= htmlspecialchars($apiToken) ?>

{
  "action": "create",
  "token": "<?= htmlspecialchars($apiToken) ?>",
  "username": "agent_user",
  "location": "<?= htmlspecialchars((string) ($products[0]['Location'] ?? 'PANEL')) ?>",
  "volume": 20,
  "days": 30
}</pre></div>
      <div class="card"><h2>لیست کاربران</h2><pre class="api">POST <?= htmlspecialchars((string) ($domainhosts ?? '')) ?>/api/user.php
{ "action": "list", "token": "<?= htmlspecialchars($apiToken) ?>" }</pre></div>
    </section>

    <?php elseif ($view === 'settings'): ?>
    <section id="v-settings">
      <div class="rs-head"><div><h1>تنظیمات</h1><p>رنگ‌بندی و اطلاعات پنل نمایندگی.</p></div></div>
      <div class="card"><h2>🎨 رنگ‌بندی پنل</h2><p class="sub">یک ظاهر را انتخاب کن؛ تغییرات بلافاصله اعمال می‌شود.</p>
        <div class="swatches" id="th-grid">
          <?php foreach ($themes as $key => $label): ?>
            <a class="sw <?= $theme === $key ? 'sel' : '' ?>" href="index.php?view=settings&theme=<?= htmlspecialchars($key) ?>">
              <span style="background:<?= ['teal' => '#0e9488', 'blue' => '#1d7ff2', 'purple' => '#7c3aed', 'green' => '#16a34a', 'orange' => '#ea7c28'][$key] ?>"></span>
              <?= htmlspecialchars($label) ?>
            </a>
          <?php endforeach; ?>
        </div>
        <label class="toggle"><input type="checkbox" id="s-dark" <?= $dark ? 'checked' : '' ?> onclick="location.href='index.php?view=settings&dark=<?= $dark ? '0' : '1' ?>'"> حالت تیره</label>
      </div>
      <div class="card"><h2>ℹ️ اطلاعات پنل</h2>
        <div class="kv"><span>شناسه نماینده</span><b class="mono"><?= htmlspecialchars($myId) ?></b></div>
        <div class="kv"><span>موجودی کیف پول</span><b><?= nx_num($credit) ?> تومان</b></div>
        <div class="kv"><span>پورسنت فروش</span><b><?= nx_num($percent) ?>٪</b></div>
        <div class="kv"><span>تاریخ عضویت</span><b class="mono"><?= htmlspecialchars((string) ($me['register'] ?? '')) ?></b></div>
      </div>
    </section>
    <?php endif; ?>

  </main>
</div>
<script>
  // The order of the agent: a product is chosen, the name of the customer is
  // written down and the wallet of the agent pays for the service. A random
  // suffix is added to the name of the customer by the server.
  function nxOpenBuy(p) {
    var modal = document.getElementById('nx-buy-modal');
    if (!modal) return;
    document.getElementById('nx-buy-id').value = p.id;
    document.getElementById('nx-buy-title').textContent = p.name + ' — ' + (p.panel || '');
    document.getElementById('nx-buy-price').textContent = new Intl.NumberFormat('fa-IR').format(p.price) + ' تومان';
    var user = document.getElementById('nx-buy-username');
    if (user) { user.value = ''; user.focus(); }
    modal.hidden = false;
  }
  function nxCloseBuy() {
    var modal = document.getElementById('nx-buy-modal');
    if (modal) modal.hidden = true;
  }
  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('nx-buy-modal');
    if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) nxCloseBuy(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') nxCloseBuy(); });
  });
</script>
<script src="beauty.js?v=7" defer></script>
<script src="mobile.js?v=11" defer></script>
</body>
</html>
