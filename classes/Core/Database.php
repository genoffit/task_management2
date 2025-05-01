<?php
namespace App\Core;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;

    // Konstruktoru private etməklə birbaşa obyekt yaratmağın qarşısını alırıq
    private function __construct() {
        try {
            // Konstantlar config/database.php-dən gəlməlidir
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Xətaları Exception olaraq at
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Default fetch metodu assosiativ massiv
                PDO::ATTR_EMULATE_PREPARES   => false,                  // Real prepared statement istifadə et
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Bağlantı xətasını logla və proqramı dayandır (və ya daha yaxşı xəta idarəetməsi)
            error_log("Database Connection Error: " . $e->getMessage());
            // İstifadəçiyə ümumi xəta mesajı göstərmək olar
            die("Database connection failed. Please check configuration or contact support.");
        }
    }

    // Klonlamanın qarşısını al
    private function __clone() {}

    // Wakeup-ın qarşısını al
    public function __wakeup() {}

    // Singleton instansını almaq üçün statik metod
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // PDO bağlantı obyektini qaytarır
    public function getConnection(): PDO {
        return $this->connection;
    }
}
?>
