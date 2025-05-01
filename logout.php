<?php
// Bootstrap faylını daxil et (sessiyanı başlatmaq və konstantlar üçün)
require_once __DIR__ . '/bootstrap.php';

// Sessiya dəyişənlərini təmizlə
$_SESSION = array();

// Sessiya cookie-sini sil (əgər istifadə olunursa)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Keçmişə aid vaxt təyin et
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Sessiyanı məhv et
session_destroy();

// İstifadəçiyə çıxış mesajı göstər (isteğe bağlı)
if (function_exists('setFlashMessage')) {
    setFlashMessage('info', 'You have been logged out successfully.');
}

// Login səhifəsinə yönləndir
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '.') . '/login.php');
exit; // Yönləndirmədən sonra skriptin dayandırılması vacibdir
?>
