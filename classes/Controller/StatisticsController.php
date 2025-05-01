<?php
// e:\xampp\htdocs\task_management\classes\Controller\StatisticsController.php

namespace App\Controller;

use App\Service\Statistics;
use App\Model\Department; // Filtr üçün
use App\Model\Category;   // Filtr üçün
use App\Model\User;       // Filtr üçün

class StatisticsController {

    private Statistics $statisticsService;
    private Department $departmentModel;
    private Category $categoryModel;
    private User $userModel;

    public function __construct() {
        checkRole(['admin', 'manager']); // Statistikaya yalnız admin və manager baxa bilər
        $this->statisticsService = new Statistics();
        $this->departmentModel = new Department();
        $this->categoryModel = new Category();
        $this->userModel = new User();
    }

    /**
     * Statistika səhifəsini göstərir (GET /statistics).
     */
    public function index(): void {
        $page_title = "Statistika";
        $base_url = BASE_URL;
        $statsData = [];
        $departments = [];
        $categories = [];
        $users = [];
        $error_message_load = null;

        // Filtr dəyərlərini alaq (TaskController-ə bənzər)
        $filters = [
            'department_id' => filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
            'category_id'   => filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
            'assignee_id'   => filter_input(INPUT_GET, 'assignee_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
            'status'        => filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS),
            'priority'      => filter_input(INPUT_GET, 'priority', FILTER_SANITIZE_SPECIAL_CHARS),
            'start_date'    => filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_SPECIAL_CHARS), // Tarix filtrləri
            'end_date'      => filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_SPECIAL_CHARS),
        ];
        $filters['status'] = ($filters['status'] === '') ? null : $filters['status'];
        $filters['priority'] = ($filters['priority'] === '') ? null : $filters['priority'];
        // Tarix formatını yoxlamaq olar
        $filters['start_date'] = (!empty($filters['start_date']) && strtotime($filters['start_date'])) ? date('Y-m-d', strtotime($filters['start_date'])) : null;
        $filters['end_date'] = (!empty($filters['end_date']) && strtotime($filters['end_date'])) ? date('Y-m-d', strtotime($filters['end_date'])) : null;

        $active_filters = array_filter($filters); // Yalnız dolu filtrləri götürək

        try {
            // Statistik məlumatları filtrlərlə alaq
            $statsData = $this->statisticsService->getDetailedStats($active_filters);

            // Filtr üçün dropdown məlumatları
            $departments = $this->departmentModel->getAllDepartments() ?: [];
            $categories = $this->categoryModel->getAllCategories() ?: [];
            $users = $this->userModel->getAllUsers() ?: []; // Və ya yalnız aktivlər

        } catch (\Exception | \Error $e) {
            $error_message_load = "Statistika məlumatları yüklənərkən xəta baş verdi: " . htmlspecialchars($e->getMessage());
            error_log("Statistics Load Error (Controller): " . $e->getMessage());
            $statsData = [ // Xəta olarsa default
                'tasks_by_status' => [],
                'tasks_by_priority' => [],
                'tasks_by_assignee' => [],
                'tasks_by_department' => [],
                'tasks_by_category' => [],
                'overdue_tasks_count' => 0,
            ];
        }

        $this->loadView('statistics/index', compact(
            'page_title', 'base_url', 'statsData', 'departments', 'categories', 'users',
            'error_message_load', 'filters' // View-da filtrləri göstərmək üçün
        ));
    }

    /**
     * Statistik məlumatları CSV formatında ixrac edir (GET /statistics/export).
     * (Sadə nümunə, təkmilləşdirilə bilər)
     */
    public function export(): void {
        // Filtr dəyərlərini alaq (index metodu ilə eyni)
        $filters = [
            'department_id' => filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
            'category_id'   => filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
            'assignee_id'   => filter_input(INPUT_GET, 'assignee_id', FILTER_VALIDATE_INT, ['options' => ['default' => null]]),
            'status'        => filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS),
            'priority'      => filter_input(INPUT_GET, 'priority', FILTER_SANITIZE_SPECIAL_CHARS),
            'start_date'    => filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_SPECIAL_CHARS),
            'end_date'      => filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_SPECIAL_CHARS),
        ];
        $filters['status'] = ($filters['status'] === '') ? null : $filters['status'];
        $filters['priority'] = ($filters['priority'] === '') ? null : $filters['priority'];
        $filters['start_date'] = (!empty($filters['start_date']) && strtotime($filters['start_date'])) ? date('Y-m-d', strtotime($filters['start_date'])) : null;
        $filters['end_date'] = (!empty($filters['end_date']) && strtotime($filters['end_date'])) ? date('Y-m-d', strtotime($filters['end_date'])) : null;
        $active_filters = array_filter($filters);

        try {
            // İxrac üçün tapşırıq siyahısını alaq (TaskModel istifadə edərək)
            $taskModel = new \App\Model\Task();
            // getTasks metodu filtrləri qəbul etməlidir
            $tasksToExport = $taskModel->getTasks($active_filters, $_SESSION['user_id'], $_SESSION['user_role']) ?: [];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=task_statistics_' . date('Y-m-d') . '.csv');
            $output = fopen('php://output', 'w');

            // Başlıq sətri (lazım olan sütunları əlavə edin)
            fputcsv($output, [
                'ID', 'Basliq', 'Tesvir', 'Departament', 'Kateqoriya', 'Icraci',
                'Prioritet', 'Status', 'Baslama Tarix', 'Son Icra Tarix', 'Yaradan', 'Yaranma Tarixi'
            ]);

            // Məlumat sətirləri
            foreach ($tasksToExport as $task) {
                fputcsv($output, [
                    $task['id'],
                    $task['title'],
                    $task['description'], // CSV üçün təhlükəli ola bilər, sanitiyasiya lazım ola bilər
                    $task['department_name'] ?? '',
                    $task['category_name'] ?? '',
                    $task['assignee_fullname'] ?? 'Təyin edilməyib',
                    translatePriority($task['priority']),
                    translateStatus($task['status']),
                    $task['start_date'],
                    $task['due_date'],
                    $task['creator_fullname'] ?? '',
                    $task['created_at']
                ]);
            }
            fclose($output);
            exit;

        } catch (\Exception | \Error $e) {
            error_log("Statistics Export Error (Controller): " . $e->getMessage());
            setFlashMessage('danger', 'Məlumatlar ixrac edilərkən xəta baş verdi.');
            header("Location: " . BASE_URL . "/statistics"); // Xəta olarsa statistika səhifəsinə qayıt
            exit;
        }
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
