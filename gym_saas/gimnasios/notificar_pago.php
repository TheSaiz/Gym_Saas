<?php
// Webhook para actualizar pagos automáticamente
include_once(__DIR__ . "/../includes/db_connect.php");
$input = json_decode(file_get_contents('php://input'), true);

if(isset($input['id'])){
    $mp_id = $conn->real_escape_string($input['id']);
    $status = $conn->real_escape_string($input['status'] ?? 'pendiente');
    
    // Actualizar estado en la tabla pagos
    $conn->query("UPDATE pagos SET estado='$status' WHERE mp_id='$mp_id'");
    
    http_response_code(200);
    echo json_encode(['success'=>true]);
} else {
    http_response_code(400);
    echo json_encode(['success'=>false, 'error'=>'ID de pago no recibido']);
}
