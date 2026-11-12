<?php

// A product that was saved with a shortened panel name (without the premium
// emoji tag of the panel) does not belong to any panel, so it never shows up
// in the list of the products. The name of the panel is repaired here.
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('product') || !$schema->tableExists('marzban_panel')) {
        return;
    }
    try {
        $panels = $pdo->query("SELECT name_panel FROM marzban_panel")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return;
    }
    if (!$panels) {
        return;
    }
    $fix = 0;
    foreach ($panels as $panel) {
        $name = (string) ($panel['name_panel'] ?? '');
        if ($name === '' || stripos($name, '<tg-emoji') === false) {
            continue;
        }
        $plain = trim((string) preg_replace('#<tg-emoji\b[^>]*>(.*?)</tg-emoji>#isu', '$1', $name));
        if ($plain === '' || $plain === $name) {
            continue;
        }
        $stmt = $pdo->prepare("UPDATE product SET Location = ? WHERE Location = ?");
        $stmt->execute([$name, $plain]);
        $changed = $stmt->rowCount();
        if ($changed > 0) {
            $fix += $changed;
            echo "The panel of {$changed} product(s) was repaired.\n";
        }
    }
};
