<?php

// ---------------------------------------------------------------------------
// The products of the agent shop (فروشگاه نمایندگی) and of the agent panel
// shop (اجنت پنل) can be edited inside the bot: the name (with a premium
// emoji), the description and the price.
// ---------------------------------------------------------------------------
if (!function_exists('nexora_shop_product_rows')) {
    /**
     * The products of a shop with the marker that is used in the callbacks.
     */
    function nexora_shop_product_rows(string $shop): array
    {
        if ($shop === 'agentpanelshop') {
            return function_exists('nexora_agent_panel_products') ? nexora_agent_panel_products(false) : [];
        }
        global $pdo;
        try {
            $st = $pdo->prepare("SELECT * FROM product WHERE agent = 'agentshop' ORDER BY display_order, id");
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('nexora_shop_product_name')) {
    function nexora_shop_product_name(array $row): string
    {
        return (string) ($row['name_product'] ?? ($row['name'] ?? ''));
    }
}

if (!function_exists('nexora_shop_product_description')) {
    function nexora_shop_product_description(array $row): string
    {
        return (string) ($row['note'] ?? ($row['description'] ?? ''));
    }
}

if (!function_exists('nexora_shop_product_price')) {
    function nexora_shop_product_price(array $row): float
    {
        return floatval($row['price_product'] ?? ($row['price'] ?? 0));
    }
}

if (!function_exists('nexora_shop_product_data')) {
    /**
     * The values of a product of a shop in one shape, so the bot and the web
     * panel work with the same fields.
     */
    function nexora_shop_product_data(string $shop, array $row): array
    {
        return [
            'name' => nexora_shop_product_name($row),
            'description' => nexora_shop_product_description($row),
            'emoji' => (string) ($row['btn_emoji_id'] ?? ''),
            'price' => nexora_shop_product_price($row),
            'credit' => floatval($row['credit'] ?? 0),
            'volume' => floatval($row['Volume_constraint'] ?? 0),
            'time' => intval($row['Service_time'] ?? 0),
            'panel' => (string) ($row['Location'] ?? ($row['panel_name'] ?? '')),
        ];
    }
}

if (!function_exists('nexora_shop_wizard_stages')) {
    /**
     * The steps of the wizard of a shop. The agent panel shop asks for the
     * credit of the panel, the agent shop for the volume and the duration.
     *
     * The product of the agent shop is not a service of a panel: the
     * administrator delivers it by hand, so there is no panel to choose.
     */
    function nexora_shop_wizard_stages(string $shop): array
    {
        if ($shop === 'agentpanelshop') {
            return ['name', 'credit', 'price', 'description'];
        }
        return ['name', 'price', 'volume', 'time', 'description'];
    }
}

if (!function_exists('nexora_shop_wizard_next_stage')) {
    /**
     * The step that follows the given one. The last step of every shop is the
     * description, so the wizard always ends there.
     */
    function nexora_shop_wizard_next_stage(string $shop, string $current): string
    {
        $stages = nexora_shop_wizard_stages($shop);
        $position = array_search($current, $stages, true);
        if ($position === false) {
            return 'description';
        }
        return $stages[$position + 1] ?? 'description';
    }
}

if (!function_exists('nexora_shop_product_save')) {
    /**
     * Writes a product of a shop.
     */
    function nexora_shop_product_save(string $shop, int $id, array $data): int
    {
        global $pdo;
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return 0;
        }
        $description = (string) ($data['description'] ?? '');
        $emoji = trim((string) ($data['emoji'] ?? ''));
        $price = floatval($data['price'] ?? 0);
        $panel = trim((string) ($data['panel'] ?? ''));
        if ($shop === 'agentpanelshop') {
            $credit = floatval($data['credit'] ?? 0);
            if ($id > 0) {
                $st = $pdo->prepare("UPDATE agent_panel_product SET name = ?, price = ?, credit = ?, description = ?, btn_emoji_id = ?, panel_name = ? WHERE id = ?");
                $st->execute([$name, $price, $credit, $description, $emoji !== '' ? $emoji : null, $panel !== '' ? $panel : null, $id]);
                return $id;
            }
            $st = $pdo->prepare("INSERT INTO agent_panel_product (name, price, credit, description, btn_emoji_id, panel_name, status_product, display_order) VALUES (?,?,?,?,?,?,'active',0)");
            $st->execute([$name, $price, $credit, $description, $emoji !== '' ? $emoji : null, $panel !== '' ? $panel : null]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE agent_panel_product SET display_order = id WHERE id = ?")->execute([$newId]);
            return $newId;
        }
        $volume = floatval($data['volume'] ?? 0);
        $time = intval($data['time'] ?? 0);
        // The product of the agent shop is delivered by hand: it does not
        // belong to a panel, so the column of the panel stays empty.
        $panel = '';
        if ($id > 0) {
            $st = $pdo->prepare("UPDATE product SET name_product = ?, price_product = ?, Volume_constraint = ?, Service_time = ?, Location = ?, note = ?, btn_emoji_id = ? WHERE id = ? AND agent = 'agentshop'");
            $st->execute([$name, $price, $volume, $time, $panel, $description, $emoji !== '' ? $emoji : null, $id]);
            return $id;
        }
        $code = bin2hex(random_bytes(2));
        // The placeholders are: name, code, price, volume, time, panel, note,
        // emoji - the other columns are written as a constant.
        $st = $pdo->prepare("INSERT INTO product (name_product, code_product, price_product, Volume_constraint, Service_time, Location, agent, data_limit_reset, note, category, hide_panel, one_buy_status, status_product, btn_emoji_id) VALUES (?,?,?,?,?,?,'agentshop','no_reset',?,'','{}','0','active',?)");
        $st->execute([$name, $code, $price, $volume, $time, $panel, $description, $emoji !== '' ? $emoji : null]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE product SET display_order = id WHERE id = ?")->execute([$newId]);
        return $newId;
    }
}

if (!function_exists('nexora_shop_panels')) {
    /**
     * The panels of the administrator. A product of the agent shop belongs to
     * one of them, so the customer sees the panel of the product he buys.
     */
    function nexora_shop_panels(): array
    {
        global $pdo;
        try {
            $st = $pdo->prepare("SELECT name_panel FROM marzban_panel ORDER BY name_panel");
            $st->execute();
            $panels = [];
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $panelName) {
                $panelName = trim((string) $panelName);
                if ($panelName !== '') {
                    $panels[] = $panelName;
                }
            }
            return $panels;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('nexora_shop_admin_list')) {
    /**
     * The list of the products of a shop for the admin menu.
     */
    function nexora_shop_admin_list(string $shop): array
    {
        $rows = [];
        foreach (nexora_shop_product_rows($shop) as $row) {
            $id = (int) ($row['id'] ?? 0);
            $name = nexora_shop_product_name($row);
            $rows[] = [
                'text' => '• ' . $name . ' — ' . number_format(nexora_shop_product_price($row)) . ' تومان'
                    . '  [✏️]',
                'callback_data' => 'adv_shop_edit_' . $shop . '_' . $id,
            ];
        }
        return $rows;
    }
}
