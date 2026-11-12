<?php

// Normalises the payment gateway switches.
//
// 1. "statusSwapWallet" (the second Rial gateway) was seeded with the legacy
//    value "offnSolutions". The admin menu rewrote every unknown value to
//    "onSwapinoBot", so a gateway the administrator had switched off showed up
//    again. Every value that is not the canonical "on" is now the canonical
//    "off".
// 2. The FrenzyEx base URL was seeded with the address of another installation.
//    It is only cleared when the gateway was never configured (no API key), so
//    a working installation keeps its own address.
return static function (PDO $pdo, Schema $schema): void {
    if (!$schema->tableExists('PaySetting')) {
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = 'statusSwapWallet' LIMIT 1");
        $stmt->execute();
        $value = $stmt->fetchColumn();
        if ($value !== false && $value !== null && (string) $value !== 'onSwapinoBot' && (string) $value !== 'offSwapinoBot') {
            $upd = $pdo->prepare("UPDATE PaySetting SET ValuePay = 'offSwapinoBot' WHERE NamePay = 'statusSwapWallet'");
            $upd->execute();
        }
    } catch (Throwable $e) {
        // the table row does not exist yet - nothing to repair
    }

    try {
        $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = 'frenzyex_api_key' LIMIT 1");
        $stmt->execute();
        $apiKey = trim((string) $stmt->fetchColumn());
        $stmt = $pdo->prepare("SELECT ValuePay FROM PaySetting WHERE NamePay = 'frenzyex_base_url' LIMIT 1");
        $stmt->execute();
        $baseUrl = trim((string) $stmt->fetchColumn());
        if ($apiKey === '' && $baseUrl !== '' && $baseUrl !== '0') {
            $upd = $pdo->prepare("UPDATE PaySetting SET ValuePay = '' WHERE NamePay = 'frenzyex_base_url'");
            $upd->execute();
        }
    } catch (Throwable $e) {
        // nothing to repair
    }
};
