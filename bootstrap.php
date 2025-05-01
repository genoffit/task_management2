<?php
// bootstrap.php

// === DÜZƏLİŞ: ROOT_PATH tərifini başa köçür ===
// Əsas qovluq yolu (YALNIZ BURADA TƏYİN EDİLİR)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}
// === DÜZƏLİŞ SONU ===

// === DÜZƏLİŞ: Composer Autoloader-i başa köçür ===
// Composer Autoloader (MÜTLƏQ LAZIMDIR)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
} else {
    // Autoloader tapılmasa, layihə işləməyəcək
    $errorMsg = "FATAL ERROR: Composer autoloader not found at " . ROOT_PATH . '/vendor/autoload.php' . ". Please run 'composer install'.";
    error_log($errorMsg); // Xətanı logla
    die($errorMsg); // Proqramı dayandır
}
// === DÜZƏLİŞ SONU ===


// Monolog namespace-lərini indi istifadə edə bilərik
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter; // Format üçün

// Logger üçün ad təyin et (məsələn, tətbiqin adı)
$logChannelName = 'TaskManagementApp';
$logFilePath = ROOT_PATH . '/logs/app.log'; // Loq faylının yolu (ROOT_PATH artıq təyin edilib)
$logLevel = Logger::DEBUG; // Loq səviyyəsi (DEBUG, INFO, WARNING, ERROR və s.)

// Format təyin et (istəyə bağlı)
$logFormat = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
$dateFormat = "Y-m-d H:i:s";
$formatter = new LineFormatter($logFormat, $dateFormat, true, true); // true, true -> allowInlineLineBreaks, ignoreEmptyContextAndExtra

// Handler yarat (fayla yazmaq üçün)
$streamHandler = new StreamHandler($logFilePath, $logLevel);
$streamHandler->setFormatter($formatter); // Formatı tətbiq et

// Logger obyektini yarat
$logger = new Logger($logChannelName);
$logger->pushHandler($streamHandler);

// Logger obyektini qlobal olaraq əlçatan etmək (sadə üsul)
// Daha yaxşı üsul: Dependency Injection Container istifadə etmək
$GLOBALS['logger'] = $logger;

// Qeyd: `logs` qovluğunun mövcud olduğundan və yazma icazəsi olduğundan əmin olun.
if (!is_dir(dirname($logFilePath))) {
    // Qovluğu yarat (əgər yoxdursa) - İcazə problemi ola bilər
    @mkdir(dirname($logFilePath), 0775, true);
    // Yaradılıb-yaradılmadığını yoxla
    if (!is_dir(dirname($logFilePath))) {
        error_log("Failed to create logs directory: " . dirname($logFilePath) . ". Please check permissions.");
        // Loqlama işləməyəcək, amma proqram davam etsin
    }
}

// Loggerin işə düşdüyünü test etmək üçün (sonra silinə bilər)
$logger->info('Bootstrap initialized. Logger is active.');


// Xətaları göstərmək (Development üçün) - Production-da söndürün və loglayın
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sessiyanı həmişə başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Konfiqurasiya və Konstantlar
// DB konstantları üçün
if (file_exists(ROOT_PATH . '/config/database.php')) {
    require_once ROOT_PATH . '/config/database.php';
} else {
     $errorMsg = "FATAL ERROR: Database configuration file not found at " . ROOT_PATH . '/config/database.php';
     error_log($errorMsg);
     die($errorMsg);
}
// Digər konstantlar (BASE_URL, PUSHER_* və s.)
if (file_exists(ROOT_PATH . '/config/constants.php')) {
    require_once ROOT_PATH . '/config/constants.php';
} else {
     // Əgər constants.php yoxdursa, default BASE_URL təyin etməyə çalışaq
    if (!defined('BASE_URL')) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Host adı olmaya bilər (CLI)
        $script_dir = isset($_SERVER['SCRIPT_NAME']) ? str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']) : '/';
        define('BASE_URL', rtrim($protocol . $host . $script_dir, '/'));
        // Və ya manual: define('BASE_URL', 'http://localhost/task_management');
        if (isset($GLOBALS['logger'])) $GLOBALS['logger']->warning('constants.php not found. Guessed BASE_URL: ' . BASE_URL);
    }
    // Pusher və digər konstantlar üçün xəbərdarlıq vermək olar
    if (isset($GLOBALS['logger'])) $GLOBALS['logger']->warning("constants.php not found. Some features might not work.");
}

// Yardımçı funksiyalar (flash mesajlar, sanitize və s.)
if (file_exists(ROOT_PATH . '/includes/functions.php')) {
    require_once ROOT_PATH . '/includes/functions.php';
} else {
    // Əsas funksiyalar yoxdursa, problem ola bilər
    if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("includes/functions.php not found.");
    // Minimal funksiyaları burada təyin etmək olar (məsələn, sanitize)
    if (!function_exists('sanitize')) {
        function sanitize(?string $data): string {
            return strip_tags(trim($data ?? ''));
        }
    }
    // Flash mesaj funksiyaları olmadan sistem işləməyə bilər
    if (!function_exists('setFlashMessage')) { function setFlashMessage(string $type, string $message): void { $_SESSION['fallback_flash'] = "$type: $message"; } }
    if (!function_exists('displayFlashMessages')) { function displayFlashMessages(): void { if(isset($_SESSION['fallback_flash'])) { echo "<div class='alert alert-warning'>{$_SESSION['fallback_flash']}</div>"; unset($_SESSION['fallback_flash']); } } }
    if (!function_exists('generateCsrfToken')) { function generateCsrfToken(): string { return 'dummy_csrf_token'; } } // Təhlükəsiz deyil!
    if (!function_exists('validateCsrfToken')) { function validateCsrfToken(?string $token): bool { return true; } } // Təhlükəsiz deyil!
}

// CSRF funksiyaları (əgər ayrı fayldadırsa)
// if (file_exists(ROOT_PATH . '/includes/csrf_functions.php')) {
//     require_once ROOT_PATH . '/includes/csrf_functions.php';
// }

?>
