<?php
// e:\xampp\htdocs\task_management\classes\Service\Notification.php

namespace App\Service;

use App\Core\Database;
use Pusher\Pusher; // Pusher namespace
use PDO;
use PDOException;
use Exception; // Pusher üçün ümumi Exception

class Notification {
    private ?Pusher $pusher = null; // Pusher obyektini saxlayır (nullable)
    private PDO $db; // PDO tipini təyin edək
    private string $table_name = "notifications";

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();

        // === PUSHER BAŞLATMA DÜZƏLİŞLƏRİ ===
        // 1. Pusher aktivləşdirilibmi? (constants.php-dən)
        if (defined('PUSHER_ENABLED') && PUSHER_ENABLED === true) {
            // 2. Bütün lazımi konstantlar təyin edilibmi?
            if (defined('PUSHER_APP_ID') && defined('PUSHER_KEY') && defined('PUSHER_SECRET') && defined('PUSHER_CLUSTER')) {
                try {
                    // 3. Düzgün konstant adları ilə Pusher obyektini yarat
                    $this->pusher = new Pusher(
                        PUSHER_KEY,      // Düzgün ad
                        PUSHER_SECRET,   // Düzgün ad
                        PUSHER_APP_ID,   // Düzgün ad
                        [
                            'cluster' => PUSHER_CLUSTER, // Düzgün ad
                            'useTLS' => true // HTTPS istifadəsi tövsiyə olunur
                        ]
                    );
                     // Test üçün kiçik bir trigger (development zamanı faydalı ola bilər, sonra silin)
                     // $this->pusher->trigger('test-channel', 'test-event', ['message' => 'Pusher connected successfully!']);
                     // error_log("Pusher initialized successfully."); // Uğurlu başlatmanı logla

                } catch (Exception $e) { // Pusher xətalarını tut
                    error_log("Pusher initialization failed: " . $e->getMessage());
                    $this->pusher = null; // Başlatma uğursuz olarsa null təyin et
                }
            } else {
                // Lazımi konstantlar tapılmadıqda log mesajı
                error_log("Pusher is enabled but required constants (PUSHER_APP_ID, PUSHER_KEY, PUSHER_SECRET, PUSHER_CLUSTER) are not defined. Real-time notifications disabled.");
                $this->pusher = null;
            }
        } else {
            // Pusher deaktiv edildikdə log mesajı
            // error_log("Pusher is disabled (PUSHER_ENABLED is false or not defined). Real-time notifications disabled."); // İstəyə bağlı log
            $this->pusher = null;
        }
        // === PUSHER BAŞLATMA DÜZƏLİŞLƏRİ SONU ===
    }

    /**
     * Real-time bildiriş göndərir (əgər Pusher konfiqurasiya edilibsə və aktivdirsə).
     * @param string|array $channels Kanal adı və ya kanallar massivi.
     * @param string $event Hadisə adı.
     * @param mixed $data Göndəriləcək məlumat.
     * @return bool Uğurlu olub-olmadığı.
     */
    public function sendRealtime(string|array $channels, string $event, mixed $data): bool {
        // Pusher obyektinin mövcudluğunu yoxla (konstruktorda null ola bilər)
        if ($this->pusher instanceof Pusher) {
            try {
                // Pusher trigger metodunu çağır
                $this->pusher->trigger($channels, $event, $data);
                // error_log("Pusher triggered: Channel(s)=".json_encode($channels).", Event=$event"); // Uğurlu trigger-i logla (debug üçün)
                return true;
            } catch (Exception $e) { // Pusher trigger xətalarını tut
                error_log("Pusher trigger failed: " . $e->getMessage() . " | Channels: " . json_encode($channels) . " | Event: " . $event);
                return false;
            }
        } else {
            // Pusher aktiv deyilsə və ya başlatılmayıbsa
            // error_log("Pusher trigger skipped: Pusher is not initialized or disabled."); // Log mesajı (debug üçün)
            return false; // Pusher işləmirsə false qaytar
        }
    }

    /**
     * Verilənlər bazasına bildiriş yaradır.
     * @param int $user_id Bildirişi alacaq istifadəçinin ID-si.
     * @param string $message Bildiriş mətni.
     * @param string $type Bildiriş növü (məs: 'info', 'task_assigned', 'comment_added').
     * @param string|null $link Bildirişə aid link (məs: tapşırığın URL-i).
     * @return int|false Yaradılan bildirişin ID-si və ya xəta baş verdikdə false.
     */
    public function createDatabaseNotification(int $user_id, string $message, string $type = 'info', ?string $link = null): int|false {
        // DİQQƏT: `notifications` cədvəlinin strukturuna uyğun olmalıdır.
        // created_at sütununun DB tərəfindən avtomatik təyin olunduğunu fərz edirik (DEFAULT CURRENT_TIMESTAMP).
        $sql = "INSERT INTO {$this->table_name} (user_id, message, type, link, is_read)
                VALUES (:user_id, :message, :type, :link, 0)"; // is_read default 0 olsun
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':link', $link, PDO::PARAM_STR); // NULL ola bilər

            if ($stmt->execute()) {
                $lastId = $this->db->lastInsertId();
                return ($lastId && $lastId > 0) ? (int)$lastId : false; // ID-ni yoxla və int qaytar
            } else {
                error_log("Create DB Notification Execution Failed: " . implode(' | ', $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("Error creating database notification for user ID ($user_id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * İstifadəçinin oxunmamış bildirişlərini qaytarır.
     * @param int $user_id
     * @param int $limit Qaytarılacaq maksimum bildiriş sayı.
     * @return array|false Oxunmamış bildirişlər massivi və ya xəta baş verdikdə false.
     */
    public function getUnreadNotificationsByUserId(int $user_id, int $limit = 10): array|false {
        $sql = "SELECT * FROM {$this->table_name}
                WHERE user_id = :user_id AND is_read = 0
                ORDER BY created_at DESC
                LIMIT :limit";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching unread notifications for user ID ($user_id): " . $e->getMessage());
            return false;
        }
    }

     /**
     * İstifadəçinin bütün bildirişlərini qaytarır (səhifələmə ilə gələcəkdə).
     * @param int $user_id
     * @param int $offset Başlanğıc nöqtəsi (səhifələmə üçün).
     * @param int $limit Səhifədəki element sayı.
     * @return array|false Bildirişlər massivi və ya xəta baş verdikdə false.
     */
    public function getAllNotificationsByUserId(int $user_id, int $offset = 0, int $limit = 20): array|false {
        $sql = "SELECT * FROM {$this->table_name}
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";
         try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching all notifications for user ID ($user_id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * İstifadəçinin bütün bildirişlərinin sayını qaytarır (səhifələmə üçün).
     * @param int $user_id
     * @return int|false Ümumi say və ya xəta.
     */
    public function countAllNotificationsByUserId(int $user_id): int|false {
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = :user_id";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting notifications for user ID ($user_id): " . $e->getMessage());
            return false;
        }
    }


    /**
     * Bildirişi oxunmuş kimi işarələyir.
     * @param int $id Bildiriş ID-si.
     * @param int|null $user_id (Optional) Təhlükəsizlik üçün yoxlamaq olar ki, bildiriş həmin istifadəçiyə aiddir.
     * @return bool Uğurlu olub-olmadığı.
     */
    public function markNotificationAsRead(int $id, ?int $user_id = null): bool {
        // DİQQƏT: `notifications` cədvəlində `read_at` sütunu olmalıdır.
        // Əgər yoxdursa, `read_at = NOW()` hissəsini silin.
        $sql = "UPDATE {$this->table_name} SET is_read = 1, read_at = NOW() WHERE id = :id";
        if ($user_id !== null) {
            $sql .= " AND user_id = :user_id"; // Yalnız öz bildirişini işarələyə bilsin
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
             if ($user_id !== null) {
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            }
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error marking notification ID ($id) as read: " . $e->getMessage());
            return false;
        }
    }

     /**
     * İstifadəçinin bütün oxunmamış bildirişlərini oxunmuş kimi işarələyir.
     * @param int $user_id
     * @return bool Uğurlu olub-olmadığı.
     */
    public function markAllNotificationsAsRead(int $user_id): bool {
        // DİQQƏT: `notifications` cədvəlində `read_at` sütunu olmalıdır.
        // Əgər yoxdursa, `read_at = NOW()` hissəsini silin.
        $sql = "UPDATE {$this->table_name} SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error marking all notifications as read for user ID ($user_id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Bildirişi silir.
     * @param int $id Bildiriş ID-si.
     * @param int|null $user_id (Optional) Təhlükəsizlik üçün yoxlamaq.
     * @return bool Uğurlu olub-olmadığı.
     */
    public function deleteNotification(int $id, ?int $user_id = null): bool {
        $sql = "DELETE FROM {$this->table_name} WHERE id = :id";
         if ($user_id !== null) {
            $sql .= " AND user_id = :user_id"; // Yalnız öz bildirişini silə bilsin
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
             if ($user_id !== null) {
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            }
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting notification ID ($id): " . $e->getMessage());
            return false;
        }
    }

    /**
     * İstifadəçinin oxunmamış bildirişlərinin sayını qaytarır.
     * @param int $user_id
     * @return int|false Say və ya xəta.
     */
    public function countUnreadNotifications(int $user_id): int|false {
        $sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE user_id = :user_id AND is_read = 0";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting unread notifications for user ID ($user_id): " . $e->getMessage());
            return false;
        }
    }

}
?>
