<?php

return function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS mobile_api_tokens');
};
