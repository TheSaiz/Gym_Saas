<?php
/**
 * Configuración de Base de Datos y Seguridad
 * Sistema de Gestión de Barbería v2.0
 */

// Iniciar sesión con configuración segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

session_start();

// Regenerar ID de sesión periódicamente para prevenir session fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) { // 30 minutos
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Configuración de la base de datos
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'barberia');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Headers de seguridad HTTP
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configuración de errores (desactivar en producción)
if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage());
    die('Error de conexión a la base de datos. Por favor, contacte al administrador.');
}

/**
 * Función para generar tokens CSRF
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Función para validar tokens CSRF
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Función para sanitizar outputs HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Función para verificar autenticación
 */
function requireAuth($role = null) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
    
    if ($role !== null && $_SESSION['user_role'] !== $role) {
        header('Location: index.php');
        exit();
    }
    
    return true;
}

/**
 * Función para registrar actividad (auditoría)
 */
function logActivity($pdo, $action, $details = null) {
    try {
        // Verificar si la tabla existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'activity_log'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO activity_log (user_id, action, details, ip_address) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $details,
                $_SERVER['REMOTE_ADDR']
            ]);
        }
    } catch (PDOException $e) {
        error_log('Log Activity Error: ' . $e->getMessage());
    }
}

/**
 * Función para validar y limpiar inputs
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}