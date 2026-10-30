<?php
require_once __DIR__ . '/inc/auth.php';
$me = nexora_agent_require_login();
$myId = (string) $me['id'];
$subs = []; $totalBuy = 0; $activeServices = 0; $percent = 0;
try {
    $stmt = $pdo->prepare('SELECT id, username, Balance FROM `user` WHERE affiliates = ? ORDER BY id LIMIT 500');
    $stmt->execute([$myId]);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $subs = []; }
try {
    $row = $pdo->query('SELECT affiliatespercentage FROM setting LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    $percent = (float) ($row['affiliatespercentage'] ?? 0);
} catch (Throwable $e) { $percent = 0; }
if ($subs !== []) {
    $ids = array_column($subs, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(price_product),0) FROM invoice WHERE id_user IN ($ph) AND Status != 'unpaid'");
        $stmt->execute($ids);
        $totalBuy = (float) $stmt->fetchColumn();
    } catch (Throwable $e) { $totalBuy = 0; }
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoice WHERE id_user IN ($ph) AND Status = 'active'");
        $stmt->execute($ids);
        $activeServices = (int) $stmt->fetchColumn();
    } catch (Throwable $e) { $activeServices = 0; }
}
$commission = $totalBuy * $percent / 100;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#141a2e">
  <title>پنل نمایندگی — نکسورا</title>
  <link rel="stylesheet" href="reseller-panel.css?v=7">
  <link rel="stylesheet" href="beauty.css?v=12">
  <link rel="stylesheet" href="mobile.css?v=11">
  <link rel="stylesheet" href="../panel/css/style.css">
  <link rel="stylesheet" href="../panel/css/nexora-glass.css">
</head>
<body data-theme="teal">
<div class="card fade-up" style="max-width:720px;margin:4vh auto;padding:24px">
  <div class="card-head"><div>
    <div class="card-title">پنل نمایندگی نکسورا</div>
    <div class="card-subtitle"><?= htmlspecialchars($me['username'] ?? $myId) ?> · <a href="logout.php">خروج</a></div>
  </div></div>
  <div class="tbl-wrap"><table class="tbl"><tbody>
    <tr><td>موجودی شما</td><td class="cf"><?= number_format((float) ($me['Balance'] ?? 0)) ?> تومان</td></tr>
    <tr><td>تعداد زیرمجموعه</td><td class="cf"><?= count($subs) ?></td></tr>
    <tr><td>سرویس فعال زیرمجموعه‌ها</td><td class="cf"><?= $activeServices ?></td></tr>
    <tr><td>مجموع خرید زیرمجموعه‌ها</td><td class="cf"><?= number_format($totalBuy) ?> تومان</td></tr>
    <tr><td>پورسانت تقریبی (<?= $percent ?>٪)</td><td class="cf"><?= number_format($commission) ?> تومان</td></tr>
  </tbody></table></div>
</div>
<div class="card fade-up d1" style="max-width:720px;margin:0 auto 6vh;padding:24px">
  <div class="card-head"><div><div class="card-title">زیرمجموعه‌ها</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>آیدی</th><th>نام کاربری</th><th>موجودی</th></tr></thead>
    <tbody>
      <?php if ($subs === []): ?><tr><td colspan="3">زیرمجموعه‌ای ندارید.</td></tr><?php endif; ?>
      <?php foreach ($subs as $s): ?>
      <tr>
        <td class="cf"><?= htmlspecialchars($s['id']) ?></td>
        <td class="cs">@<?= htmlspecialchars($s['username'] ?? '—') ?></td>
        <td class="cf"><?= number_format((float) ($s['Balance'] ?? 0)) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
</body>
</html>
