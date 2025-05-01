<?php
// e:\xampp\htdocs\task_management\modules\tasks\create.php

// Bu faylda birbaşa require/include olmamalıdır! Bütün yükləmələr bootstrap.php və index.php (və ya Controller) üzərindən edilir.

// Controller tərəfindən `extract()` ilə ötürülən dəyişənlər:
// $page_title, $base_url, $departments, $categories, $assignableUsers,
// $data_load_error, $form_errors, $form_data

// Dəyişənlərin mövcudluğunu yoxlayaq və default dəyərlər təyin edək (Controller-dən gəlməzsə xəta olmasın)
$page_title = $page_title ?? 'Yeni Tapşırıq Yarat';
$base_url = $base_url ?? '';
$departments = $departments ?? [];
$categories = $categories ?? [];
$assignableUsers = $assignableUsers ?? [];
$data_load_error = $data_load_error ?? null;
$form_errors = $form_errors ?? [];
$form_data = $form_data ?? [];

// Formun göstərilib göstərilməyəcəyini təyin edən flag
// Form yalnız məlumat yükləmə xətası yoxdursa VƏ həm şöbə, həm də kateqoriya siyahısı boş deyilsə göstərilir.
$can_show_form = (empty($data_load_error) && !empty($departments) && !empty($categories));

?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
        <a href="<?= htmlspecialchars($base_url) ?>/tasks" class="btn btn-secondary btn-sm shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Geri Qayıt
        </a>
    </div>

    <?php // Flash mesajları göstər (functions.php-dəki funksiya ilə) ?>
    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>

    <?php // Controller-dən gələn məlumat yükləmə xətası varsa göstər (qırmızı alert) ?>
    <?php if (!empty($data_load_error)): ?>
        <div class="alert alert-danger">
             <i class="fas fa-exclamation-triangle me-2"></i>
             <?= htmlspecialchars($data_load_error) ?>
        </div>
    <?php endif; ?>

    <?php // Əgər şöbə və ya kateqoriya yoxdursa (amma başqa yükləmə xətası yoxdursa), xəbərdarlıq göstər (sarı alert) ?>
    <?php if ($can_show_form === false && empty($data_load_error) && (empty($departments) || empty($categories))): ?>
         <div class="alert alert-warning">
             <i class="fas fa-info-circle me-2"></i>
             Tapşırıq yaratmaq üçün sistemdə aktiv şöbə və kateqoriya mövcud olmalıdır. Zəhmət olmasa, əvvəlcə onları <a href="<?= htmlspecialchars($base_url) ?>/settings" class="alert-link">Tənzimləmələr</a> bölməsində yaradın.
         </div>
    <?php endif; ?>


    <?php // Yalnız bütün şərtlər ödənirsə ($can_show_form true) formu göstər ?>
    <?php if ($can_show_form): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Tapşırıq Məlumatları</h6>
            </div>
            <div class="card-body">
                <?php // Formun action atributu /tasks/create URL-inə POST sorğusu göndərir ?>
                <?php // index.php router-i bu sorğunu TaskController::store() metoduna yönləndirəcək ?>
                <form method="POST" action="<?= htmlspecialchars($base_url) ?>/tasks/create" id="createTaskForm"> <?php // enctype="multipart/form-data" fayl yükləmə üçün lazımdır ?>
                    <?php // CSRF tokeni (functions.php-dəki generateCsrfToken() ilə yaradılır) ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken() ?? ''); ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="title" class="form-label">Başlıq <span class="text-danger">*</span></label>
                            <input type="text"
                                   class="form-control <?= isset($form_errors['title']) ? 'is-invalid' : '' ?>"
                                   id="taskTitle"
                                   name="title"
                                   value="<?= htmlspecialchars($form_data['title'] ?? '') ?>"
                                   required <?php // HTML5 validasiyası ?>
                                   maxlength="255">
                            <?php if (isset($form_errors['title'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($form_errors['title']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Prioritet <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($form_errors['priority']) ? 'is-invalid' : '' ?>"
                                    id="taskPriority"
                                    name="priority"
                                    required>
                                <option value="" disabled <?= !isset($form_data['priority']) || $form_data['priority'] === '' ? 'selected' : '' ?>>Seçin...</option>
                                <option value="low" <?= (isset($form_data['priority']) && $form_data['priority'] === 'low') ? 'selected' : '' ?>>Aşağı</option>
                                <option value="medium" <?= (isset($form_data['priority']) && $form_data['priority'] === 'medium') ? 'selected' : '' ?>>Orta</option>
                                <option value="high" <?= (isset($form_data['priority']) && $form_data['priority'] === 'high') ? 'selected' : '' ?>>Yüksək</option>
                            </select>
                            <?php if (isset($form_errors['priority'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($form_errors['priority']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Təsvir <span class="text-danger">*</span></label>
                        <textarea class="form-control <?= isset($form_errors['description']) ? 'is-invalid' : '' ?>"
                                  id="taskDescription"
                                  name="description"
                                  rows="4"
                                  required><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
                        <?php if (isset($form_errors['description'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($form_errors['description']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Şöbə <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($form_errors['department_id']) ? 'is-invalid' : '' ?>"
                                    id="taskDepartment"
                                    name="department_id"
                                    required>
                                <option value="" disabled <?= !isset($form_data['department_id']) || $form_data['department_id'] === '' ? 'selected' : '' ?>>Seçin...</option>
                                <?php // Controller-dən gələn $departments array-i üzrə dövr ?>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= htmlspecialchars($department['id']) ?>"
                                            <?= (isset($form_data['department_id']) && (int)$form_data['department_id'] === (int)$department['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($department['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($form_errors['department_id'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($form_errors['department_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label">Kateqoriya <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($form_errors['category_id']) ? 'is-invalid' : '' ?>"
                                    id="taskCategory"
                                    name="category_id"
                                    required>
                                <option value="" disabled <?= !isset($form_data['category_id']) || $form_data['category_id'] === '' ? 'selected' : '' ?>>Seçin...</option>
                                <?php // Controller-dən gələn $categories array-i üzrə dövr ?>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category['id']) ?>"
                                            <?= (isset($form_data['category_id']) && (int)$form_data['category_id'] === (int)$category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($form_errors['category_id'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($form_errors['category_id']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="assignee_id" class="form-label">İcraçı</label>
                            <select class="form-select <?= isset($form_errors['assignee_id']) ? 'is-invalid' : '' ?>"
                                    id="taskAssignee"
                                    name="assignee_id">
                                <?php // Default olaraq "Hamı" seçilsin ?>
                                <option value="everyone" <?= (!isset($form_data['assignee_id']) || ($form_data['assignee_id'] ?? '') === 'everyone') ? 'selected' : '' ?>>Hamı (Təyin edilməyib)</option>
                                <?php // Controller-dən gələn $assignableUsers array-i boş deyilsə ?>
                                <?php if (!empty($assignableUsers)): ?>
                                <optgroup label="İstifadəçilər"> <?php // İstifadəçiləri qruplaşdırırıq ?>
                                    <?php foreach ($assignableUsers as $user): ?>
                                        <?php // Controller artıq yalnız uyğun rolları (məsələn, employee, manager) göndərir ?>
                                        <option value="<?= (int)$user['id'] ?>" <?php // Value rəqəm olmalıdır ?>
                                                <?= (isset($form_data['assignee_id']) && (int)$form_data['assignee_id'] === (int)$user['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['name'] ?? 'Ad yoxdur') ?> (<?= htmlspecialchars(translateUserRole($user['role'] ?? '')) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">Tapşırığı konkret birinə və ya hamıya təyin edə bilərsiniz.</div>
                            <?php if (isset($form_errors['assignee_id'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($form_errors['assignee_id']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php // İkinci sütun boşdur, lazım gələrsə əlavə edilə bilər ?>
                        <div class="col-md-6 mb-3">
                            <?php // Boş yer ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label">Başlanğıc Tarixi</label>
                            <input type="date"
                                   class="form-control <?= isset($form_errors['start_date']) || isset($form_errors['date_logic']) ? 'is-invalid' : '' ?>" <?php // date_logic xətası da ola bilər ?>
                                   id="taskStartDate"
                                   name="start_date"
                                   value="<?= htmlspecialchars($form_data['start_date'] ?? '') ?>"
                                   min="<?= date('Y-m-d') ?>"> <?php // Keçmiş tarix seçməyin qarşısını alır ?>
                            <?php if (isset($form_errors['start_date'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($form_errors['start_date']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Son İcra Tarixi <span class="text-danger">*</span></label>
                            <input type="date"
                                   class="form-control <?= isset($form_errors['due_date']) || isset($form_errors['date_logic']) ? 'is-invalid' : '' ?>" <?php // date_logic xətası da ola bilər ?>
                                   id="taskDueDate"
                                   name="due_date"
                                   value="<?= htmlspecialchars($form_data['due_date'] ?? '') ?>"
                                   required
                                   min="<?= date('Y-m-d') ?>"> <?php // Keçmiş tarix seçməyin qarşısını alır ?>
                            <?php if (isset($form_errors['due_date'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($form_errors['due_date']) ?></div>
                            <?php endif; ?>
                            <?php if (isset($form_errors['date_logic'])): // Başlanğıc/Son tarix məntiqi xətası ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($form_errors['date_logic']) ?></div> <?php // Düzəliş: date_logic xətasını göstər ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Fayl yükləmə (əgər lazımdırsa, aktivləşdirin) -->
                    <!--
                    <div class="mb-3">
                        <label for="attachments" class="form-label">Əlavələr</label>
                        <input class="form-control <?php //= isset($form_errors['attachments']) ? 'is-invalid' : '' ?>"
                               type="file"
                               id="attachments"
                               name="attachments[]"
                               multiple>
                        <?php // if (isset($form_errors['attachments'])): ?>
                            <div class="invalid-feedback"><?php //= htmlspecialchars($form_errors['attachments']) ?></div>
                        <?php // endif; ?>
                        <div class="form-text">Birdən çox fayl seçmək üçün Ctrl (və ya Cmd) düyməsini basılı saxlayın.</div>
                    </div>
                    -->

                    <hr>

                    <?php // Formu göndərmək üçün "Yadda Saxla" düyməsi ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Yadda Saxla
                    </button>
                    <a href="<?= htmlspecialchars($base_url) ?>/tasks" class="btn btn-secondary">Ləğv Et</a>
                </form>
            </div> <!-- /.card-body -->
        </div> <!-- /.card -->
    <?php endif; // if ($can_show_form) ?>

</div> <!-- /.container-fluid -->
