<?php

// The questions that the customer of the agent shop has to answer after the
// payment. The answers are sent to the administrator together with the
// details of the payment.
return [
    'create' => <<<SQL
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        shop varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'agentshop',
        question varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        status_product varchar(20) NOT NULL DEFAULT 'active',
        created_at INT UNSIGNED NOT NULL DEFAULT 0
        SQL,
    'columns' => [
        ['shop', 'agentshop', 'varchar(50)'],
        ['question', '', 'VARCHAR(1000)'],
        ['display_order', '0', 'INT NOT NULL DEFAULT 0'],
        ['status_product', 'active', "VARCHAR(20) NOT NULL DEFAULT 'active'"],
        ['created_at', '0', 'INT UNSIGNED NOT NULL DEFAULT 0'],
    ],
    'indexes' => [
        ['shop', 'idx_shop_question', '`shop`(50)'],
    ],
];
