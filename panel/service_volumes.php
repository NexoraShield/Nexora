<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_auth();

// List services nearing expiry or low volume
$items = [];
try {
    $stmt = $pdo->query("SELECT * FROM invoice WHERE (status = 'active' OR status = 'end_of_time' OR status = 'end_of_volume' OR status = 'sendedwarn' OR status = 'send_on_hold') ORDER BY time_sell DESC LIMIT 200");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $items = []; }

// Flag near-expiry / low-volume
$now = time();
$flagged = [];
foreach ($items as $inv) {
    $flag = '';
    // time check: Service_time days from time_sell
    $ts = (int)($inv['time_sell'] ?? 0);
    $days = (int)($inv['Service_time'] ?? 0);
    if ($days > 0 && $ts > 0) {
        $expiry = $ts + $days * 86400;
        $remaining = $expiry - $now;
        if ($remaining < 3 * 86400 && $remaining > 0) $flag = 'expiring_soon';
        elseif ($remaining <= 0) $flag = 'expired';
    }
    // volume check: Volume (GB) - used volume unknown; flag if Volume small
    if ($flag === '' && (float)($inv['Volume'] ?? 0) <= 1) $flag = 'low_volume';
    if ($flag !== '') {
        $inv['_flag'] = $flag;
        $flagged[] = $inv;
    }
}

$pageTitle = '📊 بررسی حجم/زمان سرویس‌ها';
$pageLede = 'سرویس‌های نزدیک به اتمام حجم یا زمان.';
$activeNav = 'service_volumes';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">📊 بررسی حجم/زمان سرویس‌ها (<?= count($flagged) ?>)</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>#</th><th>کاربر</th><th>سرویس</th><th>پنل</th><th>حجم (GB)</th><th>زمان (روز)</th><th>وضعیت</th><th>هشدار</th></tr></thead>
    <tbody>
      <?php foreach ($flagged as $i => $inv): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td dir="ltr"><?= htmlspecialchars($inv['id_user']) ?></td>
        <td><?= htmlspecialchars($inv['name_product'] ?? '') ?></td>
        <td><?= htmlspecialchars($inv['Service_location'] ?? '') ?></td>
        <td><?= htmlspecialchars($inv['Volume'] ?? '') ?></td>
        <td><?= htmlspecialchars($inv['Service_time'] ?? '') ?></td>
        <td><?= htmlspecialchars($inv['Status'] ?? '') ?></td>
        <td><?= $inv['_flag'] === 'expired' ? '⛔ منقضی' : ($inv['_flag'] === 'expiring_soon' ? '⚠️ نزدیک انقضا' : '📉 حجم کم') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($flagged)): ?>
        <tr><td colspan="8" style="text-align:center;opacity:.6">موردی یافت نشد.</td></tr>
      <?php endif; ?>
    </tbody>
  </table></div>
  <p style="margin-top:10px;opacity:.7;font-size:.85rem">Cron پیشنهادی: <code>*/30 * * * * php BOTDIR/cronbot/check_volumes.php</code></p>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>