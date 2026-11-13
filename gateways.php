<?php

if (!function_exists('nexora_color_slugs')) {
    // the colour palette lives in nexora_style.php, this file needs it for the
    // colour of a gateway
    $__nexoraStyleLib = __DIR__ . '/nexora_style.php';
    if (is_file($__nexoraStyleLib)) {
        require_once $__nexoraStyleLib;
    }
}

// ---------------------------------------------------------------------------
// The payment gateways of the bot.
//
// One list for the bot (the admin menu and the payment keyboard of the buyer)
// and for the web panel (the finance page), so a gateway can only be
// configured in one place and the colour that the administrator picks in the
// panel is the colour the buyer sees in the bot.
//
// The colour of a gateway is stored in PaySetting under "nx_gw_style_<key>".
// ---------------------------------------------------------------------------
if (!function_exists('nexora_payment_gateways')) {
    function nexora_payment_gateways(): array
    {
        return [
            'card' => [
                'label' => 'کارت به کارت',
                'icon' => '💳',
                'setting' => 'Cartstatus',
                'on' => 'oncard',
                'off' => 'offcard',
                'style' => 'primary',
                'fields' => [
                    ['name' => 'CartDirect', 'label' => 'شناسه پرداخت', 'type' => 'text'],
                    ['name' => 'cardnumber', 'label' => 'شماره کارت', 'type' => 'text'],
                    ['name' => 'namecard', 'label' => 'نام صاحب کارت', 'type' => 'text'],
                    ['name' => 'chashbackcart', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalancecart', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalancecart', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'plisio' => [
                'label' => 'پلیسیو (Plisio)',
                'icon' => '🪙',
                'setting' => 'nowpaymentstatus',
                'on' => 'onnowpayment',
                'off' => 'offnowpayment',
                'style' => 'orange',
                'fields' => [
                    ['name' => 'apinowpayment', 'label' => 'کلید API', 'type' => 'text'],
                    ['name' => 'chashbackplisio', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalanceplisio', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalanceplisio', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'nowpayment' => [
                'label' => 'نوم‌پیمنت (NOWPayments)',
                'icon' => '💰',
                'setting' => 'statusnowpayment',
                'on' => '1',
                'off' => '0',
                'style' => 'indigo',
                'fields' => [
                    ['name' => 'marchent_tronseller', 'label' => 'کلید API', 'type' => 'text'],
                    ['name' => 'cashbacknowpayment', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalancenowpayment', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalancenowpayment', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'iranpay1' => [
                'label' => 'درگاه پرداخت ریالی دوم',
                'icon' => '💸',
                'setting' => 'statusSwapWallet',
                'on' => 'onSwapinoBot',
                'off' => 'offSwapinoBot',
                'style' => 'cyan',
                'fields' => [
                    ['name' => 'marchent_floypay', 'label' => 'کد API', 'type' => 'text'],
                    ['name' => 'chashbackiranpay1', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalanceiranpay1', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalanceiranpay1', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'iranpay2' => [
                'label' => 'درگاه پرداخت ریالی (CubePay)',
                'icon' => '💠',
                'setting' => 'statustarnado',
                'on' => 'onternado',
                'off' => 'offternado',
                'style' => 'purple',
                'fields' => [
                    ['name' => 'apiternado', 'label' => 'کلید API', 'type' => 'text'],
                    ['name' => 'feeternado', 'label' => 'کارمزد (درصد یا تومان)', 'type' => 'text'],
                    ['name' => 'chashbackiranpay2', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalanceiranpay2', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalanceiranpay2', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'iranpay3' => [
                'label' => 'درگاه پرداخت ریالی سوم',
                'icon' => '🏦',
                'setting' => 'statusiranpay3',
                'on' => 'oniranpay3',
                'off' => 'offiranpay3',
                'style' => 'teal',
                'fields' => [
                    ['name' => 'apiiranpay', 'label' => 'کد API', 'type' => 'text'],
                    ['name' => 'chashbackiranpay3', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalanceiranpay', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalanceiranpay', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'iranpay4' => [
                'label' => 'آبان گیت وی',
                'icon' => '🎫',
                'setting' => 'statusiranpay4',
                'on' => 'oniranpay4',
                'off' => 'offiranpay4',
                'style' => 'amber',
                'fields' => [
                    ['name' => 'apiiranpay4', 'label' => 'کلید API', 'type' => 'text'],
                    ['name' => 'endpointiranpay4', 'label' => 'آدرس درگاه', 'type' => 'text'],
                    ['name' => 'dailylimitiranpay4', 'label' => 'سقف روزانه (تومان)', 'type' => 'text'],
                    ['name' => 'chashbackiranpay4', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalanceiranpay4', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalanceiranpay4', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'aqayepardakht' => [
                'label' => 'آقای پرداخت',
                'icon' => '🔵',
                'setting' => 'statusaqayepardakht',
                'on' => 'onaqayepardakht',
                'off' => 'offaqayepardakht',
                'style' => 'info',
                'fields' => [
                    ['name' => 'merchant_id_aqayepardakht', 'label' => 'مرچنت‌کد', 'type' => 'text'],
                    ['name' => 'chashbackaqayepardakht', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalanceaqayepardakht', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalanceaqayepardakht', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'zarinpal' => [
                'label' => 'زرین‌پال',
                'icon' => '🟡',
                'setting' => 'zarinpalstatus',
                'on' => 'onzarinpal',
                'off' => 'offzarinpal',
                'style' => 'yellow',
                'fields' => [
                    ['name' => 'merchant_zarinpal', 'label' => 'مرچنت‌کد', 'type' => 'text'],
                    ['name' => 'chashbackzarinpal', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalancezarinpal', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalancezarinpal', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'variza' => [
                'label' => 'واریزا',
                'icon' => '🧾',
                'setting' => 'variza_status',
                'on' => 'onvariza',
                'off' => 'offvariza',
                'style' => 'rose',
                'fields' => [
                    ['name' => 'variza_api_token', 'label' => 'توکن API', 'type' => 'text'],
                    ['name' => 'variza_webhook_secret', 'label' => 'سکرت وب‌هوک', 'type' => 'text'],
                    ['name' => 'chashbackvariza', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalancevariza', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalancevariza', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'digi' => [
                'label' => 'پرداخت مستقیم ترون (TRX)',
                'icon' => '💵',
                'setting' => 'digistatus',
                'on' => 'ondigi',
                'off' => 'offdigi',
                'style' => 'lime',
                'fields' => [
                    ['name' => 'walletaddress', 'label' => 'آدرس کیف پول', 'type' => 'text'],
                    ['name' => 'minbalancedigitaltron', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalancedigitaltron', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'star' => [
                'label' => 'Star Telegram',
                'icon' => '⭐',
                'setting' => 'statusstar',
                'on' => '1',
                'off' => '0',
                'style' => 'violet',
                'fields' => [
                    ['name' => 'chashbackstar', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalancestar', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalancestar', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
            'frenzyex' => [
                'label' => 'فرنزی ایکس',
                'icon' => '💎',
                'setting' => 'frenzyexstatus',
                'on' => 'onfrenzyex',
                'off' => 'offfrenzyex',
                // The payment key of this gateway is green.
                'style' => 'success',
                'fields' => [
                    ['name' => 'frenzyex_api_key', 'label' => 'کلید پرداخت (API Key)', 'type' => 'text'],
                    ['name' => 'frenzyex_base_url', 'label' => 'آدرس درگاه', 'type' => 'text'],
                    ['name' => 'frenzyex_callback_secret', 'label' => 'سکرت کال‌بک', 'type' => 'text'],
                    ['name' => 'cashbackfrenzyex', 'label' => 'کش‌بک (درصد)', 'type' => 'text'],
                    ['name' => 'minbalancefrenzyex', 'label' => 'حداقل مبلغ (تومان)', 'type' => 'text'],
                    ['name' => 'maxbalancefrenzyex', 'label' => 'حداکثر مبلغ (تومان)', 'type' => 'text'],
                ],
            ],
        ];
    }
}

if (!function_exists('nexora_gateway_labels')) {
    /**
     * The name of every gateway. The administrator can change it in the web
     * panel, and the buyer then sees that name on the payment button.
     */
    function nexora_gateway_labels(): array
    {
        static $labels = null;
        if ($labels !== null) {
            return $labels;
        }
        $labels = [];
        foreach (nexora_payment_gateways() as $key => $gateway) {
            $labels[$key] = (string) ($gateway['label'] ?? $key);
        }
        try {
            if (function_exists('getPaySettingValue')) {
                foreach ($labels as $key => $label) {
                    $custom = trim((string) getPaySettingValue('nx_gw_label_' . $key, ''));
                    if ($custom !== '') {
                        $labels[$key] = $custom;
                    }
                }
            }
        } catch (Throwable $e) {
            // the defaults stay
        }
        return $labels;
    }
}

if (!function_exists('nexora_gateway_label')) {
    /**
     * The name of one gateway, the one the administrator chose.
     */
    function nexora_gateway_label(string $key): string
    {
        $labels = nexora_gateway_labels();
        $label = trim((string) ($labels[$key] ?? ''));
        if ($label === '') {
            $gateway = nexora_payment_gateways();
            $label = (string) ($gateway[$key]['label'] ?? $key);
        }
        return $label !== '' ? $label : $key;
    }
}

if (!function_exists('nexora_gateway_color_map')) {    /**
     * The colour of every gateway: the one the administrator picked in the web
     * panel, otherwise the default of the gateway.
     */
    function nexora_gateway_color_map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        $default = [];
        foreach (nexora_payment_gateways() as $key => $gateway) {
            $default[$key] = (string) ($gateway['style'] ?? '');
        }
        try {
            if (function_exists('getPaySettingValue')) {
                foreach ($default as $key => $style) {
                    $stored = trim((string) getPaySettingValue('nx_gw_style_' . $key, ''));
                    if ($stored !== '' && in_array($stored, nexora_color_slugs(), true)) {
                        $map[$key] = $stored;
                        continue;
                    }
                    $map[$key] = $style;
                }
                return $map;
            }
        } catch (Throwable $e) {
            // fall through to the defaults
        }
        $map = $default;
        return $map;
    }
}

if (!function_exists('nexora_gateway_style')) {
    /**
     * The colour slug of one gateway, e.g. "success" for the green one.
     */
    function nexora_gateway_style(string $key): string
    {
        $map = nexora_gateway_color_map();
        $style = (string) ($map[$key] ?? '');
        if ($style !== '' && in_array($style, nexora_color_slugs(), true)) {
            return $style;
        }
        return '';
    }
}

if (!function_exists('nexora_gateway_native_style')) {
    /**
     * The native Telegram button style of one gateway.
     */
    function nexora_gateway_native_style(string $key): string
    {
        $style = nexora_gateway_style($key);
        if ($style === '' || !function_exists('nexora_btn_style')) {
            return '';
        }
        return nexora_btn_style($style);
    }
}

if (!function_exists('nexora_gateway_is_usable')) {
    /**
     * True when a customer can pay with this gateway right now.
     *
     * The rules are the same as the ones of the payment keyboard of the bot:
     * the administrator has switched the gateway on *and* the keys the gateway
     * needs are there. A gateway that is shown without them would take the
     * buyer to a page that cannot be created.
     *
     * $context may carry "cardpayment" (the customer may only pay with the
     * card when the administrator allowed it for his account).
     */
    function nexora_gateway_is_usable(string $key, array $gateway, array $context = []): bool
    {
        if (!function_exists('nexora_pay_switch_is_on') || !function_exists('getPaySettingValue')) {
            return false;
        }
        $setting = (string) ($gateway['setting'] ?? '');
        if ($setting === '') {
            return false;
        }
        $current = (string) getPaySettingValue($setting, (string) ($gateway['off'] ?? ''));
        if (!nexora_pay_switch_is_on($current, (string) ($gateway['on'] ?? ''))) {
            return false;
        }
        // The card to card gateway is only for the accounts that may use it.
        if ($key === 'card' && isset($context['cardpayment']) && (int) $context['cardpayment'] !== 1) {
            return false;
        }
        $has = function (string $name): bool {
            $value = trim((string) getPaySettingValue($name, ''));
            return $value !== '' && $value !== '0';
        };
        switch ($key) {
            case 'iranpay4':
                return $has('apiiranpay4') && function_exists('abangatewayEndpoint') && abangatewayEndpoint() !== null;
            case 'variza':
                return $has('variza_api_token') && $has('variza_webhook_secret');
            case 'frenzyex':
                return $has('frenzyex_api_key') && $has('frenzyex_callback_secret') && $has('frenzyex_base_url');
        }
        return true;
    }
}

if (!function_exists('nexora_available_gateways')) {
    /**
     * The gateways a customer can pay with right now: the name, the icon, the
     * colour and the callback of the bot. The bot, the web panel and the web
     * application of the customer all use this list, so they always offer the
     * same gateways.
     */
    function nexora_available_gateways(array $context = []): array
    {
        $out = [];
        foreach (nexora_payment_gateways() as $key => $gateway) {
            if (!nexora_gateway_is_usable((string) $key, $gateway, $context)) {
                continue;
            }
            $out[$key] = [
                'key' => (string) $key,
                'label' => nexora_gateway_label((string) $key),
                'icon' => (string) ($gateway['icon'] ?? ''),
                'style' => (string) ($gateway['style'] ?? ''),
                'callback' => (string) $key,
            ];
        }
        return $out;
    }
}

