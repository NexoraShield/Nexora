<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../nexora_style.php';
require_auth();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS agent_auth (id_user VARCHAR(32) NOT NULL PRIMARY KEY, pass_hash VARCHAR(255) NOT NULL, created_at INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $uid = trim($_POST['uid'] ?? '');
        $agentCode = trim($_POST['agent_code'] ?? '');
        $pricediscount = trim($_POST['pricediscount'] ?? '0');
        $maxbuyagent = trim($_POST['maxbuyagent'] ?? '0');
        $grantPanel = isset($_POST['grant_panel']);
        $panelPass = trim($_POST['panel_pass'] ?? '');
        if ($uid === '') {
            flash('error', 'شناسهٔ کاربر الزامی است.');
        } else {
            try {
                $st = $pdo->prepare("UPDATE user SET agent = ?, pricediscount = ?, maxbuyagent = ? WHERE id = ?");
                $st->execute([$agentCode !== '' ? $agentCode : 'f', $pricediscount, $maxbuyagent, $uid]);
                if ($grantPanel && $panelPass !== '') {
                    $hash = password_hash($panelPass, PASSWORD_BCRYPT);
                    $pdo->prepare("INSERT INTO agent_auth (id_user, pass_hash, created_at) VALUES (?,?,UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE pass_hash=VALUES(pass_hash)")->execute([$uid, $hash]);
                } elseif (!$grantPanel) {
                    $pdo->prepare("DELETE FROM agent_auth WHERE id_user = ?")->execute([$uid]);
                }
                flash('success', 'نماینده ذخیره شد.');
            } catch (Throwable $e) {
                flash('error', 'خطا: ' . $e->getMessage());
            }
        }
        header('Location: agents.php');
        exit;
    }
    if ($action === 'delete') {
        $uid = trim($_POST['uid'] ?? '');
        if ($uid !== '') {
            try {
                $pdo->prepare("UPDATE user SET agent = 'f', pricediscount = '0', maxbuyagent = '0' WHERE id = ?")->execute([$uid]);
                $pdo->prepare("DELETE FROM agent_auth WHERE id_user = ?")->execute([$uid]);
                flash('success', 'دسترسی نمایندگی حذف شد.');
            } catch (Throwable $e) {
                flash('error', 'خطا: ' . $e->getMessage());
            }
        }
        header('Location: agents.php');
        exit;
    }
}

$agents = [];
try {
    $stmt = $pdo->query("SELECT id, username, agent, pricediscount, maxbuyagent, Balance, number FROM user WHERE agent IS NOT NULL AND agent <> 'f' AND agent <> '' ORDER BY id LIMIT 500");
    $agents = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $agents = []; }
?>
<div class="page-header">
    <h1>👥 مدیریت نمایندگان</h1>
    <p class="page-lede"><?= $pageLede ?></p>
</div>

<div class="card fade-up d1" style="margin-bottom:20px">
    <div class="card-head"><div><div class="card-title">➕ افزودن / ویرایش نماینده</div></div></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save">
            <div class="field">
                <label>شناسهٔ کاربر (User ID)</label>
                <input type="text" name="uid" class="input" placeholder="مثلاً 123456789" required>
            </div>
            <div class="field">
                <label>کد نمایندگی (Agent Code)</label>
                <input type="text" name="agent_code" class="input" placeholder="مثلاً agent1">
            </div>
            <div class="field">
                <label>تخفیف قیمت (%)</label>
                <input type="text" name="pricediscount" class="input" value="0">
            </div>
            <div class="field">
                <label>حداکثر خرید (Max Buy)</label>
                <input type="text" name="maxbuyagent" class="input" value="0">
            </div>
            <div class="field">
                <label><input type="checkbox" name="grant_panel" value="1"> دسترسی پنل نمایندگی</label>
            </div>
            <div class="field">
                <label>رمز عبور پنل (در صورت فعال‌سازی)</label>
                <input type="password" name="panel_pass" class="input" placeholder="رمز عبور">
            </div>
            <div style="margin-top:12px">
                <button type="submit" class="btn btn-primary">💾 ذخیره</button>
            </div>
        </form>
    </div>
</div>

<div class="card fade-up d2">
    <div class="card-head"><div><div class="card-title">📋 لیست نمایندگان (<?= count($agents) ?>)</div></div></div>
    <div class="card-body">
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام کاربری</th>
                        <th>کد نمایندگی</th>
                        <th>تخفیف %</th>
                        <th>حداکثر خرید</th>
                        <th>موجودی</th>
                        <th>دسترسی پنل</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agents)): ?>
                        <tr><td colspan="8" style="text-align:center">هیچ نماینده‌ای یافت نشد.</td></tr>
                    <?php else: foreach ($agents as $a): ?>
                        <tr>
                            <td><?= htmlspecialchars($a['id']) ?></td>
                            <td><?= htmlspecialchars($a['username'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($a['agent']) ?></td>
                            <td><?= htmlspecialchars($a['pricediscount'] ?? '0') ?></td>
                            <td><?= htmlspecialchars($a['maxbuyagent'] ?? '0') ?></td>
                            <td><?= number_format((float)($a['Balance'] ?? 0)) ?></td>
                            <td><?= isset($panelAccess[$a['id']]) ? '✅ دارد' : '❌ ندارد' ?></td>
                            <td>
                                <form method="POST" style="display:inline" onsubmit="return confirm('حذف دسترسی نمایندگی؟')">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="uid" value="<?= htmlspecialchars($a['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑️ حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/layout_foot.php'; ?>


$panelAccess = [];
try {
    $stmt = $pdo->query("SELECT id_user FROM agent_auth");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $panelAccess[$r['id_user']] = true;
    }
} catch (Throwable $e) {}

$pageTitle = 'مدیریت نمایندگان (Agent Panel)';
$pageLede = 'مدیریت نمایندگان، قیمت‌گذاری، دسترسی پنل و حذف.';
$activeNav = 'agents';
include __DIR__ . '/inc/layout_head.php';
?>?>
?>
?>
