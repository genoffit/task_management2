<?php
// e:\xampp\htdocs\task_management\classes\Model\Category.php

namespace App\Model;

use App\Core\Database;
use PDO;
use PDOException;

class Category {
    private $db;
    private $table_name = "categories";

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Bütün kateqoriyaları qaytarır.
     * @return array|false
     */
    public function getAllCategories(): array|false {
        $sql = "SELECT id, name, description, color FROM {$this->table_name} ORDER BY name ASC";
         try {
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all categories: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ID-yə görə kateqoriyanı qaytarır.
     * @param int $id
     * @return array|false
     */
    public function getCategoryById(int $id): array|false {
        $sql = "SELECT id, name, description, color FROM {$this->table_name} WHERE id = :id LIMIT 1";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: false; // Tapılmadıqda false qaytar
        } catch (PDOException $e) {
            error_log("Error fetching category by ID ($id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Yeni kateqoriya yaradır.
     * Cədvəldə created_at/updated_at sütunlarının olmadığını fərz edir.
     * @param string $name
     * @param string|null $description
     * @param string|null $color
     * @return int|false Yaradılan ID və ya xəta.
     */
    public function createCategory(string $name, ?string $description, ?string $color): int|false {
        // DÜZƏLİŞ: created_at və updated_at sütunları çıxarıldı
        $sql = "INSERT INTO {$this->table_name} (name, description, color) VALUES (:name, :description, :color)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':color', $color, PDO::PARAM_STR); // Rəng kodu üçün STR
            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating category: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kateqoriyanı yeniləyir.
     * Cədvəldə updated_at sütununun olmadığını fərz edir.
     * @param int $id
     * @param string $name
     * @param string|null $description
     * @param string|null $color
     * @return bool Uğurlu olub-olmadığı.
     */
    public function updateCategory(int $id, string $name, ?string $description, ?string $color): bool {
        // DÜZƏLİŞ: updated_at = NOW() hissəsi çıxarıldı
        $sql = "UPDATE {$this->table_name} SET name = :name, description = :description, color = :color WHERE id = :id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':color', $color, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating category ID ($id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Kateqoriyanı silir.
     * @param int $id
     * @return bool Uğurlu olub-olmadığı.
     */
    public function deleteCategory(int $id): bool {
        // Əvvəlcə bu kateqoriyaya bağlı tapşırıq olub-olmadığını yoxlamaq daha yaxşıdır
        // Foreign key constraint varsa, bu onsuz da xəta verəcək
        $sql = "DELETE FROM {$this->table_name} WHERE id = :id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting category ID ($id): " . $e->getMessage());
            return false;
        }
    }
}
?>
