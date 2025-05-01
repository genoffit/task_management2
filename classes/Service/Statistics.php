<?php
// e:\xampp\htdocs\task_management\classes\Service\Statistics.php

namespace App\Service;

use App\Core\Database;
use App\Model\Task; // Task modelini istifadə etmək üçün əlavə edək
use PDO;
use PDOException;
use Psr\Log\LoggerInterface; // Logger interfeysini istifadə edək

class Statistics {
    private PDO $db; // Tip təyini
    private Task $taskModel; // Task modeli üçün property
    private ?LoggerInterface $logger; // Logger üçün (nullable)

    /**
     * Statuslar massivi (İNGİLİSCƏ).
     * Verilənlər bazasındakı status dəyərləri ilə eyni olmalıdır.
     */
    private const TASK_STATUSES = ['pending', 'in_progress', 'completed', 'declined'];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->taskModel = new Task(); // Task modelini yaradaq
        // Loggeri qlobal dəyişəndən və ya DI container-dən alaq
        $this->logger = isset($GLOBALS['logger']) && $GLOBALS['logger'] instanceof LoggerInterface ? $GLOBALS['logger'] : null;
    }

    /**
     * Dashboard kartları üçün əsas statistikaları qaytarır.
     *
     * @param array $filters Optional filters to apply to counts.
     * @return array ['pending' => int, 'in_progress' => int, 'overdue' => int, 'due_today' => int, 'created_last_7_days' => int, 'completed_this_month' => int, 'total_active' => int]
     */
    public function getDashboardCardStats(array $filters = []): array {
        $stats = [
            'pending' => 0,
            'in_progress' => 0,
            'overdue' => 0,
            'due_today' => 0,
            'created_last_7_days' => 0,
            'completed_this_month' => 0,
            'total_active' => 0,
        ];

        try {
            // Statusa görə saylar (aktiv olanlar) - Task modelindən istifadə edək
            $statusCounts = $this->taskModel->countTasksByStatus($filters); // Filtrləri ötürək
            $stats['pending'] = $statusCounts['pending'] ?? 0;
            $stats['in_progress'] = $statusCounts['in_progress'] ?? 0;
            $stats['total_active'] = $stats['pending'] + $stats['in_progress'];

            // Gecikənlər (vaxtı keçib və tamamlanmayıb/ləğv edilməyib) - Task modelindən istifadə edək
            $stats['overdue'] = $this->taskModel->countOverdueTasks($filters); // Filtrləri ötürək

            // Bu gün bitməli (tarix bu gündür və tamamlanmayıb/ləğv edilməyib)
            // Bu metod Task modelində yoxdur, burada qalsın və ya Task modelinə köçürülsün
            // DİQQƏT: Bu sayma filtrləri nəzərə almır, lazım gələrsə TaskModel-ə köçürülüb filtrlər əlavə edilməlidir.
            $sql_due_today = "SELECT COUNT(*) FROM tasks
                              WHERE DATE(due_date) = CURDATE() AND status NOT IN (?, ?)";
            $stmt_due_today = $this->db->prepare($sql_due_today);
            $stmt_due_today->execute(['completed', 'declined']);
            $stats['due_today'] = (int)$stmt_due_today->fetchColumn();

            // Son 7 gündə yaradılanlar (created_at sütununun mövcudluğunu fərz edir)
            // DİQQƏT: Bu sayma filtrləri nəzərə almır, lazım gələrsə TaskModel-ə köçürülüb filtrlər əlavə edilməlidir.
            $sql_last_7_days = "SELECT COUNT(*) FROM tasks WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $stmt_last_7_days = $this->db->query($sql_last_7_days);
            $stats['created_last_7_days'] = (int)($stmt_last_7_days->fetchColumn() ?? 0);

            // Bu ay tamamlananlar
            // DİQQƏT: Bu sayma filtrləri nəzərə almır, lazım gələrsə TaskModel-ə köçürülüb filtrlər əlavə edilməlidir.
            $stats['completed_this_month'] = $this->getCompletedTasksThisMonth() ?: 0; // Bu metod burada qalır

        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error fetching dashboard card stats", ['exception' => $e->getMessage(), 'filters' => $filters]);
            else error_log("Error fetching dashboard card stats: " . $e->getMessage());
            // Xəta baş verdikdə 0 qaytarırıq
        }
        return $stats;
    }


    /**
     * Tapşırıqların statuslarına görə sayını qaytarır (Pie Chart üçün).
     *
     * @param array $filters Optional filters to apply.
     * @return array Status və sayları ehtiva edən massiv. Məs: [['status' => 'completed', 'count' => 5], ...]
     */
    public function getTaskStats(array $filters = []): array {
        // Task modelindəki metodu istifadə edək
        $resultsFromDB = $this->taskModel->countTasksByStatus($filters) ?: [];

        // Gözlənilən bütün statusları ehtiva edən son nəticə massivini yarat
        $formattedResult = [];
        // DB-dən gələn statusları və əlavə olaraq gözlənilən statusları birləşdirək
        $dbStatuses = array_keys($resultsFromDB);
        $allPossibleStatuses = array_unique(array_merge(self::TASK_STATUSES, $dbStatuses));
        // Boş statusu da əlavə edək (əgər DB-də varsa)
        if (isset($resultsFromDB[''])) {
            $allPossibleStatuses[] = ''; // Boş string olaraq əlavə edək
        }

        foreach ($allPossibleStatuses as $status) {
            $count = $resultsFromDB[$status] ?? 0;
            $displayStatus = ($status === '') ? '(Boş)' : $status; // Boş statusu göstərmək üçün

            // Sayı 0 olsa belə, bütün statusları göstərək (əgər filtr yoxdursa)
            // Filtr varsa, yalnız sayı 0-dan böyük olanları göstərmək daha məntiqli ola bilər
            // if ($count > 0 || empty($filters)) {
                $formattedResult[] = [
                    'status' => $displayStatus, // Göstərmək üçün ad
                    'status_key' => $status, // Badge class üçün orijinal açar
                    'count' => $count
                ];
            // }
        }

        // Statusa görə sırala (pending, in_progress, completed, declined, (Boş))
        $statusOrder = array_flip(array_merge(self::TASK_STATUSES, [''])); // Boş status üçün ''
        usort($formattedResult, function ($a, $b) use ($statusOrder) {
            $pos_a = $statusOrder[$a['status_key']] ?? 99; // Bilinməyənlər sona
            $pos_b = $statusOrder[$b['status_key']] ?? 99;
            return $pos_a - $pos_b;
        });

        return $formattedResult;
    }

    /**
     * Aktiv tapşırıqların şöbələrə görə paylanmasını qaytarır (Bar Chart üçün).
     *
     * @param array $filters Optional filters to apply.
     * @return array|false [['name' => string, 'count' => int], ...] və ya xəta.
     */
    public function getActiveTasksByDepartmentDistribution(array $filters = []): array|false {
        $sql = "SELECT d.name, COUNT(t.id) as count
                FROM tasks t
                JOIN departments d ON t.department_id = d.id";

        // Aktiv statuslar üçün WHERE şərti
        $whereClauses = ["t.status IN (?, ?)"]; // Placeholder istifadə edək
        $params = ['pending', 'in_progress'];

        // Filtrləri tətbiq et (TaskModel-ə bənzər)
        if (!empty($filters['department_id'])) {
            $whereClauses[] = "t.department_id = ?"; // Placeholder
            $params[] = $filters['department_id'];
        }
        if (!empty($filters['category_id'])) {
            $whereClauses[] = "t.category_id = ?"; // Placeholder
            $params[] = $filters['category_id'];
        }
        // Digər filtrlər...

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        $sql .= " GROUP BY t.department_id, d.name
                  HAVING count > 0
                  ORDER BY count DESC"; // Ən çoxdan aza doğru

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params); // Parametrləri execute-a ötürək
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error fetching active tasks by department distribution", ['exception' => $e->getMessage(), 'filters' => $filters]);
            else error_log("Error fetching active tasks by department distribution: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Cari ay ərzində tamamlanmış tapşırıqların sayını qaytarır.
     * DİQQƏT: Bu metod hələlik filtrləri dəstəkləmir.
     *
     * @return int|false Cari ayda tamamlanan tapşırıqların sayı və ya xəta baş verdikdə false.
     */
    public function getCompletedTasksThisMonth(): int|false {
        $startOfMonth = date('Y-m-01 00:00:00');
        $startOfNextMonth = date('Y-m-01 00:00:00', strtotime('+1 month'));

        // === DÜZƏLİŞ: completed_at əvəzinə status='completed' və updated_at istifadə edirik ===
        $sql = "SELECT COUNT(*) as count
                FROM tasks t
                WHERE status = ?
                  AND updated_at >= ? -- updated_at istifadə edirik
                  AND updated_at < ?"; // updated_at istifadə edirik

        try {
            $stmt = $this->db->prepare($sql);
            // Parametrləri execute ilə ötürək
            $stmt->execute(['completed', $startOfMonth, $startOfNextMonth]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['count'] : 0;
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error fetching completed tasks this month", ['exception' => $e->getMessage()]);
            else error_log("Error fetching completed tasks this month: " . $e->getMessage());
            // completed_at xəbərdarlığı artıq lazım deyil
            return false;
        }
    }

    /**
     * Filtrlərə əsasən detallı statistik məlumatları toplayır.
     *
     * @param array $filters Optional filters.
     * @return array An array containing various statistics.
     */
    public function getDetailedStats(array $filters = []): array
    {
        $stats = [];
        $errorOccurred = false; // Xəta baş verib-vermədiyini izləmək üçün

        try {
            // Statusa görə say (Task modelindəki metodu istifadə edək)
            $stats['tasks_by_status'] = $this->taskModel->countTasksByStatus($filters) ?: [];

            // Prioritetə görə say (Task modelində metod əlavə etmək lazımdır)
            // $stats['tasks_by_priority'] = $this->taskModel->countTasksByPriority($filters) ?: [];
            $stats['tasks_by_priority'] = []; // Placeholder

            // İcraçıya görə say (Task modelində metod əlavə etmək lazımdır)
            // $stats['tasks_by_assignee'] = $this->taskModel->countTasksByAssignee($filters) ?: [];
            $stats['tasks_by_assignee'] = []; // Placeholder

            // Departamentə görə say (Task modelində metod əlavə etmək lazımdır)
            // $stats['tasks_by_department'] = $this->taskModel->countTasksByDepartment($filters) ?: [];
            $stats['tasks_by_department'] = []; // Placeholder

            // Kateqoriyaya görə say (Task modelində metod əlavə etmək lazımdır)
            // $stats['tasks_by_category'] = $this->taskModel->countTasksByCategory($filters) ?: [];
            $stats['tasks_by_category'] = []; // Placeholder

            // Vaxtı keçmiş tapşırıqların sayı (Task modelindəki metodu istifadə edək)
            $overdueCount = $this->taskModel->countOverdueTasks($filters);
            $stats['overdue_tasks_count'] = ($overdueCount === false) ? 0 : $overdueCount; // Xəta olarsa 0

            // Digər statistikalar buraya əlavə edilə bilər

        } catch (\Exception | \Error $e) {
            $errorOccurred = true;
            if ($this->logger) $this->logger->error("Error gathering detailed statistics: " . $e->getMessage(), ['filters' => $filters, 'exception' => $e]);
            else error_log("Error gathering detailed statistics: " . $e->getMessage());
            // Xəta olarsa boş array qaytarmaq və ya default dəyərlər təyin etmək olar
            $stats = [
                'tasks_by_status' => [], 'tasks_by_priority' => [], 'tasks_by_assignee' => [],
                'tasks_by_department' => [], 'tasks_by_category' => [], 'overdue_tasks_count' => 0
            ];
        }

        // Xəta mesajını əlavə et (əgər baş veribsə)
        if ($errorOccurred) {
            $stats['error'] = 'Statistika hesablanarkən xəta baş verdi.';
        }

        return $stats;
    }

} // Sinifin sonu
