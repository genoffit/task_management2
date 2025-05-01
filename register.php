<?php
// Bootstrap faylını daxil et
require_once __DIR__ . '/bootstrap.php';

// Namespace istifadəsi (Siniflər App\Model altındadır)
use App\Model\User;
use App\Model\Department;

// Əgər istifadəçi artıq daxil olubsa, ana səhifəyə yönləndir
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

$departments = [];
$errors = [];
// Form datanı xəta olduqda saxlamaq üçün
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Departamentləri yüklə
try {
    $departmentObj = new Department(); // Autoloader App\Model\Department-i tapacaq
    $departments = $departmentObj->getAllDepartments();
    if ($departments === false) { // getAllDepartments xəta qaytardıqda
         $errors['system'] = 'Could not load department list. Please try again later.';
         $departments = []; // Boş array təyin et
    }
} catch (Exception $e) { // Ümumi xətalar
    error_log('Error loading departments: ' . $e->getMessage());
    $errors['system'] = 'Could not load department list. Please try again later.';
} catch (\Error $e) { // Fatal errorlar
     error_log('Fatal Error loading departments: ' . $e->getMessage());
     $errors['system'] = 'A critical error occurred loading departments.';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CSRF Token Yoxlaması (əlavə edilməlidir) ---
    // if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    //     $errors['csrf'] = 'Invalid request. Please try again.';
    // } else {

        // Inputları təmizləmə və validasiya
        $form_data = $_POST;
        $name = sanitize($form_data['name'] ?? '');
        $username = sanitize($form_data['username'] ?? '');
        $email = filter_var(sanitize($form_data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $form_data['password'] ?? '';
        $password_confirm = $form_data['password_confirm'] ?? '';
        $department_id = filter_var($form_data['department_id'] ?? '', FILTER_VALIDATE_INT);
        $role = sanitize($form_data['role'] ?? 'user');

        // --- Validasiya ---
        if (empty($name)) $errors['name'] = 'Name is required.';
        if (empty($username)) $errors['username'] = 'Username is required.';
        elseif (strlen($username) < 3) $errors['username'] = 'Username must be at least 3 characters.';

        if ($email === false) $errors['email'] = 'Invalid email format.';

        if (empty($password)) $errors['password'] = 'Password is required.';
        elseif (strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters long.';
        elseif ($password !== $password_confirm) $errors['password_confirm'] = 'Passwords do not match.';

        if ($department_id === false || $department_id <= 0) $errors['department_id'] = 'Please select a valid department.';
        if (!in_array($role, ['user', 'manager', 'admin'])) $errors['role'] = 'Invalid role selected.'; // Mümkün rolları yoxla

        // İstifadəçi adı və e-poçtun mövcudluğunu yoxla (əgər validasiya xətası yoxdursa)
        if (empty($errors)) {
            try {
                $userCheck = new User();
                if (!empty($username) && $userCheck->findByUsername($username)) {
                    $errors['username'] = 'Username already exists.';
                }
                if (!empty($email) && $userCheck->findByEmail($email)) {
                    $errors['email'] = 'Email already registered.';
                }
            } catch (Exception $e) {
                 error_log('Registration Check Error: ' . $e->getMessage());
                 $errors['system'] = 'Could not verify username/email uniqueness.';
            } catch (\Error $e) {
                 error_log('Registration Check Fatal Error: ' . $e->getMessage());
                 $errors['system'] = 'A critical error occurred during validation.';
            }
        }
        // --- Validasiya Sonu ---


        if (empty($errors)) {
            // Şifrəni hash et
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            try {
                $userObj = new User();
                $userData = [
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashed_password,
                    'department_id' => $department_id,
                    'role' => $role
                ];
                $userId = $userObj->createUser($userData);

                if ($userId) {
                    unset($_SESSION['form_data']);
                    setFlashMessage('success', 'Registration successful! You can now login.');
                    header('Location: ' . BASE_URL . '/login.php');
                    exit;
                } else {
                    // createUser metodundan false qayıtdıqda (məsələn, DB xətası və ya dublikat)
                    // User sinifindəki xəta loglaması daha detallı məlumat verməlidir
                    $errors['system'] = 'Registration failed. Please try again or contact support.';
                }
            } catch (Exception $e) {
                error_log('Registration Error: ' . $e->getMessage());
                $errors['system'] = 'An unexpected error occurred during registration.';
            } catch (\Error $e) {
                 error_log('Registration Fatal Error: ' . $e->getMessage());
                 $errors['system'] = 'A critical error occurred during registration.';
            }
        }

        // Əgər xəta varsa, form datanı sessiyada saxla və geri yönləndir
        if (!empty($errors)) {
            $_SESSION['form_data'] = $form_data;
            setFlashMessage('error', 'Please fix the errors below and try again.');
            header('Location: ' . BASE_URL . '/register.php');
            exit;
        }
    // } // CSRF else bloku
}

// --- CSRF Token Yaratmaq (əlavə edilməlidir) ---
// $csrf_token = generateCsrfToken();

// Səhifə başlığı
$page_title = "Register";

// Header-i daxil et
if (file_exists(ROOT_PATH . '/includes/header_minimal.php')) {
    require_once ROOT_PATH . '/includes/header_minimal.php';
} else {
    echo '<!DOCTYPE html><html lang="az"><head><meta charset="UTF-8"><title>Register</title>';
    echo '<link rel="stylesheet" href="' . (defined('BASE_URL') ? BASE_URL : '.') . '/assets/vendors/bootstrap/bootstrap.min.css">';
    echo '<link rel="stylesheet" href="' . (defined('BASE_URL') ? BASE_URL : '.') . '/assets/css/style.css">';
    echo '</head><body>';
}
?>

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                 <div class="card-header bg-success text-white">
                    <h1 class="text-center h4 mb-0"><?= htmlspecialchars($page_title) ?></h1>
                </div>
                <div class="card-body">
                    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>
                    <?php if (isset($errors['system'])): ?><div class="alert alert-danger"><?= htmlspecialchars($errors['system']) ?></div><?php endif; ?>
                    <?php if (isset($errors['csrf'])): ?><div class="alert alert-danger"><?= htmlspecialchars($errors['csrf']) ?></div><?php endif; ?>

                    <form method="POST" action="<?= defined('BASE_URL') ? BASE_URL : '' ?>/register.php" novalidate>
                        <!-- <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? ''); ?>"> -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Name:</label>
                            <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($form_data['name'] ?? '') ?>" required>
                            <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username:</label>
                            <input type="text" id="username" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($form_data['username'] ?? '') ?>" required>
                            <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['username']) ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email:</label>
                            <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" required>
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password:</label>
                                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                                <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                            </div>
                             <div class="col-md-6 mb-3">
                                <label for="password_confirm" class="form-label">Confirm Password:</label>
                                <input type="password" id="password_confirm" name="password_confirm" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" required>
                                <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password_confirm']) ?></div><?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="department_id" class="form-label">Department:</label>
                            <select id="department_id" name="department_id" class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>" required <?= empty($departments) ? 'disabled' : '' ?>>
                                <option value="">Select Department</option>
                                <?php if (!empty($departments)): ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?= $dept['id']; ?>" <?= (isset($form_data['department_id']) && $form_data['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dept['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($errors['department_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['department_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role:</label>
                            <select id="role" name="role" class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>">
                                <option value="user" <?= (!isset($form_data['role']) || $form_data['role'] == 'user') ? 'selected' : '' ?>>User</option>
                                <option value="manager" <?= (isset($form_data['role']) && $form_data['role'] == 'manager') ? 'selected' : '' ?>>Manager</option>
                                <!-- <option value="admin" <?= (isset($form_data['role']) && $form_data['role'] == 'admin') ? 'selected' : '' ?>>Admin</option> -->
                            </select>
                             <?php if (isset($errors['role'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['role']) ?></div><?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-success w-100" <?= empty($departments) ? 'disabled' : '' ?>>Register</button>
                    </form>
                    <p class="mt-3 text-center text-muted">
                        Already have an account? <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/login.php">Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer-i daxil et
if (file_exists(ROOT_PATH . '/includes/footer_minimal.php')) {
    require_once ROOT_PATH . '/includes/footer_minimal.php';
} else {
    echo '<script src="' . (defined('BASE_URL') ? BASE_URL : '.') . '/assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
}
?>
