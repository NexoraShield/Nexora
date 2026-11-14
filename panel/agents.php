<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/../nexora_style.php';
require_auth();

// The table of the access to the panels of the customers. It is created when it
// does not exist yet, so a database that was never migrated still works.
panel_agent_ensure_table($pdo);

$agentTab = (isset($_GET['tab']) && in_array($_GET['tab'], ['panels', 'topup', 'accounts'], true)) ? (string) $_GET['tab'] : 'agents';
// The address of the reseller panel of this installation. It is never empty and
// never the string "undefined", so every link of the page is a real link.
$agentPanelLink = panel_agent_link();
$agentUserColumns = panel_table_columns($pdo, 'user');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save') {
        $uid = trim((string) ($_POST['uid'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));
        $agentCode = trim((string) ($_POST['agent_code'] ?? ''));
        $priceDiscount = trim((string) ($_POST['pricediscount'] ?? '0'));
        $maxBuy = trim((string) ($_POST['maxbuyagent'] ?? '0'));
        $wallet = trim((string) ($_POST['wallet'] ?? ''));
        $panelPass = trim((string) ($_POST['panel_pass'] ?? ''));
        $grantPanel = ((string) ($_POST['grant_panel'] ?? '0')) === '1';

        if (!preg_match('/^\d{1,32}$/', $uid)) {
            flash('error', 'آیدی عددی تلگرام مشتری را درست وارد کنید (مثال: 123456789).');
            header('Location: agents.php?tab=agents');
            exit;
        }

        $notes = [];

        // The row of the customer is created when it is not there yet: the
        // administrator types the telegram id once and does not have to look
        // the number up somewhere else.
        if (!panel_agent_ensure_user($pdo, $uid, $username)) {
            $notes[] = 'ردیف کاربر در دیتابیس ساخته نشد. اگر مشتری تا حالا با ربات حرف نزده است، اولین پیام او را به ربات بدهید تا ردیفش ساخته شود.';
        }

        // The settings of the reseller of the customer.
        $sets = [];
        $params = [];
        if (in_array('agent', $agentUserColumns, true)) {
            $sets[] = 'agent = ?';
            $params[] = $agentCode !== '' ? $agentCode : 'f';
        }
        if (in_array('pricediscount', $agentUserColumns, true)) {
            $sets[] = 'pricediscount = ?';
            $params[] = $priceDiscount !== '' ? $priceDiscount : '0';
        }
        if (in_array('maxbuyagent', $agentUserColumns, true)) {
            $sets[] = 'maxbuyagent = ?';
            $params[] = $maxBuy !== '' ? $maxBuy : '0';
        }
        if ($wallet !== '' && is_numeric($wallet) && in_array('Balance', $agentUserColumns, true)) {
            $sets[] = 'Balance = ?';
            $params[] = (float) $wallet;
        }
        if ($sets) {
            try {
                $params[] = $uid;
                $st = $pdo->prepare("UPDATE `user` SET " . implode(', ', $sets) . " WHERE id = ?");
                $st->execute($params);
            } catch (Throwable $e) {
                $notes[] = 'تنظیمات نمایندگی ذخیره نشد: ' . $e->getMessage();
            }
        }

        // The access to the panel of the customer. The password is only stored
        // as a hash, so it is shown to the administrator once.
        if ($grantPanel) {
            $plainPass = $panelPass;
            $generated = false;
            if ($plainPass === '') {
                $generated = true;
                $plainPass = function_exists('nexora_agent_panel_reset_password') ? (string) nexora_agent_panel_reset_password($uid) : '';
                if ($plainPass === '') {
                    $plainPass = 'Nx' . bin2hex(random_bytes(3));
                    try {
                        $st = $pdo->prepare("INSERT INTO agent_auth (id_user, pass_hash, created_at) VALUES (?,?,UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE pass_hash = VALUES(pass_hash), created_at = VALUES(created_at)");
                        $st->execute([$uid, password_hash($plainPass, PASSWORD_DEFAULT)]);
                    } catch (Throwable $e) {
                        $plainPass = '';
                        $notes[] = 'دسترسی پنل ساخته نشد: ' . $e->getMessage();
                    }
                }
            } else {
                try {
                    $st = $pdo->prepare("INSERT INTO agent_auth (id_user, pass_hash, created_at) VALUES (?,?,UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE pass_hash = VALUES(pass_hash), created_at = VALUES(created_at)");
                    $st->execute([$uid, password_hash($plainPass, PASSWORD_DEFAULT)]);
                } catch (Throwable $e) {
                    $plainPass = '';
                    $notes[] = 'دسترسی پنل ساخته نشد: ' . $e->getMessage();
                }
            }
            if ($plainPass !== '') {
                $_SESSION['nx_agent_handover'] = [
                    'uid' => $uid,
                    'pass' => $plainPass,
                    'generated' => $generated,
                    'link' => $agentPanelLink,
                    'at' => time(),
                ];
                $notes[] = 'دسترسی پنل نمایندگی برای ' . $uid . ' ساخته شد.';
            }
        } else {
            try {
                $st = $pdo->prepare("DELETE FROM agent_auth WHERE id_user = ?");
                $st->execute([$uid]);
                $notes[] = 'دسترسی پنل مشتری بسته شد (ردیف دسترسی حذف شد).';
            } catch (Throwable $e) {
                $notes[] = 'بستن دسترسی پنل انجام نشد: ' . $e->getMessage();
            }
        }

        if ($notes) {
            $hasError = false;
            foreach ($notes as $note) {
                if (strpos($note, 'انجام نشد') !== false || strpos($note, 'ساخته نشد') !== false) {
                    $hasError = true;
                    break;
                }
            }
            if ($hasError) {
                flash('error', implode(' — ', $notes));
            } else {
                flash('success', implode(' — ', $notes));
            }
        }
        header('Location: agents.php?tab=agents');
        exit;
    }

    if ($action === 'revoke') {
        // The panel of the customer is closed, the reseller settings stay.
        $uid = trim((string) ($_POST['uid'] ?? ''));
        if ($uid === '') {
            flash('error', 'شناسهٔ کاربر مشخص نشده است.');
        } else {
            try {
                $st = $pdo->prepare("DELETE FROM agent_auth WHERE id_user = ?");
                $st->execute([$uid]);
                unset($_SESSION['nx_agent_handover']);
                flash('success', 'دسترسی پنل ' . $uid . ' بسته شد. مشتری دیگر نمی‌تواند وارد پنل شود.');
            } catch (Throwable $e) {
                flash('error', 'خطا: ' . $e->getMessage());
            }
        }
        header('Location: agents.php?tab=agents');
        exit;
    }

    if ($action === 'delete') {
        // The whole representation of the customer is removed.
        $uid = trim((string) ($_POST['uid'] ?? ''));
        if ($uid !== '') {
            try {
                $sets = [];
                $params = [];
                if (in_array('agent', $agentUserColumns, true)) {
                    $sets[] = 'agent = ?';
                    $params[] = 'f';
                }
                if (in_array('pricediscount', $agentUserColumns, true)) {
                    $sets[] = 'pricediscount = ?';
                    $params[] = '0';
                }
                if (in_array('maxbuyagent', $agentUserColumns, true)) {
                    $sets[] = 'maxbuyagent = ?';
                    $params[] = '0';
                }
                if ($sets) {
                    $params[] = $uid;
                    $st = $pdo->prepare("UPDATE `user` SET " . implode(', ', $sets) . " WHERE id = ?");
                    $st->execute($params);
                }
                $st = $pdo->prepare("DELETE FROM agent_auth WHERE id_user = ?");
                $st->execute([$uid]);
                unset($_SESSION['nx_agent_handover']);
                flash('success', 'دسترسی نمایندگی حذف شد.');
            } catch (Throwable $e) {
                flash('error', 'خطا: ' . $e->getMessage());
            }
        }
        header('Location: agents.php?tab=agents');
        exit;
    }
}

// The information of the hand over that was just done (it is shown only once).
$handover = null;
if (!empty($_SESSION['nx_agent_handover']) && is_array($_SESSION['nx_agent_handover'])) {
    $handover = $_SESSION['nx_agent_handover'];
    unset($_SESSION['nx_agent_handover']);
}

// The list of the representatives: the customers that got a reseller panel and
// the old style agents.
$agents = panel_agent_representatives($pdo, 500);

$pageTitle = 'مدیریت نمایندگان (Agent Panel)';
$pageLede = 'مدیریت نمایندگان، قیمت‌گذاری، دسترسی پنل، فروش پنل به نمایندگان و شارژ پنل.';
$activeNav = 'agents';
include __DIR__ . '/inc/layout_head.php';
?><div class="page-header">
    <h1>👥 مدیریت نمایندگان</h1>
    <p class="page-lede"><?= $pageLede ?></p>
</div>

<div class="card fade-up" style="margin-bottom:20px"><div class="card-body" style="display:flex;gap:8px;flex-wrap:wrap">
  <a class="btn <?= $agentTab === 'agents' ? 'btn-primary' : 'btn-ghost' ?>" href="agents.php?tab=agents">👥 نمایندگان</a>
  <a class="btn <?= $agentTab === 'panels' ? 'btn-primary' : 'btn-ghost' ?>" href="agents.php?tab=panels">🖥 فروش پنل به نمایندگان</a>
  <a class="btn <?= $agentTab === 'accounts' ? 'btn-primary' : 'btn-ghost' ?>" href="agents.php?tab=accounts">📦 پنل‌های تحویل‌شده</a>
  <a class="btn <?= $agentTab === 'topup' ? 'btn-primary' : 'btn-ghost' ?>" href="agents.php?tab=topup">💳 شارژ پنل نمایندگی</a>
</div></div>

<?php if ($agentTab === 'topup'):
    $topupShop = 'agentpanelshop';
    $topupReturn = 'agents.php';
    include __DIR__ . '/inc/topup_tab.php';
elseif ($agentTab === 'accounts'):
    $accountsReturn = 'agents.php';
    include __DIR__ . '/inc/agentpanel_accounts_tab.php';
elseif ($agentTab === 'panels'):
    $panelReturn = 'agents.php';
    include __DIR__ . '/inc/agentpanel_tab.php';
else: ?>

<?php if ($handover): ?>
<div class="card fade-up" style="margin-bottom:20px;border:1px solid var(--ok)">
    <div class="card-head"><div>
        <div class="card-title">🎁 اطلاعات تحویل به مشتری (فقط یک بار نمایش داده می‌شود)</div>
        <div class="card-subtitle">این اطلاعات را برای مشتری بفرستید. رمز پنل همان رمزی است که خودتان انتخاب کردید<?= !empty($handover['generated']) ? ' (رمز تازه ساخته شد)' : '' ?>.</div>
    </div></div>
    <div class="card-body">
        <div class="kv"><span>لینک ورود پنل نمایندگی</span><b><a href="<?= htmlspecialchars((string) ($handover['link'] ?? $agentPanelLink)) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) ($handover['link'] ?? $agentPanelLink)) ?></a></b></div>
        <div class="kv"><span>نام کاربری پنل (آیدی عددی تلگرام مشتری)</span><b dir="ltr"><?= htmlspecialchars((string) ($handover['uid'] ?? '')) ?></b></div>
        <div class="kv"><span>رمز عبور پنل</span><b dir="ltr"><?= htmlspecialchars((string) ($handover['pass'] ?? '')) ?></b></div>
    </div>
</div>
<?php endif; ?>

<div class="card fade-up d1" style="margin-bottom:20px">
    <div class="card-head"><div>
        <div class="card-title">🤝 تحویل دستی پنل نمایندگی به مشتری</div>
        <div class="card-subtitle">پنل نمایندگی را خودتان باز می‌کنید و اطلاعات ورود را برای مشتری می‌فرستید. آیدی عددی تلگرام مشتری را وارد کنید؛ اگر مشتری در ربات عضو نشده باشد، ردیف او همین‌جا ساخته می‌شود.</div>
    </div></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save">
            <div class="form-grid">
                <label class="field"><span>آیدی عددی تلگرام مشتری</span>
                    <input class="input" name="uid" inputmode="numeric" placeholder="مثال: 123456789" required>
                    <small class="field-hint">همین عدد نام کاربری پنل نمایندگی هم هست.</small>
                </label>
                <label class="field"><span>نام کاربری تلگرام (اختیاری)</span>
                    <input class="input" name="username" placeholder="مثال: @ali">
                    <small class="field-hint">فقط برای وقتی به کار می‌آید که ردیف مشتری در ربات نباشد.</small>
                </label>
                <label class="field"><span>کد نمایندگی (Agent)</span>
                    <input class="input" name="agent_code" value="n" placeholder="n یا n2 یا کد دلخواه">
                    <small class="field-hint">n نماینده، n2 نماینده با اجازهٔ منفی شدن کیف پول.</small>
                </label>
                <label class="field"><span>تخفیف قیمت (درصد)</span>
                    <input class="input" type="number" name="pricediscount" value="0">
                </label>
                <label class="field"><span>حداکثر خرید / سقف کیف پول</span>
                    <input class="input" type="number" name="maxbuyagent" value="0">
                    <small class="field-hint">سقف خرید نماینده و بیشترین مقداری که کیف پولش می‌تواند منفی شود.</small>
                </label>
                <label class="field"><span>اعتبار کیف پول مشتری (تومان)</span>
                    <input class="input" type="number" name="wallet" value="" placeholder="خالی = بدون تغییر">
                    <small class="field-hint">موجودی کیف پولی که مشتری داخل پنل نمایندگی می‌بیند.</small>
                </label>
                <label class="field"><span>رمز عبور پنل نمایندگی</span>
                    <input class="input" type="password" name="panel_pass" placeholder="اگر خالی بماند، رمز تازه ساخته می‌شود">
                    <small class="field-hint">رمز فقط به صورت هش ذخیره می‌شود و همین‌جا برای مشتری فرستاده می‌شود.</small>
                </label>
            </div>
            <div class="field" style="margin-top:12px">
                <label style="display:flex;gap:8px;align-items:center">
                    <input type="hidden" name="grant_panel" value="0">
                    <input type="checkbox" name="grant_panel" value="1" checked>
                    <span>دسترسی پنل نمایندگی ساخته/به‌روزرسانی شود (اگر تیک نخورد، دسترسی مشتری بسته می‌شود)</span>
                </label>
            </div>
            <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <button type="submit" class="btn btn-primary">💾 ذخیره و ساخت دسترسی پنل</button>
                <span style="font-size:.78rem;color:var(--mute)">لینک ورود پنل نمایندگی: <a href="<?= htmlspecialchars($agentPanelLink) ?>" target="_blank" rel="noopener" dir="ltr"><?= htmlspecialchars($agentPanelLink) ?></a></span>
            </div>
        </form>
    </div>
</div>

<div class="card fade-up d2">
    <div class="card-head"><div>
        <div class="card-title">📋 لیست نمایندگان (<?= count($agents) ?>)</div>
        <div class="card-subtitle">همهٔ مشتری‌هایی که پنل نمایندگی دارند و نمایندگان قدیمی. اگر مشتری پنل نمایندگی خریده باشد، حتی وقتی کد نمایندگی ندارد، اینجا دیده می‌شود.</div>
    </div></div>
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
                        <th>تاریخ ساخت دسترسی</th>
                        <th>لینک پنل</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($agents)): ?>
                        <tr><td colspan="10" style="text-align:center">هیچ نماینده‌ای یافت نشد.</td></tr>
                    <?php else: foreach ($agents as $a):
                        $aId = trim((string) ($a['id'] ?? ''));
                        $hasPanel = !empty($a['has_panel']);
                    ?>
                        <tr>
                            <td dir="ltr"><?= htmlspecialchars($aId) ?></td>
                            <td><?= htmlspecialchars((string) ($a['username'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($a['agent'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($a['pricediscount'] ?? '0')) ?></td>
                            <td><?= htmlspecialchars((string) ($a['maxbuyagent'] ?? '0')) ?></td>
                            <td><?= number_format((float) ($a['Balance'] ?? 0)) ?></td>
                            <td><?= $hasPanel ? '✅ دارد' : '❌ ندارد' ?></td>
                            <td><?= $hasPanel ? safe_date($a['access_created_at'] ?? 0) : '—' ?></td>
                            <td style="font-size:.72rem"><a href="<?= htmlspecialchars($agentPanelLink) ?>" target="_blank" rel="noopener" dir="ltr"><?= htmlspecialchars($agentPanelLink) ?></a></td>
                            <td style="white-space:nowrap">
                                <?php if ($hasPanel): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="revoke">
                                    <input type="hidden" name="uid" value="<?= htmlspecialchars($aId) ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" data-confirm="دسترسی پنل این مشتری بسته شود؟ مشتری دیگر نمی‌تواند وارد پنل شود.">🚫 ابطال دسترسی</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('حذف دسترسی نمایندگی؟')">
                                    <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="uid" value="<?= htmlspecialchars($aId) ?>">
                                    <button type="submit" class="btn btn-no btn-sm">🗑️ حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="js/emoji-insert.js"></script>
<?php include __DIR__ . '/inc/layout_foot.php'; ?>
