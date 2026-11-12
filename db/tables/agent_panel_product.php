<?php

return [
    'create' => <<<SQL
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
        price varchar(2000) NULL,
        credit varchar(2000) NULL,
        description TEXT NULL,
        btn_style VARCHAR(20) NULL,
        btn_emoji_id VARCHAR(32) NULL,
        status_product varchar(20) NOT NULL DEFAULT 'active',
        display_order INT NOT NULL DEFAULT 0
        SQL,
    'columns' => [
        ['price', null, 'varchar(2000)'],
        ['credit', null, 'varchar(2000)'],
        ['description', '', 'TEXT'],
        ['btn_style', null, 'VARCHAR(20) NULL'],
        ['btn_emoji_id', null, 'VARCHAR(32) NULL'],
        ['status_product', 'active', "VARCHAR(20) NOT NULL DEFAULT 'active'"],
        ['display_order', null, 'INT NOT NULL DEFAULT 0'],
    ],
];
