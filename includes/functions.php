<?php
// e:\xampp\htdocs\task_management\includes\functions.php

declare(strict_types=1); // Strict tipləri aktivləşdirək

// --- Security Functions ---

/**
 * Sanitize input data to prevent XSS attacks.
 * @param string|null $data Input data.
 * @return string Sanitized data.
 */
function sanitize(?string $data): string {
    if ($data === null || $data === '') {
        return '';
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a CSRF token and store it in the session.
 * @return string The generated CSRF token.
 */
function generateCsrfToken(): string {
    if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'] ?? ''; // Sessiya yoxdursa boş string qaytar
}

/**
 * Validate a CSRF token against the one stored in the session.
 * @param string|null $token The token to validate.
 * @return bool True if the token is valid, false otherwise.
 */
function validateCsrfToken(?string $token): bool {
    if (empty($token) || session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// --- Flash Messages ---

/**
 * Set a flash message in the session.
 * @param string $type Message type (e.g., 'success', 'error', 'warning', 'info').
 * @param string $message The message content.
 */
function setFlashMessage(string $type, string $message): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
    }
}

/**
 * Display flash messages and clear them from the session.
 */
function displayFlashMessages(): void {
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
        foreach ($_SESSION['flash_messages'] as $flash) {
            $alertClass = match ($flash['type']) {
                'success' => 'alert-success',
                'error'   => 'alert-danger', // 'error' üçün 'alert-danger' istifadə edək
                'danger'  => 'alert-danger', // 'danger' də qəbul edək
                'warning' => 'alert-warning',
                default   => 'alert-info',
            };
            echo "<div class='alert {$alertClass} alert-dismissible fade show' role='alert'>";
            echo htmlspecialchars($flash['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo "</div>";
        }
        unset($_SESSION['flash_messages']); // Clear messages after displaying
    }
}

// --- Authentication & Authorization ---

/**
 * Checks if the current user has one of the allowed roles. Redirects if not authorized.
 * @param array|string $allowedRoles An array of allowed role keys (e.g., ['admin', 'manager']) or a single role string.
 */
function checkRole(array|string $allowedRoles): void {
    if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['user_role'])) {
        setFlashMessage('error', 'Bu əməliyyatı etmək üçün daxil olmalısınız.');
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/login.php');
        exit;
    }

    $currentUserRole = $_SESSION['user_role'];
    $rolesToCheck = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles]; // String gələrsə array-ə çevir

    if (!in_array($currentUserRole, $rolesToCheck)) {
        setFlashMessage('error', 'Bu bölməyə giriş icazəniz yoxdur.');
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/dashboard'); // Və ya uyğun bir səhifə
        exit;
    }
}


// --- Task Specific Helpers ---

/**
 * Translate task status keys into human-readable Azerbaijani names.
 * @param string|null $status The status key (e.g., 'pending', 'completed').
 * @return string The translated status name.
 */
function translateStatus(?string $status): string {
    return match (strtolower($status ?? '')) {
        'pending' => 'Gözləmədə',
        'in_progress' => 'İcrada',
        'completed' => 'Tamamlandı',
        'declined' => 'Ləğv Edildi',
        default => 'Bilinmir',
    };
}

/**
 * Get the Bootstrap badge background class based on task status.
 * @param string|null $status The status key.
 * @return string The corresponding Bootstrap class (e.g., 'warning text-dark', 'success').
 */
function getStatusBadgeClass(?string $status): string {
    return match (strtolower($status ?? '')) {
        'pending' => 'warning text-dark', // Sarı
        'in_progress' => 'info text-dark',    // Mavi
        'completed' => 'success',         // Yaşıl
        'declined' => 'danger',           // Qırmızı
        default => 'secondary',         // Boz
    };
}

/**
 * Translate task priority keys into human-readable Azerbaijani names.
 * @param string|null $priority The priority key (e.g., 'low', 'medium', 'high').
 * @return string The translated priority name.
 */
function translatePriority(?string $priority): string {
    return match (strtolower($priority ?? '')) {
        'low' => 'Aşağı',
        'medium' => 'Orta',
        'high' => 'Yüksək',
        default => 'Bilinmir',
    };
}

/**
 * Get the Bootstrap text color class based on task priority.
 * @param string|null $priority The priority key.
 * @return string The corresponding Bootstrap text class (e.g., 'text-success', 'text-warning', 'text-danger').
 */
function getPriorityClass(?string $priority): string {
    return match (strtolower($priority ?? '')) {
        'low' => 'success', // Green for low priority
        'medium' => 'warning', // Yellow for medium priority
        'high' => 'danger',  // Red for high priority
        default => 'muted',   // Default muted color
    };
}

// --- User Specific Helpers ---

// === DÜZƏLİŞ: translateRole əvəzinə translateUserRole ===
if (!function_exists('translateUserRole')) {
    /**
     * Translates user role keys into human-readable Azerbaijani names.
     *
     * @param string|null $role The role key (e.g., 'admin', 'manager', 'employee', 'user').
     * @return string The translated role name or 'Bilinmir' if not found.
     */
    function translateUserRole(?string $role): string {
        // Sistemdə istifadə olunan bütün rolları əhatə edək
        return match (strtolower($role ?? '')) {
            'admin' => 'Admin',
            'manager' => 'Menecer',
            'employee' => 'İşçi', // TaskController-də bu istifadə olunur
            'user' => 'İşçi',     // Köhnə translateRole-da bu var idi, eyni mənanı verək
            default => 'Bilinmir',
        };
    }
}
// === DÜZƏLİŞ SONU ===

/**
 * Translate user status keys into human-readable Azerbaijani names.
 * @param string|null $status The status key (e.g., 'active', 'inactive').
 * @return string The translated status name.
 */
function translateUserStatus(?string $status): string {
    return match (strtolower($status ?? '')) {
        'active' => 'Aktiv',
        'inactive' => 'Qeyri-aktiv',
        default => 'Bilinmir',
    };
}

/**
 * Get the Bootstrap badge background class based on user status.
 * @param string|null $status The status key.
 * @return string The corresponding Bootstrap class.
 */
function getUserStatusBadgeClass(?string $status): string {
    return match (strtolower($status ?? '')) {
        'active' => 'success',
        'inactive' => 'danger',
        default => 'secondary',
    };
}


// --- Date/Time Helpers ---

/**
 * Format a date/time string for display.
 * @param string|null $dateTimeString The date/time string from DB.
 * @param string $format The desired output format (default: 'd.m.Y H:i').
 * @return string The formatted date/time or '-' if input is null/invalid.
 */
function formatDateTime(?string $dateTimeString, string $format = 'd.m.Y H:i'): string {
    if (empty($dateTimeString)) {
        return '-';
    }
    try { // Tarix formatı səhv olarsa xəta verməsin
        $date = new DateTime($dateTimeString);
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Invalid date/time string for formatting: " . $dateTimeString); // Xətanı loglayaq
        return '-'; // Return '-' on invalid date format
    }
}

/**
 * Format a date string for display.
 * @param string|null $dateString The date string from DB.
 * @param string $format The desired output format (default: 'd.m.Y').
 * @return string The formatted date or '-' if input is null/invalid.
 */
function formatDate(?string $dateString, string $format = 'd.m.Y'): string {
    // formatDateTime funksiyasını çağıraq, çünki eyni məntiqi yerinə yetirir
    return formatDateTime($dateString, $format);
}

/**
 * Validates a date string according to a specific format.
 * @param string|null $date The date string to validate.
 * @param string $format The expected format (default: 'Y-m-d').
 * @return bool True if the date is valid and matches the format, false otherwise.
 */
function validateDate(?string $date, string $format = 'Y-m-d'): bool {
    if (empty($date)) return false;
    $d = \DateTime::createFromFormat($format, $date);
    // Check if the created DateTime object is valid AND if formatting it back gives the original string
    return $d && $d->format($format) === $date;
}


// --- Other Helpers ---

/**
 * Truncate a string to a specified length and add ellipsis if needed.
 * Handles multi-byte characters correctly.
 * @param string|null $text The string to truncate.
 * @param int $maxLength The maximum length.
 * @param string $ellipsis The ellipsis string to append.
 * @return string The truncated string.
 */
function truncateText(?string $text, int $maxLength = 50, string $ellipsis = '...'): string {
    if ($text === null) {
        return '';
    }
    if (mb_strlen($text, 'UTF-8') > $maxLength) {
        return mb_substr($text, 0, $maxLength, 'UTF-8') . $ellipsis;
    }
    return $text;
}

