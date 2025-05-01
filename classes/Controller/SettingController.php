<?php
// e:\xampp\htdocs\task_management\classes\Controller\SettingController.php

namespace App\Controller;

use App\Model\Department;
use App\Model\Category;

class SettingController {

    private Department $departmentModel;
    private Category $categoryModel;

    public function __construct() {
        checkRole(['admin']); // Tənzimləmələrə yalnız admin daxil ola bilər
        $this->departmentModel = new Department();
        $this->categoryModel = new Category();
    }

    /**
     * Tənzimləmələr səhifəsini göstərir (Departamentlər və Kateqoriyalar) (GET /settings).
     */
    public function index(): void {
        $page_title = "Tənzimləmələr";
        $base_url = BASE_URL;
        $departments = [];
        $categories = [];
        $error_message_load = null;

        // Əvvəlki cəhddən qalan form məlumatları və xətaları sessiyadan alaq
        $form_data_dep = $_SESSION['form_data_dep'] ?? [];
        $form_errors_dep = $_SESSION['form_errors_dep'] ?? [];
        $form_data_cat = $_SESSION['form_data_cat'] ?? [];
        $form_errors_cat = $_SESSION['form_errors_cat'] ?? [];
        unset($_SESSION['form_data_dep'], $_SESSION['form_errors_dep'], $_SESSION['form_data_cat'], $_SESSION['form_errors_cat']);

        try {
            $departments = $this->departmentModel->getAllDepartments() ?: [];
            $categories = $this->categoryModel->getAllCategories() ?: [];
        } catch (\Exception | \Error $e) {
            $error_message_load = "Məlumatlar yüklənərkən xəta baş verdi: " . htmlspecialchars($e->getMessage());
            error_log("Settings Index Load Error (Controller): " . $e->getMessage());
        }

        $this->loadView('settings/index', compact(
            'page_title', 'base_url', 'departments', 'categories', 'error_message_load',
            'form_data_dep', 'form_errors_dep', 'form_data_cat', 'form_errors_cat'
        ));
    }

    /**
     * Yeni departament yaradır (POST /settings/store-department).
     */
    public function storeDepartment(): void {
        // CSRF token yoxlaması
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
             setFlashMessage('danger', 'Təhlükəsizlik xətası.');
             header("Location: " . BASE_URL . "/settings");
             exit;
        }

        $form_data = $_POST;
        $errors = [];

        if (empty($form_data['department_name'])) {
            $errors['department_name'] = "Şöbə adı boş ola bilməz.";
        } else {
            $form_data['department_name'] = sanitize($form_data['department_name']);
            // Unikal ad yoxlaması
            if ($this->departmentModel->getDepartmentByName($form_data['department_name'])) {
                 $errors['department_name'] = "Bu adda şöbə artıq mövcuddur.";
            }
        }

        if (!empty($errors)) {
            $_SESSION['form_data_dep'] = $form_data;
            $_SESSION['form_errors_dep'] = $errors;
            setFlashMessage('danger', 'Şöbə formunda xətalar var.');
        } else {
            try {
                if ($this->departmentModel->createDepartment($form_data['department_name'])) {
                    setFlashMessage('success', 'Şöbə uğurla yaradıldı.');
                } else {
                    setFlashMessage('danger', 'Şöbə yaradıla bilmədi.');
                    $_SESSION['form_data_dep'] = $form_data; // Xəta olarsa datanı saxlayaq
                }
            } catch (\Exception $e) {
                 error_log("Exception during department creation (Controller): " . $e->getMessage());
                 setFlashMessage('danger', 'Şöbə yaradıla bilmədi. Gözlənilməyən xəta baş verdi.');
                 $_SESSION['form_data_dep'] = $form_data;
            }
        }

        header("Location: " . BASE_URL . "/settings");
        exit;
    }

    /**
     * Departamenti silir (POST /settings/delete-department).
     */
    public function deleteDepartment(): void {
        // CSRF token yoxlaması
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
             setFlashMessage('danger', 'Təhlükəsizlik xətası.');
             header("Location: " . BASE_URL . "/settings");
             exit;
        }

        $departmentId = $_POST['department_id'] ?? null;

        if ($departmentId && filter_var($departmentId, FILTER_VALIDATE_INT)) {
            try {
                // Əlaqəli task və ya user olub olmadığını yoxlamaq olar (əlavə təhlükəsizlik)
                if ($this->departmentModel->deleteDepartment((int)$departmentId)) {
                     setFlashMessage('success', 'Şöbə uğurla silindi.');
                } else {
                    setFlashMessage('danger', 'Şöbə silinərkən xəta baş verdi (Mümkündür ki, əlaqəli tapşırıqlar və ya istifadəçilər var).');
                    error_log("Error deleting department id " . $departmentId . " (Controller)");
                }
            } catch (\PDOException $e) {
                 // Foreign key constraint xətası
                 if ((int)$e->getCode() === 23000) { // Integrity constraint violation
                     setFlashMessage('danger', 'Şöbə silinə bilmədi. Bu şöbəyə bağlı tapşırıqlar və ya istifadəçilər mövcuddur.');
                 } else {
                     setFlashMessage('danger', 'Şöbə silinərkən gözlənilməyən verilənlər bazası xətası baş verdi.');
                 }
                 error_log("PDOException deleting department id " . $departmentId . " (Controller): " . $e->getMessage());
            } catch (\Exception $e) {
                 setFlashMessage('danger', 'Şöbə silinərkən gözlənilməyən xəta baş verdi.');
                 error_log("Exception deleting department id " . $departmentId . " (Controller): " . $e->getMessage());
            }
        } else {
            setFlashMessage('danger', 'Silmək üçün keçərli şöbə ID-si daxil edilməyib.');
        }

        header("Location: " . BASE_URL . "/settings");
        exit;
    }

    /**
     * Yeni kateqoriya yaradır (POST /settings/store-category).
     */
    public function storeCategory(): void {
        // CSRF token yoxlaması
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
             setFlashMessage('danger', 'Təhlükəsizlik xətası.');
             header("Location: " . BASE_URL . "/settings#categories-tab"); // Tab-a yönləndir
             exit;
        }

        $form_data = $_POST;
        $errors = [];

        if (empty($form_data['category_name'])) {
            $errors['category_name'] = "Kateqoriya adı boş ola bilməz.";
        } else {
            $form_data['category_name'] = sanitize($form_data['category_name']);
            // Unikal ad yoxlaması
            if ($this->categoryModel->getCategoryByName($form_data['category_name'])) {
                 $errors['category_name'] = "Bu adda kateqoriya artıq mövcuddur.";
            }
        }

        if (!empty($errors)) {
            $_SESSION['form_data_cat'] = $form_data;
            $_SESSION['form_errors_cat'] = $errors;
            setFlashMessage('danger', 'Kateqoriya formunda xətalar var.');
        } else {
            try {
                if ($this->categoryModel->createCategory($form_data['category_name'])) {
                    setFlashMessage('success', 'Kateqoriya uğurla yaradıldı.');
                } else {
                    setFlashMessage('danger', 'Kateqoriya yaradıla bilmədi.');
                    $_SESSION['form_data_cat'] = $form_data;
                }
            } catch (\Exception $e) {
                 error_log("Exception during category creation (Controller): " . $e->getMessage());
                 setFlashMessage('danger', 'Kateqoriya yaradıla bilmədi. Gözlənilməyən xəta baş verdi.');
                 $_SESSION['form_data_cat'] = $form_data;
            }
        }

        header("Location: " . BASE_URL . "/settings#categories-tab"); // Tab-a yönləndir
        exit;
    }

    /**
     * Kateqoriyanı silir (POST /settings/delete-category).
     */
    public function deleteCategory(): void {
        // CSRF token yoxlaması
        if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
             setFlashMessage('danger', 'Təhlükəsizlik xətası.');
             header("Location: " . BASE_URL . "/settings#categories-tab");
             exit;
        }

        $categoryId = $_POST['category_id'] ?? null;

        if ($categoryId && filter_var($categoryId, FILTER_VALIDATE_INT)) {
            try {
                // Əlaqəli task olub olmadığını yoxlamaq olar
                if ($this->categoryModel->deleteCategory((int)$categoryId)) {
                     setFlashMessage('success', 'Kateqoriya uğurla silindi.');
                } else {
                    setFlashMessage('danger', 'Kateqoriya silinərkən xəta baş verdi (Mümkündür ki, əlaqəli tapşırıqlar var).');
                    error_log("Error deleting category id " . $categoryId . " (Controller)");
                }
            } catch (\PDOException $e) {
                 if ((int)$e->getCode() === 23000) {
                     setFlashMessage('danger', 'Kateqoriya silinə bilmədi. Bu kateqoriyaya bağlı tapşırıqlar mövcuddur.');
                 } else {
                     setFlashMessage('danger', 'Kateqoriya silinərkən gözlənilməyən verilənlər bazası xətası baş verdi.');
                 }
                 error_log("PDOException deleting category id " . $categoryId . " (Controller): " . $e->getMessage());
            } catch (\Exception $e) {
                 setFlashMessage('danger', 'Kateqoriya silinərkən gözlənilməyən xəta baş verdi.');
                 error_log("Exception deleting category id " . $categoryId . " (Controller): " . $e->getMessage());
            }
        } else {
            setFlashMessage('danger', 'Silmək üçün keçərli kateqoriya ID-si daxil edilməyib.');
        }

        header("Location: " . BASE_URL . "/settings#categories-tab");
        exit;
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
