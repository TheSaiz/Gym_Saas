<?php
/**
 * Webhook de MercadoPago
 * Recibe notificaciones de pagos y actualiza el estado en la base de datos
 * 
 * IMPORTANTE: Este archivo NO debe tener sesiones ni redirecciones
 * Es llamado directamente por MercadoPago
 */

// Headers de seguridad
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Incluir conexión a base de datos
include_once(__DIR__ . "/../includes/db_connect.php");

// Log de la solicitud (útil para debugging)
$log_file = __DIR__ . "/../logs/mp_webhook.log";
$log_dir = dirname($log_file);

if(!is_dir($log_dir)){
    mkdir($log_dir, 0755, true);
}

// Función para registrar logs
function logWebhook($message, $data = null) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message";
    
    if($data !== null) {
        $log_message .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT);
    }
    
    $log_message .= "\n" . str_repeat('-', 80) . "\n";
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Validar método
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    logWebhook("Método no permitido: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

// Obtener datos del webhook
$input = file_get_contents('php://input');
$data = json_decode($input, true);

logWebhook("Webhook recibido", $data);

// Validar que se recibieron datos
if(empty($data)){
    logWebhook("No se recibieron datos válidos");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Datos inválidos'
    ]);
    exit;
}

// Obtener tipo de notificación
$type = $data['type'] ?? '';
$action = $data['action'] ?? '';

// Solo procesar notificaciones de pago
if($type !== 'payment' && $action !== 'payment.created' && $action !== 'payment.updated'){
    logWebhook("Tipo de notificación no procesada: $type / $action");
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Notificación recibida pero no procesada'
    ]);
    exit;
}

// Obtener ID del pago
$payment_id = null;

if(isset($data['data']['id'])){
    $payment_id = $data['data']['id'];
} elseif(isset($data['id'])){
    $payment_id = $data['id'];
}

if(empty($payment_id)){
    logWebhook("No se encontró ID de pago en la notificación");
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'ID de pago no encontrado'
    ]);
    exit;
}

logWebhook("Procesando pago ID: $payment_id");

try {
    // Buscar el pago en la base de datos por mp_id
    $stmt = $conn->prepare("SELECT id, gimnasio_id, tipo, estado FROM pagos WHERE mp_id = ? LIMIT 1");
    $stmt->bind_param("s", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows === 0){
        logWebhook("Pago no encontrado en base de datos: $payment_id");
        $stmt->close();
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Pago no encontrado'
        ]);
        exit;
    }
    
    $pago = $result->fetch_assoc();
    $stmt->close();
    
    logWebhook("Pago encontrado", $pago);
    
    // Obtener información del pago desde MercadoPago (opcional pero recomendado)
    require_once(__DIR__ . "/../vendor/autoload.php");
    
    // Obtener token de acceso del gimnasio
    $stmt = $conn->prepare("SELECT mp_token FROM gimnasios WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $pago['gimnasio_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows === 1){
        $gimnasio = $result->fetch_assoc();
        $mp_access_token = $gimnasio['mp_token'];
        $stmt->close();
        
        // Configurar SDK de MercadoPago
        MercadoPago\SDK::setAccessToken($mp_access_token);
        
        // Obtener información del pago
        $payment = MercadoPago\Payment::find_by_id($payment_id);
        
        if($payment){
            $status = $payment->status; // approved, pending, rejected, cancelled, etc.
            
            logWebhook("Estado del pago en MercadoPago: $status");
            
            // Mapear estados de MercadoPago a nuestro sistema
            $estado_sistema = 'pendiente';
            
            switch($status){
                case 'approved':
                    $estado_sistema = 'pagado';
                    break;
                case 'pending':
                case 'in_process':
                case 'in_mediation':
                    $estado_sistema = 'pendiente';
                    break;
                case 'rejected':
                case 'cancelled':
                case 'refunded':
                case 'charged_back':
                    $estado_sistema = 'fallido';
                    break;
            }
            
            // Actualizar estado del pago en la base de datos
            $stmt = $conn->prepare("UPDATE pagos SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $estado_sistema, $pago['id']);
            
            if($stmt->execute()){
                logWebhook("Pago actualizado exitosamente a estado: $estado_sistema");
                
                // Si el pago fue aprobado y es una licencia de gimnasio, actualizar la licencia
                if($estado_sistema === 'pagado' && $pago['tipo'] === 'licencia_gimnasio'){
                    
                    // Obtener la licencia adquirida desde external_reference
                    $external_ref = $payment->external_reference ?? '';
                    
                    if(!empty($external_ref)){
                        // Format: GYM-{gimnasio_id}-{pago_id}
                        $parts = explode('-', $external_ref);
                        
                        if(count($parts) === 3){
                            // Buscar la licencia en la tabla pagos o usar una lógica específica
                            // Por ahora, extendemos 30 días como ejemplo
                            // TODO: Mejorar esto para obtener los días exactos de la licencia comprada
                            
                            $stmt = $conn->prepare("UPDATE gimnasios 
                                                   SET fecha_inicio = CURDATE(), 
                                                       fecha_fin = DATE_ADD(CURDATE(), INTERVAL 30 DAY),
                                                       estado = 'activo'
                                                   WHERE id = ?");
                            $stmt->bind_param("i", $pago['gimnasio_id']);
                            
                            if($stmt->execute()){
                                logWebhook("Licencia del gimnasio actualizada exitosamente");
                            } else {
                                logWebhook("Error al actualizar licencia: " . $stmt->error);
                            }
                            
                            $stmt->close();
                        }
                    }
                }
                
                // Si es una membresía de socio, actualizar la licencia del socio
                if($estado_sistema === 'pagado' && $pago['tipo'] === 'membresia_socio' && !empty($pago['usuario_id'])){
                    
                    // Obtener datos de la membresía desde usuario_id (que sería el socio_id)
                    $stmt = $conn->prepare("UPDATE licencias_socios 
                                           SET estado = 'activa',
                                               fecha_inicio = CURDATE(),
                                               fecha_fin = DATE_ADD(CURDATE(), INTERVAL 30 DAY),
                                               pago_id = ?
                                           WHERE socio_id = ? AND gimnasio_id = ?
                                           ORDER BY id DESC LIMIT 1");
                    $stmt->bind_param("sii", $payment_id, $pago['usuario_id'], $pago['gimnasio_id']);
                    
                    if($stmt->execute()){
                        logWebhook("Membresía del socio actualizada exitosamente");
                    } else {
                        logWebhook("Error al actualizar membresía: " . $stmt->error);
                    }
                    
                    $stmt->close();
                }
                
                $stmt->close();
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Pago procesado correctamente',
                    'pago_id' => $pago['id'],
                    'estado' => $estado_sistema
                ]);
                
            } else {
                logWebhook("Error al actualizar pago: " . $stmt->error);
                $stmt->close();
                
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Error al actualizar el pago'
                ]);
            }
            
        } else {
            logWebhook("No se pudo obtener información del pago desde MercadoPago");
            
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo obtener información del pago'
            ]);
        }
        
    } else {
        logWebhook("Gimnasio no encontrado o sin token de MercadoPago");
        $stmt->close();
        
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Configuración de MercadoPago no encontrada'
        ]);
    }
    
} catch(Exception $e) {
    logWebhook("Error en el procesamiento: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
}

exit;
