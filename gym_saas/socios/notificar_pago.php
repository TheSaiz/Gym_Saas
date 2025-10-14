<?php
/**
 * Webhook de MercadoPago - Notificación de Pagos
 * Manejo seguro de notificaciones IPN de MercadoPago
 */

// NO iniciar sesión en webhooks
include_once(__DIR__ . "/../includes/db_connect.php");

// ============================================
// 1. LOGGING Y SEGURIDAD
// ============================================

// Función para log seguro
function logPaymentNotification($message, $data = []) {
    $log_file = __DIR__ . '/../logs/payment_notifications.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_message = "[$timestamp] [$ip] $message";
    
    if (!empty($data)) {
        $log_message .= " | Data: " . json_encode($data);
    }
    
    $log_message .= PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

// Log de inicio
logPaymentNotification("Webhook received", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'get' => $_GET,
    'post_keys' => array_keys($_POST)
]);

// ============================================
// 2. VALIDACIÓN DE ORIGEN
// ============================================

// Verificar que sea una petición POST o GET de MercadoPago
$allowed_ips = [
    '209.225.49.0/24',  // Rango de IPs de MercadoPago
    '216.33.197.0/24',
    '216.33.196.0/24'
];

$request_ip = $_SERVER['REMOTE_ADDR'] ?? '';

// Validar User-Agent de MercadoPago
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$is_mp_user_agent = strpos($user_agent, 'MercadoPago') !== false;

// ============================================
// 3. OBTENER DATOS DE LA NOTIFICACIÓN
// ============================================

$topic = $_GET['topic'] ?? $_POST['topic'] ?? '';
$id = $_GET['id'] ?? $_POST['id'] ?? '';

// MercadoPago puede enviar diferentes tipos de notificaciones
// topic puede ser: 'payment', 'merchant_order', etc.

logPaymentNotification("Processing notification", [
    'topic' => $topic,
    'id' => $id
]);

// Validar que tengamos los datos necesarios
if (empty($topic) || empty($id)) {
    logPaymentNotification("ERROR: Missing topic or id");
    http_response_code(400);
    exit('Missing parameters');
}

// ============================================
// 4. OBTENER ACCESS TOKEN
// ============================================

// Obtener access token del superadmin como fallback
$stmt = $conn->prepare("SELECT mp_access_token FROM superadmin WHERE id = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$mp_access_token = '';

if ($result->num_rows === 1) {
    $superadmin = $result->fetch_assoc();
    $mp_access_token = $superadmin['mp_access_token'] ?? '';
}
$stmt->close();

if (