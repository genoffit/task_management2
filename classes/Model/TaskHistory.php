<?php
// e:\xampp\htdocs\task_management\classes\Model\TaskHistory.php

namespace App\Model;

use App\Core\Database;
use PDO;
use PDOException;

class TaskHistory {
    private PDO $db; // Düzəliş: private etdik (əvvəlki düzəliş)
    private string $table_name = "task_history";

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Adds a new entry to the task history.
     * @param int $taskId
     * @param int|null $userId User performing the action (null for system actions).
     * @param string $action Action performed (e.g., 'created', 'started', 'status_changed', 'commented').
     * @param string|null $details Additional details about the action.
     * @return bool True on success, false on failure.
     */
    public function addHistoryEntry(int $taskId, ?int $userId, string $action, ?string $details = null): bool {
        // === DÜZƏLİŞ: details sütunu çıxarıldı ===
        $sql = "INSERT INTO {$this->table_name} (task_id, user_id, action, /*details,*/ created_at)
                VALUES (:task_id, :user_id, :action, /*:details,*/ NOW())";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error adding task history: " . $e->getMessage());
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error adding task history", ['exception' => $e->getMessage(), 'taskId' => $taskId]);
            return false;
        }
    }

    /**
     * Gets the history for a specific task, ordered by creation date.
     * @param int $taskId
     * @return array|false Array of history entries or false on failure.
     */
    public function getHistoryByTaskId(int $taskId): array|false {
        $sql = "SELECT th.*, u.name as user_name
                FROM {$this->table_name} th
                LEFT JOIN users u ON th.user_id = u.id
                WHERE th.task_id = :task_id
                ORDER BY th.created_at DESC"; // Son qeydlər yuxarıda
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching history for task ID ($taskId): " . $e->getMessage());
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error fetching task history", ['exception' => $e->getMessage(), 'taskId' => $taskId]);
            return false;
        }
    }

    /**
     * Deletes all history entries for a specific task.
     * @param int $taskId
     * @return bool True on success, false on failure.
     */
    public function deleteHistoryByTaskId(int $taskId): bool {
        $sql = "DELETE FROM {$this->table_name} WHERE task_id = :task_id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':task_id', $taskId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting history for task ID ($taskId): " . $e->getMessage());
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error deleting task history", ['exception' => $e->getMessage(), 'taskId' => $taskId]);
            return false;
        }
    }
}