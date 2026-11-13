<?php

// ---------------------------------------------------------------------------
// The products of the bot the agent can sell (محصولات آماده) and the purchase
// of them.
//
// The section looks like the shop of the Nexora site: the products are grouped
// by their category, every card comes in with the same movement and the
// premium emoji of the product is shown as a picture - never as the raw code
// of the tag.
// ---------------------------------------------------------------------------

if (!function_exists('nx_agent_columns')) {
    /**
     * The columns a table really has. A database that does not know a column
     * yet must never empty a list, so every optional part is checked first.
     */
    function nx_agent_columns(PDO $pdo, string $table): array
    {
        static $cache = [];
        $key = strtolower($table);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $st = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $st->execute([$table]);
            $columns = $st->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $columns = [];
        }
        $cache[$key] = $columns;
        return $columns;
    }
}

if (!function_exists('nx_agent_products')) {
    /**
     * The products of the bot the agent may sell.
     *
     * Only the products of the bot itself are listed: the products of the two
     * panel shops and the test services are never in this list.
     */
    function nx_agent_products(PDO $pdo): array
    {
        $columns = array_map('strtolower', nx_agent_columns($pdo, 'product'));
        $testFilter = in_array('is_test', $columns, true) ? ' AND is_test = 0' : '';
        $wanted = ['id', 'code_product', 'name_product', 'price_product', 'Volume_constraint', 'Service_time', 'Location', 'category', 'note', 'btn_emoji_id'];
        $selected = array_values(array_intersect($wanted, array_map('strtolower', $columns)));
        // A database that is still very old does not know every column: the
        // ones it has are enough for the list.
        if (!$selected) {
            $selected = ['id', 'name_product', 'price_product', 'Volume_constraint', 'Service_time', 'Location'];
        }
        $selected = array_map(function ($column) {
            return in_array($column, ['id', 'Volume_constraint', 'Service_time'], true) ? $column : '`' . $column . '`';
        }, $selected);
        $sql = "SELECT " . implode(', ', $selected) . " FROM product WHERE status_product = 'active' AND agent = 'f'" . $testFilter . " ORDER BY display_order, id LIMIT 300";
        try {
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('nx_agent_products: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('nx_agent_emoji_html')) {
    /**
     * A name that carries a premium emoji becomes HTML.
     *
     * The premium emoji is a tag only Telegram understands
     * (<tg-emoji emoji-id="123">🔥</tg-emoji>). The panel shows the very same
     * emoji as a picture, so the customer never sees the raw code.
     */
    function nx_agent_emoji_html(string $text): string
    {
        if ($text === '') {
            return '';
        }
        if (stripos($text, '<tg-emoji') === false) {
            return htmlspecialchars($text);
        }
        $result = '';
        $offset = 0;
        $pattern = '#<tg-emoji\b[^>]*\bemoji-id\s*=\s*"(\d+)"[^>]*>(.*?)</tg-emoji>#isu';
        while (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $result .= htmlspecialchars(substr($text, $offset, $start - $offset));
            $id = $m[1][0];
            $fallback = trim(strip_tags((string) $m[2][0]));
            $result .= '<img class="nx-emoji" src="https://t.me/i/emoji/' . htmlspecialchars($id) . '.webp"'
                . ' alt="' . htmlspecialchars($fallback) . '" title="' . htmlspecialchars($fallback) . '" loading="lazy">';
            $offset = $start + strlen((string) $m[0][0]);
        }
        $result .= htmlspecialchars(substr($text, $offset));
        return $result;
    }
}

if (!function_exists('nx_agent_emoji_image')) {
    /**
     * The picture of a premium emoji (the id of the emoji or the name of it).
     */
    function nx_agent_emoji_image(PDO $pdo, string $ref): string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return '';
        }
        if (preg_match('/^\d{1,32}$/', $ref)) {
            return 'https://t.me/i/emoji/' . $ref . '.webp';
        }
        $slug = trim($ref, '{}');
        if (stripos($slug, 'emoji:') === 0) {
            $slug = trim(substr($slug, 6));
        }
        if ($slug === '') {
            return '';
        }
        try {
            $st = $pdo->prepare("SELECT custom_emoji_id FROM bot_custom_emoji WHERE emoji_slug = ? OR emoji_name = ? LIMIT 1");
            $st->execute([$slug, $slug]);
            $id = (string) $st->fetchColumn();
        } catch (Throwable $e) {
            $id = '';
        }
        if (!preg_match('/^\d{1,32}$/', $id)) {
            return '';
        }
        return 'https://t.me/i/emoji/' . $id . '.webp';
    }
}

if (!function_exists('nx_agent_first_emoji')) {
    /**
     * The first premium emoji of a name: the id and the character it carries.
     */
    function nx_agent_first_emoji(string $text): array
    {
        if (preg_match('#<tg-emoji\b[^>]*\bemoji-id\s*=\s*"(\d+)"[^>]*>(.*?)</tg-emoji>#isu', $text, $m)) {
            return ['id' => $m[1], 'char' => trim(strip_tags($m[2]))];
        }
        return ['id' => '', 'char' => ''];
    }
}

if (!function_exists('nx_agent_plain_name')) {
    /**
     * The name of a product without the tags of the premium emoji.
     */
    function nx_agent_plain_name(string $name): string
    {
        $clean = trim((string) preg_replace('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', '$1', $name));
        $clean = trim((string) preg_replace('/\s{2,}/u', ' ', $clean));
        return $clean !== '' ? $clean : $name;
    }
}

if (!function_exists('nx_agent_palette')) {
    /**
     * The colours of the categories. Every category gets its own colour, the
     * same way the site of Nexora gives every country its own accent.
     */
    function nx_agent_palette(): array
    {
        return [
            ['c' => '#0e9488', 'rgb' => '14,148,136', 'c2' => '#0a6e65', 'e' => '📦'],
            ['c' => '#1d7ff2', 'rgb' => '29,127,242', 'c2' => '#1557b0', 'e' => '🌐'],
            ['c' => '#7c3aed', 'rgb' => '124,58,237', 'c2' => '#5b21b6', 'e' => '💎'],
            ['c' => '#f59e0b', 'rgb' => '245,158,11', 'c2' => '#b45309', 'e' => '⭐'],
            ['c' => '#e0405a', 'rgb' => '224,64,90', 'c2' => '#a52a3e', 'e' => '🔥'],
            ['c' => '#16a34a', 'rgb' => '22,163,74', 'c2' => '#0a7a38', 'e' => '🚀'],
        ];
    }
}

if (!function_exists('nx_agent_volume_text')) {
    /**
     * The volume of a product in a readable text (the volume of a product is
     * in gigabyte, exactly like in the bot).
     */
    function nx_agent_volume_text($volume): string
    {
        $gb = (float) $volume;
        if ($gb <= 0) {
            return 'نامحدود';
        }
        if ($gb < 1) {
            $mb = $gb * 1024;
            return rtrim(rtrim(number_format($mb, 2), '0'), '.') . ' مگابایت';
        }
        return rtrim(rtrim(number_format($gb, 2), '0'), '.') . ' گیگابایت';
    }
}

if (!function_exists('nx_agent_create_customer')) {
    /**
     * The customer of a service of the agent.
     *
     * A customer that does not exist yet is created (with the id of telegram
     * the agent writes down) and is bound to the agent, so the service shows
     * up in the list of the panel.
     */
    function nx_agent_create_customer(string $userId, string $agentId): ?array
    {
        global $pdo, $setting;
        $userId = trim($userId);
        if ($userId === '' || !preg_match('/^\d{1,32}$/', $userId)) {
            return null;
        }
        try {
            $st = $pdo->prepare("SELECT * FROM `user` WHERE id = ? LIMIT 1");
            $st->execute([$userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ((string) ($row['affiliates'] ?? '') !== $agentId) {
                    $pdo->prepare("UPDATE `user` SET affiliates = ? WHERE id = ?")->execute([$agentId, $userId]);
                    $row['affiliates'] = $agentId;
                }
                return $row;
            }
            $data = [
                'id' => $userId,
                'step' => 'none',
                'limit_usertest' => (int) ($setting['limit_usertest_all'] ?? 0),
                'User_Status' => 'Active',
                'number' => 'none',
                'Balance' => '0',
                'pagenumber' => '1',
                'username' => 'user' . substr($userId, -6),
                'agent' => 'f',
                'message_count' => '0',
                'last_message_time' => '0',
                'affiliates' => $agentId,
                'affiliatescount' => '0',
                'cardpayment' => (string) ($setting['showcard'] ?? '0'),
                'number_username' => '100',
                'namecustom' => 'none',
                'register' => time(),
                'verify' => 1,
                'codeInvitation' => bin2hex(random_bytes(6)),
                'pricediscount' => '0',
                'maxbuyagent' => '0',
                'joinchannel' => '0',
                'score' => '0',
                'status_cron' => '1',
            ];
            $columns = array_map('strtolower', nx_agent_columns($pdo, 'user'));
            if ($columns) {
                $data = array_intersect_key($data, array_flip($columns));
            }
            $names = implode(',', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $stmt = $pdo->prepare("INSERT IGNORE INTO user ({$names}) VALUES ({$placeholders})");
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();
            $st = $pdo->prepare("SELECT * FROM `user` WHERE id = ? LIMIT 1");
            $st->execute([$userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            error_log('nx_agent_create_customer: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('nx_agent_buy_product')) {
    /**
     * Buys a product of the bot for one of the customers of the agent.
     *
     * The wallet of the agent pays for it (with his discount), the account is
     * created on the panel of the product and the invoice is written, so the
     * service shows up in the list of the customer and in the operations of the
     * panel.
     *
     * @return array{ok:bool,msg:string,detail:array}
     */
    function nx_agent_buy_product(array $agent, int $productId, string $customerId, string $customName, float $percent, string $newUserId = ''): array
    {
        global $pdo, $ManagePanel;
        $agentId = (string) ($agent['id'] ?? '');
        if ($agentId === '') {
            return ['ok' => false, 'msg' => 'نماینده شناخته نشد.', 'detail' => []];
        }
        $st = $pdo->prepare("SELECT * FROM product WHERE id = ? AND status_product = 'active' LIMIT 1");
        $st->execute([$productId]);
        $product = $st->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return ['ok' => false, 'msg' => 'این محصول دیگر در دسترس نیست.', 'detail' => []];
        }
        if ((string) ($product['agent'] ?? '') !== 'f') {
            return ['ok' => false, 'msg' => 'این محصول متعلق به ربات نیست.', 'detail' => []];
        }
        if ((int) ($product['is_test'] ?? 0) === 1) {
            return ['ok' => false, 'msg' => 'سرویس‌های تست در این بخش نیستند.', 'detail' => []];
        }
        $customerId = trim($customerId);
        $newUserId = trim($newUserId);
        if ($customerId === '' && $newUserId !== '') {
            // A customer that does not exist yet is created and bound to the
            // agent.
            $created = nx_agent_create_customer($newUserId, $agentId);
            if (!$created) {
                return ['ok' => false, 'msg' => 'شناسه کاربر درست نیست.', 'detail' => []];
            }
            $customer = $created;
            $customerId = (string) $created['id'];
        } else {
            if ($customerId === '') {
                return ['ok' => false, 'msg' => 'کاربر را انتخاب کنید.', 'detail' => []];
            }
            $st = $pdo->prepare("SELECT * FROM `user` WHERE id = ? AND affiliates = ? LIMIT 1");
            $st->execute([$customerId, $agentId]);
            $customer = $st->fetch(PDO::FETCH_ASSOC);
            if (!$customer) {
                return ['ok' => false, 'msg' => 'این کاربر در پنل شما ثبت نشده است.', 'detail' => []];
            }
        }
        $location = trim((string) ($product['Location'] ?? ''));
        $panel = null;
        if ($location !== '' && $location !== '/all') {
            $panel = select("marzban_panel", "*", "name_panel", $location, "select");
        } else {
            // A product of every panel: the first panel that sells services
            // takes the account.
            try {
                $st = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND type <> 'nexora_agent' AND type <> 'Manualsale' ORDER BY display_order, id LIMIT 1");
                $st->execute();
                $panel = $st->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (Throwable $e) {
                $panel = null;
            }
        }
        if (!$panel || (string) ($panel['status'] ?? '') !== 'active') {
            return ['ok' => false, 'msg' => 'پنل این محصول در دسترس نیست.', 'detail' => []];
        }
        // The panel of the agent is not a panel that sells services.
        if ((string) ($panel['type'] ?? '') === 'nexora_agent') {
            return ['ok' => false, 'msg' => 'این محصول روی پنل نمایندگی ساخته نمی‌شود.', 'detail' => []];
        }
        $panelAgent = trim((string) ($panel['agent'] ?? ''));
        $agentKind = trim((string) ($agent['agent'] ?? 'f'));
        if ($panelAgent !== '' && $panelAgent !== 'all' && $agentKind !== '' && $panelAgent !== $agentKind) {
            return ['ok' => false, 'msg' => 'این محصول برای نقش شما در دسترس نیست.', 'detail' => []];
        }
        $price = (float) $product['price_product'];
        if ($percent > 0) {
            $price = $price - (($price * $percent) / 100);
        }
        $price = max(0, (int) round($price));
        if ($price > 0) {
            $st = $pdo->prepare("UPDATE `user` SET Balance = Balance - ? WHERE id = ? AND Balance >= ?");
            $st->execute([$price, $agentId, $price]);
            if ($st->rowCount() === 0) {
                return ['ok' => false, 'msg' => 'موجودی کیف پول شما برای این محصول کافی نیست.', 'detail' => []];
            }
        }
        $randomString = bin2hex(random_bytes(4));
        $username = '';
        $dataoutput = [];
        try {
            $username = generateUsername(
                (string) $customer['id'],
                (string) ($panel['MethodUsername'] ?? ''),
                (string) ($customer['username'] ?? ''),
                $randomString,
                $customName !== '' ? $customName : $randomString,
                (string) ($panel['namecustom'] ?? ''),
                (string) ($customer['namecustom'] ?? '')
            );
            $username = strtolower(trim((string) $username));
            if ($username === '') {
                throw new RuntimeException('نام کاربری ساخته نشد.');
            }
            $days = (int) ($product['Service_time'] ?? 0);
            $timestamp = $days > 0 ? strtotime(date("Y-m-d H:i:s", strtotime("+" . $days . "days"))) : 0;
            $datac = [
                'expire' => $timestamp,
                'data_limit' => (float) ($product['Volume_constraint'] ?? 0) * pow(1024, 3),
                'from_id' => (string) $customer['id'],
                'username' => (string) ($customer['username'] ?? ''),
                'type' => 'buy',
            ];
            $dataoutput = $ManagePanel->createUser((string) $panel['name_panel'], (string) ($product['code_product'] ?? ''), $username, $datac);
            if (!is_array($dataoutput) || empty($dataoutput['username'])) {
                throw new RuntimeException('اکانت روی پنل ساخته نشد.');
            }
            $username = (string) $dataoutput['username'];
        } catch (Throwable $e) {
            error_log('nx_agent_buy_product: ' . $e->getMessage());
            if ($price > 0) {
                $st = $pdo->prepare("UPDATE `user` SET Balance = Balance + ? WHERE id = ?");
                $st->execute([$price, $agentId]);
            }
            return ['ok' => false, 'msg' => 'ساخت سرویس انجام نشد؛ مبلغ از کیف پول شما برداشته شد و برگشت داده شد.', 'detail' => []];
        }
        // The invoice: the service belongs to the customer, the agent paid for it.
        $notifctions = json_encode(['volume' => false, 'time' => false]);
        $st = $pdo->prepare("INSERT IGNORE INTO invoice (id_user, id_invoice, username, time_sell, Service_location, name_product, price_product, Volume, Service_time, Status, note, refral, notifctions) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute([
            (string) $customer['id'],
            $randomString,
            $username,
            time(),
            (string) $panel['name_panel'],
            (string) $product['name_product'],
            $price,
            (float) ($product['Volume_constraint'] ?? 0),
            (int) ($product['Service_time'] ?? 0),
            'active',
            (string) ($product['note'] ?? ''),
            (string) ($customer['affiliates'] ?? '0'),
            $notifctions,
        ]);
        $link = '';
        if ((string) ($panel['sublink'] ?? '') === 'onsublink') {
            $link = (string) ($dataoutput['subscription_url'] ?? '');
        }
        $configs = [];
        if ((string) ($panel['config'] ?? '') === 'onconfig' && is_array($dataoutput['configs'] ?? null)) {
            $configs = $dataoutput['configs'];
        }
        // The customer gets his service in the bot, exactly like a normal buy.
        try {
            global $textbotlang;
            $text = (string) ($textbotlang['textbot']['afterPay'] ?? '');
            if ($text === '') {
                $text = "✅ سرویس با موفقیت ایجاد شد\n\n👤 نام کاربری سرویس: {username}\n🌿 نام سرویس: {name_service}\n📍 لوکیشن: {location}\n⏳ مدت زمان: {day} روز\n🗜 حجم سرویس: {volume} گیگابایت\n\nلینک اتصال:\n{config}\n{links}";
            }
            $text = str_replace('{username}', '<code>' . $username . '</code>', $text);
            $text = str_replace('{name_service}', nx_agent_plain_name((string) $product['name_product']), $text);
            $text = str_replace('{location}', (string) $panel['name_panel'], $text);
            $text = str_replace('{day}', (string) ($product['Service_time'] ?? 0), $text);
            $text = str_replace('{volume}', (string) ($product['Volume_constraint'] ?? 0), $text);
            $text = str_replace('{config}', '<code>' . $link . '</code>', $text);
            $text = str_replace('{links}', implode("\n", $configs), $text);
            $text = str_replace('{links2}', $link, $text);
            if (function_exists('sendmessage') && $text !== '') {
                sendmessage((string) $customer['id'], $text, null, 'HTML');
            }
        } catch (Throwable $e) {
            error_log('nx_agent_buy_product (message): ' . $e->getMessage());
        }
        return [
            'ok' => true,
            'msg' => 'سرویس ساخته شد و به کاربر تحویل داده شد.',
            'detail' => [
                'username' => $username,
                'link' => $link,
                'configs' => $configs,
                'price' => $price,
                'product' => nx_agent_plain_name((string) $product['name_product']),
                'panel' => (string) $panel['name_panel'],
            ],
        ];
    }
}
