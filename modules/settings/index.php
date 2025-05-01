<?php
// e:\xampp\htdocs\task_management\modules\settings\index.php

// Namespace istifadəsi
use App\Model\Department;
use App\Model\Category;

// Səlahiyyət yoxlaması (yalnız admin)
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
$page_title = "Tənzimləmələr";

echo "<!-- Settings Module Content Start -->";

// Məlumatları yükləmək üçün dəyişənlər
$departments = [];
$categories = [];
$error_message_load = null;
$base_url = defined('BASE_URL') ? BASE_URL : '';

// Əvvəlki cəhddən qalan form məlumatları və xətaları sessiyadan alaq
$form_data = $_SESSION['form_data'] ?? [];
$form_errors = $_SESSION['form_errors'] ?? []; // Hələlik istifadə olunmur, amma gələcəkdə lazım ola bilər
unset($_SESSION['form_data'], $_SESSION['form_errors']);

try {
    // Departamentləri yüklə
    $deptObj = new Department();
    $departments = $deptObj->getAllDepartments() ?: [];

    // Kateqoriyaları yüklə
    $catObj = new Category();
    $categories = $catObj->getAllCategories() ?: [];

} catch (\Exception | \Error $e) {
    $error_message_load = "Tənzimləmə məlumatları yüklənərkən xəta baş verdi: " . htmlspecialchars($e->getMessage());
    error_log("Settings Load Error: " . $e->getMessage());
}

?>

<div class="container-fluid"> <?php // mt-4 silindi ?>
    <h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>

    <?php if ($error_message_load): ?>
        <div class="alert alert-danger"><?= $error_message_load ?></div>
    <?php endif; ?>

    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>

    <div class="row">
        <div class="col-lg-12">
            <!-- Tab Naviqasiyası -->
            <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab" aria-controls="departments" aria-selected="true">Şöbələr</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab" aria-controls="categories" aria-selected="false">Kateqoriyalar</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="false">Sistem</button>
                </li>
            </ul>

            <!-- Tab Məzmunu -->
            <div class="tab-content" id="settingsTabContent">

                <!-- Şöbələr Tabı -->
                <div class="tab-pane fade show active" id="departments" role="tabpanel" aria-labelledby="departments-tab">
                    <div class="row">
                        <!-- Yeni Şöbə Formu -->
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Yeni Şöbə Əlavə Et</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>"> <!-- Token əlavə edildi -->
                                        <input type="hidden" name="action" value="create_department">
                                        <div class="mb-3">
                                            <label for="department_name" class="form-label">Şöbə Adı <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="department_name" name="department_name" value="<?= htmlspecialchars($form_data['department_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="department_description" class="form-label">Təsvir</label>
                                            <textarea class="form-control" id="department_description" name="department_description" rows="3"><?= htmlspecialchars($form_data['department_description'] ?? '') ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Əlavə Et</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Şöbələr Siyahısı -->
                        <div class="col-lg-8 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Mövcud Şöbələr</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($departments) && !$error_message_load): ?>
                                        <p class="text-muted">Heç bir şöbə tapılmadı.</p>
                                    <?php elseif (!empty($departments)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Ad</th>
                                                        <th>Təsvir</th>
                                                        <th>Əməliyyatlar</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($dept['id']) ?></td>
                                                            <td><?= htmlspecialchars($dept['name']) ?></td>
                                                            <td><?= htmlspecialchars($dept['description'] ?? '-') ?></td>
                                                            <td>
                                                                <?php // DÜZƏLİŞ: Redaktə düyməsi (hələlik link, edit səhifəsi yaradılmalıdır) ?>
                                                                <a href="<?= $base_url ?>/settings/edit_department/<?= htmlspecialchars($dept['id']) ?>" class="btn btn-warning btn-sm me-1" title="Redaktə Et">
                                                                    <i class="fas fa-edit fa-sm"></i> Redaktə Et
                                                                </a>
                                                                <?php // DÜZƏLİŞ: Sil düyməsi (form ilə) ?>
                                                                <form method="POST" action="" onsubmit="return confirm('Bu şöbəni silmək istədiyinizə əminsiniz? Bu şöbəyə bağlı tapşırıqlar və ya istifadəçilər varsa, silinməyə bilər.');" style="display: inline-block;">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>"> <!-- Token əlavə edildi -->
                                                                    <input type="hidden" name="action" value="delete_department">
                                                                    <input type="hidden" name="department_id" value="<?= htmlspecialchars($dept['id']) ?>">
                                                                    <button type="submit" class="btn btn-danger btn-sm" title="Sil">
                                                                        <i class="fas fa-trash fa-sm"></i> Sil
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- /#departments -->

                <!-- Kateqoriyalar Tabı -->
                <div class="tab-pane fade" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                     <div class="row">
                        <!-- Yeni Kateqoriya Formu -->
                        <div class="col-lg-4 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Yeni Kateqoriya Əlavə Et</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>"> <!-- Token əlavə edildi -->
                                        <input type="hidden" name="action" value="create_category">
                                        <div class="mb-3">
                                            <label for="category_name" class="form-label">Kateqoriya Adı <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="category_name" name="category_name" value="<?= htmlspecialchars($form_data['category_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="category_description" class="form-label">Təsvir</label>
                                            <textarea class="form-control" id="category_description" name="category_description" rows="3"><?= htmlspecialchars($form_data['category_description'] ?? '') ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="category_color" class="form-label">Rəng</label>
                                            <input type="color" class="form-control form-control-color" id="category_color" name="category_color" value="<?= htmlspecialchars($form_data['category_color'] ?? '#6c757d') ?>" title="Rəng seçin">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Əlavə Et</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Kateqoriyalar Siyahısı -->
                        <div class="col-lg-8 mb-4">
                            <div class="card shadow">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Mövcud Kateqoriyalar</h6>
                                </div>
                                <div class="card-body">
                                     <?php if (empty($categories) && !$error_message_load): ?>
                                        <p class="text-muted">Heç bir kateqoriya tapılmadı.</p>
                                    <?php elseif (!empty($categories)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Ad</th>
                                                        <th>Rəng</th>
                                                        <th>Təsvir</th>
                                                        <th>Əməliyyatlar</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($categories as $cat): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($cat['id']) ?></td>
                                                            <td><?= htmlspecialchars($cat['name']) ?></td>
                                                            <td>
                                                                <span class="badge" style="background-color: <?= htmlspecialchars($cat['color'] ?? '#6c757d') ?>; color: white; padding: 5px 10px;">
                                                                    <?= htmlspecialchars($cat['color'] ?? 'N/A') ?>
                                                                </span>
                                                            </td>
                                                            <td><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                                                            <td>
                                                                 <?php // DÜZƏLİŞ: Redaktə düyməsi (hələlik link, edit səhifəsi yaradılmalıdır) ?>
                                                                <a href="<?= $base_url ?>/settings/edit_category/<?= htmlspecialchars($cat['id']) ?>" class="btn btn-warning btn-sm me-1" title="Redaktə Et">
                                                                    <i class="fas fa-edit fa-sm"></i> Redaktə Et
                                                                </a>
                                                                <?php // DÜZƏLİŞ: Sil düyməsi (form ilə) ?>
                                                                <form method="POST" action="" onsubmit="return confirm('Bu kateqoriyanı silmək istədiyinizə əminsiniz? Bu kateqoriyaya bağlı tapşırıqlar varsa, silinməyə bilər.');" style="display: inline-block;">
                                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>">
                                                                    <input type="hidden" name="action" value="delete_category">
                                                                    <input type="hidden" name="category_id" value="<?= htmlspecialchars($cat['id']) ?>">
                                                                    <button type="submit" class="btn btn-danger btn-sm" title="Sil">
                                                                        <i class="fas fa-trash fa-sm"></i> Sil
                                                                    </button>
                                                                </form>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div> <!-- /#categories -->

                <!-- Sistem Tənzimləmələri Tabı -->
                <div class="tab-pane fade" id="system" role="tabpanel" aria-labelledby="system-tab">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Sistem Tənzimləmələri</h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted"><i>Bu funksionallıq hələ implementasiya edilməyib.</i></p>
                            <p>Burada bildiriş ayarları, tema seçimi, e-poçt konfiqurasiyası kimi ümumi sistem tənzimləmələri yer alacaq.</p>
                            <!-- Sistem tənzimləmələri formu buraya əlavə ediləcək -->
                        </div>
                    </div>
                </div> <!-- /#system -->

            </div> <!-- /.tab-content -->
        </div> <!-- /.col-lg-12 -->
    </div> <!-- /.row -->

</div> <!-- /.container-fluid -->

<?php
echo "<!-- Settings Module Content End -->";
?>
