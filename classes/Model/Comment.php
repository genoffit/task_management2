<?php
namespace App\Model;

use App\Core\Database;
use PDO;
use PDOException;

class Comment {
    private $db;
    private $table_name = "task_comments";

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Tapşırığa yeni şərh əlavə edir.
     * @param int $task_id
     * @param int $user_id
     * @param string $comment
     * @return int|false Yaradılan şərhin ID-si və ya xəta.
     */
    public function addComment(int $task_id, int $user_id, string $comment): int|false {
        $sql = "INSERT INTO {$this->table_name} (task_id, user_id, comment, created_at) VALUES (:task_id, :user_id, :comment, NOW())";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            if ($stmt->execute()) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error adding comment to task ID ($task_id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verilmiş tapşırığın bütün şərhlərini qaytarır (istifadəçi adı ilə birlikdə).
     * @param int $task_id
     * @return array|false
     */
    public function getCommentsByTaskId(int $task_id): array|false {
        $sql = "SELECT c.*, u.name as user_name, u.username as user_username
                FROM {$this->table_name} c
                JOIN users u ON c.user_id = u.id
                WHERE c.task_id = :task_id
                ORDER BY c.created_at ASC";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':task_id', $task_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching comments for task ID ($task_id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Şərhi ID-yə görə qaytarır.
     * @param int $id
     * @return array|false
     */
    public function getCommentById(int $id): array|false {
        $sql = "SELECT * FROM {$this->table_name} WHERE id = :id LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching comment by ID ($id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Şərhi silir.
     * @param int $id
     * @return bool Uğurlu olub-olmadığı.
     */
    public function deleteComment(int $id): bool {
        $sql = "DELETE FROM {$this->table_name} WHERE id = :id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting comment ID ($id): " . $e->getMessage());
            return false;
        }
    }

    // Şərhi redaktə etmək üçün metod da əlavə edilə bilər
}
?>
