<?php
// includes/header.php

// Namespace istifadəsi (Notification üçün)
use App\Service\Notification;

// BASE_URL və ROOT_PATH bootstrap.php tərəfindən təyin edilməlidir
$base_url = defined('BASE_URL') ? BASE_URL : '';
$root_path = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__); // Fallback

// Cari modulu təyin etmək üçün (index.php-dən gəlir)
global $module;
$current_module = $module ?? 'dashboard';
$action = $action ?? 'index'; // Cari action-ı da alaq

// İstifadəçi məlumatları (əgər varsa)
$user_id_session = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_role = $_SESSION['user_role'] ?? null;

// === BİLDİRİŞ MƏLUMATLARINI YÜKLƏ ===
$unread_notification_count = 0;
$latest_notifications = [];
$notification_load_error = null;

if ($user_id_session) { // Yalnız daxil olmuş istifadəçilər üçün
    try {
        $notificationService = new Notification();
        $unread_notification_count = $notificationService->countUnreadNotifications($user_id_session) ?: 0;
        $latest_notifications = $notificationService->getUnreadNotificationsByUserId($user_id_session, 5) ?: []; // Son 5 oxunmamış
    } catch (\Exception | \Error $e) {
        $notification_load_error = "Bildirişlər yüklənərkən xəta baş verdi.";
        error_log("Header Notification Load Error: " . $e->getMessage());
        // Xəta olsa belə, səhifənin qalanı işləməlidir
    }
}
// === BİLDİRİŞ MƏLUMATLARINI YÜKLƏ SONU ===

?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Task Management</title>

    <!-- === PUSHER ÜÇÜN META TEQLƏR === -->
    <?php if (defined('PUSHER_ENABLED') && PUSHER_ENABLED === true && defined('PUSHER_KEY') && defined('PUSHER_CLUSTER')): ?>
        <meta name="pusher-key" content="<?= htmlspecialchars(PUSHER_KEY) ?>">
        <meta name="pusher-cluster" content="<?= htmlspecialchars(PUSHER_CLUSTER) ?>">
    <?php endif; ?>
    <?php if ($user_id_session): // İstifadəçi ID-sini ötür ?>
        <meta name="user-id" content="<?= htmlspecialchars($user_id_session) ?>">
    <?php endif; ?>
    <!-- === PUSHER ÜÇÜN META TEQLƏR SONU === -->

    <!-- Bootstrap CSS -->
    <link href="<?= $base_url ?>/assets/vendors/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome (İkonlar üçün) -->
    <link href="<?= $base_url ?>/assets/vendors/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700|Roboto:400,500,700&display=swap&subset=latin-ext" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= $base_url ?>/assets/css/style.css" rel="stylesheet">

</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar" class="sidebar">
            <div class="sidebar-header">
                <h3>Task Management</h3>
                <!-- Logo buraya əlavə edilə bilər -->
            </div>

            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_module === 'dashboard') ? 'active' : '' ?>" href="<?= $base_url ?>/dashboard">
                        <i class="fas fa-fw fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_module === 'tasks' && $action === 'create') ? 'active' : '' ?>" href="<?= $base_url ?>/tasks/create">
                       <i class="fas fa-fw fa-plus-circle"></i> Tapşırıq yarat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_module === 'tasks' && $action !== 'create') ? 'active' : '' ?>" href="<?= $base_url ?>/tasks">
                        <i class="fas fa-fw fa-tasks"></i> Tapşırıqlar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($current_module === 'statistics') ? 'active' : '' ?>" href="<?= $base_url ?>/statistics">
                        <i class="fas fa-fw fa-chart-bar"></i> Statistika
                    </a>
                </li>
                <!-- Admin və ya Menecer üçün görünən bölmələr -->
                <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_module === 'users') ? 'active' : '' ?>" href="<?= $base_url ?>/users">
                            <i class="fas fa-fw fa-users"></i> İstifadəçilər
                        </a>
                    </li>
                <?php endif; ?>
                <?php if ($user_role === 'admin'): // Yalnız Admin üçün ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_module === 'settings') ? 'active' : '' ?>" href="<?= $base_url ?>/settings">
                            <i class="fas fa-fw fa-cog"></i> Tənzimləmələr
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Əsas Məzmun Sahəsi -->
        <div id="content" class="content">

            <!-- Yuxarı Navbar (Header) -->
            <nav class="navbar navbar-expand navbar-light topbar mb-4 static-top shadow">
                <div class="container-fluid">

                    <!-- Mobil üçün Sidebar Toggle Düyməsi -->
                    <button type="button" id="sidebarCollapse" class="btn btn-primary d-md-none me-3">
                        <i class="fas fa-bars"></i>
                    </button>

                    <!-- Axtarış Formu (istəyə bağlı) -->
                    <!-- ... -->

                    <!-- Navbar-ın sağ tərəfi (Bildirişlər, Profil) -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Bildirişlər -->
                        <li class="nav-item dropdown no-arrow mx-1">
                            <a class="nav-link dropdown-toggle notification-bell" href="#" id="alertsDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-bell fa-fw"></i>
                                <!-- Counter - Alerts -->
                                <span class="badge bg-danger badge-counter notification-count" style="<?= $unread_notification_count > 0 ? '' : 'display: none;' ?>">
                                    <?= $unread_notification_count > 9 ? '9+' : $unread_notification_count ?>
                                </span>
                            </a>
                            <!-- Dropdown - Alerts -->
                            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in notification-dropdown-menu" aria-labelledby="alertsDropdown">
                                <h6 class="dropdown-header">
                                    Bildiriş Mərkəzi
                                    <?php if ($notification_load_error): ?>
                                        <span class="badge bg-danger ms-2">Xəta</span>
                                    <?php endif; ?>
                                </h6>
                                <div class="notification-items-container" style="max-height: 300px; overflow-y: auto;"> <?php // Scrollable container ?>
                                    <?php if (!empty($latest_notifications)): ?>
                                        <?php foreach ($latest_notifications as $notification): ?>
                                            <a class="dropdown-item d-flex align-items-center notification-item" href="<?= htmlspecialchars($notification['link'] ?? '#') ?>" data-notification-id="<?= $notification['id'] ?>">
                                                <div class="me-3">
                                                    <div class="icon-circle bg-primary"> <?php // İkon növə görə dəyişə bilər ?>
                                                        <i class="fas fa-bell text-white"></i>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="small text-gray-500"><?= htmlspecialchars(date('d.m.Y H:i', strtotime($notification['created_at']))) ?></div>
                                                    <span class="fw-bold"><?= htmlspecialchars($notification['message']) ?></span>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php elseif (!$notification_load_error): ?>
                                        <div class="dropdown-item text-center small text-gray-500">Oxunmamış bildiriş yoxdur.</div>
                                    <?php else: ?>
                                         <div class="dropdown-item text-center small text-danger"><?= htmlspecialchars($notification_load_error) ?></div>
                                    <?php endif; ?>
                                </div>
                                <a class="dropdown-item text-center small text-gray-500" href="#">Bütün Bildirişləri Göstər</a> <?php // Bu link ayrıca səhifəyə aparmalıdır ?>
                            </div>
                        </li>

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Profil -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="me-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($user_name) ?></span>
                                <img class="img-profile rounded-circle" src="<?= $base_url ?>/assets/img/undraw_profile.svg" width="30">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="<?= $base_url ?>/users/profile">
                                    <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                                    Profil
                                </a>
                                <?php // Tənzimləmələr linki yalnız admin üçün görünsün ?>
                                <?php if ($user_role === 'admin'): ?>
                                <a class="dropdown-item" href="<?= $base_url ?>/settings">
                                    <i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i>
                                    Tənzimləmələr
                                </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="<?= $base_url ?>/logout.php">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                                    Çıxış
                                </a>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>
            <!-- /Yuxarı Navbar -->

             <!-- Mobil üçün Overlay -->
             <div class="overlay"></div>

            <!-- Əsas Məzmun (Modullar buraya yüklənəcək) -->
            <div class="main-content">
                <!-- index.php tərəfindən require_once edilən modul faylının məzmunu burada görünəcək -->
