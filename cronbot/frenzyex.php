<?php
ini_set('error_log', 'error_log');
date_default_timezone_set('Asia/Tehran');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../botapi.php';
require_once __DIR__ . '/../function.php';
require_once __DIR__ . '/../panels.php';
require_once __DIR__ . '/../jdf.php';
$ManagePanel = new ManagePanel();
$setting = select("setting", "*");
$textbotlang = languagechange();
$frenzyexStatus = getPaySettingValue('frenzyexstatus', 'offfrenzyex');
if ($frenzyexStatus !== 'onfrenzyex') exit;
$apiKey = getPaySettingValue('frenzyex_api_key', '');
$baseUrl = getPaySettingValue('frenzyex_base_url', 'https://frenzy.fastsnap.info');
if ($apiKey === '' || $baseUrl === '') exit;
$stmt = $pdo->prepare("SELECT * FROM Payment_report WHERE Payment_Method = 'frenzyex' AND payment_Status = 'pending' ORDER BY id ASC LIMIT 50");
$stmt->execute();
$pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($pending as $payment) {
    $requestId = (string) $payment['dec_not_confirmed'];
    if ($requestId === '') continue;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/v1/payments/' . $requestId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $apiKey, 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !is_string($response)) continue;
    $data = json_decode($response, true);
    if (!is_array($data)) continue;
    $status = $data['status'] ?? '';
    if ($status !== 'paid' && $status !== 'finished') continue;
    $orderId = (string) ($data['order_ref'] ?? $data['order_id'] ?? '');
    if ($orderId === '' || $orderId !== (string) $payment['id_order']) continue;
    $priceCurrency = strtoupper(trim((string) ($data['price_currency'] ?? '')));
    $priceAmount = $data['price_amount'] ?? null;
    if ($priceCurrency !== 'TMN' || !is_numeric($priceAmount) || abs((float) $priceAmount - (float) $payment['price']) > 0.001) continue;
    try { DirectPayment($payment['id_order'], __DIR__ . '/../images.jpg'); } catch (Throwable $e) { error_log('[frenzyex-cron] DirectPayment failed order=' . $payment['id_order'] . ' error=' . $e->getMessage()); }
}
