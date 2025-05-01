<?php
// e:\xampp\htdocs\task_management\classes\Controller\UserController.php

namespace App\Controller;

use App\Model\User;
use App\Model\Department; // İstifadəçi yaratma və redaktə formaları üçün lazım olacaq
use Psr\Log\LoggerInterface; // Logger üçün

class UserController {

    private User $userModel;
    private Department $departmentModel;
    private ?LoggerInterface $logger; // Logger üçün (nullable)
    private string $base_url; // BASE_URL üçün

    public function __construct() {
        $this->userModel = new User();
        $this->departmentModel = new Department();
        $this->logger = isset($GLOBALS['logger']) && $GLOBALS['logger'] instanceof LoggerInterface ? $GLOBALS['logger'] : null;
        $this->base_url = defined('BASE_URL') ? BASE_URL : '';

        // Autentifikasiya yoxlaması (bütün metodlar üçün ümumi)
        if (!isset($_SESSION['user_id'])) {
            setFlashMessage('error', 'Bu bölməyə daxil olmaq üçün giriş etməlisiniz.');
            $this->redirect('/login.php');
        }
    }

    /**
     * Helper method for redirection.
     * @param string $url The path to redirect to (relative to BASE_URL).
     */
    protected function redirect(string $url): void {
        if (strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }
        header('Location: ' . $this->base_url . $url);
        exit;
    }

    /**
     * İstifadəçi siyahısını göstərir (GET /users).
     * Yalnız admin və manager görə bilər.
     */
    public function index(): void {
        checkRole(['admin', 'manager']); // Səlahiyyət yoxlaması

        $page_title = "İstifadəçilər";
        $users = [];
        $error_message_load = null;

        try {
            // === DÜZƏLİŞ: getAllUsers() metodu çağırılır ===
            // getAllUsers metodu artıq departament adını da gətirir
            $users = $this->userModel->getAllUsers() ?: [];
        } catch (\Exception | \Error $e) {
            $error_message_load = "İstifadəçi məlumatları yüklənərkən xəta baş verdi: " . htmlspecialchars($e->getMessage());
            if ($this->logger) $this->logger->error("Users Index Load Error (Controller): " . $e->getMessage(), ['exception' => $e]);
            else error_log("Users Index Load Error (Controller): " . $e->getMessage());
        }

        $this->loadView('users/index', compact(
            'page_title', 'users', 'error_message_load'
            // base_url loadView daxilində əlçatandır
        ));
    }

    /**
     * Yeni istifadəçi yaratma formasını göstərir (GET /users/create).
     * Yalnız admin yarada bilər.
     */
    public function create(): void {
        checkRole(['admin']); // Səlahiyyət yoxlaması

        $page_title = "Yeni İstifadəçi Yarat";
        $departments = [];
        $data_load_error = null;

        // Əvvəlki cəhddən qalan form məlumatları və xətaları sessiyadan alaq
        $form_data = $_SESSION['form_data'] ?? [];
        $form_errors = $_SESSION['form_errors'] ?? [];
        unset($_SESSION['form_data'], $_SESSION['form_errors']);

        try {
            $departments = $this->departmentModel->getAllDepartments() ?: [];
            if (empty($departments)) {
                 $data_load_error = "İstifadəçi yaratmaq üçün əvvəlcə Tənzimləmələr bölməsində şöbə əlavə edilməlidir.";
                 // Flash mesajı view-da göstəriləcək, burada set etməyə ehtiyac yoxdur
                 if ($this->logger) $this->logger->warning("Cannot create user: No departments found in DB.");
            }
        } catch (\Exception | \Error $e) {
            $data_load_error = "Şöbə məlumatları yüklənərkən xəta baş verdi.";
            if ($this->logger) $this->logger->error("Create User Form Load Error (Controller): " . $e->getMessage(), ['exception' => $e]);
            // Flash mesajı view-da göstəriləcək
        }

        $this->loadView('users/create', compact(
            'page_title', 'departments', 'data_load_error', 'form_data', 'form_errors'
        ));
    }

    /**
     * Yeni istifadəçini verilənlər bazasına yazır (POST /users/create).
     * Yalnız admin yarada bilər.
     */
    public function store(): void {
        checkRole(['admin']); // Səlahiyyət yoxlaması

        // Yalnız POST sorğularını qəbul et
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users/create');
        }

        // CSRF token yoxlaması
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
             setFlashMessage('danger', 'Təhlükəsizlik xətası. Sorğu qəbul edilmədi.');
             $this->redirect('/users/create');
        }

        $form_data = $_POST;
        $errors = [];

        // --- Validasiya və Sanitiyasiya ---
        // === DÜZƏLİŞ: fullname -> name ===
        if (empty($form_data['name'])) $errors['name'] = "Ad Soyad boş ola bilməz.";
        else $form_data['name'] = sanitize($form_data['name']);

        if (empty($form_data['username'])) $errors['username'] = "İstifadəçi adı boş ola bilməz.";
        elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) $errors['username'] = "İstifadəçi adı yalnız hərf, rəqəm və alt xəttdən ibarət ola bilər.";
        else $form_data['username'] = sanitize($form_data['username']);

        if (empty($form_data['email'])) $errors['email'] = "E-poçt boş ola bilməz.";
        elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = "Keçərli e-poçt ünvanı daxil edin.";
        else $form_data['email'] = filter_var($form_data['email'], FILTER_SANITIZE_EMAIL);

        if (empty($form_data['password'])) $errors['password'] = "Şifrə boş ola bilməz.";
        elseif (strlen($form_data['password']) < 6) $errors['password'] = "Şifrə ən azı 6 simvol olmalıdır.";
        // Şifrə təsdiqi yoxlaması
        if ($form_data['password'] !== ($form_data['password_confirm'] ?? '')) $errors['password_confirm'] = "Şifrələr uyğun gəlmir.";

        // === DÜZƏLİŞ: employee -> user ===
        $valid_roles = ['admin', 'manager', 'user'];
        if (empty($form_data['role']) || !in_array($form_data['role'], $valid_roles)) $errors['role'] = "Keçərli rol seçin.";
        else $form_data['role'] = sanitize($form_data['role']);

        if (empty($form_data['department_id']) || !filter_var($form_data['department_id'], FILTER_VALIDATE_INT)) $errors['department_id'] = "Şöbə seçimi məcbburidir.";
        else $form_data['department_id'] = (int)$form_data['department_id'];
        // TODO: Seçilmiş department_id-nin DB-də mövcudluğunu yoxla (DepartmentModel ilə)

        // İstifadəçi adı və e-poçt unikal olmalıdır (Modelə müraciət)
        if (empty($errors['username']) && $this->userModel->findByUsername($form_data['username'])) {
            $errors['username'] = "Bu istifadəçi adı artıq mövcuddur.";
        }
        if (empty($errors['email']) && $this->userModel->findByEmail($form_data['email'])) {
            $errors['email'] = "Bu e-poçt ünvanı artıq istifadə olunur.";
        }

        // --- Validasiya Nəticəsi ---
        if (!empty($errors)) {
            $_SESSION['form_data'] = $form_data; // Şifrəni sessiyaya yazmayaq
            unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password_confirm']);
            $_SESSION['form_errors'] = $errors;
            setFlashMessage('danger', 'Formda xətalar var. Zəhmət olmasa, düzəldin.');
            $this->redirect('/users/create');
        } else {
            // --- Verilənlər Bazasına Yazma ---
            try {
                 $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
                 // === DÜZƏLİŞ: fullname -> name ===
                 $userDataToInsert = [
                     'name' => $form_data['name'],
                     'username' => $form_data['username'],
                     'email' => $form_data['email'],
                     'password' => $hashed_password,
                     'role' => $form_data['role'],
                     'department_id' => $form_data['department_id'],
                     'status' => 'active', // Yeni istifadəçi default olaraq aktiv olsun
                     // created_at, updated_at DB tərəfindən idarə olunmursa, User modelində əlavə edilməlidir
                 ];

                 $new_user_id = $this->userModel->createUser($userDataToInsert);

                 if ($new_user_id === false) {
                    setFlashMessage('danger', 'İstifadəçi verilənlər bazasına yazıla bilmədi (Mümkün səbəb: DB xətası).');
                    $_SESSION['form_data'] = $form_data;
                    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password_confirm']);
                    if ($this->logger) $this->logger->error("User creation failed in DB (Model returned false).", ['data' => $userDataToInsert]);
                    $this->redirect('/users/create');
                 } else {
                    if ($this->logger) $this->logger->info("User created successfully.", ['user_id' => $new_user_id, 'username' => $form_data['username']]);
                    setFlashMessage('success', 'İstifadəçi uğurla yaradıldı!');
                    $this->redirect('/users'); // İstifadəçi siyahısına yönləndir
                 }
            } catch (\Exception | \Error $e) {
                 if ($this->logger) $this->logger->critical("Exception during user creation (Controller): " . $e->getMessage(), ['exception' => $e, 'data' => $form_data]);
                 else error_log("Exception during user creation (Controller): " . $e->getMessage());
                 setFlashMessage('danger', 'İstifadəçi yaradıla bilmədi. Gözlənilməyən sistem xətası baş verdi.');
                 $_SESSION['form_data'] = $form_data;
                 unset($_SESSION['form_data']['password'], $_SESSION['form_data']['password_confirm']);
                 $this->redirect('/users/create');
            }
        }
    }

    /**
     * İstifadəçi profil səhifəsini göstərir (GET /users/profile).
     * Hər kəs öz profilinə baxa bilər.
     */
    public function profile(): void {
        // Autentifikasiya yoxlaması konstruktorda edilir

        $page_title = "Mənim Profilim";
        $userData = null;
        $error_message = null;
        $userId = $_SESSION['user_id']; // Giriş etmiş istifadəçinin ID-si

        try {
            // Model vasitəsilə istifadəçi məlumatlarını al (departament adı ilə birlikdə)
            $userData = $this->userModel->findById($userId);
            if (!$userData) {
                // Bu çox nadir halda baş verməlidir
                if ($this->logger) $this->logger->error("Logged in user (ID: $userId) not found in DB for profile view.");
                session_destroy(); // Sessiyanı məhv et
                setFlashMessage('error', 'Profil məlumatları tapılmadı. Zəhmət olmasa, yenidən daxil olun.');
                $this->redirect('/login.php');
            }
        } catch (\Exception | \Error $e) {
            $error_message = "Profil məlumatları yüklənərkən xəta baş verdi.";
            if ($this->logger) $this->logger->error("User Profile Load Error (Controller ID: $userId): " . $e->getMessage(), ['exception' => $e]);
            else error_log("User Profile Load Error (Controller ID: $userId): " . $e->getMessage());
        }

        $this->loadView('users/profile', compact(
            'page_title', 'userData', 'error_message'
        ));
    }

    /**
     * İstifadəçi statusunu yeniləyir (aktiv/deaktiv) (POST /users/update-status).
     * Yalnız admin edə bilər.
     */
    public function updateStatus(): void {
        checkRole(['admin']); // Səlahiyyət yoxlaması

        // Yalnız POST sorğularını qəbul et
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/users');
        }

        // CSRF token yoxlaması
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
             setFlashMessage('danger', 'Təhlükəsizlik xətası. Sorğu qəbul edilmədi.');
             $this->redirect('/users');
        }

        $userId = $_POST['user_id'] ?? null;
        $action = $_POST['action'] ?? null; // 'activate_user' və ya 'deactivate_user'

        if ($userId && filter_var($userId, FILTER_VALIDATE_INT) && ($action === 'activate_user' || $action === 'deactivate_user')) {
            $userId = (int)$userId;
            $newStatus = ($action === 'activate_user') ? 'active' : 'inactive';

            // Admin özünü deaktiv edə bilməz
            if ($userId === ($_SESSION['user_id'] ?? null) && $newStatus === 'inactive') {
                setFlashMessage('warning', 'Admin öz hesabını deaktiv edə bilməz.');
            } else {
                try {
                    if ($this->userModel->updateUserStatus($userId, $newStatus)) {
                         if ($this->logger) $this->logger->info("User status updated.", ['user_id' => $userId, 'new_status' => $newStatus, 'updated_by' => $_SESSION['user_id']]);
                         setFlashMessage('success', 'İstifadəçi statusu uğurla yeniləndi.');
                    } else {
                        if ($this->logger) $this->logger->error("Failed to update user status (Model returned false).", ['user_id' => $userId, 'new_status' => $newStatus]);
                        setFlashMessage('danger', 'İstifadəçi statusu yenilənərkən xəta baş verdi.');
                    }
                } catch (\Exception | \Error $e) {
                     if ($this->logger) $this->logger->error("Exception updating user status.", ['user_id' => $userId, 'exception' => $e->getMessage()]);
                     else error_log("Exception updating user status for id " . $userId . " (Controller): " . $e->getMessage());
                     setFlashMessage('danger', 'Status yenilənərkən gözlənilməyən xəta baş verdi.');
                }
            }
        } else {
            if ($this->logger) $this->logger->warning("Invalid data received for user status update.", ['post_data' => $_POST]);
            setFlashMessage('danger', 'Statusu yeniləmək üçün keçərli məlumatlar daxil edilməyib.');
        }

        $this->redirect('/users'); // Hər halda istifadəçi siyahısına yönləndir
    }

    // --- Placeholder Methods (Gələcək üçün) ---
    /**
     * İstifadəçi redaktə formasını göstərir (GET /users/edit/{id}).
     * Yalnız admin edə bilər.
     */
    public function edit(int $id): void {
        checkRole(['admin']);
        // TODO: İstifadəçi məlumatlarını $id ilə yüklə (findById)
        // TODO: Departamentləri yüklə (getAllDepartments)
        // TODO: Sessiyadan xəta/form məlumatlarını al
        // TODO: users/edit.php view faylını yüklə
        setFlashMessage('info', 'İstifadəçi redaktəsi funksiyası hələ hazır deyil.');
        $this->redirect('/users');
    }

    /**
     * İstifadəçi məlumatlarını yeniləyir (POST /users/edit/{id}).
     * Yalnız admin edə bilər.
     */
    public function update(int $id): void {
        checkRole(['admin']);
        // TODO: POST sorğusunu yoxla
        // TODO: CSRF yoxla
        // TODO: Validasiya et (şifrə dəyişməsi istəyə bağlı ola bilər)
        // TODO: Unikal ad/email yoxla (özü xaric)
        // TODO: UserModel::updateUser() metodunu çağır
        // TODO: Nəticəyə görə flash mesaj və yönləndirmə
        setFlashMessage('info', 'İstifadəçi yeniləmə funksiyası hələ hazır deyil.');
        $this->redirect('/users');
    }

    /**
     * İstifadəçini silir (POST /users/delete/{id} - gələcəkdə).
     * Yalnız admin edə bilər.
     */
    public function delete(int $id): void {
         checkRole(['admin']);
         // TODO: POST/DELETE sorğusunu yoxla
         // TODO: CSRF yoxla
         // TODO: Admin özünü silə bilməz yoxlaması
         // TODO: Əlaqəli tapşırıqları yoxla (və ya sil/null et)
         // TODO: UserModel::deleteUser() metodunu çağır
         // TODO: Nəticəyə görə flash mesaj və yönləndirmə
         setFlashMessage('info', 'İstifadəçi silmə funksiyası hələ hazır deyil.');
         $this->redirect('/users');
    }


    // --- Yardımçı Metodlar ---
    /**
     * Loads a view file with provided data, including header and footer.
     * @param string $viewPath Path to the view file relative to the modules directory (e.g., 'users/index').
     * @param array $data Data to be extracted for the view.
     */
    protected function loadView(string $viewPath, array $data = []): void {
        // View faylının tam yolunu təyin et
        $fullViewPath = ROOT_PATH . '/modules/' . $viewPath . '.php';

        if (file_exists($fullViewPath)) {
            // $base_url dəyişənini view üçün əlçatan et
            $data['base_url'] = $this->base_url;
            extract($data);

            // Header-i daxil et
            $headerPath = ROOT_PATH . '/includes/header.php';
            if (file_exists($headerPath)) {
                require $headerPath;
            } else {
                 if ($this->logger) $this->logger->warning("Header file not found at: " . $headerPath);
            }

            // View faylını daxil et
            require $fullViewPath;

            // Footer-i daxil et
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
            else error_log($errorMsg);
            http_response_code(500);
            // İstifadəçiyə sadə xəta mesajı göstər
            echo "<!DOCTYPE html><html><head><title>Server Error</title></head><body>";
            echo "<div style='padding: 20px; border: 1px solid red; background-color: #ffecec; font-family: sans-serif;'>";
            echo "<strong>Internal Server Error:</strong> Required view file is missing.<br>";
            echo "Please contact the system administrator.";
            if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) { // Yalnız development rejimində göstər
                echo "<hr><pre>" . htmlspecialchars($errorMsg) . "</pre>";
            }
            echo "</div></body></html>";
            exit;
        }
    }
}
