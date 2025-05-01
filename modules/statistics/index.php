<?php
// e:\xampp\htdocs\task_management\modules\statistics\index.php

// Namespace istifadəsi
use App\Service\Statistics;

// Səlahiyyət yoxlaması (məsələn, menecer və adminlər)
if (function_exists('checkRole')) {
    checkRole(['manager', 'admin']);
} else {
    // Fallback
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
        if (function_exists('setFlashMessage')) setFlashMessage('error', 'Bu bölməyə giriş icazəniz yoxdur.');
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/dashboard');
        exit;
    }
}

// Səhifə başlığı
$page_title = "Statistika";

echo "<!-- Statistics Module Content Start -->";

// Məlumatları yükləmək üçün dəyişənlər
$taskStats = []; // Tərcümə olunmuş statuslarla cədvəl üçün
$error_message_load = null;
$base_url = defined('BASE_URL') ? BASE_URL : '';

// Chart.js üçün məlumatlar
$chartLabels = [];
$chartData = [];
$chartColors = [];
$statusColorMap = [ // İngiliscə açarlarla qalır
    'pending' => '#f6c23e',
    'in_progress' => '#36b9cc',
    'completed' => '#1cc88a',
    'declined' => '#e74a3b',
    '(boş və ya null)' => '#858796',
];

try {
    // Statistik məlumatları yüklə
    $statsObj = new Statistics();
    $taskStatsRaw = $statsObj->getTaskStats(); // İngiliscə statuslarla gəlir

    if (is_array($taskStatsRaw)) {
        foreach ($taskStatsRaw as $stat) {
            $statusName = $stat['status'] ?? 'Bilinmir'; // İngiliscə
            $statusCount = (int)($stat['count'] ?? 0);
            $statusKey = strtolower(trim($statusName));

            // Cədvəl üçün tərcümə olunmuş məlumatları yığ
            $taskStats[] = [
                'status_key' => $statusName, // İngiliscə açar (badge üçün)
                'status_display' => translateStatus($statusName), // Azərbaycan adı (göstərmək üçün)
                'count' => $statusCount
            ];

            // Chart üçün məlumatları yığ (yalnız sayı 0-dan böyük olanları)
            // if ($statusCount > 0) {
                $chartLabels[] = translateStatus($statusName); // Azərbaycan adı
                $chartData[] = $statusCount;
                $chartColors[] = $statusColorMap[$statusKey] ?? '#858796';
            // }
        }
    } else {
         $error_message_load = "Statistika məlumatları yüklənə bilmədi.";
    }

} catch (\Exception | \Error $e) {
    $error_message_load = "Statistika məlumatları yüklənərkən xəta baş verdi: " . htmlspecialchars($e->getMessage());
    error_log("Statistics Load Error: " . $e->getMessage());
}

// Digər diaqramlar üçün placeholderlar (backend lazım olacaq)
$deptChartLabels = ['Marketinq', 'İT', 'Satış', 'HR'];
$deptChartData = [25, 35, 15, 10]; // Nümunə data
$monthlyLabels = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'İyn'];
$monthlyCreatedData = [10, 15, 12, 18, 20, 25];
$monthlyCompletedData = [8, 12, 10, 15, 18, 22];

?>

<div class="container-fluid"> <?php // mt-4 silindi ?>
    <h1 class="h3 mb-4 text-gray-800"><?= htmlspecialchars($page_title) ?></h1>

    <?php if ($error_message_load): ?>
        <div class="alert alert-danger"><?= $error_message_load ?></div>
    <?php endif; ?>

    <?php if (function_exists('displayFlashMessages')) displayFlashMessages(); ?>

    <!-- Filtr Paneli (Placeholder) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
             <h6 class="m-0 font-weight-bold text-primary">Filtrlər</h6>
        </div>
        <div class="card-body">
            <form>
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="dateRange" class="form-label">Tarix Aralığı</label>
                        <input type="text" class="form-control" id="dateRange" placeholder="Tarix seçin...">
                        <?php // Date range picker JS kitabxanası lazım olacaq ?>
                    </div>
                    <div class="col-md-2">
                        <label for="departmentFilter" class="form-label">Şöbə</label>
                        <select id="departmentFilter" class="form-select">
                            <option selected>Bütün şöbələr</option>
                            <!-- Şöbələr DB-dən yüklənməlidir -->
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="categoryFilter" class="form-label">Kateqoriya</label>
                        <select id="categoryFilter" class="form-select">
                            <option selected>Bütün kateqoriyalar</option>
                            <!-- Kateqoriyalar DB-dən yüklənməlidir -->
                        </select>
                    </div>
                     <div class="col-md-2">
                        <label for="statusFilter" class="form-label">Status</label>
                        <select id="statusFilter" class="form-select">
                            <option selected>Bütün statuslar</option>
                            <option value="pending">Gözləmədə</option>
                            <option value="in_progress">İcrada</option>
                            <option value="completed">Tamamlandı</option>
                            <option value="declined">Ləğv Edildi</option>
                        </select>
                    </div>
                    <div class="col-md-auto">
                        <button type="submit" class="btn btn-primary">Filtrlə</button>
                    </div>
                     <div class="col-md-auto ms-auto">
                         <button type="button" class="btn btn-outline-secondary"><i class="fas fa-file-pdf me-1"></i> PDF</button>
                         <button type="button" class="btn btn-outline-success"><i class="fas fa-file-excel me-1"></i> Excel</button>
                     </div>
                </div>
            </form>
        </div>
    </div>


    <div class="row">
        <!-- Task Status Pie Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Status üzrə Paylanma</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($chartData)): ?>
                        <div class="chart-pie pt-4 pb-2" style="height: 250px;">
                            <canvas id="statsStatusPieChart"></canvas>
                        </div>
                        <div class="mt-4 text-center small">
                            <?php foreach ($chartLabels as $index => $label): ?>
                                <span class="me-2">
                                    <i class="fas fa-circle" style="color: <?= htmlspecialchars($chartColors[$index] ?? '#858796') ?>;"></i> <?= htmlspecialchars($label) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                         <p class="text-muted text-center">Status diaqramı üçün məlumat yoxdur.</p>
                    <?php endif; ?>
                </div>
            </div>

             <!-- Task Status Summary Table -->
             <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Status İcmalı</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($taskStats)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Sayı</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($taskStats as $stat): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?= getStatusBadgeClass($stat['status_key']) ?>">
                                                    <?= htmlspecialchars($stat['status_display']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end"><?= $stat['count'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Statistika məlumatı tapılmadı.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <!-- Şöbələr üzrə Tapşırıq Sayı (Bar Chart) -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Şöbələr üzrə Tapşırıq Sayı</h6>
                </div>
                <div class="card-body">
                     <?php if (!empty($deptChartData)): ?>
                        <div class="chart-bar" style="height: 300px;">
                            <canvas id="statsDepartmentBarChart"></canvas>
                        </div>
                     <?php else: ?>
                         <p class="text-muted text-center">Şöbə diaqramı üçün məlumat yoxdur.</p>
                     <?php endif; ?>
                </div>
            </div>

            <!-- Aylıq Tapşırıq Dinamikası (Line Chart) -->
             <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Aylıq Tapşırıq Dinamikası</h6>
                </div>
                <div class="card-body">
                     <?php if (!empty($monthlyCreatedData)): ?>
                        <div class="chart-line" style="height: 300px;">
                            <canvas id="statsMonthlyLineChart"></canvas>
                        </div>
                     <?php else: ?>
                         <p class="text-muted text-center">Aylıq dinamika üçün məlumat yoxdur.</p>
                     <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Digər statistika elementləri buraya əlavə edilə bilər -->
    <!-- Məsələn: İcraçılar üzrə statistika, Prioritet paylanması və s. -->

</div> <!-- /.container-fluid -->

<?php // Chart.js üçün konfiqurasiya skripti ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Pie Chart Konfiqurasiyası
    var ctxPie = document.getElementById("statsStatusPieChart");
    if (ctxPie && <?= !empty($chartData) ? 'true' : 'false' ?>) {
        new Chart(ctxPie, {
            type: 'pie', // Pie chart kimi göstərək
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    data: <?= json_encode($chartData) ?>,
                    backgroundColor: <?= json_encode($chartColors) ?>,
                    hoverOffset: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                     tooltip: { padding: 10, displayColors: true },
                     legend: { display: false } // Manual legend istifadə olunur
                 }
            }
        });
    }

    // Bar Chart Konfiqurasiyası
    var ctxBar = document.getElementById("statsDepartmentBarChart");
    if (ctxBar && <?= !empty($deptChartData) ? 'true' : 'false' ?>) {
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: <?= json_encode($deptChartLabels) ?>,
                datasets: [{
                    label: "Tapşırıq Sayı",
                    backgroundColor: "rgba(58, 123, 213, 0.8)", // Əsas rəngin şəffaf variantı
                    borderColor: "#3a7bd5",
                    borderWidth: 1,
                    data: <?= json_encode($deptChartData) ?>,
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                indexAxis: 'y', // Şöbə adları uzun ola biləcəyi üçün horizontal bar chart
                scales: {
                    x: { beginAtZero: true, ticks: { stepSize: 5 } }, // Tam ədədlər
                    y: { grid: { display: false } }
                },
                plugins: {
                     legend: { display: false },
                     tooltip: { padding: 10 }
                 }
            }
        });
    }

     // Line Chart Konfiqurasiyası
    var ctxLine = document.getElementById("statsMonthlyLineChart");
    if (ctxLine && <?= !empty($monthlyCreatedData) ? 'true' : 'false' ?>) {
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthlyLabels) ?>,
                datasets: [{
                    label: "Yaradılan",
                    data: <?= json_encode($monthlyCreatedData) ?>,
                    borderColor: "#3a7bd5", // Əsas rəng
                    backgroundColor: "rgba(58, 123, 213, 0.1)",
                    fill: true,
                    tension: 0.3 // Xətti yumşaltmaq üçün
                }, {
                    label: "Tamamlanan",
                    data: <?= json_encode($monthlyCompletedData) ?>,
                    borderColor: "#1cc88a", // Success rəngi
                    backgroundColor: "rgba(28, 200, 138, 0.1)",
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 5 } }
                },
                 plugins: {
                     legend: { position: 'top' }, // Legend yuxarıda
                     tooltip: { mode: 'index', intersect: false, padding: 10 }
                 },
                 interaction: { // Hover effektləri
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                 }
            }
        });
    }

});
</script>

<?php
echo "<!-- Statistics Module Content End -->";
?>
