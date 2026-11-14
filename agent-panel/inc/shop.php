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

if (!function_exists('nx_agent_manual_panels')) {
    /**
     * The panels whose accounts are not created by the api: the manual panels
     * (the administrator hands the accounts out by hand) and the panel of the
     * agent. A product of such a panel can not be bought in the panel of the
     * agent, so it is not in the list of the ready products.
     */
    function nx_agent_manual_panels(PDO $pdo): array
    {
        try {
            $st = $pdo->prepare("SELECT name_panel FROM marzban_panel WHERE type IN ('Manualsale','nexora_agent')");
            $st->execute();
            return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            error_log('nx_agent_manual_panels: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('nx_agent_products')) {
    /**
     * The products of the bot the agent may sell.
     *
     * Only the products of the bot itself are listed: the products of the two
     * panel shops and the test services are never in this list. The products
     * of a manual panel are not in the list either: they can not be created
     * by the api, so buying them would not work.
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
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('nx_agent_products: ' . $e->getMessage());
            return [];
        }
        $manualPanels = nx_agent_manual_panels($pdo);
        if (!$manualPanels) {
            return $rows;
        }
        // The products of a manual panel are left out: the account of such a
        // product is made by hand by the administrator.
        return array_values(array_filter($rows, function ($row) use ($manualPanels) {
            $location = trim((string) ($row['Location'] ?? ''));
            return $location === '' || $location === '/all' || !in_array($location, $manualPanels, true);
        }));
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

if (!function_exists('nx_agent_clean_username')) {
    /**
     * The name the agent writes down, cleaned up for the panel.
     *
     * The panels know the latin letters, the digits and a few signs only, so
     * everything else is dropped and the name is made small.
     */
    function nx_agent_clean_username(string $name): string
    {
        $name = strtolower(trim($name));
        $name = (string) preg_replace('/\s+/', '_', $name);
        $name = (string) preg_replace('/[^a-z0-9._-]+/', '', $name);
        $name = trim($name, "._-");
        if (strlen($name) > 24) {
            $name = rtrim(substr($name, 0, 24), "._-");
        }
        return $name;
    }
}

if (!function_exists('nx_agent_username_taken')) {
    /**
     * Is the name already used on the panel?
     *
     * The panel is asked with the very same helper the rest of the bot uses,
     * so a name that is taken on the panel is never used again.
     */
    function nx_agent_username_taken($ManagePanel, string $panelName, string $username): bool
    {
        try {
            $info = $ManagePanel->DataUser($panelName, $username);
        } catch (Throwable $e) {
            error_log('nx_agent_username_taken: ' . $e->getMessage());
            return false;
        }
        if (!is_array($info)) {
            return false;
        }
        if (trim((string) ($info['status'] ?? '')) === 'Unsuccessful') {
            // The panel does not know this name (or the panel did not answer).
            return false;
        }
        return trim((string) ($info['username'] ?? '')) !== '';
    }
}

if (!function_exists('nx_agent_panel_username')) {
    /**
     * The name of the account on the panel.
     *
     * The name of the customer is the base and a random suffix is added
     * behind it: the account is always created, even when the name is used
     * on the panel already. The suffix grows as long as the name is taken.
     */
    function nx_agent_panel_username($ManagePanel, string $panelName, string $base): string
    {
        $suffix = bin2hex(random_bytes(3));
        $name = $base . '_' . $suffix;
        $tries = 0;
        while ($tries < 8 && nx_agent_username_taken($ManagePanel, $panelName, $name)) {
            $suffix .= bin2hex(random_bytes(2));
            $name = $base . '_' . $suffix;
            $tries++;
        }
        return $name;
    }
}

if (!function_exists('nx_agent_find_customer')) {
    /**
     * The customer of a service of the agent, found by the name.
     *
     * The agent writes a name and never a telegram id, so the customer of a
     * service is the row of the bot that carries this name.
     */
    function nx_agent_find_customer(string $name, string $agentId): ?array
    {
        global $pdo;
        $name = trim($name);
        if ($name === '' || $agentId === '') {
            return null;
        }
        try {
            $st = $pdo->prepare("SELECT * FROM `user` WHERE affiliates = ? AND LOWER(username) = ? LIMIT 1");
            $st->execute([$agentId, strtolower($name)]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && $row ? $row : null;
        } catch (Throwable $e) {
            error_log('nx_agent_find_customer: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('nx_agent_new_customer_id')) {
    /**
     * A new id for a customer of the agent.
     *
     * The agent does not write a telegram id any more, so the row of the
     * customer gets an id of its own: it never clashes with a telegram id.
     */
    function nx_agent_new_customer_id(PDO $pdo): string
    {
        for ($i = 0; $i < 12; $i++) {
            $id = 'ag' . bin2hex(random_bytes(8));
            try {
                $st = $pdo->prepare("SELECT id FROM `user` WHERE id = ? LIMIT 1");
                $st->execute([$id]);
                if ($st->fetchColumn() === false) {
                    return $id;
                }
            } catch (Throwable $e) {
                return $id;
            }
        }
        return 'ag' . bin2hex(random_bytes(16));
    }
}

if (!function_exists('nx_agent_save_customer')) {
    /**
     * The row of the customer in the table of the bot.
     *
     * The row is bound to the agent, so the service shows up in the list of
     * the users of the agent panel.
     */
    function nx_agent_save_customer(string $id, string $name, string $agentId): ?array
    {
        global $pdo, $setting;
        $id = trim($id);
        $name = trim($name);
        if ($id === '' || $name === '' || $agentId === '') {
            return null;
        }
        try {
            // The keys are small: the names of the columns of MySQL do not
            // care about the case, so the row is always written the way the
            // table of the bot knows it.
            $data = [
                'id' => $id,
                'step' => 'none',
                'limit_usertest' => (int) ($setting['limit_usertest_all'] ?? 0),
                'user_status' => 'Active',
                'number' => 'none',
                'balance' => '0',
                'pagenumber' => '1',
                'username' => $name,
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
                'codeinvitation' => bin2hex(random_bytes(6)),
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
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return is_array($row) && $row ? $row : null;
        } catch (Throwable $e) {
            error_log('nx_agent_save_customer: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('nx_agent_service_panels')) {
    /**
     * The panels that can create an account automatically.
     *
     * The panel of the agent (اجنت پنل) and the manual panels are not part of
     * the list: a service of the agent has to be created by the API of the
     * panel, never by hand.
     */
    function nx_agent_service_panels(PDO $pdo): array
    {
        try {
            $st = $pdo->prepare("SELECT * FROM marzban_panel WHERE status = 'active' AND type <> 'nexora_agent' AND type <> 'Manualsale' ORDER BY display_order, id");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('nx_agent_service_panels: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('nx_agent_panel_candidates')) {
    /**
     * The panels a product can be created on: the panel of the product first
     * and then the other panels that sell services. The list is never empty
     * when the bot has a panel that sells services.
     */
    function nx_agent_panel_candidates(PDO $pdo, string $location): array
    {
        $candidates = [];
        $location = trim($location);
        if ($location !== '' && $location !== '/all') {
            try {
                $st = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1");
                $st->execute([$location]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row && (string) ($row['status'] ?? '') === 'active'
                    && !in_array((string) ($row['type'] ?? ''), ['nexora_agent', 'Manualsale'], true)) {
                    $candidates[] = $row;
                }
            } catch (Throwable $e) {
                error_log('nx_agent_panel_candidates: ' . $e->getMessage());
            }
        }
        foreach (nx_agent_service_panels($pdo) as $row) {
            $name = (string) ($row['name_panel'] ?? '');
            if ($name === '') {
                continue;
            }
            $known = false;
            foreach ($candidates as $candidate) {
                if ((string) ($candidate['name_panel'] ?? '') === $name) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                $candidates[] = $row;
            }
        }
        return $candidates;
    }
}

if (!function_exists('nx_agent_buy_product')) {
    /**
     * Buys a product of the bot for a customer of the agent.
     *
     * The agent writes the name of his customer (a name, never a telegram
     * id): the account is created on the panel of the product with this name
     * and a random suffix behind it, so the name is never in the way. The
     * wallet of the agent pays for it (with his discount) and the invoice is
     * written, so the service shows up in the list of the users of the panel
     * and in the operations of the panel.
     *
     * @return array{ok:bool,msg:string,detail:array}
     */
    function nx_agent_buy_product(array $agent, int $productId, string $username, float $percent): array
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
        // The customer: a name and nothing else. The name is cleaned up
        // before it is used on the panel.
        $rawName = trim($username);
        if ($rawName === '') {
            return ['ok' => false, 'msg' => 'نام کاربری مشتری را بنویسید.', 'detail' => []];
        }
        $cleanName = nx_agent_clean_username($rawName);
        if ($cleanName === '') {
            return ['ok' => false, 'msg' => 'نام کاربری باید انگلیسی باشد (حروف، عدد و _ . - ).', 'detail' => []];
        }
        if (strlen($cleanName) < 3) {
            return ['ok' => false, 'msg' => 'نام کاربری باید حداقل ۳ حرف باشد.', 'detail' => []];
        }
        // The panels the account can be created on. The panel of the product
        // comes first, the other panels of the bot are the backup: a service
        // of the agent is always created automatically.
        $productPanel = null;
        $productLocation = trim((string) ($product['Location'] ?? ''));
        if ($productLocation !== '' && $productLocation !== '/all') {
            try {
                $st = $pdo->prepare("SELECT * FROM marzban_panel WHERE name_panel = ? LIMIT 1");
                $st->execute([$productLocation]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $productPanel = $row;
                }
            } catch (Throwable $e) {
                error_log('nx_agent_buy_product (panel): ' . $e->getMessage());
            }
        }
        if ($productPanel && (string) ($productPanel['type'] ?? '') === 'Manualsale') {
            // A manual panel has a stock of accounts that the administrator
            // hands out by hand: it can not create an account by itself.
            return ['ok' => false, 'msg' => 'این محصول فروش دستی است و از این بخش قابل خریدنیست.', 'detail' => []];
        }
        $candidates = nx_agent_panel_candidates($pdo, $productLocation);
        if (!$candidates) {
            return ['ok' => false, 'msg' => 'هیچ پنل فعالی برای ساخت سرویس وجود ندارد.', 'detail' => []];
        }
        $agentKind = trim((string) ($agent['agent'] ?? 'f'));
        $allowed = [];
        foreach ($candidates as $candidate) {
            $candidateAgent = trim((string) ($candidate['agent'] ?? ''));
            if ($candidateAgent !== '' && $candidateAgent !== 'all' && $agentKind !== '' && $candidateAgent !== $agentKind) {
                // The panel is not for the role of this agent.
                continue;
            }
            $allowed[] = $candidate;
        }
        if (!$allowed) {
            return ['ok' => false, 'msg' => 'این محصول برای نقش شما در دسترس نیست.', 'detail' => []];
        }
        $panel = $allowed[0];
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
        // The customer of the service: the row of the bot that carries the
        // name. A customer that does not exist yet gets a row of his own, so
        // the service shows up in the list of the users of the panel.
        $customer = nx_agent_find_customer($cleanName, $agentId);
        $customerExists = $customer !== null;
        if (!$customerExists) {
            $customer = [
                'id' => nx_agent_new_customer_id($pdo),
                'username' => $cleanName,
                'affiliates' => $agentId,
            ];
        }
        $randomString = bin2hex(random_bytes(4));
        $username = '';
        $dataoutput = [];
        $lastError = '';
        $usedPanel = null;
        foreach ($allowed as $candidate) {
            $panelName = trim((string) ($candidate['name_panel'] ?? ''));
            if ($panelName === '') {
                continue;
            }
            // The name of the account: the name of the customer with a random
            // suffix behind it. The suffix grows as long as the name is used
            // on the panel, so the account is always created.
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $username = nx_agent_panel_username($ManagePanel, $panelName, $cleanName);
                try {
                    $days = (int) ($product['Service_time'] ?? 0);
                    $timestamp = $days > 0 ? strtotime(date("Y-m-d H:i:s", strtotime("+" . $days . "days"))) : 0;
                    $datac = [
                        'expire' => $timestamp,
                        'data_limit' => (float) ($product['Volume_constraint'] ?? 0) * pow(1024, 3),
                        'from_id' => (string) $customer['id'],
                        'username' => $cleanName,
                        'type' => 'buy',
                    ];
                    $dataoutput = $ManagePanel->createUser($panelName, (string) ($product['code_product'] ?? ''), $username, $datac);
                    if (!is_array($dataoutput) || empty($dataoutput['username'])) {
                        $lastError = trim((string) ($dataoutput['msg'] ?? 'اکانت روی پنل ساخته نشد.'));
                        throw new RuntimeException($lastError !== '' ? $lastError : 'اکانت روی پنل ساخته نشد.');
                    }
                    $username = (string) $dataoutput['username'];
                    $usedPanel = $candidate;
                    break;
                } catch (Throwable $e) {
                    error_log('nx_agent_buy_product: ' . $e->getMessage());
                    $lastError = $e->getMessage();
                    $dataoutput = [];
                }
            }
            if ($usedPanel !== null) {
                break;
            }
        }
        if ($usedPanel === null) {
            if ($price > 0) {
                $st = $pdo->prepare("UPDATE `user` SET Balance = Balance + ? WHERE id = ?");
                $st->execute([$price, $agentId]);
            }
            return [
                'ok' => false,
                'msg' => 'ساخت سرویس انجام نشد؛ مبلغ از کیف پول شما برداشته شد و برگشت داده شد.'
                    . ($lastError !== '' ? ' (' . $lastError . ')' : ''),
                'detail' => [],
            ];
        }
        $panel = $usedPanel;
        // The customer of the service is written down now: the service has to
        // show up in the list of the users of the panel.
        if (!$customerExists) {
            $saved = nx_agent_save_customer((string) $customer['id'], $cleanName, $agentId);
            if ($saved) {
                $customer = $saved;
            } else {
                error_log('nx_agent_buy_product: the row of the customer could not be written.');
            }
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
        $subscriptionUrl = (string) ($dataoutput['subscription_url'] ?? '');
        $configs = [];
        if ((string) ($panel['config'] ?? '') === 'onconfig' && is_array($dataoutput['configs'] ?? null)) {
            $configs = $dataoutput['configs'];
        }
        // The customer gets his service in the bot, exactly like a normal buy.
        // The customer of the agent has a name and not always a telegram id:
        // the service is only sent when the id of the customer is a telegram id.
        $customerId = (string) $customer['id'];
        $telegramId = is_numeric($customerId) && (int) $customerId > 0 ? $customerId : '';
        if ($telegramId !== '') {
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
                    sendmessage($telegramId, $text, null, 'HTML');
                }
            } catch (Throwable $e) {
                error_log('nx_agent_buy_product (message): ' . $e->getMessage());
            }
        }
        return [
            'ok' => true,
            'msg' => 'سرویس ساخته شد؛ مشخصات اتصال را به مشتری بدهید.',
            'detail' => [
                'username' => $username,
                'customer' => $cleanName,
                'link' => $link,
                'subscription_url' => $subscriptionUrl,
                'configs' => $configs,
                'price' => $price,
                'product' => nx_agent_plain_name((string) $product['name_product']),
                'panel' => (string) $panel['name_panel'],
            ],
        ];
    }
}
