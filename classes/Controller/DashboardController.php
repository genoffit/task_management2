<?php
// e:\xampp\htdocs\task_management\classes\Controller\DashboardController.php

namespace App\Controller;

use App\Model\Task;
use App\Service\Statistics; // Statistik məlumatlar üçün

class DashboardController {

    private Task $taskModel;
    private Statistics $statisticsService;

    public function __construct() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        $this->taskModel = new Task();
        $this->statisticsService = new Statistics();
    }

    /**
     * Dashboard səhifəsini göstərir (GET /dashboard və ya /).
     */
    public function index(): void {
        $page_title = "İdarə Paneli";
        $base_url = BASE_URL;
        $currentUserId = $_SESSION['user_id'];
        $currentUserRole = $_SESSION['user_role'];
        $stats = [];
        $recentTasks = [];
        $error_message_load = null;

        try {
            // === DÜZƏLİŞ BURADA: getDashboardStats -> getDashboardCardStats ===
            // Statistik məlumatları alaq (Kartlar üçün olan metod)
            $stats = $this->statisticsService->getDashboardCardStats();
            // === DÜZƏLİŞ SONU ===

            // Son tapşırıqları alaq (məsələn, son 5)
            // getRecentTasks metodu artıq düzəldilib
            $recentTasks = $this->taskModel->getRecentTasks(5, $currentUserId, $currentUserRole) ?: [];

        } catch (\Exception | \Error $e) {
            $error_message_load = "İdarə paneli məlumatları yüklənərkən xəta baş verdi: " . htmlspecialchars($e->getMessage());
            error_log("Dashboard Load Error (Controller): " . $e->getMessage());
            // === DÜZƏLİŞ BURADA: Default dəyərləri getDashboardCardStats-a uyğunlaşdır ===
            $stats = [ // Xəta olarsa default dəyərlər
                'pending' => 0, 'in_progress' => 0, 'overdue' => 0,
                'due_today' => 0, 'created_last_7_days' => 0, 'total_active' => 0
            ];
            // === DÜZƏLİŞ SONU ===
            $recentTasks = [];
        }

        $this->loadView('dashboard/index', compact(
            'page_title', 'base_url', 'stats', 'recentTasks', 'error_message_load',
            'currentUserId', 'currentUserRole'
        ));
    }

    // --- Yardımçı Metodlar ---
    protected function loadView(string $viewPath, array $data = []): void {
        extract($data);
        global $module, $action, $params;
        if (file_exists(ROOT_PATH . '/includes/header.php')) require ROOT_PATH . '/includes/header.php';
        $fullViewPath = ROOT_PATH . '/modules/' . $viewPath . '.php';
        if (file_exists($fullViewPath)) require $fullViewPath;
        else { echo "<div class='alert alert-danger'>View file not found: " . htmlspecialchars($fullViewPath) . "</div>"; error_log("View file not found: " . $fullViewPath); }
        if (file_exists(ROOT_PATH . '/includes/footer.php')) require ROOT_PATH . '/includes/footer.php';
    }
}
