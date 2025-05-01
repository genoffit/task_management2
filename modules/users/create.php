<?php
// e:\xampp\htdocs\task_management\modules\users\create.php

// DİQQƏT: Bu faylda heç bir require_once və ya include sətri OLMAMALIDIR.
// Siniflər Composer autoloader tərəfindən yüklənir.

// Namespace istifadəsi (Autoloader bunları tapacaq)
use App\Model\Department;
use App\Model\User; // User modeli lazım olmasa da, gələcək üçün saxlayaq

// Səlahiyyət yoxlaması (məsələn, yalnız adminlər)
if (function_exists('checkRole')) {
    checkRole('admin');
} else {
    // Fallback
    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        if (function_exists('setFlashMessage')) setFlashMessage('error', 'Bu bölməyə giriş icazəniz yoxdur.');
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/dashboard');
        exit;
    }
}


// Səhifə başlığı
$page_title = "Yeni İstifadəçi Yarat";

echo "<!-- Create User Module Content Start -->";

// Form üçün lazım olan məlumatları yükləmək üçün dəyişənlər
$departments = [];
$data_load_error = null; // Məlumat yükləmə xətaları üçün

// Əvvəlki cəhddən qalan form məlumatları və xətaları sessiyadan alaq
$form_data = $_SESSION['form_data'] ?? [];
$form_errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_data'], $_SESSION['form_errors']); // Sessiyanı təmizləyək

// Departamentləri yükləyək
try {
    $departmentObj = new Department(); // Autoloader App\Model\Department-i tapacaq
    $departments = $departmentObj->getAllDepartments() ?: [];
    if (empty($departments)) {
         // Departament yoxdursa, xəbərdarlıq etmək olar, amma prosesi dayandırmayaq
         // $data_load_error = "Heç bir departament tapılmadı.";
    }
} catch (\Exception | \Error $e) {
    $data_load_error = "Departament siyahısı yüklənərkən xəta baş verdi.";
    error_log("Create User Form Load Error (Departments): " . $e->getMessage());
    if (function_exists('setFlashMessage')) setFlashMessage('warning', $data_load_error);
}

// BASE_URL konstantının mövcudluğunu yoxlayaq
$base_url = defined('BASE_URL') ? BASE_URL : '';

// POST sorğusunu emal etmək üçün index.php-yə göndərilir,
// ona görə burada POST emalı yoxdur. Yalnız form göstərilir.

?>

<div class="container-fluid mt-4">
    <h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>

    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>
    <?php if ($data_load_error && !function_exists('displayFlashMessages')): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($data_load_error) ?></div>
    <?php endif; ?>


    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">İstifadəçi Məlumatları</h6>
        </div>
        <div class="card-body">
            <?php // DİQQƏT: Formun action atributu boş olmalıdır ki, index.php-yə göndərilsin ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>"> <!-- CSRF token əlavə edildi -->
                <input type="hidden" name="action" value="create_user"> <?php // index.php üçün action ?>

                <div class="mb-3">
                    <label for="name" class="form-label">Ad və Soyad <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($form_errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" required>
                    <?php if (isset($form_errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($form_errors['name']) ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">İstifadəçi Adı <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($form_errors['username']) ? 'is-invalid' : '' ?>" id="username" name="username" value="<?= htmlspecialchars($form_data['username'] ?? '') ?>" required>
                     <?php if (isset($form_errors['username'])): ?><div class="invalid-feedback"><?= htmlspecialchars($form_errors['username']) ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">E-poçt <span class="text-danger">*</span></label>
                    <input type="email" class="form-control <?= isset($form_errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" required>
                     <?php if (isset($form_errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($form_errors['email']) ?></div><?php endif; ?>
                </div>

                 <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Şifrə <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($form_errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" required>
                         <?php if (isset($form_errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($form_errors['password']) ?></div><?php endif; ?>
                    </div>
                     <div class="col-md-6 mb-3">
                        <label for="password_confirm" class="form-label">Şifrə Təkrarı <span class="text-danger">*</span></label>
                        <input type="password" class="form-control <?= isset($form_errors['password_confirm']) ? 'is-invalid' : '' ?>" id="password_confirm" name="password_confirm" required>
                         <?php if (isset($form_errors['password_confirm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($form_errors['password_confirm']) ?></div><?php endif; ?>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="department_id" class="form-label">Şöbə <span class="text-danger">*</span></label>
                    <select class="form-select <?= isset($form_errors['department_id']) ? 'is-invalid' : '' ?>" id="department_id" name="department_id" required <?= empty($departments) ? 'disabled' : '' ?>>
                        <option value="" selected disabled>Şöbə seçin...</option>
                        <?php if (!empty($departments)): ?>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= (isset($form_data['department_id']) && $form_data['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                     <?php if (isset($form_errors['department_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($form_errors['department_id']) ?></div><?php endif; ?>
                     <?php if (empty($departments) && !$data_load_error): ?> <div class="form-text text-warning">Heç bir departament tapılmadı. Əvvəlcə Tənzimləmələr bölməsindən departament əlavə edin.</div> <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="role" class="form-label">Rol <span class="text-danger">*</span></label>
                    <select class="form-select <?= isset($form_errors['role']) ? 'is-invalid' : '' ?>" id="role" name="role" required>
                        <option value="user" <?= (!isset($form_data['role']) || $form_data['role'] == 'user') ? 'selected' : '' ?>>İstifadəçi (User)</option>
                        <option value="manager" <?= (isset($form_data['role']) && $form_data['role'] == 'manager') ? 'selected' : '' ?>>Menecer (Manager)</option>
                        <option value="admin" <?= (isset($form_data['role']) && $form_data['role'] == 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                     <?php if (isset($form_errors['role'])): ?><div class="invalid-feedback"><?= htmlspecialchars($form_errors['role']) ?></div><?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary" <?= empty($departments) ? 'disabled' : '' ?>>İstifadəçi Yarat</button>
                <a href="<?= $base_url ?>/users" class="btn btn-secondary">Ləğv Et</a>
            </form>
        </div> <!-- /.card-body -->
    </div> <!-- /.card -->

</div> <!-- /.container-fluid -->

<?php
echo "<!-- Create User Module Content End -->";
?>
