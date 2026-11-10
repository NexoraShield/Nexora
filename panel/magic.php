<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
$__nexoraStyleLib = __DIR__ . '/../nexora_style.php';
if (is_file($__nexoraStyleLib)) require_once $__nexoraStyleLib;
require_auth();

$report = [];
$ok = 0; $fixed = 0;
function magic_log($msg, $type='info') { global $report; $report[] = ['msg'=>$msg,'type'=>$type]; }

// 1. Ensure style/emoji columns exist
$targets = ['product','category','marzban_panel'];
foreach ($targets as $t) {
    foreach ([['btn_style','VARCHAR(20) NULL'],['btn_emoji_id','VARCHAR(32) NULL']] as [$col,$def]) {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
            $st->execute([$t,$col]);
            if ((int)$st->fetchColumn()===0) { $pdo->exec("ALTER TABLE `$t` ADD `$col` $def"); $fixed++; magic_log("Added column $col to $t", 'fix'); }
            else { $ok++; }
        } catch (Throwable $e) { magic_log("Error on $t.$col: ".$e->getMessage(),'err'); }
    }
}
// 2. Ensure PaySetting table has ValuePay column etc (just check)
try { $pdo->query("SELECT 1 FROM PaySetting LIMIT 1"); $ok++; magic_log("PaySetting table OK",'ok'); }
catch (Throwable $e) { magic_log("PaySetting missing: ".$e->getMessage(),'err'); }
// 3. Clear opcache
if (function_exists('opcache_reset')) { @opcache_reset(); $fixed++; magic_log("OPcache cleared",'fix'); }
// 4. Count records
try {
    foreach (['user','product','category','marzban_panel','Invoice'] as $t) {
        try { $c = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); magic_log("$t: $c rows",'ok'); }
        catch (Throwable $e) {}
    }
} catch (Throwable $e) {}
// 5. Remove orphan override empty files
$ov = dirname(__DIR__).'/lang/override';
if (is_dir($ov)) {
    foreach (glob($ov.'/*.php') as $f) {
        $d = @include $f;
        if (!is_array($d) || empty($d)) { @unlink($f); $fixed++; magic_log("Removed empty override: ".basename($f),'fix'); }
    }
}

$pageTitle = '✨ معجزه — بهینه‌سازی خودکار';
$pageLede = 'یک کلیک برای تعمیر، بهینه‌سازی و بررسی سلامت کل سیستم.';
$activeNav = 'magic';
include __DIR__ . '/inc/layout_head.php';
?>
<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">✨ معجزه</div></div></div>
  <div class="card-body">
    <p>نتیجهٔ بررسی و بهینه‌سازی:</p>
    <ul style="line-height:2">
      <?php foreach ($report as $r): ?>
        <li style="color:<?= $r['type']==='fix'?'#d4af37':($r['type']==='err'?'#e17076':'#4fae4e') ?>">
          <?= $r['type']==='fix'?'🔧':($r['type']==='err'?'❌':'✅') ?> <?= htmlspecialchars($r['msg']) ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p style="margin-top:12px;opacity:.7">✅ OK: <?= $ok ?> | 🔧 Fixed: <?= $fixed ?></p>
    <a href="magic.php" class="btn btn-primary" style="margin-top:12px">🔄 اجرای دوباره</a>
  </div>
</div>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
