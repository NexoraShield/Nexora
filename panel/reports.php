<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../nexora_style.php';
require_once __DIR__ . '/../gateways.php';
require_once __DIR__ . '/inc/reports_stats.php';
require_auth();

// The period of the page and every number of it.
$days = panel_reports_range_days();
$report = panel_reports_data($pdo, $days);
$kpi = $report['kpi'];

$money = function ($value): string {
    return number_format((float) $value);
};
$unit = 'تومان';

// The two series of the "ترکیب زمان": the money of the subscriptions and the
// money of the services that reached their end.
$timelineSeries = [
    ['key' => 'subscription', 'name' => 'اشتراک', 'color' => '#2563eb'],
    ['key' => 'expired', 'name' => 'سرویس‌های جایگاهی', 'color' => '#06b6d4'],
];
$timelineItems = [];
foreach ($report['timeline'] as $day) {
    $timelineItems[] = [
        'label' => $day['label'],
        'tip' => $day['day'],
        'subscription' => $day['subscription'],
        'expired' => $day['expired'],
    ];
}

// The trend: the revenue and the number of the orders of every day.
$trendItems = [];
foreach ($report['trend'] as $day) {
    $trendItems[] = [
        'label' => $day['label'],
        'tip' => $day['day'],
        'revenue' => $day['revenue'],
        'count' => $day['count'],
    ];
}

// The days of the week.
$weekItems = [];
foreach ($report['week'] as $day) {
    $weekItems[] = ['label' => $day['name'], 'tip' => $day['name'], 'value' => $day['count']];
}

// The hours of the day.
$hourItems = [];
foreach ($report['hours'] as $hour) {
    $hourItems[] = ['label' => $hour['label'], 'tip' => 'ساعت ' . $hour['label'], 'value' => $hour['count']];
}

// The gateways.
$gatewayItems = [];
foreach ($report['payments']['gateways'] as $gateway) {
    $gatewayItems[] = ['label' => $gateway['name'], 'tip' => $gateway['name'], 'value' => $gateway['amount']];
}

// The payments: the two donuts.
$paymentParts = [
    ['name' => 'پرداخت‌شده', 'value' => $report['payments']['paid'], 'color' => '#22c55e'],
    ['name' => 'پرداخت‌نشده', 'value' => $report['payments']['unpaid'], 'color' => '#f87171'],
];
$statusParts = [
    ['name' => 'موفق', 'value' => $report['payments']['success'], 'color' => '#22c55e'],
    ['name' => 'ناموفق', 'value' => $report['payments']['failed'], 'color' => '#f87171'],
    ['name' => 'در انتظار', 'value' => $report['payments']['pending'], 'color' => '#f59e0b'],
];

// The services: the money of every panel and the money of every type.
$locationParts = [];
$locationLegend = [];
$palette = ['#2563eb', '#06b6d4', '#22c55e', '#f59e0b', '#a855f7', '#ef4444', '#14b8a6', '#f97316'];
foreach ($report['locations'] as $index => $location) {
    $color = $palette[$index % count($palette)];
    $locationParts[] = ['name' => $location['name'], 'value' => $location['revenue'], 'color' => $color];
    $locationLegend[] = ['name' => $location['name'], 'value' => $location['revenue'], 'color' => $color];
}
$serviceParts = [];
$serviceLegend = [];
foreach ($report['services'] as $index => $service) {
    $color = $palette[($index + 3) % count($palette)];
    $serviceParts[] = ['name' => $service['name'], 'value' => $service['revenue'], 'color' => $color];
    $serviceLegend[] = ['name' => $service['name'], 'value' => $service['revenue'], 'color' => $color];
}

// The growth of the users.
$growthItems = [];
foreach ($report['growth'] as $day) {
    $growthItems[] = ['label' => $day['label'], 'tip' => $day['day'], 'new' => $day['new'], 'total' => $day['total']];
}

// The table of the best selling products.
$productRows = $report['products'];
$productTotal = 0.0;
foreach ($productRows as $row) {
    $productTotal += (float) $row['revenue'];
}

$pageTitle = 'گزارش‌ها و آمار';
$pageLede = 'درآمد، فروش، پرداخت‌ها، سرویس‌ها و رشد کاربران در یک نگاه.';
$activeNav = 'reports';
include __DIR__ . '/inc/layout_head.php';
?>

<div class="rp fade-up">

    <div class="range-tabs" id="rp-range">
        <?php foreach ([7, 14, 30, 90, 365] as $rangeDays): ?>
            <a href="reports.php?range=<?= $rangeDays ?>" class="<?= $days === $rangeDays ? 'active' : '' ?>">
                <?= $rangeDays === 365 ? 'یک سال' : ($rangeDays === 90 ? 'سه ماه' : $rangeDays . ' روز') ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ---------------------------------------------------- the cards -->
    <div class="rp-kpis">
        <div class="rp-kpi">
            <small>درآمد کل</small>
            <b><?= $money($kpi['revenue']) ?><em><?= $unit ?></em></b>
            <span class="rp-cap">مجموع فروش سرویس‌ها</span>
        </div>
        <div class="rp-kpi is-ok">
            <small>درآمد اشتراک</small>
            <b><?= $money($kpi['subscription']) ?><em><?= $unit ?></em></b>
            <span class="rp-cap">سرویس‌های در حال اجرا</span>
        </div>
        <div class="rp-kpi is-warn">
            <small>درآمد سررسیدهای جایگاهی</small>
            <b><?= $money($kpi['expired']) ?><em><?= $unit ?></em></b>
            <span class="rp-cap">سرویس‌هایی که به سررسید رسیدند</span>
        </div>
        <div class="rp-kpi">
            <small>تعداد سرویس</small>
            <b><?= number_format($kpi['services']) ?></b>
            <span class="rp-cap">همهٔ سرویس‌های فروخته‌شده</span>
        </div>
        <div class="rp-kpi">
            <small>مدت باقیمانده</small>
            <b><?= number_format($kpi['remaining_days']) ?><em>روز</em></b>
            <span class="rp-cap">مجموع زمان سرویس‌های در حال اجرا</span>
        </div>
        <div class="rp-kpi">
            <small>تعداد خرید</small>
            <b><?= number_format($kpi['purchases']) ?></b>
            <span class="rp-cap">پرداخت‌های موفق</span>
        </div>
        <div class="rp-kpi is-ok">
            <small>مبلغ کل درآمد هر خریدار</small>
            <b><?= $money($kpi['per_buyer']) ?><em><?= $unit ?></em></b>
            <span class="rp-cap">میانگین از <?= number_format($kpi['buyers']) ?> خریدار</span>
        </div>
        <div class="rp-kpi">
            <small>کل فروش دوره</small>
            <b><?= number_format(array_sum(array_column($report['timeline'], 'count'))) ?><em>سفارش</em></b>
            <span class="rp-cap">در <?= number_format($days) ?> روز گذشته</span>
        </div>
    </div>

    <!-- --------------------------------------- the four important numbers -->
    <div class="rp-highlights">
        <div class="rp-hl">
            <span class="ic">◆</span>
            <div>
                <small>پرفروش‌ترین محصول</small>
                <b>
                    <?= htmlspecialchars($report['peak']['product']['name'] !== '' ? $report['peak']['product']['name'] : '—') ?>
                    <span><?= $report['peak']['product']['count'] > 0 ? number_format($report['peak']['product']['count']) . ' فروش • ' . $money($report['peak']['product']['price']) . ' ' . $unit : 'هنوز فروشی ثبت نشده' ?></span>
                </b>
            </div>
        </div>
        <div class="rp-hl">
            <span class="ic">▦</span>
            <div>
                <small>پرفروش‌ترین روز</small>
                <b>
                    <?= htmlspecialchars($report['peak']['day']['name'] !== '' ? $report['peak']['day']['name'] : '—') ?>
                    <span><?= $report['peak']['day']['count'] > 0 ? number_format($report['peak']['day']['count']) . ' سفارش' : 'داده‌ای نیست' ?></span>
                </b>
            </div>
        </div>
        <div class="rp-hl">
            <span class="ic">◔</span>
            <div>
                <small>ساعت اوج خرید</small>
                <b>
                    <?= $report['peak']['hour']['count'] > 0 ? 'ساعت ' . str_pad((string) $report['peak']['hour']['hour'], 2, '0', STR_PAD_LEFT) : '—' ?>
                    <span><?= $report['peak']['hour']['count'] > 0 ? number_format($report['peak']['hour']['count']) . ' سفارش' : 'داده‌ای نیست' ?></span>
                </b>
            </div>
        </div>
        <div class="rp-hl">
            <span class="ic">▤</span>
            <div>
                <small>برترین درگاه</small>
                <b>
                    <?= htmlspecialchars($report['peak']['gateway']['name'] !== '' ? $report['peak']['gateway']['name'] : '—') ?>
                    <span><?= $report['peak']['gateway']['amount'] > 0 ? $money($report['peak']['gateway']['amount']) . ' ' . $unit : 'پرداختی ثبت نشده' ?></span>
                </b>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------------- the income and the sales -->
    <div class="rp-section"><h2>درآمد و فروش</h2><span class="rp-pill">روند</span></div>
    <div class="rp-cols-2">
        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">ترکیب زمان</div>
                <div class="card-subtitle">اشتراک و سرویس‌های جایگاهی در <?= number_format($days) ?> روز گذشته</div>
            </div></div>
            <div class="card-body">
                <div class="rp-chart short rp-stacked"
                     data-unit="<?= $unit ?>"
                     data-items='<?= htmlspecialchars(json_encode($timelineItems, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'
                     data-series='<?= htmlspecialchars(json_encode($timelineSeries, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
                <div class="rp-legend" style="flex-direction:row;gap:16px;margin-top:10px">
                    <?php foreach ($timelineSeries as $series): ?>
                        <div><i style="background:<?= $series['color'] ?>"></i><?= $series['name'] ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">روند فروش و درآمد</div>
                <div class="card-subtitle">خط پررنگ: درآمد • خط‌چین: میانگین ۷ روز • ستون: تعداد سفارش</div>
            </div></div>
            <div class="card-body">
                <div class="rp-chart short rp-trend"
                     data-unit="<?= $unit ?>"
                     data-count-unit="سفارش"
                     data-items='<?= htmlspecialchars(json_encode($trendItems, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </div>
        </div>
    </div>

    <!-- ---------------------------------------------------- the best sellers -->
    <div class="rp-section"><h2>محصولات پرفروش</h2><span class="rp-pill">جدول</span></div>
    <div class="card fade-up">
        <div class="tbl-wrap">
            <table class="tbl-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>محصول</th>
                        <th>تعداد فروش</th>
                        <th>درآمد (<?= $unit ?>)</th>
                        <th>سهم از درآمد</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$productRows): ?>
                        <tr><td colspan="5"><div class="empty"><p>در این بازه فروشی ثبت نشده است.</p></div></td></tr>
                    <?php else: $rank = 0; foreach ($productRows as $row): $rank++; ?>
                        <tr>
                            <td><span class="rp-rank"><?= $rank ?></span></td>
                            <td class="cs"><?= htmlspecialchars((string) $row['name']) ?></td>
                            <td class="cn"><?= number_format((int) $row['count']) ?></td>
                            <td class="cn"><?= $money($row['revenue']) ?></td>
                            <td>
                                <?php $share = $productTotal > 0 ? (float) $row['revenue'] / $productTotal * 100 : 0; ?>
                                <div class="rp-share">
                                    <div class="bar"><div class="bar-fill" style="width:<?= round($share, 1) ?>%"></div></div>
                                    <span><?= number_format($share, 1) ?>٪</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- --------------------------------------------------------- the payments -->
    <div class="rp-section"><h2>پرداخت‌ها</h2><span class="rp-pill">توزیع و وضعیت</span></div>
    <div class="rp-cols-3">
        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">نرخ تبدیل پرداخت‌ها</div>
                <div class="card-subtitle">پرداخت‌شده در برابر پرداخت‌نشده</div>
            </div></div>
            <div class="card-body">
                <div class="rp-donut-wrap">
                    <div class="rp-donut-box"
                         data-center="<?= number_format($report['payments']['conversion'], 1) ?>%"
                         data-center-sub="نرخ پرداخت‌شده"
                         data-parts='<?= htmlspecialchars(json_encode($paymentParts, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
                    <div class="rp-legend">
                        <?php foreach ($paymentParts as $part): ?>
                            <div><i style="background:<?= $part['color'] ?>"></i><?= $part['name'] ?><b><?= number_format($part['value']) ?></b></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">وضعیت پرداخت‌ها</div>
                <div class="card-subtitle">موفق، ناموفق و در انتظار</div>
            </div></div>
            <div class="card-body">
                <div class="rp-donut-wrap">
                    <div class="rp-donut-box"
                         data-center="<?= number_format($report['payments']['success_rate'], 1) ?>%"
                         data-center-sub="نرخ موفقیت"
                         data-parts='<?= htmlspecialchars(json_encode($statusParts, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
                    <div class="rp-legend">
                        <?php foreach ($statusParts as $part): ?>
                            <div><i style="background:<?= $part['color'] ?>"></i><?= $part['name'] ?><b><?= number_format($part['value']) ?></b></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">روش‌های پرداخت</div>
                <div class="card-subtitle">درآمد هر درگاه</div>
            </div></div>
            <div class="card-body">
                <div class="rp-chart short rp-bars"
                     data-unit="<?= $unit ?>"
                     data-items='<?= htmlspecialchars(json_encode($gatewayItems, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </div>
        </div>
    </div>

    <!-- ------------------------------------------ the services and the panels -->
    <div class="rp-section"><h2>سرویس‌ها و لوکیشن‌ها</h2><span class="rp-pill">پیکربندی</span></div>
    <div class="rp-cols-3">
        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">فروش بر اساس روز هفته</div>
                <div class="card-subtitle">تعداد سفارش در هر روز</div>
            </div></div>
            <div class="card-body">
                <div class="rp-chart short rp-bars"
                     data-unit="سفارش"
                     data-items='<?= htmlspecialchars(json_encode($weekItems, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </div>
        </div>

        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">نوع سرویس‌های جایگاهی</div>
                <div class="card-subtitle">درآمد هر پنل</div>
            </div></div>
            <div class="card-body">
                <?php if ($locationLegend): ?>
                    <div class="rp-donut-wrap">
                        <div class="rp-donut-box"
                             data-center="<?= number_format(count($locationParts)) ?>"
                             data-center-sub="پنل"
                             data-parts='<?= htmlspecialchars(json_encode($locationParts, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
                        <div class="rp-legend">
                            <?php foreach ($locationLegend as $part): ?>
                                <div><i style="background:<?= $part['color'] ?>"></i><span><?= htmlspecialchars($part['name']) ?></span><b><?= number_format($part['value']) ?></b></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty"><p>در این بازه سرویسی ثبت نشده است.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">فروش به تفکیک سرویس</div>
                <div class="card-subtitle">درآمد هر نوع سرویس</div>
            </div></div>
            <div class="card-body">
                <?php if ($serviceLegend): ?>
                    <div class="rp-donut-wrap">
                        <div class="rp-donut-box"
                             data-center="<?= number_format(count($serviceParts)) ?>"
                             data-center-sub="نوع سرویس"
                             data-parts='<?= htmlspecialchars(json_encode($serviceParts, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
                        <div class="rp-legend">
                            <?php foreach ($serviceLegend as $part): ?>
                                <div><i style="background:<?= $part['color'] ?>"></i><span><?= htmlspecialchars($part['name']) ?></span><b><?= number_format($part['value']) ?></b></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty"><p>در این بازه سرویسی ثبت نشده است.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- -------------------------------------------- the users and the timing -->
    <div class="rp-section"><h2>کاربران و زمان‌بندی</h2><span class="rp-pill">رشد</span></div>
    <div class="rp-cols-2">
        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">رشد کاربران</div>
                <div class="card-subtitle">ستون: کاربر جدید روزانه • خط: مجموع کاربران</div>
            </div></div>
            <div class="card-body">
                <div class="rp-chart rp-growth"
                     data-new-unit="کاربر جدید"
                     data-total-unit="کاربر"
                     data-items='<?= htmlspecialchars(json_encode($growthItems, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </div>
        </div>

        <div class="card fade-up">
            <div class="card-head"><div>
                <div class="card-title">ساعت اوج خرید</div>
                <div class="card-subtitle">تعداد سفارش در هر ساعت از شبانه‌روز</div>
            </div></div>
            <div class="card-body">
                <div class="rp-chart rp-bars"
                     data-unit="سفارش"
                     data-items='<?= htmlspecialchars(json_encode($hourItems, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'></div>
            </div>
        </div>
    </div>

    <p class="rp-note">
        همهٔ عددهای این صفحه از دیتابیس همین ربات خوانده می‌شوند:
        فاکتورهای سرویس‌ها (<span class="cm">invoice</span>) برای فروش و درآمد،
        پرداخت‌ها (<span class="cm">Payment_report</span>) برای درگاه‌ها و نرخ پرداخت و
        کاربران (<span class="cm">user</span>) برای رشد.
        دورهٔ زمانی با <span class="cm">?range=</span> تغییر می‌کند.
    </p>
</div>

<script src="js/reports.js"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
