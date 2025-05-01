<?php
// e:\xampp\htdocs\task_management\config\constants.php

// --- Əsas URL (Sərt Təyin Etmə) ---
define('BASE_URL', 'http://10.100.176.223/task_management');

// --- Verilənlər Bazası Konfiqurasiyası ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
// === DÜZƏLİŞ: Sizin işləyən versiyadakı DB adını istifadə edirik ===
define('DB_NAME', 'task_management');
// ==============================================================

// --- Pusher Konfiqurasiyası ---
define('PUSHER_ENABLED', true);
define('PUSHER_APP_ID', '1979982');
define('PUSHER_KEY', '7afa96ee941c0605d601');
define('PUSHER_SECRET', 'b07b8c6d48175ee89de9');
define('PUSHER_CLUSTER', 'ap2');

// --- Digər Konstantlar (əgər lazımdırsa) ---
// define('ROOT_PATH', dirname(__DIR__)); // bootstrap.php-də təyin olunur
// define('DB_CHARSET', 'utf8mb4');
// define('SESSION_NAME', 'task_mgmt_session');
// define('PASSWORD_RESET_TOKEN_EXPIRY', 3600);
// define('ITEMS_PER_PAGE', 10);

?>
