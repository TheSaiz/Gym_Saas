<?php
/**
 * Webhook de MercadoPago - Notificación de Pagos de Socios
 * Manejo seguro de notificaciones IPN de MercadoPago
 * Versión: 1.0
 */

// NO iniciar sesión en webhooks
include_once(__DIR__ . "/../includes/db_connect.php");

// ============================================
// 1. FUNCIONES DE LOGGING Y SEGURIDAD
// ============================================

function logPaymentNotification($message, $data = []) {
    $log_file = __DIR__ . '/../logs/payment_notifications.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_message = "[$timestamp] [$ip] $message";
    
    if (!empty($data)) {
        $sanitized_data = array_map(function($v) {
            return is_string($v) ? substr($v, 0, 200) : $v;
        }, $data);
        $log_message .= " | Data: " . json_encode($sanitized_data);
    }
    
    $log_message .= PHP_EOL;
    
    @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

function sendJsonResponse($success, $message, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'timestamp' => time()
    ]);
    exit;
}

// ============================================
// 2. VALIDACIÓN INICIAL
// ============================================

logPaymentNotification("Webhook received", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'get' => $_GET,
    'headers' => [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN'
    ]
]);

// Verificar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    logPaymentNotification("ERROR: Invalid HTTP method");
    sendJsonResponse(false, 'Method not allowed', 405);
}

// ============================================
// 3. VALIDACIÓN DE ORIGEN (OPCIONAL - COMENTADO PARA TESTING)
// ============================================

/*
$allowed_ips = [
    '209.225.49.0/24',
    '216.33.197.0/24',
    '216.33.196.0/24',
    '209.225.48.0/24'
];

function isIpInRange($ip, $range) {
    list($subnet, $mask) = explode('/', $range);
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - $mask);
    return ($ip_long & $mask_long) == ($subnet_long & $mask_long);
}

$request_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ip_valid = false;

foreach ($allowed_ips as $range) {
    if (isIpInRange($request_ip, $range)) {
        $ip_valid = true;
        break;
    }
}

if (!$ip_valid && !in_array($request_ip, ['127.0.0.1', '::1'])) {
    logPaymentNotification("ERROR: Invalid IP address", ['ip' => $request_ip]);
    sendJsonResponse(false, 'Unauthorized', 403);
}
*/

// Validar User-Agent de MercadoPago (relajado para testing)
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
// $is_mp_user_agent = strpos(strtolower($user_agent), 'mercadopago') !== false;

// ============================================
// 4. OBTENER DATOS DE LA NOTIFICACIÓN
// ============================================

$topic = $_GET['topic'] ?? $_POST['topic'] ?? '';
$id = $_GET['id'] ?? $_POST['id'] ?? '';

// Leer el body raw para notificaciones tipo application/json
$raw_input = file_get_contents('php://input');
if (!empty($raw_input)) {
    $json_data = json_decode($raw_input, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $topic = $topic ?: ($json_data['topic'] ?? ($json_data['type'] ?? ''));
        $id = $id ?: ($json_data['id'] ?? ($json_data['data']['id'] ?? ''));
    }
}

logPaymentNotification("Processing notification", [
    'topic' => $topic,
    'id' => $id,
    'raw_input_length' => strlen($raw_input)
]);

// Validar que tengamos los datos necesarios
if (empty($id)) {
    logPaymentNotification("ERROR: Missing id parameter");
    sendJsonResponse(false, 'Missing id parameter', 400);
}

// Normalizar topic
$topic = strtolower($topic);
if (empty($topic)) {
    $topic = 'payment'; // Default a payment
}

// ============================================
// 5. OBTENER ACCESS TOKEN
// ============================================

$stmt = $conn->prepare("SELECT mp_access_token FROM superadmin WHERE id = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$mp_access_token = '';

if ($result->num_rows === 1) {
    $superadmin = $result->fetch_assoc();
    $mp_access_token = $superadmin['mp_access_token'] ?? '';
}
$stmt->close();

if (empty($mp_access_token)) {
    logPaymentNotification("ERROR: MercadoPago access token not configured");
    sendJsonResponse(false, 'Payment gateway not configured', 500);
}

// ============================================
// 6. CONSULTAR INFORMACIÓN DEL PAGO EN MERCADOPAGO
// ============================================

function getMercadoPagoPayment($payment_id, $access_token) {
    $url = "https://api.mercadopago.com/v1/payments/{$payment_id}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$access_token}",
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        logPaymentNotification("ERROR: cURL error", ['error' => $error]);
        return null;
    }
    
    if ($http_code !== 200) {
        logPaymentNotification("ERROR: MP API returned non-200", [
            'http_code' => $http_code,
            'response' => substr($response, 0, 500)
        ]);
        return null;
    }
    
    return json_decode($response, true);
}

// Solo procesamos notificaciones de tipo 'payment'
if ($topic !== 'payment') {
    logPaymentNotification("INFO: Ignoring non-payment topic", ['topic' => $topic]);
    sendJsonResponse(true, 'Notification received (non-payment)', 200);
}

// Consultar el pago en MercadoPago
$payment_data = getMercadoPagoPayment($id, $mp_access_token);

if ($payment_data === null) {
    logPaymentNotification("ERROR: Could not retrieve payment data from MercadoPago");
    sendJsonResponse(false, 'Could not retrieve payment data', 500);
}

logPaymentNotification("Payment data retrieved", [
    'payment_id' => $payment_data['id'] ?? 'N/A',
    'status' => $payment_data['status'] ?? 'N/A',
    'external_reference' => $payment_data['external_reference'] ?? 'N/A'
]);

// ============================================
// 7. VALIDAR Y EXTRAER DATOS DEL PAGO
// ============================================

$payment_id = $payment_data['id'] ?? null;
$payment_status = $payment_data['status'] ?? '';
$external_reference = $payment_data['external_reference'] ?? '';
$transaction_amount = $payment_data['transaction_amount'] ?? 0;
$payment_method = $payment_data['payment_method_id'] ?? 'unknown';
$payer_email = $payment_data['payer']['email'] ?? '';

// Estados posibles: approved, pending, rejected, cancelled, refunded, charged_back
$valid_statuses = ['approved', 'pending', 'rejected', 'cancelled', 'refunded', 'charged_back'];

if (!in_array($payment_status, $valid_statuses)) {
    logPaymentNotification("ERROR: Invalid payment status", ['status' => $payment_status]);
    sendJsonResponse(false, 'Invalid payment status', 400);
}

// Parsear external_reference: formato esperado "SOCIO-{socio_id}-MEM-{membresia_id}"
if (empty($external_reference)) {
    logPaymentNotification("ERROR: Missing external_reference");
    sendJsonResponse(false, 'Missing external_reference', 400);
}

// Extraer socio_id y membresia_id
preg_match('/SOCIO-(\d+)-MEM-(\d+)/', $external_reference, $matches);

if (count($matches) !== 3) {
    logPaymentNotification("ERROR: Invalid external_reference format", ['ref' => $external_reference]);
    sendJsonResponse(false, 'Invalid reference format', 400);
}

$socio_id = (int)$matches[1];
$membresia_id = (int)$matches[2];

if ($socio_id <= 0 || $membresia_id <= 0) {
    logPaymentNotification("ERROR: Invalid IDs in reference", [
        'socio_id' => $socio_id,
        'membresia_id' => $membresia_id
    ]);
    sendJsonResponse(false, 'Invalid reference IDs', 400);
}

// ============================================
// 8. VERIFICAR QUE EL PAGO NO SE HAYA PROCESADO ANTES
// ============================================

$stmt = $conn->prepare("SELECT id, estado FROM pagos WHERE mp_id = ? AND tipo = 'membresia_socio' LIMIT 1");
$stmt->bind_param("s", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

$pago_existente = null;
if ($result->num_rows === 1) {
    $pago_existente = $result->fetch_assoc();
}
$stmt->close();

// Si el pago ya está marcado como pagado, evitar procesamiento duplicado
if ($pago_existente && $pago_existente['estado'] === 'pagado' && $payment_status === 'approved') {
    logPaymentNotification("WARNING: Payment already processed", ['payment_id' => $payment_id]);
    sendJsonResponse(true, 'Payment already processed', 200);
}

// ============================================
// 9. VERIFICAR DATOS DEL SOCIO Y MEMBRESÍA
// ============================================

$stmt = $conn->prepare("SELECT s.id, s.gimnasio_id, s.nombre, s.apellido, s.email 
                        FROM socios s 
                        WHERE s.id = ? AND s.estado = 'activo' 
                        LIMIT 1");
$stmt->bind_param("i", $socio_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    logPaymentNotification("ERROR: Socio not found or inactive", ['socio_id' => $socio_id]);
    $stmt->close();
    sendJsonResponse(false, 'Member not found', 404);
}

$socio = $result->fetch_assoc();
$stmt->close();

// Verificar membresía
$stmt = $conn->prepare("SELECT m.id, m.nombre, m.precio, m.dias, m.gimnasio_id 
                        FROM membresias m 
                        WHERE m.id = ? AND m.gimnasio_id = ? AND m.estado = 'activo' 
                        LIMIT 1");
$stmt->bind_param("ii", $membresia_id, $socio['gimnasio_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    logPaymentNotification("ERROR: Membresía not found or inactive", [
        'membresia_id' => $membresia_id,
        'gimnasio_id' => $socio['gimnasio_id']
    ]);
    $stmt->close();
    sendJsonResponse(false, 'Membership not found', 404);
}

$membresia = $result->fetch_assoc();
$stmt->close();

// Validar monto (tolerancia de ±1% para diferencias de redondeo)
$expected_amount = (float)$membresia['precio'];
$received_amount = (float)$transaction_amount;
$amount_difference = abs($expected_amount - $received_amount);
$tolerance = $expected_amount * 0.01;

if ($amount_difference > $tolerance) {
    logPaymentNotification("WARNING: Amount mismatch", [
        'expected' => $expected_amount,
        'received' => $received_amount,
        'difference' => $amount_difference
    ]);
    // No bloqueamos, solo registramos
}

// ============================================
// 10. PROCESAR SEGÚN ESTADO DEL PAGO
// ============================================

$conn->begin_transaction();

try {
    // Mapear estado de MercadoPago a nuestro sistema
    $estado_interno = 'pendiente';
    switch ($payment_status) {
        case 'approved':
            $estado_interno = 'pagado';
            break;
        case 'pending':
            $estado_interno = 'pendiente';
            break;
        case 'rejected':
        case 'cancelled':
        case 'refunded':
        case 'charged_back':
            $estado_interno = 'fallido';
            break;
    }
    
    // Actualizar o insertar el pago
    if ($pago_existente) {
        // Actualizar pago existente
        $stmt = $conn->prepare("UPDATE pagos 
                               SET estado = ?, 
                                   fecha = CURRENT_TIMESTAMP 
                               WHERE id = ?");
        $stmt->bind_param("si", $estado_interno, $pago_existente['id']);
        $stmt->execute();
        $stmt->close();
        
        $pago_db_id = $pago_existente['id'];
        
        logPaymentNotification("Payment updated", [
            'pago_id' => $pago_db_id,
            'estado' => $estado_interno
        ]);
    } else {
        // Insertar nuevo pago
        $stmt = $conn->prepare("INSERT INTO pagos 
                               (tipo, usuario_id, gimnasio_id, monto, estado, mp_id) 
                               VALUES ('membresia_socio', ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidss", $socio_id, $socio['gimnasio_id'], $received_amount, $estado_interno, $payment_id);
        $stmt->execute();
        $pago_db_id = $conn->insert_id;
        $stmt->close();
        
        logPaymentNotification("Payment inserted", [
            'pago_id' => $pago_db_id,
            'estado' => $estado_interno
        ]);
    }
    
    // Si el pago fue aprobado, activar/crear la licencia del socio
    if ($payment_status === 'approved') {
        
        // Verificar si ya existe una licencia activa para este socio y gimnasio
        $stmt = $conn->prepare("SELECT id, fecha_fin 
                               FROM licencias_socios 
                               WHERE socio_id = ? 
                               AND gimnasio_id = ? 
                               AND estado = 'activa' 
                               ORDER BY fecha_fin DESC 
                               LIMIT 1");
        $stmt->bind_param("ii", $socio_id, $socio['gimnasio_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $licencia_activa = null;
        if ($result->num_rows === 1) {
            $licencia_activa = $result->fetch_assoc();
        }
        $stmt->close();
        
        // Calcular fechas
        if ($licencia_activa) {
            // Extender la licencia existente
            $fecha_inicio = $licencia_activa['fecha_fin'];
            $stmt = $conn->prepare("SELECT DATE_ADD(?, INTERVAL ? DAY) as fecha_fin");
            $stmt->bind_param("si", $fecha_inicio, $membresia['dias']);
            $stmt->execute();
            $result = $stmt->get_result();
            $fecha_fin = $result->fetch_assoc()['fecha_fin'];
            $stmt->close();
            
            // Actualizar la licencia existente
            $stmt = $conn->prepare("UPDATE licencias_socios 
                                   SET fecha_fin = ?, 
                                       membresia_id = ?, 
                                       pago_id = ? 
                                   WHERE id = ?");
            $stmt->bind_param("sisi", $fecha_fin, $membresia_id, $payment_id, $licencia_activa['id']);
            $stmt->execute();
            $stmt->close();
            
            $licencia_id = $licencia_activa['id'];
            
            logPaymentNotification("License extended", [
                'licencia_id' => $licencia_id,
                'nueva_fecha_fin' => $fecha_fin
            ]);
            
        } else {
            // Crear nueva licencia
            $fecha_inicio = date('Y-m-d');
            $stmt = $conn->prepare("SELECT DATE_ADD(CURDATE(), INTERVAL ? DAY) as fecha_fin");
            $stmt->bind_param("i", $membresia['dias']);
            $stmt->execute();
            $result = $stmt->get_result();
            $fecha_fin = $result->fetch_assoc()['fecha_fin'];
            $stmt->close();
            
            // Insertar nueva licencia
            $stmt = $conn->prepare("INSERT INTO licencias_socios 
                                   (socio_id, gimnasio_id, membresia_id, fecha_inicio, fecha_fin, estado, pago_id) 
                                   VALUES (?, ?, ?, ?, ?, 'activa', ?)");
            $stmt->bind_param("iiisss", $socio_id, $socio['gimnasio_id'], $membresia_id, $fecha_inicio, $fecha_fin, $payment_id);
            $stmt->execute();
            $licencia_id = $conn->insert_id;
            $stmt->close();
            
            logPaymentNotification("License created", [
                'licencia_id' => $licencia_id,
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin' => $fecha_fin
            ]);
        }
        
        // Marcar otras licencias pendientes como procesadas
        $stmt = $conn->prepare("UPDATE licencias_socios 
                               SET estado = 'vencida' 
                               WHERE socio_id = ? 
                               AND gimnasio_id = ? 
                               AND estado = 'pendiente' 
                               AND id != ?");
        $stmt->bind_param("iii", $socio_id, $socio['gimnasio_id'], $licencia_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Si el pago fue rechazado o falló
    if (in_array($payment_status, ['rejected', 'cancelled', 'refunded'])) {
        // Marcar licencias pendientes como fallidas
        $stmt = $conn->prepare("UPDATE licencias_socios 
                               SET estado = 'vencida' 
                               WHERE socio_id = ? 
                               AND gimnasio_id = ? 
                               AND estado = 'pendiente'");
        $stmt->bind_param("ii", $socio_id, $socio['gimnasio_id']);
        $stmt->execute();
        $stmt->close();
        
        logPaymentNotification("Payment failed - licenses marked as expired", [
            'socio_id' => $socio_id,
            'payment_status' => $payment_status
        ]);
    }
    
    $conn->commit();
    
    logPaymentNotification("Transaction completed successfully", [
        'payment_id' => $payment_id,
        'status' => $payment_status,
        'socio_id' => $socio_id,
        'membresia_id' => $membresia_id
    ]);
    
    sendJsonResponse(true, 'Notification processed successfully', 200);
    
} catch (Exception $e) {
    $conn->rollback();
    
    logPaymentNotification("ERROR: Transaction failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    sendJsonResponse(false, 'Internal server error', 500);
}

// ============================================
// 11. FUNCIÓN OPCIONAL: ENVIAR EMAIL
// ============================================


function sendPaymentConfirmationEmail($socio, $membresia, $payment_status) {
    $to = $socio['email'];
    $subject = "Confirmación de Pago - " . $membresia['nombre'];
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f5f5f5; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>¡Pago Confirmado!</h1>
            </div>
            <div class='content'>
                <p>Hola {$socio['nombre']} {$socio['apellido']},</p>
                <p>Tu pago ha sido procesado exitosamente.</p>
                <p><strong>Membresía:</strong> {$membresia['nombre']}</p>
                <p><strong>Monto:</strong> $ {$membresia['precio']}</p>
                <p><strong>Duración:</strong> {$membresia['dias']} días</p>
            </div>
            <div class='footer'>
                <p>Gracias por tu confianza</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@tugimnasio.com" . "\r\n";
    
    mail($to, $subject, $message, $headers);
}
