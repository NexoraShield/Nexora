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
        if ($shop === 'agentpanelshop') {
            if ($id > 0) {
                $st = $pdo->prepare("UPDATE agent_panel_product SET name = ?, description = ?, btn_emoji_id = ? WHERE id = ?");
                $st->execute([$name, $description, $emoji !== '' ? $emoji : null, $id]);
                return $id;
            }
            $st = $pdo->prepare("INSERT INTO agent_panel_product (name, price, credit, description, btn_emoji_id, status_product, display_order) VALUES (?,0,0,?,?,'active',0)");
            $st->execute([$name, $description, $emoji !== '' ? $emoji : null]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE agent_panel_product SET display_order = id WHERE id = ?")->execute([$newId]);
            return $newId;
        }
        if ($id > 0) {
            $st = $pdo->prepare("UPDATE product SET name_product = ?, note = ?, btn_emoji_id = ? WHERE id = ? AND agent = 'agentshop'");
            $st->execute([$name, $description, $emoji !== '' ? $emoji : null, $id]);
            return $id;
        }
        $code = bin2hex(random_bytes(2));
        $st = $pdo->prepare("INSERT INTO product (name_product, code_product, price_product, Volume_constraint, Service_time, Location, agent, data_limit_reset, note, category, hide_panel, one_buy_status, status_product, btn_emoji_id) VALUES (?,?,0,0,0,'','agentshop','no_reset',?,'','{}','0','active',?)");
        $st->execute([$name, $code, $description, $emoji !== '' ? $emoji : null]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE product SET display_order = id WHERE id = ?")->execute([$newId]);
        return $newId;
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
