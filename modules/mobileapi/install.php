<?php

/**
 * جدول توكنات تطبيق الجوال: التوكن لا يُخزَّن خاماً أبداً - نخزّن SHA-256 له فقط،
 * وكل جهاز يحمل توكنه الخاص حتى يمكن إبطال جهاز واحد دون بقية أجهزة المستخدم.
 */
return function (PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mobile_api_tokens (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            device_name VARCHAR(190) NOT NULL DEFAULT '',
            platform VARCHAR(30) NOT NULL DEFAULT 'ios',
            push_token VARCHAR(255) NULL,
            last_used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            UNIQUE KEY uq_token_hash (token_hash),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
};
