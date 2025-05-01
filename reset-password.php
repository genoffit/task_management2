<?php
// Bootstrap faylını daxil et (sessiya, konstantlar, funksiyalar, autoloading)
require_once __DIR__ . '/bootstrap.php';

// Namespace istifadəsi
use App\Model\User;
use App\Core\Database; // Yalnız token əməliyyatları üçün DB bağlantısı lazımdır
// use PHPMailer\PHPMailer\PHPMailer; // E-poçt üçün (lazım gələrsə aktivləşdirin)
// use PHPMailer\PHPMailer\Exception as PHPMailerException; // E-poçt üçün (lazım gələrsə aktivləşdirin)

// Əgər istifadəçi artıq daxil olubsa, ana səhifəyə yönləndir
if (isset($_SESSION['user_id'])) {
    // BASE_URL konstantının mövcudluğunu yoxlayaq
    $dashboard_url = (defined('BASE_URL') ? BASE_URL : '') . '/dashboard';
    header('Location: ' . $dashboard_url);
    exit;
}

$errors = [];
$email_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CSRF Token Yoxlaması (əlavə edilməlidir) ---
    // if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
    //     $errors['csrf'] = 'Invalid request. Please try again.';
    // } else {

        $email_input = sanitize($_POST['email'] ?? '');
        $email = filter_var($email_input, FILTER_VALIDATE_EMAIL);

        if ($email === false) {
            $errors['email'] = 'Invalid email format.';
        } else {
            $userFound = false; // İstifadəçinin tapılıb-tapılmadığını izləmək üçün
            try {
                // İstifadəçinin mövcudluğunu yoxla
                $userObj = new User();
                $user = $userObj->findByEmail($email);

                if ($user) {
                    $userFound = true;
                    // Token yarat və verilənlər bazasına yaz
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token 1 saat qüvvədədir

                    $db = Database::getInstance()->getConnection(); // DB bağlantısını al

                    // Köhnə tokenləri sil (eyni e-poçt üçün)
                    $stmt_delete = $db->prepare("DELETE FROM password_resets WHERE email = :email");
                    $stmt_delete->bindParam(':email', $email, \PDO::PARAM_STR); // PDO namespace olmadan istifadə edilir
                    $stmt_delete->execute();

                    // Yeni tokeni `password_resets` cədvəlinə daxil et
                    $stmt_insert = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)");
                    $stmt_insert->bindParam(':email', $email, \PDO::PARAM_STR);
                    $stmt_insert->bindParam(':token', $token, \PDO::PARAM_STR);
                    $stmt_insert->bindParam(':expires_at', $expires, \PDO::PARAM_STR);

                    if ($stmt_insert->execute()) {
                        // --- E-POÇT GÖNDƏRMƏ (Implementasiya edilməlidir) ---
                        $reset_link = (defined('BASE_URL') ? BASE_URL : '') . '/update-password.php?token=' . $token;
                        $email_sent = false; // Hələlik false, implementasiyadan sonra true olacaq

                        /* // PHPMailer Nümunəsi (Composer ilə quraşdırılmalıdır: composer require phpmailer/phpmailer)
                        try {
                            $mail = new PHPMailer(true);

                            // Server settings (config faylından oxumaq daha yaxşıdır)
                            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Debug üçün
                            $mail->isSMTP();
                            $mail->Host       = 'your_smtp_host'; // Məs: smtp.gmail.com
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'your_smtp_username@example.com';
                            $mail->Password   = 'your_smtp_password';
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Və ya SMTPS
                            $mail->Port       = 587; // Və ya 465 (SMTPS üçün)

                            // Recipients
                            $mail->setFrom('no-reply@yourdomain.com', 'Task Management App');
                            $mail->addAddress($email, $user['name']); // Alanın adı ilə

                            // Content
                            $mail->isHTML(true);
                            $mail->Subject = 'Password Reset Request';
                            $mail->Body    = "Hello " . htmlspecialchars($user['name']) . ",<br><br>You requested a password reset. Click the link below to set a new password:<br><a href='{$reset_link}'>{$reset_link}</a><br><br>This link is valid for 1 hour.<br><br>If you did not request this, please ignore this email.";
                            $mail->AltBody = "Hello " . htmlspecialchars($user['name']) . ",\n\nYou requested a password reset. Copy and paste the following link into your browser to set a new password:\n{$reset_link}\n\nThis link is valid for 1 hour.\n\nIf you did not request this, please ignore this email.";

                            $mail->send();
                            $email_sent = true;
                        } catch (PHPMailerException $e) {
                            error_log("Mailer Error: {$mail->ErrorInfo}");
                            // E-poçt göndərmə xətası baş versə belə, istifadəçiyə ümumi mesaj göstərmək olar
                        }
                        */

                        if ($email_sent) {
                             if (function_exists('setFlashMessage')) setFlashMessage('success', 'If an account exists for ' . htmlspecialchars($email) . ', a password reset link has been sent.');
                        } else {
                            // E-poçt göndərilməsə belə, istifadəçiyə ümumi mesaj göstər
                             if (function_exists('setFlashMessage')) setFlashMessage('success', 'If an account exists for ' . htmlspecialchars($email) . ', a password reset link has been sent. (Check spam folder)');
                            // Developer üçün log:
                            error_log("Password reset token generated for $email, but email sending failed or is disabled.");
                            // Test üçün linki göstərmək olar (yalnız development-də!)
                            // if (function_exists('setFlashMessage')) setFlashMessage('info', 'DEV ONLY: Reset link: ' . $reset_link);
                        }
                        // --- E-POÇT GÖNDƏRMƏ SONU ---
                    } else {
                        $errors['system'] = 'Failed to store password reset request.';
                    }
                }
                // İstifadəçi tapılmasa belə, eyni mesajı göstər (enumeration attack qarşısı)
                 if (!$userFound && empty($errors)) {
                     if (function_exists('setFlashMessage')) setFlashMessage('success', 'If an account exists for ' . htmlspecialchars($email) . ', a password reset link has been sent.');
                 }

            } catch (\PDOException $e) { // DB xətaları
                 error_log('Password Reset DB Error: ' . $e->getMessage());
                 $errors['system'] = 'A database error occurred. Please try again later.';
            } catch (\Exception $e) { // Ümumi xətalar (məs., User sinifi, random_bytes)
                error_log('Password Reset Error: ' . $e->getMessage());
                $errors['system'] = 'An unexpected error occurred. Please try again later.';
            } catch (\Error $e) { // Fatal errorlar (məs., sinif tapılmama)
                 error_log('Password Reset Fatal Error: ' . $e->getMessage());
                 $errors['system'] = 'A critical error occurred. Please contact support.';
            }
        }

        // Əgər xəta varsa, form datanı sessiyada saxla
        if (!empty($errors)) {
            $_SESSION['form_data'] = ['email' => $email_input];
            // Xəta mesajını flash-a yazmaq daha yaxşıdır, çünki yönləndiririk
            $errorMsg = $errors['email'] ?? $errors['system'] ?? $errors['csrf'] ?? 'An error occurred.';
             if (function_exists('setFlashMessage')) setFlashMessage('error', $errorMsg);
        }

        // PRG Pattern: POST sonrası yönləndirmə
        $redirect_url = (defined('BASE_URL') ? BASE_URL : '') . '/reset-password.php';
        header('Location: ' . $redirect_url);
        exit;
    // } // CSRF else bloku
}

// Sessiyadan form datanı al (əgər varsa)
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
$email_input = $form_data['email'] ?? '';

// --- CSRF Token Yaratmaq (əlavə edilməlidir) ---
// $csrf_token = generateCsrfToken(); // Bu funksiya functions.php-də olmalıdır

// Səhifə başlığı
$page_title = "Reset Password";

// Header-i daxil et
if (file_exists(ROOT_PATH . '/includes/header_minimal.php')) {
    require_once ROOT_PATH . '/includes/header_minimal.php';
} else {
    // Fallback header
    echo '<!DOCTYPE html><html lang="az"><head><meta charset="UTF-8"><title>Reset Password</title>';
    echo '<link rel="stylesheet" href="' . (defined('BASE_URL') ? BASE_URL : '.') . '/assets/vendors/bootstrap/bootstrap.min.css">';
    echo '<link rel="stylesheet" href="' . (defined('BASE_URL') ? BASE_URL : '.') . '/assets/css/style.css">'; // Əsas stil faylı
    echo '</head><body>';
}
?>

<div class="container mt-5">
     <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h1 class="text-center h4 mb-0"><?= htmlspecialchars($page_title) ?></h1>
                </div>
                <div class="card-body">
                    <?php
                        // Flash mesajları göstər (functions.php-də olmalıdır)
                        if (function_exists('displayFlashMessages')) {
                            displayFlashMessages();
                        } else {
                            // Fallback: Sessiyadan birbaşa oxumaq (əgər funksiya yoxdursa)
                            if (isset($_SESSION['flash_message'])) {
                                $message = $_SESSION['flash_message'];
                                unset($_SESSION['flash_message']);
                                $type = $message['type'] ?? 'info';
                                $text = $message['text'] ?? '';
                                echo "<div class='alert alert-{$type}'>{$text}</div>";
                            }
                        }
                    ?>

                    <p class="text-muted">Enter your account's email address and we will send you a link to reset your password.</p>

                    <form method="POST" action="<?= defined('BASE_URL') ? BASE_URL : '' ?>/reset-password.php" novalidate>
                         <!-- CSRF Token Input (əlavə edilməlidir) -->
                         <!-- <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? ''); ?>"> -->

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address:</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email_input) ?>" required autofocus>
                            <!-- Xəta mesajı flash mesajda göstərildiyi üçün burada ayrıca göstərməyə ehtiyac yoxdur -->
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Send Password Reset Link</button>
                    </form>
                    <p class="mt-3 text-center">
                        Remembered your password? <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/login.php">Login</a>
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
    // Fallback footer
    echo '<script src="' . (defined('BASE_URL') ? BASE_URL : '.') . '/assets/vendors/bootstrap/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
}
?>
