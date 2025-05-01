<?php
// e:\xampp\htdocs\task_management\classes\Controller\TaskController.php

namespace App\Controller;

use App\Model\Task;
use App\Model\User;
use App\Model\Department;
use App\Model\Category;
use App\Model\Comment; // Comment modelini daxil et
use App\Model\TaskHistory; // TaskHistory modelini daxil et
// use App\Core\View; // View sinifi istifadə olunursa (əgər varsa)
// use App\Core\BaseController; // Əgər BaseController varsa

// class TaskController extends BaseController { // Əgər BaseController varsa
class TaskController {

    private $taskModel;
    private $userModel;
    private $departmentModel;
    private $categoryModel;
    private $commentModel; // Comment modeli üçün property
    private $taskHistoryModel; // TaskHistory modeli üçün property
    private $logger; // Logger üçün
    private $base_url; // BASE_URL üçün

    public function __construct() {
        // Modelleri yarat
        $this->taskModel = new Task();
        $this->userModel = new User();
        $this->departmentModel = new Department();
        $this->categoryModel = new Category();
        $this->commentModel = new Comment(); // Comment modelini yarat
        $this->taskHistoryModel = new TaskHistory(); // TaskHistory modelini yarat

        // Loggeri qlobal dəyişəndən al (və ya dependency injection ilə)
        $this->logger = $GLOBALS['logger'] ?? null;

        // BASE_URL konstantını al
        $this->base_url = defined('BASE_URL') ? BASE_URL : '';

        // Əgər BaseController varsa, onun constructor-unu çağır
        // parent::__construct();
    }

    /**
     * Helper method for redirection.
     * @param string $url The path to redirect to (relative to BASE_URL).
     */
    protected function redirect(string $url): void {
        // URL-in başlanğıcında '/' olub olmadığını yoxla
        if (strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }
        header('Location: ' . $this->base_url . $url);
        exit;
    }

    /**
     * Displays the task list page with filters.
     */
    public function index() {
        $page_title = "Tapşırıqlar";
        $error_message_load = null;
        $tasks = [];
        $departments = [];
        $categories = [];
        $users = []; // Assignable users
        $filters = []; // Filtri burada başlat
        $active_filters = []; // Aktiv filtri burada başlat

        $currentUserId = $_SESSION['user_id'] ?? null;
        $currentUserRole = $_SESSION['user_role'] ?? null;

        // Səlahiyyət yoxlaması
        if ($currentUserId === null) {
            setFlashMessage('error', 'Tapşırıqları görmək üçün daxil olmalısınız.');
            $this->redirect('/login.php');
        }

        try {
            // Filterləri GET sorğusundan al və təmizlə (sanitize)
            $filters['department_id'] = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT) ?: null;
            $filters['category_id'] = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null;
            // assignee_id üçün 'everyone' və ya rəqəm ola bilər, ona görə fərqli yanaşma
            $assignee_filter_input = filter_input(INPUT_GET, 'assignee_id', FILTER_SANITIZE_SPECIAL_CHARS);
            if ($assignee_filter_input === null || $assignee_filter_input === '') {
                $filters['assignee_id'] = null; // Boş və ya yoxdursa null
            } elseif ($assignee_filter_input === 'everyone') {
                 $filters['assignee_id'] = 'everyone'; // 'everyone' string olaraq saxla
            } else {
                 $filters['assignee_id'] = filter_var($assignee_filter_input, FILTER_VALIDATE_INT) ?: null; // Rəqəm deyilsə null
            }
            $filters['status'] = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
            $filters['priority'] = filter_input(INPUT_GET, 'priority', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
            // Digər filtrlər də əlavə edilə bilər (search, date range vs.)

            // Aktiv filtrləri təyin et (null olmayanlar)
            $active_filters = array_filter($filters, function($value) { return $value !== null; });

            // Məlumatları yüklə
            $tasks = $this->taskModel->getTasks($filters, $currentUserId, $currentUserRole);
            $departments = $this->departmentModel->getAllDepartments();
            $categories = $this->categoryModel->getAllCategories();
            $users = $this->userModel->getAssignableUsers(); // Yalnız manager və user rollu aktiv istifadəçilər

            // Model metodlarının false qaytarıb qaytarmadığını yoxla
            if ($tasks === false || $departments === false || $categories === false || $users === false) {
                $error_message_load = "Tapşırıq məlumatları yüklənərkən xəta baş verdi.";
                if ($this->logger) $this->logger->error("Failed to load data for task index page. One or more models returned false.");
                // Xəta varsa, array-ləri boşaltmaq daha təhlükəsizdir
                $tasks = []; $departments = []; $categories = []; $users = [];
            }

        } catch (\Exception | \Error $e) {
            $error_message_load = "Tapşırıqlar yüklənərkən sistem xətası baş verdi.";
            if ($this->logger) $this->logger->error("Error loading task index data: " . $e->getMessage(), ['exception' => $e]);
            // Xəta baş verdikdə array-ları boşaltmaq daha təhlükəsizdir
            $tasks = []; $departments = []; $categories = []; $users = [];
        }

        // View üçün base_url dəyişənini təyin et
        $base_url = $this->base_url;

        // View faylını yüklə və dəyişənləri ötür
        $viewData = compact(
            'page_title', 'tasks', 'departments', 'categories', 'users',
            'error_message_load', 'filters', 'active_filters',
            'currentUserId', 'currentUserRole', 'base_url' // base_url-i də ötürək
        );
        $this->loadView('tasks/index', $viewData);
    }

    /**
     * Displays the task creation form.
     */
    public function create() {
        // Səlahiyyət yoxlaması (əgər lazımdırsa, məsələn yalnız admin/manager yarada bilər)
        if (!isset($_SESSION['user_id'])) {
             setFlashMessage('error', 'Yeni tapşırıq yaratmaq üçün daxil olmalısınız.');
             $this->redirect('/login.php');
        }
        // checkRole(['admin', 'manager']); // Əgər yalnız bu rollar yarada bilərsə

        $page_title = "Yeni Tapşırıq Yarat";
        $data_load_error = null;
        $departments = [];
        $categories = [];
        $assignableUsers = [];

        // Form xətaları və datası üçün sessiyadan məlumatları al
        $form_errors = $_SESSION['form_errors'] ?? [];
        $form_data = $_SESSION['form_data'] ?? [];
        unset($_SESSION['form_errors'], $_SESSION['form_data']);

        try {
            $departments = $this->departmentModel->getAllDepartments();
            $categories = $this->categoryModel->getAllCategories();
            $assignableUsers = $this->userModel->getAssignableUsers();

            // Model metodlarının false qaytarıb qaytarmadığını yoxla
            if ($departments === false || $categories === false || $assignableUsers === false) {
                $data_load_error = "Form məlumatları yüklənərkən verilənlər bazası xətası baş verdi.";
                if ($this->logger) $this->logger->error("Failed to load data for task creation form. One or more models returned false.");
                // Xəta varsa, array-ləri boşaltmaq daha təhlükəsizdir
                $departments = []; $categories = []; $assignableUsers = [];
            }
            // Əgər xəta yoxdursa, amma siyahılar boşdursa, xəbərdarlıq et
            elseif (empty($departments) || empty($categories)) {
                 // $data_load_error dəyişənini təyin etməyək ki, form göstərilsin amma xəbərdarlıqla
                 // View faylı özü bu vəziyyəti idarə edəcək.
                 if ($this->logger) $this->logger->warning("Cannot create task effectively: Departments or categories are missing in DB.");
            }

        } catch (\Exception | \Error $e) {
            $data_load_error = "Form məlumatları yüklənərkən gözlənilməyən sistem xətası baş verdi.";
            if ($this->logger) $this->logger->error("Error loading data for task creation form: " . $e->getMessage(), ['exception' => $e]);
            // Xəta baş verdikdə array-ları boşaltmaq daha təhlükəsizdir
            $departments = []; $categories = []; $assignableUsers = [];
        }

        // View üçün base_url dəyişənini təyin et
        $base_url = $this->base_url;

        $viewData = compact(
            'page_title', 'departments', 'categories', 'assignableUsers',
            'data_load_error', 'form_errors', 'form_data', 'base_url'
        );
        $this->loadView('tasks/create', $viewData);
    }

    /**
     * Stores a new task submitted from the create form.
     */
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/tasks/create');
        }

        // Səlahiyyət yoxlaması
        if (!isset($_SESSION['user_id'])) {
             setFlashMessage('error', 'Tapşırıq yaratmaq üçün daxil olmalısınız.');
             $this->redirect('/login.php');
        }
        // checkRole(['admin', 'manager']); // Əgər yalnız bu rollar yarada bilərsə

        // CSRF yoxlaması
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            setFlashMessage('error', 'Təhlükəsizlik xətası. Sorğu qəbul edilmədi.');
            $this->redirect('/tasks/create');
        }

        // Form məlumatlarını al və təmizlə
        $data = $_POST;
        $errors = [];

        $title = sanitize($data['title'] ?? '');
        $description = sanitize($data['description'] ?? '');
        $priority = sanitize($data['priority'] ?? '');
        $department_id = filter_var($data['department_id'] ?? '', FILTER_VALIDATE_INT);
        $category_id = filter_var($data['category_id'] ?? '', FILTER_VALIDATE_INT);
        $assignee_id_input = $data['assignee_id'] ?? 'everyone'; // Default 'everyone'
        $start_date = sanitize($data['start_date'] ?? '');
        $due_date = sanitize($data['due_date'] ?? '');
        $currentUserId = $_SESSION['user_id']; // Yaradan istifadəçi

        // Assignee ID-ni təyin et
        $assignee_id = null; // Default olaraq NULL (yəni 'everyone')
        if ($assignee_id_input !== 'everyone') {
            $assignee_id = filter_var($assignee_id_input, FILTER_VALIDATE_INT);
            if ($assignee_id === false || $assignee_id <= 0) {
                $errors['assignee_id'] = 'Keçərsiz icraçı seçilib.';
                $assignee_id = null; // Xəta varsa null et
            }
            // TODO: Seçilmiş assignee_id-nin həqiqətən assignableUsers arasında olub olmadığını yoxla
        }

        // Validasiya
        if (empty($title)) $errors['title'] = 'Başlıq mütləqdir.';
        if (mb_strlen($title) > 255) $errors['title'] = 'Başlıq çox uzundur (maksimum 255 simvol).';
        if (empty($description)) $errors['description'] = 'Təsvir mütləqdir.';
        if (!in_array($priority, ['low', 'medium', 'high'])) $errors['priority'] = 'Düzgün prioritet seçin.';
        if ($department_id === false || $department_id <= 0) $errors['department_id'] = 'Düzgün şöbə seçin.';
        // TODO: Seçilmiş department_id-nin DB-də mövcudluğunu yoxla
        if ($category_id === false || $category_id <= 0) $errors['category_id'] = 'Düzgün kateqoriya seçin.';
        // TODO: Seçilmiş category_id-nin DB-də mövcudluğunu yoxla
        if (empty($due_date)) $errors['due_date'] = 'Son icra tarixi mütləqdir.';
        elseif (!validateDate($due_date)) $errors['due_date'] = 'Son icra tarixi düzgün formatda deyil (YYYY-MM-DD).';

        if (!empty($start_date)) {
            if (!validateDate($start_date)) $errors['start_date'] = 'Başlanğıc tarixi düzgün formatda deyil (YYYY-MM-DD).';
            elseif (!empty($due_date) && validateDate($due_date) && strtotime($start_date) > strtotime($due_date)) {
                $errors['start_date'] = 'Başlanğıc tarixi son icra tarixindən sonra ola bilməz.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $data;
            setFlashMessage('danger', 'Zəhmət olmasa, formadakı xətaları düzəldin.');
            $this->redirect('/tasks/create');
        }

        // Verilənlər bazasına yazmaq üçün datanı hazırla
        $taskData = [
            'title' => $title,
            'description' => $description,
            'department_id' => $department_id,
            'category_id' => $category_id,
            'assignee_id' => $assignee_id, // NULL (everyone) və ya user ID
            'priority' => $priority,
            'status' => 'pending', // Yeni tapşırıq default olaraq 'pending'
            'start_date' => !empty($start_date) ? $start_date : null,
            'due_date' => $due_date,
            // 'created_by' => $currentUserId, // DB-də sütun olmadığı üçün çıxarıldı
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        try {
            $taskId = $this->taskModel->createTask($taskData);
            if ($taskId) {
                // Tarixçəyə qeyd əlavə et
                $this->taskHistoryModel->addHistoryEntry($taskId, $currentUserId, 'created', "Tapşırıq yaradıldı: " . $title);

                if ($this->logger) $this->logger->info('New task created successfully.', ['task_id' => $taskId, 'title' => $title, 'created_by' => $currentUserId]);
                setFlashMessage('success', 'Yeni tapşırıq uğurla yaradıldı.');
                $this->redirect('/tasks'); // Tapşırıq siyahısına yönləndir
            } else {
                if ($this->logger) $this->logger->error('Failed to create new task in DB. TaskModel::createTask returned false.', ['data' => $taskData]);
                $_SESSION['form_data'] = $data;
                setFlashMessage('danger', 'Tapşırıq verilənlər bazasına yazıla bilmədi.');
                $this->redirect('/tasks/create');
            }
        } catch (\Exception | \Error $e) {
            if ($this->logger) $this->logger->critical('Exception during task creation.', ['exception' => $e->getMessage(), 'data' => $taskData]);
            $_SESSION['form_data'] = $data;
            setFlashMessage('danger', 'Tapşırıq yaradılarkən gözlənilməyən sistem xətası baş verdi.');
            $this->redirect('/tasks/create');
        }
    }

    /**
     * Displays the details of a specific task.
     * @param int $id Task ID from URL parameter.
     */
    public function view(int $id) {
        // Səlahiyyət yoxlaması
        if (!isset($_SESSION['user_id'])) {
             setFlashMessage('error', 'Tapşırığa baxmaq üçün daxil olmalısınız.');
             $this->redirect('/login.php');
        }

        $page_title = "Tapşırıq Detalları";
        $taskData = null;
        $error_message = null;
        $comments = []; // Default boş array
        $history = []; // Default boş array
        $currentUserId = $_SESSION['user_id'];
        $currentUserRole = $_SESSION['user_role'];

        if ($id <= 0) {
             $error_message = "Yanlış tapşırıq ID.";
             if ($this->logger) $this->logger->warning("Invalid task ID requested in view.", ['id' => $id]);
             http_response_code(400); // Bad Request
        } else {
            try {
                $taskData = $this->taskModel->getTaskById($id);
                if (!$taskData) {
                    $error_message = "Tapşırıq tapılmadı.";
                    if ($this->logger) $this->logger->warning("Task not found for view.", ['id' => $id]);
                    http_response_code(404); // Not Found
                } else {
                    // İcazə yoxlaması: İstifadəçi bu tapşırığı görə bilərmi?
                    // Admin/Manager hər şeyi görür. Employee yalnız özünə təyin ediləni və ya 'everyone' olanı görür.
                    $isAssigned = ($taskData['assignee_id'] === $currentUserId);
                    // $isCreator = ($taskData['created_by'] === $currentUserId); // DÜZƏLİŞ: created_by artıq taskData-da yoxdur
                    $isPublic = ($taskData['assignee_id'] === null); // 'everyone' üçün assignee_id NULL olur
                    $isAdminOrManager = in_array($currentUserRole, ['admin', 'manager']);

                    if (!$isAdminOrManager && !$isAssigned && !$isPublic) { // DÜZƏLİŞ: !$isCreator şərti çıxarıldı
                        $error_message = "Bu tapşırığa baxmaq üçün icazəniz yoxdur.";
                        if ($this->logger) $this->logger->warning("Unauthorized access attempt to view task.", ['task_id' => $id, 'user_id' => $currentUserId, 'role' => $currentUserRole]);
                        http_response_code(403); // Forbidden
                        $taskData = null; // Məlumatları göstərmə
                    } else {
                        $page_title = "Tapşırıq: " . htmlspecialchars($taskData['title']);
                        // Şərhləri və tarixçəni yüklə
                        $comments = $this->commentModel->getCommentsByTaskId($id) ?: [];
                        $history = $this->taskHistoryModel->getHistoryByTaskId($id) ?: [];
                    }
                }
            } catch (\Exception | \Error $e) {
                $error_message = "Tapşırıq məlumatları yüklənərkən xəta baş verdi.";
                if ($this->logger) $this->logger->error("Error loading task view data.", ['id' => $id, 'exception' => $e->getMessage()]);
                $taskData = null;
                http_response_code(500); // Internal Server Error
            }
        }

        // View üçün base_url dəyişənini təyin et
        $base_url = $this->base_url;

        // Tarixçə və şərhləri də ötür
        $viewData = compact('page_title', 'taskData', 'error_message', 'base_url', 'comments', 'history', 'currentUserId', 'currentUserRole');
        $this->loadView('tasks/view', $viewData);
    }

    /**
     * Handles updating the status of a task (e.g., finishing or declining).
     * Triggered by POST /tasks with action=finish_task
     */
    public function updateStatus() {
        if ($this->logger) $this->logger->info('TaskController::updateStatus method entered.', ['request_method' => $_SERVER['REQUEST_METHOD'], 'post_data' => $_POST]);

        // Təhlükəsizlik: Yalnız POST sorğularını qəbul et
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->logger) $this->logger->warning('Non-POST request received in updateStatus.');
            setFlashMessage('error', 'Yanlış sorğu metodu.');
            $this->redirect('/tasks');
        }

        // Səlahiyyət yoxlaması
        if (!isset($_SESSION['user_id'])) {
             setFlashMessage('error', 'Bu əməliyyatı etmək üçün daxil olmalısınız.');
             $this->redirect('/login.php');
        }
        $current_user_id = $_SESSION['user_id'];
        $currentUserRole = $_SESSION['user_role'];

        // CSRF Token yoxlaması
        $csrf_token = $_POST['csrf_token'] ?? null;
        if (!validateCsrfToken($csrf_token)) {
            if ($this->logger) $this->logger->error('CSRF token validation failed in updateStatus.');
            setFlashMessage('error', 'Təhlükəsizlik xətası. Sorğu qəbul edilmədi.');
            $this->redirect('/tasks');
        }
        if ($this->logger) $this->logger->debug('CSRF token validated successfully.');

        // Gələn məlumatları al və təmizlə
        $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
        $new_status = sanitize($_POST['new_status'] ?? ''); // 'completed' və ya 'declined' olmalıdır
        $comment = trim(sanitize($_POST['comment'] ?? '')); // Boşluqları təmizlə

        if ($this->logger) $this->logger->debug('Input data received for status update.', ['task_id' => $task_id, 'new_status' => $new_status, 'comment_length' => strlen($comment), 'user_id' => $current_user_id]);

        // --- Validasiya ---
        $errors = [];
        $taskData = null; // Tapşırıq məlumatlarını yoxlamaq üçün
        if ($task_id === false || $task_id <= 0) {
            $errors[] = 'Yanlış tapşırıq ID.';
        } else {
            // Tapşırığın mövcudluğunu və cari statusunu yoxla
            $taskData = $this->taskModel->getTaskById($task_id);
            if (!$taskData) {
                $errors[] = 'Tapşırıq tapılmadı.';
            } elseif ($taskData['status'] !== 'in_progress') {
                 $errors[] = 'Yalnız "İcrada" statusunda olan tapşırıqları bitirmək və ya ləğv etmək olar.';
            }
            // İcazə yoxlaması: Admin, Manager və ya təyin edilmiş istifadəçi statusu dəyişə bilər
            elseif (!in_array($currentUserRole, ['admin', 'manager']) && $taskData['assignee_id'] !== $current_user_id) {
                 $errors[] = 'Bu tapşırığın statusunu dəyişməyə icazəniz yoxdur.';
            }
        }
        if (!in_array($new_status, ['completed', 'declined'])) {
            $errors[] = 'Keçərsiz yekun status seçilib.';
        }
        if (empty($comment)) {
            $errors[] = 'Yekun şərh mütləqdir.';
        }

        // Əgər validasiya xətası varsa, logla və geri yönləndir
        if (!empty($errors)) {
            if ($this->logger) $this->logger->warning('Validation failed in TaskController::updateStatus.', ['errors' => $errors, 'post_data' => $_POST]);
            setFlashMessage('danger', implode(' ', $errors));
            $redirect_url = ($task_id > 0) ? '/tasks/view/' . $task_id : '/tasks';
            $this->redirect($redirect_url);
        }
        if ($this->logger) $this->logger->debug('Input validation passed for status update.');

        // --- Statusu Yeniləmə və Şərh Əlavə Etmə (try-catch bloku içində) ---
        try {
            // 1. Statusu yenilə
            if ($this->logger) $this->logger->debug('Calling TaskModel::updateTaskStatus.', ['task_id' => $task_id, 'new_status' => $new_status]);
            $statusUpdated = $this->taskModel->updateTaskStatus($task_id, $new_status, $current_user_id); // completed_by əlavə edildi

            if ($statusUpdated) {
                if ($this->logger) $this->logger->info('Task status updated successfully via model.', ['task_id' => $task_id, 'new_status' => $new_status, 'updated_by' => $current_user_id]);

                // 2. Yekun şərhi əlavə et
                if ($this->logger) $this->logger->debug('Calling CommentModel::addComment.', ['task_id' => $task_id, 'user_id' => $current_user_id]);
                $commentId = $this->commentModel->addComment($task_id, $current_user_id, $comment);

                if ($commentId) {
                    // Tarixçəyə qeyd əlavə et (status dəyişikliyi və şərh)
                    $status_text = translateStatus($new_status); // Tərcümə edilmiş status
                    $historyDetails = "Status '$status_text' olaraq dəyişdirildi. Yekun şərh: " . mb_substr($comment, 0, 100) . (mb_strlen($comment) > 100 ? '...' : '');
                    $this->taskHistoryModel->addHistoryEntry($task_id, $current_user_id, 'status_changed', $historyDetails);

                    if ($this->logger) $this->logger->info('Closing comment added successfully.', ['task_id' => $task_id, 'user_id' => $current_user_id, 'comment_id' => $commentId]);
                    setFlashMessage('success', 'Tapşırıq statusu yeniləndi və yekun şərh əlavə edildi.');
                } else {
                    // Şərh əlavə olunmasa belə status yeniləndi, amma xəbərdarlıq etmək olar
                    if ($this->logger) $this->logger->warning('Task status updated, but failed to add closing comment.', ['task_id' => $task_id, 'user_id' => $current_user_id, 'comment_text' => $comment]);
                    // Tarixçəyə yalnız status dəyişikliyi üçün qeyd əlavə et
                    $status_text = translateStatus($new_status);
                    $this->taskHistoryModel->addHistoryEntry($task_id, $current_user_id, 'status_changed', "Status '$status_text' olaraq dəyişdirildi (şərh əlavə edilmədi).");
                    setFlashMessage('warning', 'Tapşırıq statusu yeniləndi, lakin yekun şərh yadda saxlanılmadı.');
                }

                // Uğurlu olduqda tapşırıq siyahısına yönləndir
                $this->redirect('/tasks');

            } else {
                // Model statusu yeniləyə bilmədi (Task::updateTaskStatus false qaytardı)
                if ($this->logger) $this->logger->error('TaskModel::updateTaskStatus returned false. Status not updated in DB.', ['task_id' => $task_id, 'new_status' => $new_status]);
                setFlashMessage('danger', 'Tapşırıq statusunu yeniləmək mümkün olmadı (Verilənlər bazası xətası və ya icazə problemi).');
                if ($this->logger) $this->logger->debug('Redirecting back to task view after failed status update.');
                $this->redirect('/tasks/view/' . $task_id);
            }

        } catch (\Exception | \Error $e) {
            // Model metodlarında exception baş verərsə
            if ($this->logger) $this->logger->error('Exception during task status update or comment addition.', ['task_id' => $task_id ?? 'N/A', 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            else error_log('CRITICAL ERROR during task status update: ' . $e->getMessage());
            setFlashMessage('danger', 'Tapşırıq yenilənərkən gözlənilməyən xəta baş verdi. Sistem administratoru ilə əlaqə saxlayın.');
            $redirect_url = ($task_id > 0) ? '/tasks/view/' . $task_id : '/tasks';
            $this->redirect($redirect_url);
        }
    }

    /**
     * Handles starting the progress of a task.
     * Triggered by POST /tasks with action=start_progress
     */
    public function startProgress() {
        if ($this->logger) $this->logger->info('TaskController::startProgress method entered.', ['post_data' => $_POST]);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->logger) $this->logger->warning('Non-POST request received in startProgress.');
            setFlashMessage('error', 'Yanlış sorğu metodu.');
            $this->redirect('/tasks');
        }

        // Səlahiyyət yoxlaması
        if (!isset($_SESSION['user_id'])) {
             setFlashMessage('error', 'Bu əməliyyatı etmək üçün daxil olmalısınız.');
             $this->redirect('/login.php');
        }
        $current_user_id = $_SESSION['user_id'];
        $currentUserRole = $_SESSION['user_role'];

        // CSRF yoxlaması
        if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
            if ($this->logger) $this->logger->error('CSRF token validation failed in startProgress.');
            setFlashMessage('error', 'Təhlükəsizlik xətası. Sorğu qəbul edilmədi.');
            $this->redirect('/tasks');
        }

        $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);

        // Validasiya
        $taskData = null;
        if ($task_id === false || $task_id <= 0) {
            if ($this->logger) $this->logger->warning('Invalid task ID for starting progress.', ['task_id' => $task_id]);
            setFlashMessage('error', 'Yanlış tapşırıq ID.');
            $this->redirect('/tasks');
        } else {
            // Tapşırığın mövcudluğunu və statusunu yoxla
            $taskData = $this->taskModel->getTaskById($task_id);
            if (!$taskData) {
                setFlashMessage('error', 'Tapşırıq tapılmadı.');
                $this->redirect('/tasks');
            } elseif ($taskData['status'] !== 'pending') {
                setFlashMessage('warning', 'Yalnız "Gözləmədə" statusunda olan tapşırıqları icraya götürmək olar.');
                $this->redirect('/tasks/view/' . $task_id);
            }
            // İcazə yoxlaması: Admin, Manager və ya təyin edilmiş istifadəçi icraya götürə bilər
            elseif (!in_array($currentUserRole, ['admin', 'manager']) && $taskData['assignee_id'] !== $current_user_id && $taskData['assignee_id'] !== null) {
                 setFlashMessage('error', 'Bu tapşırığı icraya götürməyə icazəniz yoxdur.');
                 $this->redirect('/tasks/view/' . $task_id);
            }
        }

        try {
            // Tapşırığı icraya götür (started_by_user_id də təyin olunacaq)
            $started = $this->taskModel->startTask($task_id, $current_user_id);

            if ($started) {
                // Tarixçəyə qeyd əlavə et
                $this->taskHistoryModel->addHistoryEntry($task_id, $current_user_id, 'started', 'Tapşırıq icraya götürüldü (Status: İcrada).');

                if ($this->logger) $this->logger->info('Task progress started successfully.', ['task_id' => $task_id, 'user_id' => $current_user_id]);
                setFlashMessage('success', 'Tapşırıq uğurla icraya götürüldü.');
            } else {
                if ($this->logger) $this->logger->error('Failed to start task progress (maybe DB error or already started).', ['task_id' => $task_id, 'user_id' => $current_user_id]);
                setFlashMessage('error', 'Tapşırığı icraya götürmək mümkün olmadı (Verilənlər bazası xətası).');
            }
        } catch (\Exception | \Error $e) {
            if ($this->logger) $this->logger->critical('Exception during starting task progress.', ['task_id' => $task_id, 'exception' => $e->getMessage()]);
            setFlashMessage('danger', 'Tapşırıq icraya götürülərkən gözlənilməyən sistem xətası baş verdi.');
        }

        // Tapşırığın view səhifəsinə geri yönləndir
        $this->redirect('/tasks/view/' . $task_id);
    }

    /**
     * Handles deleting a task.
     * Triggered by POST /tasks with action=delete_task
     */
    public function delete() {
        if ($this->logger) $this->logger->info('TaskController::delete method entered.', ['post_data' => $_POST]);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->logger) $this->logger->warning('Non-POST request received in delete.');
            setFlashMessage('error', 'Yanlış sorğu metodu.');
            $this->redirect('/tasks');
        }

        // Səlahiyyət yoxlaması
        if (!isset($_SESSION['user_id'])) {
             setFlashMessage('error', 'Bu əməliyyatı etmək üçün daxil olmalısınız.');
             $this->redirect('/login.php');
        }
        $currentUserId = $_SESSION['user_id'];
        $currentUserRole = $_SESSION['user_role'];

        // CSRF yoxlaması
        if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
            if ($this->logger) $this->logger->error('CSRF token validation failed in delete.');
            setFlashMessage('error', 'Təhlükəsizlik xətası. Sorğu qəbul edilmədi.');
            $this->redirect('/tasks');
        }

        $task_id = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);

        if ($task_id === false || $task_id <= 0) {
            if ($this->logger) $this->logger->warning('Invalid task ID for deletion.', ['task_id' => $task_id]);
            setFlashMessage('error', 'Yanlış tapşırıq ID.');
            $this->redirect('/tasks');
        }

        // İcazə yoxlaması (yalnız admin və ya manager silə bilər)
        if (!in_array($currentUserRole, ['admin', 'manager'])) {
             if ($this->logger) $this->logger->error('Unauthorized attempt to delete task.', ['task_id' => $task_id, 'user_id' => $currentUserId, 'role' => $currentUserRole]);
             setFlashMessage('error', 'Bu tapşırığı silməyə icazəniz yoxdur.');
             $this->redirect('/tasks');
        }

        try {
            // Əvvəlcə əlaqəli şərhləri və tarixçəni silmək lazımdır (foreign key constraint)
            // Bu əməliyyatları modellərə köçürmək daha yaxşıdır
            $commentsDeleted = $this->commentModel->deleteCommentsByTaskId($task_id);
            $historyDeleted = $this->taskHistoryModel->deleteHistoryByTaskId($task_id);

            if ($this->logger) $this->logger->debug('Attempted to delete related data for task.', ['task_id' => $task_id, 'comments_deleted' => $commentsDeleted, 'history_deleted' => $historyDeleted]);

            // Sonra tapşırığı sil
            $deleted = $this->taskModel->deleteTask($task_id);

            if ($deleted) {
                if ($this->logger) $this->logger->info('Task deleted successfully.', ['task_id' => $task_id, 'deleted_by_user' => $currentUserId]);
                setFlashMessage('success', 'Tapşırıq və əlaqəli məlumatlar uğurla silindi.');
            } else {
                if ($this->logger) $this->logger->error('Failed to delete task from DB (TaskModel::deleteTask returned false).', ['task_id' => $task_id]);
                setFlashMessage('danger', 'Tapşırığı silmək mümkün olmadı (Verilənlər bazası xətası).');
            }
        } catch (\Exception | \Error $e) {
            if ($this->logger) $this->logger->critical('Exception during task deletion.', ['task_id' => $task_id, 'exception' => $e->getMessage()]);
            setFlashMessage('danger', 'Tapşırıq silinərkən gözlənilməyən sistem xətası baş verdi.');
        }

        $this->redirect('/tasks');
    }

    // --- Placeholder Methods ---
    public function edit(int $id) {
        // TODO: Load task data, departments, categories, users
        // TODO: Check permissions
        // TODO: Load edit view with form data
        setFlashMessage('info', 'Tapşırığı redaktə etmə funksiyası hələ hazır deyil.');
        $this->redirect('/tasks/view/' . $id);
    }

    public function update(int $id) {
        // TODO: Handle POST request from edit form
        // TODO: Validate data
        // TODO: Check permissions
        // TODO: Call TaskModel::updateTask()
        // TODO: Add history entry
        // TODO: Redirect
        setFlashMessage('info', 'Tapşırığı yeniləmə funksiyası hələ hazır deyil.');
        $this->redirect('/tasks/view/' . $id);
    }

    /**
     * Loads a view file with provided data, including header and footer.
     * @param string $viewPath Path to the view file relative to the modules directory (e.g., 'tasks/index').
     * @param array $data Data to be extracted for the view.
     */
    protected function loadView(string $viewPath, array $data = []): void {
        // View faylının tam yolunu təyin et
        $fullViewPath = ROOT_PATH . '/modules/' . $viewPath . '.php';

        if (file_exists($fullViewPath)) {
            // Dəyişənləri view üçün əlçatan et
            // CSRF tokenini də burada yaratmaq olar (əgər hər view-da lazımdırsa)
            // $data['csrf_token'] = generateCsrfToken();
            extract($data);

            // Header-i daxil et (əgər varsa)
            $headerPath = ROOT_PATH . '/includes/header.php';
            if (file_exists($headerPath)) {
                require $headerPath;
            } else {
                 if ($this->logger) $this->logger->warning("Header file not found at: " . $headerPath);
            }

            // View faylını daxil et
            require $fullViewPath;

            // Footer-i daxil et (əgər varsa)
            $footerPath = ROOT_PATH . '/includes/footer.php';
            if (file_exists($footerPath)) {
                require $footerPath;
            } else {
                 if ($this->logger) $this->logger->warning("Footer file not found at: " . $footerPath);
            }
        } else {
            // View faylı tapılmadıqda kritik xəta ver
            $errorMsg = "View file not found: " . $fullViewPath;
            if ($this->logger) $this->logger->critical($errorMsg);
            http_response_code(500);
            // İstifadəçiyə sadə xəta mesajı göstər
            echo "<!DOCTYPE html><html><head><title>Server Error</title></head><body>";
            echo "<div style='padding: 20px; border: 1px solid red; background-color: #ffecec; font-family: sans-serif;'>";
            echo "<strong>Internal Server Error:</strong> Required view file is missing.<br>";
            echo "Please contact the system administrator.";
            if (ini_get('display_errors')) { // Yalnız development rejimində göstər
                echo "<hr><pre>" . htmlspecialchars($errorMsg) . "</pre>";
            }
            echo "</div></body></html>";
            exit;
        }
    }

} // --- TaskController sinifinin sonu ---

// Helper function (əgər functions.php-də yoxdursa)
if (!function_exists('validateDate')) {
    function validateDate($date, $format = 'Y-m-d') {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}
?>
