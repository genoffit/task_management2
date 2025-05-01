<?php
// e:\xampp\htdocs\task_management\modules\dashboard\index.php

// Namespace istifadəsi
use App\Service\Statistics;
use App\Model\Task;

// Səhifə başlığı
$page_title = "İdarə Paneli";

echo "<!-- Dashboard Content Start -->";

// Məlumatları yükləmək üçün dəyişənlər
$cardStats = []; // Kartlar üçün ümumi statistika
$statusDistribution = []; // Status diaqramı üçün
$departmentDistribution = []; // Şöbə diaqramı üçün
$recentTasks = []; // Son tapşırıqlar siyahısı
$overdueTasksList = []; // Gecikənlər siyahısı
$myActiveTasks = []; // Mənə təyin edilənlər
$load_error_message = null;

// Chart məlumatları üçün
$statusChartLabels = [];
$statusChartData = [];
$statusChartColors = [];
$deptChartLabels = [];
$deptChartData = [];

// Cari istifadəçi ID-si
$currentUserId = $_SESSION['user_id'] ?? null;

try {
    // Model və Servisləri yarat
    $statsService = new Statistics();
    $taskModel = new Task();

    // 1. Kartlar üçün Statistikaları al
    $cardStats = $statsService->getDashboardCardStats();

    // 2. Status Diaqramı üçün Məlumatları al və emal et
    $statusDistributionRaw = $statsService->getTaskStats(); // getTaskStats istifadə edirik
    if (is_array($statusDistributionRaw)) {
        $statusColorMap = ['pending' => '#f6c23e', 'in_progress' => '#36b9cc', 'completed' => '#1cc88a', 'declined' => '#e74a3b', '(boş)' => '#858796']; // Boş status üçün rəng
        foreach ($statusDistributionRaw as $stat) {
            $statusName = $stat['status'] ?? 'Bilinmir';
            $statusCount = (int)($stat['count'] ?? 0);
            if ($statusCount > 0) { // Yalnız sayı 0-dan böyük olanları göstər
                $statusKey = strtolower(trim($statusName));
                $statusChartLabels[] = translateStatus($statusName); // Tərcümə et
                $statusChartData[] = $statusCount;
                $statusChartColors[] = $statusColorMap[$statusKey] ?? '#858796'; // Bilinməyən status üçün boz rəng
            }
        }
    } else {
        $load_error_message .= " Status statistikası yüklənə bilmədi.";
    }

    // 3. Şöbə Diaqramı üçün Məlumatları al və emal et
    $departmentDistributionRaw = $statsService->getActiveTasksByDepartmentDistribution();
     if (is_array($departmentDistributionRaw)) {
         foreach ($departmentDistributionRaw as $stat) {
             $deptName = $stat['name'] ?? 'Bilinmir';
             $deptCount = (int)($stat['count'] ?? 0);
             if ($deptCount > 0) {
                 $deptChartLabels[] = htmlspecialchars($deptName);
                 $deptChartData[] = $deptCount;
             }
         }
     } else {
         $load_error_message .= " Şöbə statistikası yüklənə bilmədi.";
     }

    // 4. Siyahılar üçün Məlumatları al
    $recentTasks = $taskModel->getRecentTasks(9) ?: []; // Son 9
    $overdueTasksList = $taskModel->getOverdueTasksList(9) ?: []; // Son 9 gecikən

    // 5. Mənə təyin edilmiş aktiv tapşırıqlar (əgər istifadəçi daxil olubsa)
    if ($currentUserId) {
        $myActiveTasks = $taskModel->getActiveTasksAssignedTo($currentUserId, 9) ?: [];
    }

} catch (\Exception | \Error $e) {
    $load_error_message = "Dashboard məlumatları yüklənərkən xəta baş verdi.";
    error_log("Dashboard Load Exception/Error: " . $e->getMessage());
    // Xəta baş verdikdə kartları sıfırla
    $cardStats = array_fill_keys(['pending', 'in_progress', 'overdue', 'due_today', 'created_last_7_days', 'total_active'], 0);
}

// BASE_URL
$base_url = defined('BASE_URL') ? BASE_URL : '';
?>

<div class="container-fluid">

    <!-- Səhifə Başlığı -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>
        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="fas fa-download fa-sm text-white-50"></i> Hesabat Yarat</a>
    </div>

    <?php if ($load_error_message): ?>
        <div class="alert alert-danger"><?= trim($load_error_message) ?></div>
    <?php endif; ?>
    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>


    <!-- Statistika Kartları Sırası -->
    <div class="row">

        <!-- Gözləmədə Olanlar -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div>
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Gözləmədə</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?= htmlspecialchars($cardStats['pending'] ?? 0) ?></div>
                    </div>
                    <div class="icon-circle icon-circle-warning">
                        <i class="fas fa-pause fa-lg text-white"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gecikənlər -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div>
                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">Gecikənlər</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?= htmlspecialchars($cardStats['overdue'] ?? 0) ?></div>
                    </div>
                     <div class="icon-circle icon-circle-danger">
                        <i class="fas fa-exclamation-triangle fa-lg text-white"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bu Gün Bitməli -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2 stat-card">
                <div class="card-body">
                     <div>
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Bu Gün Bitməli</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?= htmlspecialchars($cardStats['due_today'] ?? 0) ?></div>
                    </div>
                    <div class="icon-circle icon-circle-info">
                        <i class="fas fa-calendar-day fa-lg text-white"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Son 7 Gün -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div>
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Son 7 Gündə Yaradılan</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?= htmlspecialchars($cardStats['created_last_7_days'] ?? 0) ?></div>
                    </div>
                     <div class="icon-circle icon-circle-primary">
                        <i class="fas fa-plus-circle fa-lg text-white"></i>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- /.row -->


    <!-- Diaqramlar Sırası -->
    <div class="row">

        <!-- Status Paylanması (Pie Chart) -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Status üzrə Paylanma</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($statusChartData)): ?>
                        <div class="chart-pie pt-4 pb-2" style="height: 250px;">
                            <canvas id="dashboardStatusPieChart"></canvas>
                        </div>
                        <div class="mt-4 text-center small">
                            <?php foreach ($statusChartLabels as $index => $label): ?>
                                <span class="me-2">
                                    <i class="fas fa-circle" style="color: <?= htmlspecialchars($statusChartColors[$index] ?? '#858796') ?>;"></i> <?= htmlspecialchars($label) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                         <p class="text-muted text-center">Status diaqramı üçün məlumat yoxdur.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Şöbələr üzrə Aktiv Tapşırıqlar (Bar Chart) -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Şöbələr üzrə Aktiv Tapşırıqlar</h6>
                </div>
                <div class="card-body">
                     <?php if (!empty($deptChartData)): ?>
                        <div class="chart-bar" style="height: 320px;">
                            <canvas id="dashboardDepartmentBarChart"></canvas>
                        </div>
                     <?php else: ?>
                         <p class="text-muted text-center">Şöbə diaqramı üçün məlumat yoxdur.</p>
                     <?php endif; ?>
                </div>
            </div>
        </div>
    </div><!-- /.row -->

     <!-- Tapşırıq Siyahıları Sırası -->
     <div class="row">
         <!-- Son Əlavə Edilmiş Tapşırıqlar -->
         <div class="col-lg-6 mb-4">
             <div class="card shadow">
                 <div class="card-header py-3">
                     <h6 class="m-0 font-weight-bold text-primary">Son Əlavə Edilmiş Tapşırıqlar</h6>
                 </div>
                 <div class="card-body">
                     <?php if (!empty($recentTasks)): ?>
                         <ul class="list-group list-group-flush">
                             <?php foreach ($recentTasks as $task): ?>
                                 <li class="list-group-item d-flex justify-content-between align-items-center">
                                     <a href="<?= $base_url ?>/tasks/view/<?= $task['id'] ?>"><?= htmlspecialchars($task['title']) ?></a>
                                     <span class="badge bg-<?= getStatusBadgeClass($task['status'] ?? null) ?> rounded-pill">
                                         <?= htmlspecialchars(translateStatus($task['status'] ?? null)) ?>
                                     </span>
                                 </li>
                             <?php endforeach; ?>
                         </ul>
                     <?php else: ?>
                         <p class="text-muted">Son tapşırıq yoxdur.</p>
                     <?php endif; ?>
                 </div>
             </div>
         </div>

         <!-- Gecikən Tapşırıqlar -->
         <div class="col-lg-6 mb-4">
             <div class="card shadow">
                 <div class="card-header py-3">
                     <h6 class="m-0 font-weight-bold text-danger">Gecikən Tapşırıqlar</h6>
                 </div>
                 <div class="card-body">
                     <?php if (!empty($overdueTasksList)): ?>
                          <ul class="list-group list-group-flush">
                             <?php foreach ($overdueTasksList as $task):
                                 $daysOverdue = 0;
                                 if ($task['due_date']) {
                                     try {
                                         $dueDate = new DateTime($task['due_date']);
                                         $now = new DateTime('today');
                                         if ($dueDate < $now) {
                                             $interval = $now->diff($dueDate);
                                             $daysOverdue = $interval->days;
                                         }
                                     } catch (Exception $e) { /* Tarix formatı səhvdirsə */ }
                                 }
                             ?>
                                 <li class="list-group-item d-flex justify-content-between align-items-center">
                                     <div>
                                         <a href="<?= $base_url ?>/tasks/view/<?= $task['id'] ?>" class="text-danger"><?= htmlspecialchars($task['title']) ?></a>
                                         <small class="d-block text-muted">Son tarix: <?= htmlspecialchars($task['due_date'] ? date('d.m.Y', strtotime($task['due_date'])) : '-') ?></small>
                                     </div>
                                     <?php if ($daysOverdue > 0): ?>
                                         <span class="badge bg-danger rounded-pill">
                                             <?= $daysOverdue ?> gün gecikir
                                         </span>
                                     <?php endif; ?>
                                 </li>
                             <?php endforeach; ?>
                         </ul>
                     <?php else: ?>
                         <p class="text-muted">Gecikən tapşırıq yoxdur.</p>
                     <?php endif; ?>
                 </div>
             </div>
         </div>

         <!-- Mənə Təyin Edilmiş Aktiv Tapşırıqlar -->
         <?php if (!empty($myActiveTasks)): ?>
         <div class="col-lg-12 mb-4">
             <div class="card shadow">
                 <div class="card-header py-3">
                     <h6 class="m-0 font-weight-bold text-info">Mənə Təyin Edilmiş Aktiv Tapşırıqlar</h6>
                 </div>
                 <div class="card-body">
                     <ul class="list-group list-group-flush">
                         <?php foreach ($myActiveTasks as $task): ?>
                             <li class="list-group-item d-flex justify-content-between align-items-center">
                                 <div>
                                     <a href="<?= $base_url ?>/tasks/view/<?= $task['id'] ?>"><?= htmlspecialchars($task['title']) ?></a>
                                     <small class="d-block text-muted">Son tarix: <?= htmlspecialchars($task['due_date'] ? date('d.m.Y', strtotime($task['due_date'])) : '-') ?></small>
                                 </div>
                                 <span class="badge bg-<?= getStatusBadgeClass($task['status'] ?? null) ?> rounded-pill">
                                     <?= htmlspecialchars(translateStatus($task['status'] ?? null)) ?>
                                 </span>
                             </li>
                         <?php endforeach; ?>
                     </ul>
                 </div>
             </div>
         </div>
         <?php endif; ?>

     </div><!-- /.row -->


</div> <!-- /.container-fluid -->

<?php // Chart.js üçün konfiqurasiya skripti (əvvəlki kimi) ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pie Chart Məlumatları
    const pieLabels = <?= json_encode($statusChartLabels) ?>;
    const pieData = <?= json_encode($statusChartData) ?>;
    const pieColors = <?= json_encode($statusChartColors) ?>;

    // Pie Chart Konfiqurasiyası
    var ctxPie = document.getElementById("dashboardStatusPieChart");
    if (ctxPie && pieData.length > 0) {
        var myPieChart = new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: pieLabels,
                datasets: [{ data: pieData, backgroundColor: pieColors, hoverBackgroundColor: pieColors, hoverBorderColor: "rgba(234, 236, 244, 1)" }],
            },
            options: {
                maintainAspectRatio: false, responsive: true,
                plugins: { tooltip: { /* ... */ }, legend: { display: false } },
                cutout: '70%',
            },
        });
    }

    // Bar Chart Məlumatları
    const barLabels = <?= json_encode($deptChartLabels) ?>;
    const barData = <?= json_encode($deptChartData) ?>;

    // Bar Chart Konfiqurasiyası
    var ctxBar = document.getElementById("dashboardDepartmentBarChart");
    if (ctxBar && barData.length > 0) {
        var myBarChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: barLabels,
                datasets: [{ label: "Aktiv Tapşırıqlar", backgroundColor: "#3a7bd5", hoverBackgroundColor: "#2e59d9", borderColor: "#3a7bd5", data: barData, maxBarThickness: 35 }],
            },
            options: {
                maintainAspectRatio: false, responsive: true,
                scales: { x: { grid: { display: false } }, y: { ticks: { min: 0, stepSize: 1 }, grid: { color: "rgb(234, 236, 244)" } } },
                plugins: { legend: { display: false }, tooltip: { /* ... */ } },
            }
        });
    }
});
</script>


<?php
echo "<!-- Dashboard Content End -->";
?>
