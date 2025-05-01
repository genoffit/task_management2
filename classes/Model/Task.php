<?php
// e:\xampp\htdocs\task_management\classes\Model\Task.php

namespace App\Model;

use App\Core\Database; // Verilənlər bazası bağlantısı üçün
use PDO;
use PDOException;
use Psr\Log\LoggerInterface; // Logger üçün

class Task
{
    private ?PDO $db; // PDO bağlantısı üçün property
    private ?LoggerInterface $logger; // Logger üçün (nullable)

    public function __construct()
    {
        // Verilənlər bazası bağlantısını al
        $this->db = Database::getInstance()->getConnection();
        // Loggeri qlobal dəyişəndən al (əgər varsa)
        $this->logger = isset($GLOBALS['logger']) && $GLOBALS['logger'] instanceof LoggerInterface ? $GLOBALS['logger'] : null;
    }

    /**
     * Retrieves a list of tasks based on filters and user role.
     *
     * @param array $filters Associative array of filters (department_id, category_id, assignee_id, status, priority).
     * @param int|null $currentUserId The ID of the currently logged-in user.
     * @param string|null $currentUserRole The role of the currently logged-in user.
     * @return array|false An array of tasks or false on failure.
     */
    public function getTasks(array $filters = [], ?int $currentUserId = null, ?string $currentUserRole = null): array|false
    {
        // === DÜZƏLİŞ: t.created_by və u_creator.name silindi ===
        $baseSql = "SELECT
                        t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at, t.start_date,
                        d.name as department_name,
                        c.name as category_name, c.color as category_color,
                        u_assignee.name as assignee_name, -- Düzəliş: fullname -> name
                        t.assignee_id
                    FROM tasks t
                    LEFT JOIN departments d ON t.department_id = d.id
                    LEFT JOIN categories c ON t.category_id = c.id
                    LEFT JOIN users u_assignee ON t.assignee_id = u_assignee.id";
                    // === DÜZƏLİŞ: LEFT JOIN users u_creator ON t.created_by = u_creator.id silindi ===

        $whereClauses = [];
        $params = [];

        // Role-based filtering
        // === DÜZƏLİŞ: 'employee' əvəzinə 'user' rolunu yoxlayaq (əgər sistemdə 'user' istifadə olunursa) ===
        if ($currentUserRole === 'user' && $currentUserId !== null) {
            // User sees tasks assigned to them OR assigned to everyone (assignee_id IS NULL)
            $whereClauses[] = "(t.assignee_id = :currentUserId OR t.assignee_id IS NULL)";
            $params[':currentUserId'] = $currentUserId;
        } // Admin/Manager sees all tasks by default, unless filtered

        // Apply standard filters
        if (!empty($filters['department_id'])) {
            $whereClauses[] = "t.department_id = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }
        if (!empty($filters['category_id'])) {
            $whereClauses[] = "t.category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        if (isset($filters['assignee_id'])) { // assignee_id üçün xüsusi yoxlama
            if ($filters['assignee_id'] === 'everyone' || $filters['assignee_id'] === null) {
                $whereClauses[] = "t.assignee_id IS NULL";
            } elseif (filter_var($filters['assignee_id'], FILTER_VALIDATE_INT)) {
                $whereClauses[] = "t.assignee_id = :assignee_id";
                $params[':assignee_id'] = (int)$filters['assignee_id'];
            }
        }
        if (!empty($filters['status'])) {
            $whereClauses[] = "t.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $whereClauses[] = "t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }
        // Add date range filters if needed
        // if (!empty($filters['start_date'])) { ... }
        // if (!empty($filters['end_date'])) { ... }

        $sql = $baseSql;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        // Default ordering (e.g., by due date, then priority)
        $sql .= " ORDER BY t.due_date ASC, FIELD(t.priority, 'high', 'medium', 'low'), t.created_at DESC";

        try {
            $stmt = $this->db->prepare($sql);
            // Bind parameters with correct types
            foreach ($params as $key => &$value) {
                $type = PDO::PARAM_STR; // Default type
                if (is_int($value) || $key === ':currentUserId' || $key === ':department_id' || $key === ':category_id' || $key === ':assignee_id') {
                    $type = PDO::PARAM_INT;
                }
                $stmt->bindParam($key, $value, $type);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error fetching tasks: " . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            else error_log("Error fetching tasks: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a single task by its ID, including related data.
     *
     * @param int $id The ID of the task.
     * @return array|false An associative array with task data or false if not found or on error.
     */
    public function getTaskById(int $id): array|false
    {
        // === DÜZƏLİŞ: t.created_by və u_creator.name silindi ===
        // === DÜZƏLİŞ: completed_at silindi ===
        // === DÜZƏLİŞ: users cədvəlində created_by sütunu yoxdur, ona görə u_creator join-u və creator_name silinir ===
        // === DÜZƏLİŞ: t.completed_by silindi ===
        $sql = "SELECT
                    t.id, t.title, t.description, t.status, t.priority, t.due_date, t.created_at, t.start_date, t.updated_at, t.assignee_id, t.started_at, t.started_by_user_id, /*t.completed_by,*/ t.department_id, t.category_id,
                    d.name as department_name,
                    c.name as category_name, c.color as category_color,
                    u_assignee.name as assignee_name -- Düzəliş: fullname -> name
                    -- u_creator.name as creator_name -- SİLİNDİ
                FROM tasks t
                LEFT JOIN departments d ON t.department_id = d.id
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN users u_assignee ON t.assignee_id = u_assignee.id
                -- LEFT JOIN users u_creator ON t.created_by = u_creator.id -- SİLİNDİ
                WHERE t.id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC); // Fetch single row
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error fetching task by ID: " . $e->getMessage(), ['task_id' => $id]);
            else error_log("Error fetching task by ID ($id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Creates a new task in the database.
     *
     * @param array $data Associative array of task data.
     * @return int|false The ID of the newly created task or false on failure.
     */
    public function createTask(array $data): int|false
    {
        // === DÜZƏLİŞ: created_by sütunu DB-də olmadığı üçün çıxarıldı ===
        // Əgər DB-də created_by sütunu yoxdursa, onu SQL-dən və bindParam-dan silin.
        $sql = "INSERT INTO tasks (title, description, department_id, category_id, assignee_id, priority, status, start_date, due_date, /*created_by,*/ created_at, updated_at)
                VALUES (:title, :description, :department_id, :category_id, :assignee_id, :priority, :status, :start_date, :due_date, /*:created_by,*/ NOW(), NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':department_id', $data['department_id'], PDO::PARAM_INT);
            $stmt->bindParam(':category_id', $data['category_id'], PDO::PARAM_INT);
            $stmt->bindParam(':assignee_id', $data['assignee_id'], $data['assignee_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':priority', $data['priority']);
            $stmt->bindParam(':status', $data['status']);
            $stmt->bindParam(':start_date', $data['start_date'], $data['start_date'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':due_date', $data['due_date']);
            // $stmt->bindParam(':created_by', $data['created_by'], PDO::PARAM_INT); // created_by çıxarıldı

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            } else {
                if ($this->logger) $this->logger->error("Failed to execute createTask statement.", ['data' => $data, 'errorInfo' => $stmt->errorInfo()]);
                else error_log("Failed to execute createTask statement: " . implode(", ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error creating task: " . $e->getMessage(), ['data' => $data]);
            else error_log("Error creating task: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates the status of a task.
     * Sets completed_by or resets it based on status.
     *
     * @param int $taskId The ID of the task to update.
     * @param string $newStatus The new status ('completed', 'declined', etc.).
     * @param int $userId The ID of the user performing the action.
     * @return bool True on success, false on failure.
     */
    public function updateTaskStatus(int $taskId, string $newStatus, int $userId): bool
    {
        // === DÜZƏLİŞ: completed_by sütunu çıxarıldı ===
        $sql = "UPDATE tasks SET
                    status = :status,
                    updated_at = NOW()
                    /* completed_by = CASE WHEN :status IN ('completed', 'declined') THEN :user_id ELSE NULL END */
                WHERE id = :task_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':status', $newStatus);
            $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error updating task status: " . $e->getMessage(), ['task_id' => $taskId, 'new_status' => $newStatus, 'user_id' => $userId]);
            else error_log("Error updating task status ($taskId): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Starts the progress of a task.
     * Sets status to 'in_progress', started_at, and started_by_user_id.
     *
     * @param int $taskId The ID of the task to start.
     * @param int $userId The ID of the user starting the task.
     * @return bool True on success, false on failure.
     */
    public function startTask(int $taskId, int $userId): bool
    {
        $sql = "UPDATE tasks SET
                    status = 'in_progress',
                    started_at = NOW(),
                    started_by_user_id = :user_id,
                    updated_at = NOW()
                WHERE id = :task_id AND status = 'pending'"; // Yalnız pending olanı başlat

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error starting task: " . $e->getMessage(), ['task_id' => $taskId, 'user_id' => $userId]);
            else error_log("Error starting task ($taskId): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a task from the database.
     * NOTE: Related comments and history should be deleted first (or use CASCADE DELETE).
     *
     * @param int $taskId The ID of the task to delete.
     * @return bool True on success, false on failure.
     */
    public function deleteTask(int $taskId): bool
    {
        // Əvvəlcə əlaqəli şərhləri və tarixçəni silmək daha təhlükəsizdir
        // Bu əməliyyatlar Controller-də və ya burada edilə bilər
        // $commentModel = new Comment(); $commentModel->deleteCommentsByTaskId($taskId);
        // $historyModel = new TaskHistory(); $historyModel->deleteHistoryByTaskId($taskId);

        $sql = "DELETE FROM tasks WHERE id = :task_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            // Foreign key xətası baş verə bilər (əgər əlaqəli məlumatlar silinməyibsə)
            if ($this->logger) $this->logger->error("Error deleting task: " . $e->getMessage(), ['task_id' => $taskId]);
            else error_log("Error deleting task ($taskId): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a specified number of recent tasks, considering user role and filters.
     *
     * @param int $limit The maximum number of recent tasks to retrieve.
     * @param int|null $currentUserId The ID of the currently logged-in user.
     * @param string|null $currentUserRole The role of the currently logged-in user.
     * @param array $filters Optional filters (same structure as getTasks).
     * @return array|false An array of recent tasks or false on failure.
     */
    public function getRecentTasks(int $limit = 5, ?int $currentUserId = null, ?string $currentUserRole = null, array $filters = []): array|false
    {
        // === DÜZƏLİŞ: 'employee' əvəzinə 'user' rolunu yoxlayaq ===
        $baseSql = "SELECT
                        t.id, t.title, t.status, t.priority, t.due_date, t.created_at,
                        u_assignee.name as assignee_name
                    FROM tasks t
                    LEFT JOIN users u_assignee ON t.assignee_id = u_assignee.id";

        $whereClauses = [];
        $params = [];

        // Role-based filtering
        if ($currentUserRole === 'user' && $currentUserId !== null) {
            $whereClauses[] = "(t.assignee_id = :currentUserId OR t.assignee_id IS NULL)";
            $params[':currentUserId'] = $currentUserId;
        }

        // Apply standard filters (if any passed)
        // ... (Filtrlər lazım gələrsə əlavə edilə bilər)

        $sql = $baseSql;
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }

        // Add ordering and limit for recent tasks
        $sql .= " ORDER BY t.created_at DESC LIMIT :limit";
        $params[':limit'] = $limit;

        try {
            $stmt = $this->db->prepare($sql);
            // Bind parameters with correct types
            foreach ($params as $key => &$value) {
                $type = ($key === ':limit' || $key === ':currentUserId') ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindParam($key, $value, $type);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error fetching recent tasks: " . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            else error_log("Error fetching recent tasks: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a list of overdue tasks, considering user role and optional limit.
     * Overdue means status is not 'completed' or 'declined' and due_date is in the past.
     *
     * @param int|null $limit Optional limit for the number of tasks.
     * @param int|null $currentUserId The ID of the currently logged-in user.
     * @param string|null $currentUserRole The role of the currently logged-in user.
     * @return array|false An array of overdue tasks or false on failure.
     */
    public function getOverdueTasksList(?int $limit = null, ?int $currentUserId = null, ?string $currentUserRole = null): array|false
    {
        // === DÜZƏLİŞ: 'employee' əvəzinə 'user' rolunu yoxlayaq ===
        $baseSql = "SELECT
                        t.id, t.title, t.status, t.priority, t.due_date,
                        u_assignee.name as assignee_name
                    FROM tasks t
                    LEFT JOIN users u_assignee ON t.assignee_id = u_assignee.id";

        $whereClauses = [
            "t.status NOT IN ('completed', 'declined')",
            "t.due_date < CURDATE()" // Vaxtı keçmiş şərti
        ];
        $params = [];

        // Role-based filtering
        if ($currentUserRole === 'user' && $currentUserId !== null) {
            $whereClauses[] = "(t.assignee_id = :currentUserId OR t.assignee_id IS NULL)";
            $params[':currentUserId'] = $currentUserId;
        }

        $sql = $baseSql . " WHERE " . implode(" AND ", $whereClauses);

        // Order by due date (most overdue first)
        $sql .= " ORDER BY t.due_date ASC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }

        try {
            $stmt = $this->db->prepare($sql);
            // Bind parameters
            foreach ($params as $key => &$value) {
                $type = ($key === ':limit' || $key === ':currentUserId') ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindParam($key, $value, $type);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error fetching overdue tasks list: " . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            else error_log("Error fetching overdue tasks list: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves active tasks assigned to a specific user.
     * Active means status is 'pending' or 'in_progress'.
     *
     * @param int $userId The ID of the user.
     * @param int|null $limit Optional limit for the number of tasks.
     * @return array|false An array of active assigned tasks or false on failure.
     */
    public function getActiveTasksAssignedTo(int $userId, ?int $limit = null): array|false
    {
        $baseSql = "SELECT
                        t.id, t.title, t.status, t.priority, t.due_date
                    FROM tasks t";

        $whereClauses = [
            "t.assignee_id = :userId",
            "t.status IN ('pending', 'in_progress')" // Aktiv statuslar
        ];
        $params = [':userId' => $userId];

        $sql = $baseSql . " WHERE " . implode(" AND ", $whereClauses);

        // Order by due date
        $sql .= " ORDER BY t.due_date ASC";

        if ($limit !== null) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;
        }

        try {
            $stmt = $this->db->prepare($sql);
            // Bind parameters
            $stmt->bindParam(':userId', $params[':userId'], PDO::PARAM_INT);
            if ($limit !== null) $stmt->bindParam(':limit', $params[':limit'], PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error fetching active tasks for user: " . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            else error_log("Error fetching active tasks for user ($userId): " . $e->getMessage());
            return false;
        }
    }

    // --- Statistik məlumatlar üçün lazım ola biləcək metodlar ---

    /**
     * Counts tasks based on status, optionally filtered.
     * @param array $filters Filters to apply.
     * @return array Associative array with status counts (e.g., ['pending' => 5, 'completed' => 10]).
     */
    public function countTasksByStatus(array $filters = []): array {
        $sql = "SELECT status, COUNT(*) as count FROM tasks";
        $whereClauses = [];
        $params = [];

        // Apply filters
        if (!empty($filters['department_id'])) {
            $whereClauses[] = "department_id = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }
        if (!empty($filters['category_id'])) {
            $whereClauses[] = "category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        if (isset($filters['assignee_id'])) {
            if ($filters['assignee_id'] === 'everyone' || $filters['assignee_id'] === null) {
                $whereClauses[] = "assignee_id IS NULL";
            } elseif (filter_var($filters['assignee_id'], FILTER_VALIDATE_INT)) {
                $whereClauses[] = "assignee_id = :assignee_id";
                $params[':assignee_id'] = (int)$filters['assignee_id'];
            }
        }
        // Status filtri burada mənasızdır, çünki statusa görə qruplaşdırırıq
        // if (!empty($filters['status'])) { ... }
        if (!empty($filters['priority'])) {
            $whereClauses[] = "priority = :priority";
            $params[':priority'] = $filters['priority'];
        }
        // ... other filters

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        $sql .= " GROUP BY status";

        try {
            $stmt = $this->db->prepare($sql);
            // Bind filter parameters
            foreach ($params as $key => &$value) {
                 $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                 $stmt->bindParam($key, $value, $type);
            }
            $stmt->execute();
            // Fetch results as key-value pairs (status => count)
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error counting tasks by status: " . $e->getMessage(), ['filters' => $filters]);
            else error_log("Error counting tasks by status: " . $e->getMessage());
            return []; // Return empty array on error
        }
    }

    /**
     * Counts overdue tasks, optionally filtered.
     * Overdue means status is not 'completed' or 'declined' and due_date is in the past.
     * @param array $filters Filters to apply.
     * @return int Count of overdue tasks.
     */
    public function countOverdueTasks(array $filters = []): int {
        $sql = "SELECT COUNT(*) FROM tasks";
        $whereClauses = ["status NOT IN ('completed', 'declined')", "due_date < CURDATE()"]; // Basic overdue conditions
        $params = [];

        // Apply filters
        if (!empty($filters['department_id'])) {
            $whereClauses[] = "department_id = :department_id";
            $params[':department_id'] = $filters['department_id'];
        }
        if (!empty($filters['category_id'])) {
            $whereClauses[] = "category_id = :category_id";
            $params[':category_id'] = $filters['category_id'];
        }
        if (isset($filters['assignee_id'])) {
            if ($filters['assignee_id'] === 'everyone' || $filters['assignee_id'] === null) {
                $whereClauses[] = "assignee_id IS NULL";
            } elseif (filter_var($filters['assignee_id'], FILTER_VALIDATE_INT)) {
                $whereClauses[] = "assignee_id = :assignee_id";
                $params[':assignee_id'] = (int)$filters['assignee_id'];
            }
        }
        // Status filtri burada mənasızdır, çünki status şərti artıq var
        // if (!empty($filters['status'])) { ... }
        if (!empty($filters['priority'])) {
            $whereClauses[] = "priority = :priority";
            $params[':priority'] = $filters['priority'];
        }
        // ... other filters

        $sql .= " WHERE " . implode(" AND ", $whereClauses);

        try {
            $stmt = $this->db->prepare($sql);
            // Bind filter parameters
             foreach ($params as $key => &$value) {
                 $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                 $stmt->bindParam($key, $value, $type);
            }
            $stmt->execute();
            return (int)$stmt->fetchColumn(); // Fetch the count
        } catch (PDOException $e) {
            if ($this->logger) $this->logger->error("Error counting overdue tasks: " . $e->getMessage(), ['filters' => $filters]);
            else error_log("Error counting overdue tasks: " . $e->getMessage());
            return 0; // Return 0 on error
        }
    }

    // Digər statistik metodlar (prioritetə görə say, icraçıya görə say və s.) buraya əlavə edilə bilər

} // --- Task Model sinifinin sonu ---
