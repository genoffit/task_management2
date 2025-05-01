<?php
// e:\xampp\htdocs\task_management\classes\Model\Department.php

namespace App\Model;

use App\Core\Database;
use PDO;
use PDOException;

class Department {
    private $db;
    private $table_name = "departments";

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Bütün departamentləri qaytarır.
     * @return array|false
     */
    public function getAllDepartments(): array|false {
        $sql = "SELECT id, name, description FROM {$this->table_name} ORDER BY name ASC";
        try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all departments: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ID-yə görə departamenti qaytarır.
     * @param int $id
     * @return array|false
     */
    public function getDepartmentById(int $id): array|false {
        $sql = "SELECT id, name, description FROM {$this->table_name} WHERE id = :id LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: false; // Tapılmadıqda false qaytar
        } catch (PDOException $e) {
            error_log("Error fetching department by ID ($id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Yeni departament yaradır.
     * Cədvəldə created_at/updated_at sütunlarının olmadığını fərz edir.
     * @param string $name
     * @param string|null $description
     * @return int|false Yaradılan ID və ya xəta.
     */
    public function createDepartment(string $name, ?string $description): int|false {
        // DÜZƏLİŞ: created_at və updated_at sütunları çıxarıldı
        $sql = "INSERT INTO {$this->table_name} (name, description) VALUES (:name, :description)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            if ($stmt->execute()) {
                // lastInsertId() string qaytara bilər, int-ə çevirək
                return (int)$this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating department: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Departamenti yeniləyir.
     * Cədvəldə updated_at sütununun olmadığını fərz edir.
     * @param int $id
     * @param string $name
     * @param string|null $description
     * @return bool Uğurlu olub-olmadığı.
     */
    public function updateDepartment(int $id, string $name, ?string $description): bool {
        // DÜZƏLİŞ: updated_at = NOW() hissəsi çıxarıldı
        $sql = "UPDATE {$this->table_name} SET name = :name, description = :description WHERE id = :id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating department ID ($id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Departamenti silir.
     * @param int $id
     * @return bool Uğurlu olub-olmadığı.
     */
    public function deleteDepartment(int $id): bool {
        // Əvvəlcə bu departamentə bağlı istifadəçi/tapşırıq olub-olmadığını yoxlamaq daha yaxşıdır
        // Foreign key constraint varsa, bu onsuz da xəta verəcək
        $sql = "DELETE FROM {$this->table_name} WHERE id = :id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            // execute() təsirə məruz qalan sətirlərin sayını qaytarmır, sadəcə uğurlu/uğursuz (true/false)
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting department ID ($id): " . $e->getMessage());
            return false;
        }
    }
}
?>
