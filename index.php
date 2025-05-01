<?php
// e:\xampp\htdocs\task_management\index.php (Refaktored Version 2 - Updated)

// 1. Bootstrap: Əsas funksionallıqları yüklə
require_once __DIR__ . '/bootstrap.php';

// 2. Namespace-lər (Controller-lər üçün)
use App\Controller\TaskController;
use App\Controller\UserController;
use App\Controller\SettingController;
use App\Controller\DashboardController;
use App\Controller\StatisticsController;
// use App\Controller\AuthController; // Login/Register üçün gələcəkdə

// 3. URL Analizi və Routing
$url = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL) ?? 'dashboard';
$url = trim($url, '/');
$url_parts = explode('/', $url);

// Modul, action və parametrləri təyin et
$module = preg_replace('/[^a-zA-Z0-9_-]/', '', $url_parts[0] ?? 'dashboard');
$action = preg_replace('/[^a-zA-Z0-9_-]/', '', $url_parts[1] ?? 'index');
$params = array_slice($url_parts, 2); // Qalan hissələr parametr kimi

// HTTP Metodunu təyin et
$requestMethod = $_SERVER['REQUEST_METHOD'];

// --- Sadə Routing Mexanizmi ---
$controllerName = null;
$methodName = null;

// Modula görə Controller təyin et
switch ($module) {
    case 'dashboard':
        $controllerName = DashboardController::class;
        $methodName = ($requestMethod === 'GET') ? 'index' : null;
        break;

    case 'tasks':
        $controllerName = TaskController::class;
        switch ($action) {
            case 'index': $methodName = ($requestMethod === 'GET') ? 'index' : null; break;
            case 'create': $methodName = ($requestMethod === 'GET') ? 'create' : (($requestMethod === 'POST') ? 'store' : null); break;
            case 'view': $methodName = ($requestMethod === 'GET') ? 'view' : null; break;
            case 'edit': $methodName = ($requestMethod === 'GET') ? 'edit' : (($requestMethod === 'POST') ? 'update' : null); break; // Gələcəkdə edit üçün
            // 'delete' və 'update-status' kimi actionlar URL-də deyil, POST datası ilə gələcək
            // Ona görə buradakı case-ləri silib, aşağıdakı POST yoxlamasına əlavə edirik.
            // case 'delete': ... silindi
            // case 'update-status': ... silindi
            default: if ($requestMethod === 'GET') $methodName = 'index'; // Default olaraq index
        }
        break;

    case 'users':
        $controllerName = UserController::class;
        switch ($action) {
            case 'index': $methodName = ($requestMethod === 'GET') ? 'index' : null; break;
            case 'create': $methodName = ($requestMethod === 'GET') ? 'create' : (($requestMethod === 'POST') ? 'store' : null); break;
            case 'profile': $methodName = ($requestMethod === 'GET') ? 'profile' : null; break;
            // case 'edit': $methodName = ($requestMethod === 'GET') ? 'edit' : (($requestMethod === 'POST') ? 'update' : null); break; // Gələcəkdə
            case 'update-status':
                 if ($requestMethod === 'POST' && (($_POST['action'] ?? '') === 'activate_user' || ($_POST['action'] ?? '') === 'deactivate_user')) $methodName = 'updateStatus';
                 break;
            // case 'delete': ... // Gələcəkdə
            default: if ($requestMethod === 'GET') $methodName = 'index';
        }
        break;

    case 'settings':
        $controllerName = SettingController::class;
        switch ($action) {
            case 'index': $methodName = ($requestMethod === 'GET') ? 'index' : null; break;
            // POST actionları üçün xüsusi URL-lər və ya action parametri istifadə edək
            case 'store-department': if ($requestMethod === 'POST') $methodName = 'storeDepartment'; break;
            case 'delete-department': if ($requestMethod === 'POST') $methodName = 'deleteDepartment'; break;
            case 'store-category': if ($requestMethod === 'POST') $methodName = 'storeCategory'; break;
            case 'delete-category': if ($requestMethod === 'POST') $methodName = 'deleteCategory'; break;
            // case 'edit-department': ...
            // case 'edit-category': ...
            default: if ($requestMethod === 'GET') $methodName = 'index';
        }
        break;

     case 'statistics':
        $controllerName = StatisticsController::class;
        switch ($action) {
            case 'index': $methodName = ($requestMethod === 'GET') ? 'index' : null; break;
            case 'export': $methodName = ($requestMethod === 'GET') ? 'export' : null; break; // CSV export üçün
            default: if ($requestMethod === 'GET') $methodName = 'index';
        }
        break;

    // --- Autentifikasiya Routları (Gələcəkdə AuthController ilə) ---
    // case 'login':
    // case 'register':
    // case 'logout':
    // case 'forgot-password':
    // case 'reset-password':
    //     $controllerName = AuthController::class;
    //     // ... metod təyin etmələri ...
    //     break;

    default:
        // Tanınmayan modul -> 404
        http_response_code(404);
        $controllerName = null; // Controller tapılmadı
        $methodName = null;
        // 404 səhifəsini göstər (aşağıda)
}

// --- POST Sorğularını $_POST['action'] ilə idarə et (əgər URL strukturunda təyin edilməyibsə) ---
if ($requestMethod === 'POST' && $methodName === null) {
    $postAction = $_POST['action'] ?? null;
    switch ($postAction) {
        // Settings Actions
        case 'create_department': $methodName = 'storeDepartment'; $controllerName = SettingController::class; break;
        case 'delete_department': $methodName = 'deleteDepartment'; $controllerName = SettingController::class; break;
        case 'create_category': $methodName = 'storeCategory'; $controllerName = SettingController::class; break;
        case 'delete_category': $methodName = 'deleteCategory'; $controllerName = SettingController::class; break;

        // Task Actions
        case 'delete_task': $methodName = 'delete'; $controllerName = TaskController::class; break;
        case 'finish_task': $methodName = 'updateStatus'; $controllerName = TaskController::class; break;
        case 'update_task_status': $methodName = 'updateStatus'; $controllerName = TaskController::class; break;
        case 'start_progress': $methodName = 'startProgress'; $controllerName = TaskController::class; break; // Yeni action

        // User Actions
        case 'activate_user':
        case 'deactivate_user': $methodName = 'updateStatus'; $controllerName = UserController::class; break;
    }
}

// --- Controller və Metodu Çağırmaq ---
if ($controllerName && class_exists($controllerName) && $methodName && method_exists($controllerName, $methodName)) {
    try {
        $controller = new $controllerName();
        call_user_func_array([$controller, $methodName], $params);
    } catch (\Throwable $e) {
        error_log("Controller Error ($controllerName->$methodName): " . $e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        $page_title = "Sistem Xətası";
        // Sadə xəta mesajı (header/footer olmadan)
        echo "<!DOCTYPE html><html><head><title>Xəta</title><link rel='stylesheet' href='".BASE_URL."/assets/css/bootstrap.min.css'></head><body>";
        echo "<div class='container mt-5'><div class='alert alert-danger'>Üzr istəyirik, sistemdə gözlənilməz xəta baş verdi. Ətraflı məlumat log faylına yazıldı.</div></div>";
        echo "</body></html>";
        exit;
    }
} else {
    // --- Əgər Controller/Metod tapılmasa ---
    // Bu hissə artıq yalnız 404 və ya köhnə, Controller-ə köçürülməmiş GET modulları üçündür
    // POST sorğuları artıq yuxarıdakı switch bloklarında idarə olunmalıdır.

    $module_file = ROOT_PATH . '/modules/' . $module . '/' . $action . '.php';

    if ($requestMethod === 'GET' && file_exists($module_file) && !in_array($module, ['dashboard', 'tasks', 'users', 'settings', 'statistics'])) {
        // Çox güman ki, bura heç vaxt işləməyəcək, çünki bütün əsas modullar Controller-ə keçirildi.
        // Amma hər ehtimala qarşı saxlayırıq.
        if (file_exists(ROOT_PATH . '/includes/header.php')) require ROOT_PATH . '/includes/header.php';
        require $module_file;
        if (file_exists(ROOT_PATH . '/includes/footer.php')) require ROOT_PATH . '/includes/footer.php';
    } else {
        // --- 404 Səhifəsi ---
        http_response_code(404);
        $page_title = "Səhifə Tapılmadı";

        // Header/Footer yükləmədən sadə 404
        echo "<!DOCTYPE html><html><head><title>404</title><link rel='stylesheet' href='".BASE_URL."/assets/css/bootstrap.min.css'></head><body>";
        if (file_exists(ROOT_PATH . '/includes/404.php')) {
            // 404.php faylına $page_title və $base_url ötürmək lazım ola bilər
            $base_url = BASE_URL;
            require ROOT_PATH . '/includes/404.php';
        } else {
            echo "<div class='container mt-5'><div class='alert alert-danger'><h1>404 - Səhifə Tapılmadı</h1><p>Axtardığınız səhifə mövcud deyil.</p><a href='" . BASE_URL . "'>Əsas səhifə</a></div></div>";
        }
        echo "</body></html>";
        exit;
    }
}

// Skriptin sonu
?>
