<?php
// Bootstrap faylını daxil et (sessiya, konstantlar, funksiyalar, autoloading)
require_once __DIR__ . '/bootstrap.php';

// Namespace istifadəsi (User sinifi App\Model namespace-dədir)
use App\Model\User;

// Əgər istifadəçi artıq daxil olubsa, ana səhifəyə (dashboard) yönləndir
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard'); // index.php yerinə modul adı
    exit;
}

$errors = [];
$username_input = ''; // Formda göstərmək üçün

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CSRF Token Yoxlaması (əlavə edilməlidir) ---
    // if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    //     $errors['csrf'] = 'Invalid request. Please try again.';
    // } else {

        // Inputları al və təmizlə (sanitize funksiyası bootstrap.php vasitəsilə mövcud olmalıdır)
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? ''; // Şifrəni təmizləməyə ehtiyac yoxdur
        $username_input = $username; // Xəta olduqda formda saxlamaq üçün

        // Sadə validasiya
        if (empty($username)) {
            $errors['username'] = 'Username is required.';
        }
        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        }

        // Əgər validasiya xətası yoxdursa, istifadəçini yoxla
        if (empty($errors)) {
            try {
                $userObj = new User(); // Autoloader App\Model\User-i tapacaq
                $user = $userObj->findByUsername($username);

                // verifyPassword metodu User sinifindədir
                if ($user && $userObj->verifyPassword($password, $user['password'])) {
                    // Şifrə doğrudur, sessiyanı quraşdır
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_username'] = $user['username'];

                    // Sessiya ID-sini yenilə (Session Fixation hücumlarına qarşı)
                    session_regenerate_id(true);

                    // Əvvəlki səhifəyə yönləndir (əgər varsa) və ya dashboard-a
                    $redirect_url = $_SESSION['redirect_to'] ?? BASE_URL . '/dashboard';
                    unset($_SESSION['redirect_to']);

                    setFlashMessage('success', 'Welcome back, ' . htmlspecialchars($user['name']) . '!');
                    header('Location: ' . $redirect_url);
                    exit;
                } else {
                    // İstifadəçi adı və ya şifrə yanlışdır
                    $errors['credentials'] = 'Invalid username or password.';
                }
            } catch (Exception $e) { // Ümumi xətalar
                error_log('Login Error: ' . $e->getMessage());
                $errors['system'] = 'An unexpected error occurred. Please try again later.';
            } catch (\Error $e) { // Fatal errorlar (məs., sinif tapılmama)
                 error_log('Login Fatal Error: ' . $e->getMessage());
                 $errors['system'] = 'A critical error occurred. Please contact support.';
            }
        }
    // } // CSRF else bloku
}

// --- CSRF Token Yaratmaq (əlavə edilməlidir) ---
// $csrf_token = generateCsrfToken();

// Səhifə başlığı
$page_title = "Login";

// Header-i daxil et
if (file_exists(ROOT_PATH . '/includes/header_minimal.php')) {
    require_once ROOT_PATH . '/includes/header_minimal.php';
} else {
    echo '<!DOCTYPE html><html lang="az"><head><meta charset="UTF-8"><title>Login</title>';
    echo '<link rel="stylesheet" href="' . (defined('BASE_URL') ? BASE_URL : '.') . '/assets/vendors/bootstrap/bootstrap.min.css">';
    echo '<link rel="stylesheet" href="' . (defined('BASE_URL') ? BASE_URL : '.') . '/assets/css/style.css">';
    echo '</head><body>';
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="text-center h4 mb-0"><?= htmlspecialchars($page_title) ?></h1>
                </div>
                <div class="card-body">
                    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>
                    <?php if (isset($errors['credentials'])): ?><div class="alert alert-danger"><?= htmlspecialchars($errors['credentials']) ?></div><?php endif; ?>
                    <?php if (isset($errors['system'])): ?><div class="alert alert-danger"><?= htmlspecialchars($errors['system']) ?></div><?php endif; ?>
                    <?php if (isset($errors['csrf'])): ?><div class="alert alert-danger"><?= htmlspecialchars($errors['csrf']) ?></div><?php endif; ?>

                    <form method="POST" action="<?= defined('BASE_URL') ? BASE_URL : '' ?>/login.php" novalidate>
                        <!-- <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? ''); ?>"> -->
                        <div class="mb-3">
                            <label for="username" class="form-label">Username:</label>
                            <input type="text" id="username" name="username" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($username_input) ?>" required autofocus>
                            <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['username']) ?></div><?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password:</label>
                            <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" required>
                             <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                    <p class="mt-3 text-center">
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/reset-password.php">Forgot password?</a>
                    </p>
                    <p class="mt-2 text-center text-muted">
                        Don't have an account? <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/register.php">Register</a>
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
