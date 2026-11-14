<?php
// The agent panels that were delivered to the customers.
//
// The row of the agent_auth is the access to the panel of the customer and the
// wallet of the customer is the credit of the panel. The administrator can
// change the credit by hand, make a new password and delete the panel.
//
// The including page must define: $accountsReturn (the page to come back to).
// When the page forgets it, the tab still works and comes back to this page.
if (!isset($accountsReturn) || trim((string) $accountsReturn) === '') {
    $accountsReturn = 'agents.php';
}
$accountsTab = $accountsReturn . '?tab=accounts';

// The table of the access has to exist, otherwise the list would be empty
// after an update that did not create it yet.
panel_agent_ensure_table($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== '') {
    csrf_check_post();
    $action = (string) $_POST['action'];
    $uid = trim((string) ($_POST['uid'] ?? ''));
    if ($uid === '') {
        flash('error', 'شناسهٔ کاربر مشخص نشده است.');
    } elseif ($action === 'apa_credit') {
        // The credit of the panel is the wallet of the customer.
        $amount = (float) ($_POST['amount'] ?? 0);
        $dir = (string) ($_POST['dir'] ?? 'add');
        if ($amount <= 0) {
            flash('error', 'مبلغ اعتبار باید بزرگ‌تر از صفر باشد.');
        } else {
            $delta = ($dir === 'sub') ? -$amount : $amount;
            $newBalance = nexora_agent_panel_set_credit($uid, $delta);
            if ($newBalance === null) {
                flash('error', 'تغییر اعتبار انجام نشد. (موجودی کمتر از صفر نمی‌شود)');
            } else {
                flash('success', 'اعتبار پنل ' . $uid . ' ' . ($delta > 0 ? '+' : '-') . number_format(abs($delta)) . ' تومان تغییر کرد. موجودی فعلی: ' . number_format($newBalance) . ' تومان');
            }
        }
    } elseif ($action === 'apa_reset') {
        $newPass = nexora_agent_panel_reset_password($uid);
        if ($newPass === null) {
            flash('error', 'رمز جدید ساخته نشد.');
        } else {
            flash('success', 'رمز جدید پنل ' . $uid . ': ' . $newPass . ' (این رمز فقط یک بار نمایش داده می‌شود، آن را برای مشتری بفرستید.)');
        }
    } elseif ($action === 'apa_delete') {
        if (nexora_agent_panel_revoke($uid)) {
            flash('success', 'پنل ' . $uid . ' حذف شد. دسترسی مشتری به پنل بسته شد و سوابق پرداخت او باقی ماند.');
        } else {
            flash('error', 'حذف پنل انجام نشد.');
        }
    }
    header('Location: ' . $accountsTab);
    exit;
}

$agentAccounts = function_exists('nexora_agent_panel_accounts') ? nexora_agent_panel_accounts(500) : [];
if (!$agentAccounts) {
    // The helper of the bot is not there (or it failed): the list of the panel
    // is built by the panel itself, so the tab never stays empty for nothing.
    $agentAccounts = panel_agent_delivered_accounts($pdo, 500);
}
// The link of the login is always the real address of the reseller panel of
// this installation. It is never empty and never the string "undefined".
$accountsLink = panel_agent_link();
foreach ($agentAccounts as $agentAccountIndex => $agentAccountRow) {
    $agentLink = trim((string) ($agentAccountRow['link'] ?? ''));
    if ($agentLink === '' || strpos($agentLink, '{') !== false || !preg_match('#^(https?://|/)#', $agentLink)) {
        $agentLink = $accountsLink;
    }
    $agentAccounts[$agentAccountIndex]['link'] = $agentLink;
}
unset($agentAccountRow, $agentAccountIndex);
$agentAccountsTotal = count($agentAccounts);
$agentAccountsCredit = 0.0;
foreach ($agentAccounts as $agentAccountRow) {
    $agentAccountsCredit += floatval($agentAccountRow['credit'] ?? 0);
}
?>
<div class="card fade-up" style="margin-bottom:18px">
  <div class="card-head"><div><div class="card-title">🖥 پنل‌های تحویل‌شده (<?= $agentAccountsTotal ?>)</div>
    <div class="card-subtitle">هر پنلی که به مشتری تحویل داده‌اید اینجا دیده می‌شود. می‌توانید اعتبار پنل را کم و زیاد کنید، رمز تازه بسازید یا پنل را کاملاً حذف کنید.</div></div></div>
  <div class="card-body" style="display:flex;gap:14px;flex-wrap:wrap">
    <div class="kv"><span>تعداد پنل فعال</span><b><?= $agentAccountsTotal ?></b></div>
    <div class="kv"><span>مجموع اعتبار پنل‌ها</span><b><?= number_format($agentAccountsCredit) ?> تومان</b></div>
  </div>
</div>

<div class="card fade-up d1">
  <div class="card-head"><div><div class="card-title">📋 لیست پنل‌های تحویل‌شده</div></div></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>نام کاربری پنل</th><th>کاربر ربات</th><th>محصول خریداری‌شده</th><th>اعتبار پنل</th><th>لینک ورود</th><th>تاریخ ساخت</th><th>عملیات</th></tr></thead>
    <tbody>
      <?php if (!$agentAccounts): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--mute);padding:22px">هنوز هیچ پنلی به مشتری تحویل داده نشده است.</td></tr>
      <?php endif; ?>
      <?php foreach ($agentAccounts as $agentAccount):
        $rowLink = trim((string) ($agentAccount['link'] ?? ''));
        if ($rowLink === '') {
            $rowLink = $accountsLink;
        }
      ?>
        <tr>
          <td><code><?= htmlspecialchars((string) $agentAccount['id_user']) ?></code></td>
          <td><?= htmlspecialchars((string) ($agentAccount['username'] ?? '—')) ?></td>
          <td><?= htmlspecialchars((string) ($agentAccount['product'] ?? '—')) ?></td>
          <td><b><?= number_format(floatval($agentAccount['credit'] ?? 0)) ?></b></td>
          <td style="font-size:.75rem"><a href="<?= htmlspecialchars($rowLink) ?>" target="_blank" rel="noopener" dir="ltr"><?= htmlspecialchars($rowLink) ?></a></td>
          <td><?= safe_date($agentAccount['created_at'] ?? 0) ?></td>
          <td style="white-space:nowrap">
            <button class="btn btn-primary btn-sm" type="button" onclick='openAgentCredit(<?= json_encode([
                'uid' => (string) $agentAccount['id_user'],
                'credit' => floatval($agentAccount['credit'] ?? 0),
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>💰 تغییر اعتبار</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="apa_reset">
              <input type="hidden" name="uid" value="<?= htmlspecialchars((string) $agentAccount['id_user']) ?>">
              <button class="btn btn-ghost btn-sm" type="submit" data-confirm="رمز پنل عوض شود؟ رمز قبلی دیگر کار نمی‌کند.">🔑 رمز جدید</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
              <input type="hidden" name="action" value="apa_delete">
              <input type="hidden" name="uid" value="<?= htmlspecialchars((string) $agentAccount['id_user']) ?>">
              <button class="btn btn-no btn-sm" type="submit" data-confirm="این پنل حذف شود؟ مشتری دیگر نمی‌تواند وارد پنل شود.">🗑️ حذف پنل</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="modal-veil" id="agentCreditModal">
  <div class="modal">
    <div class="modal-head">
      <h3>تغییر اعتبار پنل</h3>
      <button class="modal-x" onclick="closeModal('agentCreditModal')"><?= icon('close', 14) ?></button>
    </div>
    <form method="POST">
      <div class="modal-body">
        <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="apa_credit">
        <input type="hidden" name="uid" id="apc_uid">
        <p style="margin:0 0 12px">اعتبار فعلی پنل <b id="apc_current">—</b> تومان است.</p>
        <div class="form-grid">
          <label class="field"><span>مبلغ (تومان)</span><input class="input" type="number" name="amount" min="1" step="1" value="100000" required></label>
          <label class="field"><span>عملیات</span>
            <select name="dir" class="select">
              <option value="add">افزودن به اعتبار</option>
              <option value="sub">کم کردن از اعتبار</option>
            </select>
          </label>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn btn-primary" type="submit"><?= icon('check', 13) ?> اعمال</button>
        <button class="btn btn-ghost" type="button" onclick="closeModal('agentCreditModal')">انصراف</button>
      </div>
    </form>
  </div>
</div>

<script>
window.openAgentCredit = function (p) {
    p = p || {};
    document.getElementById('apc_uid').value = p.uid || '';
    document.getElementById('apc_current').textContent = Number(p.credit || 0).toLocaleString('fa-IR');
    openModal('agentCreditModal');
};
</script>
