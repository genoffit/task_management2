<?php
// e:\xampp\htdocs\task_management\modules\tasks\view.php

// Namespace istifadəsi
use App\Model\Task;
// use App\Model\Comment; // Şərh əlavə etmə index.php-də olacaq

// Səlahiyyət yoxlaması (əgər lazımdırsa)
/* ... */

// URL-dən tapşırıq ID-sini al
global $params;
$task_id = isset($params[0]) ? (int)$params[0] : 0;

// Səhifə başlığı
$page_title = "Tapşırıq Detalları";

echo "<!-- Task View Module Content Start -->";

$taskData = null;
$error_message = null;
$comments = []; // Controller-dən gələcək
$history = []; // Controller-dən gələcək

// BASE_URL konstantının mövcudluğunu yoxlayaq
$base_url = defined('BASE_URL') ? BASE_URL : ''; // BASE_URL-i əvvəldən təyin edək

if ($task_id <= 0) {
    $error_message = "Yanlış tapşırıq ID.";
} else {
    try {
        $taskObj = new Task();
        $taskData = $taskObj->getTaskById($task_id);

        if (!$taskData) {
            $error_message = "Tapşırıq tapılmadı.";
        } else {
            $page_title = "Tapşırıq: " . htmlspecialchars($taskData['title']);
            // Controller artıq $comments və $history dəyişənlərini təyin edir
            // $comments = $comments ?? []; // Bu yoxlamalar artıq Controller-dədir
            // $history = $history ?? [];
        }

    } catch (\Exception | \Error $e) {
        $error_message = "Tapşırıq məlumatları yüklənərkən xəta baş verdi: " . htmlspecialchars($e->getMessage());
        error_log("Task View Load Error (ID: $task_id): " . $e->getMessage());
        $taskData = null;
    }
}


// Statusu formatlamaq üçün köməkçi funksiya (artıq functions.php-də translateStatus var)
// function formatStatusName(?string $status): string { ... } // Bu artıq lazım deyil

// Cari statusu və status dəyişmə imkanını yoxla (İngiliscə dəyərlərlə)
$current_status_view = $taskData['status'] ?? '';
$can_start_task = ($current_status_view === 'pending'); // Yalnız pending olanda icraya götürmək olar
$can_finish_task = ($current_status_view === 'in_progress'); // Yalnız in_progress olanda bitirmək olar

?>

<div class="container-fluid mt-4">

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?= $error_message ?></div>
    <?php elseif ($taskData): ?>
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
             <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
             <!-- Redaktə düyməsi aşağı köçürüldü -->
        </div>

        <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>

        <div class="row">
            <?php // Sağ sütun ləğv edildiyi üçün əsas sütun tam eni tutur ?>
            <div class="col-lg-12 mb-4">
                <!-- Task Details Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Tapşırıq Məlumatları</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Təsvir:</strong></p>
                        <p><?= nl2br(htmlspecialchars($taskData['description'] ?? 'Təsvir yoxdur.')) ?></p>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <?php // DƏYİŞİKLİK BURADA: translateStatus istifadə et ?>
                                <p><strong>Status:</strong> <span class="badge bg-<?= getStatusBadgeClass($taskData['status'] ?? '') ?>"><?= htmlspecialchars(translateStatus($taskData['status'])) ?></span></p>
                                <p><strong>Prioritet:</strong> <span class="text-<?= getPriorityClass($taskData['priority'] ?? '') ?>"><?= htmlspecialchars(ucfirst($taskData['priority'] ?? 'Bilinmir')) ?></span></p>
                                <p><strong>Son İcra Tarixi:</strong> <?= htmlspecialchars($taskData['due_date'] ? date('d.m.Y', strtotime($taskData['due_date'])) : 'Təyin edilməyib') ?></p>
                                <?php if (!empty($taskData['start_date'])): ?>
                                    <p><strong>Başlanğıc Tarixi:</strong> <?= htmlspecialchars(date('d.m.Y', strtotime($taskData['start_date']))) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Təyin Edilən:</strong> <?= htmlspecialchars($taskData['assigned_user_name'] ?? 'Təyin edilməyib') ?></p>
                                <p><strong>Şöbə:</strong> <?= htmlspecialchars($taskData['department_name'] ?? 'Təyin edilməyib') ?></p>
                                <?php if (!empty($taskData['started_at'])): ?>
                                    <p><strong>İcraya Götürülmə:</strong> <?= htmlspecialchars(date('d.m.Y H:i', strtotime($taskData['started_at']))) ?>
                                        <?php // TODO: started_by_user_id üçün adı göstər ?>
                                    </p>
                                <?php endif; ?>
                                <p><strong>Kateqoriya:</strong>
                                    <span class="badge" style="background-color: <?= htmlspecialchars($taskData['category_color'] ?? '#6c757d') ?>; color: white;">
                                        <?= htmlspecialchars($taskData['category_name'] ?? 'Təyin edilməyib') ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                         <hr>
                    </div> <!-- /.card-body -->
                </div> <!-- /.card -->

                <!-- === DÜYMƏLƏR BÖLMƏSİ === -->
                <div class="mt-3 mb-4 d-flex justify-content-start align-items-center gap-2 flex-wrap"> <?php // flex-wrap əlavə edildi ?>
                     <a href="<?= $base_url ?>/tasks/edit/<?= $taskData['id'] ?>" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit fa-sm"></i> Redaktə Et
                     </a>

                     <?php if ($can_start_task): // İcraya götürmə düyməsi ?>
                        <form method="POST" action="<?= $base_url ?>/tasks" style="display: inline-block;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>">
                            <input type="hidden" name="action" value="start_progress">
                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($taskData['id']) ?>">
                            <button type="submit" class="btn btn-info btn-sm">
                                <i class="fas fa-play fa-sm"></i> İcraya Götür
                            </button>
                        </form>
                     <?php endif; ?>

                     <?php if ($can_finish_task): // Bitirmə düyməsi (yalnız icrada olanda) ?>
                        <!-- Tapşırığı Bitir Düyməsi (Modalı açır) -->
                        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#finishTaskModal">
                            <i class="fas fa-check-circle fa-sm"></i> Tapşırığı Bitir
                        </button>
                     <?php endif; ?>

                     <form method="POST" action="<?= $base_url ?>/tasks" onsubmit="return confirm('Bu tapşırığı silmək istədiyinizə əminsiniz?');" style="display: inline-block;"> <?php // inline-block ?>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>">
                        <input type="hidden" name="action" value="delete_task">
                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($taskData['id']) ?>">
                        <button type="submit" class="btn btn-danger btn-sm" title="Sil">
                            <i class="fas fa-trash"></i> Sil
                        </button>
                    </form>
                </div>
                 <!-- === DÜYMƏLƏR BÖLMƏSİ SONU === -->


                <!-- Task History Card -->
                 <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Tapşırıq Tarixçəsi</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($history)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($history as $entry): ?>
                                    <?php
                                        $action_icon = match($entry['action']) {
                                            'created' => 'fas fa-plus-circle text-success',
                                            'started' => 'fas fa-play-circle text-info',
                                            'status_changed' => 'fas fa-check-circle text-primary', // completed/declined üçün fərqli ola bilər
                                            'commented' => 'fas fa-comment text-muted',
                                            'edited' => 'fas fa-edit text-warning',
                                            default => 'fas fa-info-circle text-secondary'
                                        };
                                        // Status dəyişikliyi üçün daha dəqiq ikon
                                        if ($entry['action'] === 'status_changed') {
                                            if (str_contains($entry['details'] ?? '', 'completed')) $action_icon = 'fas fa-check-circle text-success';
                                            elseif (str_contains($entry['details'] ?? '', 'declined')) $action_icon = 'fas fa-times-circle text-danger';
                                        }
                                    ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto">
                                            <div class="fw-bold"><i class="<?= $action_icon ?> me-2"></i><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $entry['action']))) ?></div>
                                            <?php if (!empty($entry['details'])): ?>
                                                <small class="d-block text-muted ms-4"><?= htmlspecialchars($entry['details']) ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($entry['user_name'])): ?>
                                                <small class="d-block text-muted ms-4"><i>by <?= htmlspecialchars($entry['user_name']) ?></i></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-light text-dark rounded-pill"><small><?= htmlspecialchars(date('d.m.Y H:i', strtotime($entry['created_at']))) ?></small></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted"><i>Bu tapşırıq üçün tarixçə qeydi yoxdur.</i></p>
                        <?php endif; ?>
                    </div>
                </div>


            </div> <!-- /.col-lg-12 -->

            <?php // Sağ sütun (col-lg-4) ləğv edildi ?>

        </div> <!-- /.row -->

        <!-- Finish Task Modal -->
        <div class="modal fade" id="finishTaskModal" tabindex="-1" aria-labelledby="finishTaskModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="<?= $base_url ?>/tasks">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>">
                        <input type="hidden" name="action" value="finish_task">
                        <input type="hidden" name="task_id" value="<?= $task_id ?>">

                        <div class="modal-header">
                            <h5 class="modal-title" id="finishTaskModalLabel">Tapşırığı Bitir</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                             <div class="mb-3">
                                <label for="finish_comment" class="form-label">Yekun Şərh <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="finish_comment" name="comment" rows="3" required></textarea>
                                <div class="form-text">Tapşırığın tamamlanması və ya ləğvi barədə qeyd əlavə edin.</div>
                            </div>
                             <div class="mb-3">
                                <label for="finish_status" class="form-label">Yekun Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="finish_status" name="new_status" required>
                                    <option value="" selected disabled>Seçin...</option>
                                    <?php // DƏYİŞİKLİK BURADA: value ingiliscə, mətn azərbaycanca ?>
                                    <option value="completed">Tamamlandı</option>
                                    <option value="declined">Ləğv Edildi</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Bağla</button>
                            <button type="submit" class="btn btn-primary">Təsdiq Et</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- /Finish Task Modal -->

    <?php else: ?>
        <div class="alert alert-info">Tapşırıq tapılmadı.</div>
    <?php endif; ?>

    <a href="<?= $base_url ?>/tasks" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left me-2"></i> Tapşırıq Siyahısına Qayıt</a>

</div> <!-- /.container-fluid -->

<?php
echo "<!-- Task View Module Content End -->";
?>
