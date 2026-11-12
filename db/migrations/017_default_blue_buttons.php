<?php

// Every button gets the blue colour as the default. A colour that was
// already chosen by the owner is never touched: only the buttons that have no
// colour at all receive the blue default.
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('PaySetting') || !$schema->tableExists('setting')) {
        return;
    }

    $texts = $schema->context('textbotlang');
    if (!is_array($texts)) {
        $texts = [];
    }

    // The colour of the main menu buttons is stored inside the keyboard
    // layout (setting.keyboardmain). Every button without a colour gets the
    // blue default there.
    $layout = null;
    $stmt = $pdo->prepare("SELECT keyboardmain FROM setting LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $layout = json_decode((string) ($row['keyboardmain'] ?? ''), true);
    }
    if (is_array($layout) && isset($layout['keyboard']) && is_array($layout['keyboard'])) {
        $changed = false;
        foreach ($layout['keyboard'] as $rowKey => $buttons) {
            if (!is_array($buttons)) {
                continue;
            }
            foreach ($buttons as $btnKey => $button) {
                if (!is_array($button) || !isset($button['text'])) {
                    continue;
                }
                if (isset($button['style']) && trim((string) $button['style']) !== '') {
                    continue;
                }
                $layout['keyboard'][$rowKey][$btnKey]['style'] = 'primary';
                $changed = true;
            }
        }
        if ($changed) {
            $encoded = json_encode($layout, JSON_UNESCAPED_UNICODE);
            if ($encoded !== false) {
                $update = $pdo->prepare("UPDATE setting SET keyboardmain = ?");
                $update->execute([$encoded]);
            }
        }
    }

    // The colours of the other buttons are stored as nx_bb_style_<key> rows.
    // The main menu keys are left out on purpose: their colour comes from the
    // keyboard layout, and an explicit row would overwrite it.
    $mainMenuKeys = [
        'text_sell', 'text_extend', 'text_usertest', 'text_wheel_luck',
        'text_Purchased_services', 'accountwallet', 'text_affiliates',
        'text_Tariff_list', 'text_support', 'text_help', 'text_agentpanel',
        'text_requestagent', 'text_agentshop', 'text_agentpanelshop',
    ];
    $keys = [];
    foreach (($texts['keyboard'] ?? []) as $key => $label) {
        if (is_string($label) && $label !== '') {
            $keys[] = (string) $key;
        }
    }
    if (isset($texts['users']['backbtn']) && is_string($texts['users']['backbtn']) && $texts['users']['backbtn'] !== '') {
        $keys[] = 'users_backbtn';
    }
    foreach (['backAdminBtn', 'backMenuBtn', 'backToShopMenu'] as $adminKey) {
        if (isset($texts['Admin'][$adminKey]) && is_string($texts['Admin'][$adminKey]) && $texts['Admin'][$adminKey] !== '') {
            $keys[] = 'Admin_' . $adminKey;
        }
    }
    $keys = array_values(array_unique(array_diff($keys, $mainMenuKeys)));
    if (!$keys) {
        return;
    }

    $existing = [];
    foreach ($pdo->query("SELECT NamePay FROM PaySetting WHERE NamePay LIKE 'nx_bb_style_%'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $existing[(string) ($r['NamePay'] ?? '')] = true;
    }
    $insert = $pdo->prepare("INSERT IGNORE INTO PaySetting (NamePay, ValuePay) VALUES (?, 'primary')");
    $added = 0;
    foreach ($keys as $key) {
        $name = 'nx_bb_style_' . $key;
        if (isset($existing[$name])) {
            continue;
        }
        $insert->execute([$name]);
        $added++;
    }
    if ($added > 0) {
        echo "Blue default colour set for {$added} buttons.\n";
    }
};
