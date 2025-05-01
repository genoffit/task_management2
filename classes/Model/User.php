<?php
// e:\xampp\htdocs\task_management\classes\Model\User.php

namespace App\Model;

use App\Core\Database;
use PDO;
use PDOException;

class User {
    private PDO $db; // Tip təyini
    private string $table_name = "users";

    public function __construct() {
        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (\Exception $e) {
            error_log("Database connection failed in User model: " . $e->getMessage());
            throw new \RuntimeException("Could not connect to database in User model.", 0, $e);
        }
    }

    /**
     * Find user by username.
     * @param string $username
     * @return array|false User data or false if not found.
     */
    public function findByUsername(string $username): array|false {
        $sql = "SELECT * FROM {$this->table_name} WHERE username = :username LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Loqlama
            $logContext = ['username' => $username, 'exception' => $e->getMessage()];
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error finding user by username", $logContext);
            else error_log("Error finding user by username ($username): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find user by email.
     * @param string $email
     * @return array|false User data or false if not found.
     */
    public function findByEmail(string $email): array|false {
        $sql = "SELECT * FROM {$this->table_name} WHERE email = :email LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Loqlama
            $logContext = ['email' => $email, 'exception' => $e->getMessage()];
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error finding user by email", $logContext);
            else error_log("Error finding user by email ($email): " . $e->getMessage());
            return false;
        }
    }

     /**
     * Find user by ID.
     * @param int $id
     * @return array|false User data or false if not found.
     */
    public function findById(int $id): array|false {
        // DİQQƏT: `users` cədvəlində `name` sütunu olduğunu fərz edirik.
        $sql = "SELECT u.id, u.name, u.username, u.email, u.role, u.status, u.department_id, d.name as department_name
                FROM {$this->table_name} u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.id = :id LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Loqlama
            $logContext = ['id' => $id, 'exception' => $e->getMessage()];
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error finding user by ID", $logContext);
            else error_log("Error finding user by ID ($id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify user password.
     * @param string $password Plain password.
     * @param string $hashedPassword Hashed password from DB.
     * @return bool True if password matches, false otherwise.
     */
    public function verifyPassword(string $password, string $hashedPassword): bool {
        return password_verify($password, $hashedPassword);
    }

    /**
     * Create a new user.
     * @param array $data User data (name, username, email, password (hashed), department_id, role, status).
     * @return int|false Inserted user ID or false on failure.
     */
    public function createUser(array $data): int|false {
        $data['status'] = $data['status'] ?? 'active';
        // DİQQƏT: `users` cədvəlində `name` sütunu olduğunu fərz edirik.
        // `created_at`, `updated_at` sütunları DB tərəfindən idarə olunmursa, buraya əlavə edilməlidir.
        $sql = "INSERT INTO {$this->table_name} (name, username, email, password, department_id, role, status)
                VALUES (:name, :username, :email, :password, :department_id, :role, :status)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':username', $data['username'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindParam(':password', $data['password'], PDO::PARAM_STR); // Already hashed
            $stmt->bindParam(':department_id', $data['department_id'], PDO::PARAM_INT);
            $stmt->bindParam(':role', $data['role'], PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);

            if ($stmt->execute()) {
                $lastId = $this->db->lastInsertId();
                return ($lastId && $lastId > 0) ? (int)$lastId : false;
            } else {
                // Loqlama
                $logContext = ['sql' => $sql, 'data' => $data, 'errorInfo' => $stmt->errorInfo()];
                if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Create User Execution Failed", $logContext);
                else error_log("Create User Execution Failed: " . implode(' | ', $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            // Loqlama
            $logContext = ['sql' => $sql, 'data' => $data, 'exception' => $e->getMessage(), 'code' => $e->getCode()];
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Create User PDOException", $logContext);
            else error_log("Create User PDOException: " . $e->getMessage());
            // Dublikat xətalarını ayrıca loglamaq (əvvəlki kimi)
            if ($e->getCode() == 23000) { /* ... */ }
            return false;
        }
    }

    /**
     * Get all users with their department name.
     * @return array|false Array of users or false on failure.
     */
    public function getAllUsers(): array|false {
        // DİQQƏT: `users` cədvəlində `name` sütunu olduğunu fərz edirik.
        $sql = "SELECT u.id, u.name, u.username, u.email, u.role, u.status, d.name as department_name
                FROM {$this->table_name} u
                LEFT JOIN departments d ON u.department_id = d.id
                ORDER BY u.name ASC";

        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Loqlama
            $logContext = ['exception' => $e->getMessage()];
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error fetching all users", $logContext);
            else error_log("Error fetching all users: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user status (active/inactive).
     * @param int $id User ID.
     * @param string $status New status ('active' or 'inactive').
     * @return bool True on success, false on failure.
     */
    public function updateUserStatus(int $id, string $status): bool {
        if (!in_array($status, ['active', 'inactive'])) {
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->warning("Invalid status provided for user status update", ['id' => $id, 'status' => $status]);
            else error_log("Invalid status provided for user ID $id: $status");
            return false;
        }

        $sql = "UPDATE {$this->table_name} SET status = :status WHERE id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $success = $stmt->execute();
            if (!$success) {
                 // Loqlama
                 $logContext = ['id' => $id, 'status' => $status, 'errorInfo' => $stmt->errorInfo()];
                 if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Update User Status Execution Failed", $logContext);
                 else error_log("Update User Status Execution Failed (ID: $id): " . implode(', ', $stmt->errorInfo()));
            }
            return $success;
        } catch (PDOException $e) {
            // Loqlama
            $logContext = ['id' => $id, 'status' => $status, 'exception' => $e->getMessage()];
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Update User Status PDOException", $logContext);
            else error_log("Update User Status PDOException (ID: $id): " . $e->getMessage());
            return false;
        }
    }

    // === YENİ METODLAR ===

    /**
     * Tapşırıq təyin edilə bilən aktiv istifadəçiləri (manager və user) qaytarır.
     * @return array|false Array of users (id, name, role) or false on failure.
     */
    public function getAssignableUsers(): array|false {
        // DİQQƏT: `users` cədvəlində `name` sütunu olduğunu fərz edirik.
        $sql = "SELECT id, name, role
                FROM {$this->table_name}
                WHERE role IN ('manager', 'user') AND status = 'active'
                ORDER BY name ASC";
        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Loqlama
            $logContext = ['exception' => $e->getMessage()];
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error fetching assignable users", $logContext);
            else error_log("Error fetching assignable users: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bildiriş göndəriləcək aktiv istifadəçiləri (manager və user) qaytarır.
     * @return array|false Array of users (id, name, email, role etc.) or false on failure.
     */
    public function getNotifiableUsers(): array|false {
        // DİQQƏT: `users` cədvəlində `name` sütunu olduğunu fərz edirik.
        $sql = "SELECT id, name, email, role -- Bildiriş üçün lazım ola biləcək sütunlar
                FROM {$this->table_name}
                WHERE role IN ('manager', 'user') AND status = 'active'
                ORDER BY id ASC";
        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Loqlama
            $logContext = ['exception' => $e->getMessage()];
            if (isset($GLOBALS['logger'])) $GLOBALS['logger']->error("Error fetching notifiable users", $logContext);
            else error_log("Error fetching notifiable users: " . $e->getMessage());
            return false;
        }
    }
    // === YENİ METODLAR SONU ===


    // Add other methods like updateUser, deleteUser etc.
    // public function updateUser(int $id, array $data): bool { ... }
    // public function deleteUser(int $id): bool { ... }

}
?>
