<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../jdf.php';
$from_id = 0; $message_id = 0;
require_once __DIR__ . '/../keyboard.php';
header('Content-Type: application/json; charset=utf-8');
function frenzyex_callback_response(int $status, array $body): void { http_response_code($status); echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') frenzyex_callback_response(405, ['ok' => false, 'error' => 'method_not_allowed']);
$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '') frenzyex_callback_response(400, ['ok' => false, 'error' => 'empty_body']);
$callbackSecret = trim((string) getPaySettingValue('frenzyex_callback_secret', ''));
if ($callbackSecret === '') { error_log('[frenzyex-callback] callback secret is not configured'); frenzyex_callback_response(503, ['ok' => false, 'error' => 'callback_not_configured']); }
$signature = trim((string) ($_SERVER['HTTP_X_FRENZY_SIGNATURE'] ?? ''));
$expectedSignature = hash_hmac('sha256', $rawBody, $callbackSecret);
if ($signature === '' || !hash_equals($expectedSignature, $signature)) { error_log('[frenzyex-callback] invalid signature'); frenzyex_callback_response(401, ['ok' => false, 'error' => 'invalid_signature']); }
$payload = json_decode($rawBody, true);
if (!is_array($payload)) frenzyex_callback_response(400, ['ok' => false, 'error' => 'invalid_json']);
$event = trim((string) ($payload['event'] ?? ''));
if ($event === 'payment.test') frenzyex_callback_response(200, ['ok' => true, 'test' => true]);
$isPaid = ($payload['status'] ?? '') === 'paid' || ($payload['payment_status'] ?? '') === 'finished';
if (!$isPaid) frenzyex_callback_response(200, ['ok' => true, 'ignored' => true]);
$orderId = trim((string) ($payload['order_ref'] ?? $payload['order_id'] ?? ''));
$requestId = trim((string) ($payload['request_id'] ?? $payload['payment_id'] ?? ''));
if ($orderId === '' || $requestId === '') frenzyex_callback_response(400, ['ok' => false, 'error' => 'missing_reference']);
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE id_order = ? AND Payment_Method = 'frenzyex' LIMIT 1");
$stmt->execute([$orderId]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($payment)) { error_log('[frenzyex-callback] order not found order=' . $orderId); frenzyex_callback_response(404, ['ok' => false, 'error' => 'order_not_found']); }
if (!hash_equals((string) ($payment['dec_not_confirmed'] ?? ''), $requestId)) { error_log('[frenzyex-callback] request mismatch order=' . $orderId); frenzyex_callback_response(409, ['ok' => false, 'error' => 'request_mismatch']); }
$priceCurrency = strtoupper(trim((string) ($payload['price_currency'] ?? '')));
$priceAmount = $payload['price_amount'] ?? null;
if ($priceCurrency !== 'TMN' || !is_numeric($priceAmount) || abs((float) $priceAmount - (float) $payment['price']) > 0.001) { error_log('[frenzyex-callback] amount mismatch order=' . $orderId); frenzyex_callback_response(409, ['ok' => false, 'error' => 'amount_mismatch']); }
if (($payment['payment_Status'] ?? '') === 'paid') frenzyex_callback_response(200, ['ok' => true, 'duplicate' => true, 'order_id' => $orderId]);
if (in_array(($payment['payment_Status'] ?? ''), ['reject', 'expire'], true)) frenzyex_callback_response(409, ['ok' => false, 'error' => 'order_closed']);
$ManagePanel = new ManagePanel();
$setting = select('setting', '*');
$textbotlang = languagechange();
try { $processed = DirectPayment($orderId, __DIR__ . '/../images.jpg'); } catch (Throwable $e) { error_log('[frenzyex-callback] processing exception order=' . $orderId . ' error=' . $e->getMessage()); frenzyex_callback_response(500, ['ok' => false, 'error' => 'processing_failed']); }
if (!$processed) {
    $check = $pdo->prepare('SELECT payment_Status FROM Payment_report WHERE id_order = ? LIMIT 1');
    $check->execute([$orderId]);
    if ((string) $check->fetchColumn() !== 'paid') { error_log('[frenzyex-callback] DirectPayment failed order=' . $orderId); frenzyex_callback_response(500, ['ok' => false, 'error' => 'processing_failed']); }
}
$paymentreports = select("topicid", "idreport", "report", "paymentreport", "select")['idreport'];
$Balance_id = select("user", "*", "id", $payment['id_user'], "select");
$pricecashback = select("PaySetting", "ValuePay", "NamePay", "cashbackfrenzyex", "select")['ValuePay'];
if ($pricecashback != "0") {
    $result = ($payment['price'] * $pricecashback) / 100;
    $Balance_confrim = intval($Balance_id['Balance']) + $result;
    update("user", "Balance", $Balance_confrim, "id", $Balance_id['id']);
    $pricecashback = number_format($pricecashback);
    $text_report = sprintf($textbotlang['paymentGateway']['giftReport'], $result);
    sendmessage($Balance_id['id'], $text_report, null, 'HTML');
}
$text_reportpayment = sprintf($textbotlang['paymentGateway']['reportFrenzyEx'] ?? "پرداخت FrenzyEx موفق\nکاربر: %s\nآیدی: %s\nمبلغ: %s تومان", $Balance_id['username'], $Balance_id['id'], $payment['price']);
if (strlen($setting['Channel_Report']) > 0) {
    telegram('sendmessage', ['chat_id' => $setting['Channel_Report'], 'message_thread_id' => $paymentreports, 'text' => $text_reportpayment, 'parse_mode' => "HTML"]);
}
frenzyex_callback_response(200, ['ok' => true, 'order_id' => $orderId]);
