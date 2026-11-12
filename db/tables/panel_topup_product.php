<?php

return [
    'create' => <<<SQL
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        shop varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'agentshop',
        name varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price varchar(2000) NULL,
        credit varchar(2000) NULL,
        description TEXT NULL,
        unit VARCHAR(32) NULL,
        btn_style VARCHAR(20) NULL,
        btn_emoji_id VARCHAR(32) NULL,
        status_product varchar(20) NOT NULL DEFAULT 'active',
        display_order INT NOT NULL DEFAULT 0
        SQL,
    'columns' => [
        ['shop', 'agentshop', 'varchar(50)'],
        ['price', null, 'varchar(2000)'],
        ['credit', null, 'varchar(2000)'],
        ['description', '', 'TEXT'],
        ['unit', null, 'VARCHAR(32) NULL'],
        ['btn_style', null, 'VARCHAR(20) NULL'],
        ['btn_emoji_id', null, 'VARCHAR(32) NULL'],
        ['status_product', 'active', "VARCHAR(20) NOT NULL DEFAULT 'active'"],
        ['display_order', null, 'INT NOT NULL DEFAULT 0'],
    ],
];
