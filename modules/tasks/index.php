<?php
// e:\xampp\htdocs\task_management\modules\tasks\index.php

// Namespace istifadəsi
use App\Model\Task;
use App\Model\User;
use App\Model\Department;
use App\Model\Category;

// Səlahiyyət yoxlaması
if (!isset($_SESSION['user_id'])) {
    if (function_exists('setFlashMessage')) setFlashMessage('error', 'Tapşırıqları görmək üçün daxil olmalısınız.');
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/login.php');
    exit;
}

// Səhifə başlığı
$page_title = "Tapşırıqlar";

echo "<!-- Tasks Module Content Start -->";

// Məlumatları yükləmək üçün dəyişənlər
// === DƏYİŞİKLİK: $tasks, $departments və s. artıq Controller tərəfindən ötürülür ===
// $tasks = [];
// $departments = [];
// $categories = [];
// $users = [];
// $error_message_load = null;
// $base_url = defined('BASE_URL') ? BASE_URL : '';
// $currentUserId = $_SESSION['user_id'] ?? null;
// $currentUserRole = $_SESSION['user_role'] ?? null;
// $filters = [];
// $active_filters = [];
// === DƏYİŞİKLİK SONU ===

// Controller tərəfindən ötürülən dəyişənləri qəbul edirik (extract() ilə gəlir)
// $page_title, $base_url, $tasks, $departments, $categories, $users,
// $error_message_load, $filters, $active_filters, $currentUserId, $currentUserRole

?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
        <a href="<?= $base_url ?>/tasks/create" class="btn btn-primary btn-sm shadow-sm">
            <i class="fas fa-plus fa-sm text-white-50"></i> Yeni Tapşırıq Yarat
        </a>
    </div>

    <?php if ($error_message_load): ?>
        <div class="alert alert-danger"><?= $error_message_load ?></div>
    <?php endif; ?>

    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>

    <!-- Filtr Paneli -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
             <h6 class="m-0 font-weight-bold text-primary">Filtrlər</h6>
        </div>
        <div class="card-body">
             <form method="GET" action=""> <?php // action boş olmalıdır ki, cari URL-ə göndərsin ?>
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="departmentFilterList" class="form-label">Şöbə</label>
                        <select id="departmentFilterList" name="department_id" class="form-select form-select-sm">
                            <option value="">Bütün şöbələr</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>" <?= (isset($filters['department_id']) && $filters['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="categoryFilterList" class="form-label">Kateqoriya</label>
                        <select id="categoryFilterList" name="category_id" class="form-select form-select-sm">
                            <option value="">Bütün kateqoriyalar</option>
                             <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= (isset($filters['category_id']) && $filters['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="assigneeFilterList" class="form-label">İcraçı</label>
                        <select id="assigneeFilterList" name="assignee_id" class="form-select form-select-sm">
                            <option value="" <?= (!isset($filters['assignee_id']) || $filters['assignee_id'] === '') ? 'selected' : '' ?>>Bütün İcraçılar</option> <?php // Mətn dəyişdirildi və seçilmə şərti gücləndirildi ?>
                             <?php // DİQQƏT: $users indi yalnız assignable olanları ehtiva edir (TaskController-dən) ?>
                             <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= (isset($filters['assignee_id']) && $filters['assignee_id'] == $user['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="statusFilterList" class="form-label">Status</label>
                        <select id="statusFilterList" name="status" class="form-select form-select-sm">
                            <option value="">Bütün statuslar</option>
                            <option value="pending" <?= (isset($filters['status']) && $filters['status'] == 'pending') ? 'selected' : '' ?>>Gözləmədə</option>
                            <option value="in_progress" <?= (isset($filters['status']) && $filters['status'] == 'in_progress') ? 'selected' : '' ?>>İcrada</option>
                            <option value="completed" <?= (isset($filters['status']) && $filters['status'] == 'completed') ? 'selected' : '' ?>>Tamamlandı</option>
                            <option value="declined" <?= (isset($filters['status']) && $filters['status'] == 'declined') ? 'selected' : '' ?>>Ləğv Edildi</option>
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="priorityFilterList" class="form-label">Prioritet</label>
                        <select id="priorityFilterList" name="priority" class="form-select form-select-sm">
                            <option value="">Bütün prioritetlər</option>
                            <option value="low" <?= (isset($filters['priority']) && $filters['priority'] == 'low') ? 'selected' : '' ?>>Aşağı</option>
                            <option value="medium" <?= (isset($filters['priority']) && $filters['priority'] == 'medium') ? 'selected' : '' ?>>Orta</option>
                            <option value="high" <?= (isset($filters['priority']) && $filters['priority'] == 'high') ? 'selected' : '' ?>>Yüksək</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary btn-sm">Filtrlə</button>
                        <a href="<?= $base_url ?>/tasks" class="btn btn-secondary btn-sm">Təmizlə</a>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Tapşırıq Siyahısı</h6>
        </div>
        <div class="card-body">
            <?php if (empty($tasks) && !$error_message_load): ?>
                <?php if (!empty($active_filters)): ?>
                    <p class="text-muted no-tasks-row">Seçilmiş filtrlərə uyğun tapşırıq tapılmadı.</p>
                <?php else: ?>
                    <p class="text-muted no-tasks-row">Heç bir tapşırıq tapılmadı.</p>
                <?php endif; ?>
            <?php elseif (!empty($tasks)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="tasksTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <!-- === DƏYİŞİKLİK: Sütun başlıqları === -->
                                <th>Başlıq</th>
                                <th>Təsviri</th>
                                <th>Yaradılma Tarixi</th>
                                <th>Son İcra Tarixi</th>
                                <th>İcraçı</th>
                                <th>Prioritet</th>
                                <th>Status</th>
                                <th>Əməliyyatlar</th>
                                <!-- === DƏYİŞİKLİK SONU === -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr id="task-row-<?= $task['id'] ?>">
                                    <!-- === DƏYİŞİKLİK: Sütun məzmunu === -->
                                    <td><?= htmlspecialchars($task['title']) ?></td>
                                    <td>
                                        <?php // Təsviri qısaltmaq (məsələn, ilk 50 simvol) ?>
                                        <?= htmlspecialchars(mb_substr($task['description'] ?? '', 0, 50)) ?>
                                        <?= (mb_strlen($task['description'] ?? '') > 50) ? '...' : '' ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($task['created_at'] ? date('d.m.Y H:i', strtotime($task['created_at'])) : '-') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($task['due_date'] ? date('d.m.Y', strtotime($task['due_date'])) : '-') ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($task['assignee_name'] ?? 'Hamı') ?> <?php // NULL olarsa "Hamı" göstər ?>
                                    </td>
                                    <td>
                                        <span class="text-<?= getPriorityClass($task['priority'] ?? null) ?>">
                                            <?= htmlspecialchars(translatePriority($task['priority'] ?? null)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= getStatusBadgeClass($task['status'] ?? null) ?>">
                                            <?= htmlspecialchars(translateStatus($task['status'] ?? null)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= $base_url ?>/tasks/view/<?= htmlspecialchars($task['id']) ?>" class="btn btn-info btn-sm me-1" title="Bax">
                                            <i class="fas fa-eye fa-sm"></i> <?php // Mətn silindi ?>
                                        </a>
                                        <?php
                                            $canEditDelete = false;
                                            if (in_array($currentUserRole, ['admin', 'manager'])) {
                                                $canEditDelete = true;
                                            }
                                        ?>
                                        <?php if ($canEditDelete): ?>
                                            <a href="<?= $base_url ?>/tasks/edit/<?= htmlspecialchars($task['id']) ?>" class="btn btn-warning btn-sm me-1" title="Redaktə Et">
                                                <i class="fas fa-edit fa-sm"></i> <?php // Mətn silindi ?>
                                            </a>
                                            <form method="POST" action="" onsubmit="return confirm('Bu tapşırığı silmək istədiyinizə əminsiniz?');" style="display: inline-block;"> <!-- Token əlavə edildi -->
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>">
                                                <input type="hidden" name="action" value="delete_task"> <?php // index.php-dəki router üçün ?>
                                                <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Sil">
                                                    <i class="fas fa-trash fa-sm"></i> <?php // Mətn silindi ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                     <!-- === DƏYİŞİKLİK SONU === -->
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div> <!-- /.container-fluid -->

<?php
echo "<!-- Tasks Module Content End -->";
?>
