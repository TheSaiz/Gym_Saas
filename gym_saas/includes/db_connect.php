<?php
/**
 * Conexión Segura a la Base de Datos
 * Incluye manejo de errores y configuración
 */

// Cargar variables de entorno
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Cargar .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configuración de zona horaria
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Argentina/Buenos_Aires');

// Configuración de errores según entorno
if ($_ENV['APP_ENV'] === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Configuración de sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Solo HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.name', $_ENV['SESSION_NAME'] ?? 'GYM_SAAS_SESSION');

// Conexión a la base de datos
try {
    $conn = new mysqli(
        $_ENV['DB_HOST'],
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        $_ENV['DB_NAME'],
        (int)$_ENV['DB_PORT']
    );

    // Verificar conexión
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    // Establecer charset
    if (!$conn->set_charset($_ENV['DB_CHARSET'] ?? 'utf8mb4')) {
        throw new Exception("Error estableciendo charset: " . $conn->error);
    }

    // Configurar modo SQL estricto
    $conn->query("SET sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");

} catch (Exception $e) {
    // Log del error
    error_log($e->getMessage());
    
    // Respuesta según entorno
    if ($_ENV['APP_ENV'] === 'production') {
        die('Error de conexión. Por favor contacte al administrador.');
    } else {
        die('Error de conexión: ' . $e->getMessage());
    }
}

/**
 * Función helper para ejecutar queries preparadas de forma segura
 */
function db_execute($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Error preparando query: " . $conn->error);
        return false;
    }
    
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Error ejecutando query: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    return $stmt;
}

/**
 * Función helper para obtener resultados
 */
function db_fetch($conn, $sql, $types = '', $params = []) {
    $stmt = db_execute($conn, $sql, $types, $params);
    
    if (!$stmt) {
        return false;
    }
    
    $result = $stmt->get_result();
    $data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $data;
}

/**
 * Función para logging de actividad
 */
function log_activity($conn, $user_type, $user_id, $action, $details = '') {
    if (!$_ENV['LOG_ENABLED']) {
        return;
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $sql = "INSERT INTO activity_log (user_type, user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    db_execute($conn, $sql, 'sissss', [
        $user_type,
        $user_id,
        $action,
        $details,
        $ip,
        $user_agent
    ]);
}

// Crear tabla de logs si no existe
$conn->query("CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('superadmin', 'gimnasio', 'socio', 'staff') NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_type, user_id),
    INDEX(created_at)
)");
