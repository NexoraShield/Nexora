<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/inc/auth.php';
nexora_ensure_agent_table();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim((string) ($_POST['id'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');
    if ($id !== '' && $pass !== '' && preg_match('/^\d+$/', $id)) {
        try {
            $stmt = $pdo->prepare('SELECT pass_hash FROM agent_auth WHERE id_user = ? LIMIT 1');
            $stmt->execute([$id]);
            $hash = $stmt->fetchColumn();
            if (is_string($hash) && password_verify($pass, $hash)) {
                $_SESSION['nexora_agent_id'] = $id;
                header('Location: index.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'خطای دیتابیس.';
        }
    }
    if ($error === '') $error = 'آیدی یا رمز اشتباه است.';
    sleep(1);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#141a2e">
  <title>ورود نمایندگی — نکسورا</title>
  <link rel="stylesheet" href="../panel/css/style.css">
  <link rel="stylesheet" href="../panel/css/nexora-glass.css">
</head>
<body>
<div class="card fade-up" style="max-width:380px;margin:8vh auto;padding:24px">
  <div class="card-title">ورود پنل نمایندگی نکسورا</div>
  <div class="card-subtitle">رمز را از مدیر اصلی (داخل ربات: <code dir="ltr">/agentpass</code>) بگیرید</div>
  <?php if ($error !== ''): ?><p style="color:#e17076"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="POST">
    <div class="field"><label>آیدی عددی تلگرام</label><input type="text" name="id" class="input" dir="ltr" inputmode="numeric" required></div>
    <div class="field"><label>رمز عبور</label><input type="password" name="pass" class="input" required></div>
    <div class="modal-foot" style="padding:12px 0 0"><button type="submit" class="btn btn-primary">ورود</button></div>
  </form>
</div>
</body>
</html>
