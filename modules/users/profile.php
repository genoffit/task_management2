<?php
// modules/users/profile.php

// Səlahiyyət yoxlaması (istifadəçi daxil olubmu?)
if (!isset($_SESSION['user_id'])) {
    if (function_exists('setFlashMessage')) setFlashMessage('error', 'Profilinizi görmək üçün daxil olmalısınız.');
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/login.php');
    exit;
}

// Səhifə başlığı
$page_title = "Mənim Profilim";

echo "<!-- User Profile Module Content Start -->";

// İstifadəçi məlumatlarını sessiyadan və ya DB-dən almaq olar
$user_name_profile = $_SESSION['user_name'] ?? 'N/A';
$user_username_profile = $_SESSION['user_username'] ?? 'N/A';
$user_role_profile = $_SESSION['user_role'] ?? 'N/A';
// E-poçt və digər məlumatlar üçün DB-dən oxumaq lazım ola bilər
// $userObj = new \App\Model\User();
// $userDataProfile = $userObj->findById($_SESSION['user_id']);
// $user_email_profile = $userDataProfile['email'] ?? 'N/A';

// BASE_URL
$base_url = defined('BASE_URL') ? BASE_URL : '';
?>

<div class="container-fluid mt-4">
    <h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>

    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Profil Məlumatları</h6>
        </div>
        <div class="card-body">
            <p><strong>Ad:</strong> <?= htmlspecialchars($user_name_profile) ?></p>
            <p><strong>İstifadəçi Adı:</strong> <?= htmlspecialchars($user_username_profile) ?></p>
            <p><strong>Rol:</strong> <?= htmlspecialchars(ucfirst($user_role_profile)) ?></p>
            <!-- <p><strong>E-poçt:</strong> <?= htmlspecialchars($user_email_profile) ?></p> -->
            <hr>
            <p><i>Profil redaktəsi funksionallığı gələcəkdə əlavə ediləcək.</i></p>
            <!-- <a href="<?= $base_url ?>/users/edit/<?= $_SESSION['user_id'] ?>" class="btn btn-warning">Profili Redaktə Et</a> -->
        </div>
    </div>

</div> <!-- /.container-fluid -->

<?php
echo "<!-- User Profile Module Content End -->";
?>
