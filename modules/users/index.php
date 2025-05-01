<?php
// e:\xampp\htdocs\task_management\modules\users\index.php

// Bu faylda birbaşa require/include olmamalıdır! Bütün yükləmələr bootstrap.php və index.php (və ya Controller) üzərindən edilir.

// Controller tərəfindən `extract()` ilə ötürülən dəyişənlər:
// $page_title, $base_url, $users, $error_message_load

// Dəyişənlərin mövcudluğunu yoxlayaq və default dəyərlər təyin edək
$page_title = $page_title ?? 'İstifadəçilər';
$base_url = $base_url ?? '';
$users = $users ?? [];
$error_message_load = $error_message_load ?? null;

// Cari istifadəçinin rolunu sessiyadan alaq (düymələri göstərmək üçün)
$currentUserRole = $_SESSION['user_role'] ?? null;
$currentUserId = $_SESSION['user_id'] ?? null;

?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
        <?php // Yalnız admin yeni istifadəçi yarada bilsin ?>
        <?php if ($currentUserRole === 'admin'): ?>
            <a href="<?= htmlspecialchars($base_url) ?>/users/create" class="btn btn-primary btn-sm shadow-sm">
                <i class="fas fa-plus fa-sm text-white-50"></i> Yeni İstifadəçi Yarat
            </a>
        <?php endif; ?>
    </div>

    <?php // Controller-dən gələn yükləmə xətası varsa göstər ?>
    <?php if ($error_message_load): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message_load) ?></div>
    <?php endif; ?>

    <?php // Flash mesajları göstər ?>
    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">İstifadəçi Siyahısı</h6>
        </div>
        <div class="card-body">
            <?php // İstifadəçi siyahısı boşdursa mesaj göstər ?>
            <?php if (empty($users) && !$error_message_load): ?>
                <p class="text-muted">Heç bir istifadəçi tapılmadı.</p>
            <?php // İstifadəçi siyahısı boş deyilsə cədvəli göstər ?>
            <?php elseif (!empty($users)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="usersTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ad Soyad</th>
                                <th>İstifadəçi Adı</th>
                                <th>E-poçt</th>
                                <th>Şöbə</th>
                                <th>Rol</th>
                                <th>Status</th>
                                <?php // Əməliyyatlar sütunu yalnız admin üçün ?>
                                <?php if ($currentUserRole === 'admin'): ?>
                                    <th>Əməliyyatlar</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php
                                    // === DÜZƏLİŞ: Status adı və badge classını ayrı alırıq ===
                                    $statusText = translateUserStatus($user['status'] ?? null);
                                    $statusBadgeClass = getUserStatusBadgeClass($user['status'] ?? null);
                                    // === DÜZƏLİŞ SONU ===
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['id']) ?></td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= htmlspecialchars($user['department_name'] ?? 'Təyin edilməyib') ?></td>
                                    <td><?= htmlspecialchars(translateUserRole($user['role'] ?? null)) ?></td>
                                    <td>
                                        <?php // Düzəliş: Ayrı dəyişənləri istifadə edirik ?>
                                        <span class="badge bg-<?= $statusBadgeClass ?>">
                                            <?= htmlspecialchars($statusText) ?>
                                        </span>
                                    </td>
                                    <?php // Əməliyyatlar yalnız admin üçün ?>
                                    <?php if ($currentUserRole === 'admin'): ?>
                                    <td>
                                        <a href="<?= htmlspecialchars($base_url) ?>/users/edit/<?= htmlspecialchars($user['id']) ?>" class="btn btn-warning btn-sm me-1" title="Redaktə Et">
                                            <i class="fas fa-edit fa-sm"></i> <?php // Redaktə Et ?>
                                        </a>

                                        <?php // Aktiv/Deaktiv düymələri ?>
                                        <?php if ($user['status'] === 'active' && $user['id'] != $currentUserId): // Admin özünü deaktiv edə bilməz ?>
                                            <form method="POST" action="<?= htmlspecialchars($base_url) ?>/users/update-status" onsubmit="return confirm('Bu istifadəçini deaktiv etmək istədiyinizə əminsiniz?');" style="display: inline-block;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>">
                                                <input type="hidden" name="action" value="deactivate_user">
                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" title="Deaktiv Et">
                                                    <i class="fas fa-user-slash fa-sm"></i> <?php // Deaktiv Et ?>
                                                </button>
                                            </form>
                                        <?php elseif ($user['status'] === 'inactive'): ?>
                                             <form method="POST" action="<?= htmlspecialchars($base_url) ?>/users/update-status" onsubmit="return confirm('Bu istifadəçini aktiv etmək istədiyinizə əminsiniz?');" style="display: inline-block;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>">
                                                <input type="hidden" name="action" value="activate_user">
                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['id']) ?>">
                                                <button type="submit" class="btn btn-success btn-sm" title="Aktiv Et">
                                                    <i class="fas fa-user-check fa-sm"></i> <?php // Aktiv Et ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php // Silmə düyməsi (gələcəkdə əlavə edilə bilər) ?>
                                        <!--
                                        <form method="POST" action="<?php //= htmlspecialchars($base_url) ?>/users/delete" onsubmit="return confirm('Bu istifadəçini silmək istədiyinizə əminsiniz? Bu əməliyyat geri qaytarıla bilməz!');" style="display: inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?php //= htmlspecialchars(generateCsrfToken() ?? ''); ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php //= htmlspecialchars($user['id']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Sil">
                                                <i class="fas fa-trash fa-sm"></i> Sil
                                            </button>
                                        </form>
                                        -->
                                    </td>
                                    <?php endif; // if ($currentUserRole === 'admin') ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; // if (!empty($users)) ?>
        </div> <!-- /.card-body -->
    </div> <!-- /.card -->

</div> <!-- /.container-fluid -->

<?php
// DataTables üçün JavaScript (əgər istifadə olunursa, şərhi qaldırın)
/*
<script>
$(document).ready(function() {
    $('#usersTable').DataTable({
        "language": { // Azərbaycan dilinə tərcümə (əgər lazımdırsa)
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Azerbaijan.json"
        },
        "order": [[ 1, "asc" ]] // Ada görə sırala
    });
});
</script>
*/
?>
