<?php
include_once(__DIR__."/db_connect.php");
require_once(__DIR__."/vendor/autoload.php");
MercadoPago\SDK::setAccessToken('TU_ACCESS_TOKEN');

$topic = $_GET['topic'] ?? '';
$id = $_GET['id'] ?? '';

if($topic=='payment' && $id){
    $payment = MercadoPago\Payment::find_by_id($id);
    if($payment->status=='approved'){
        $socio_id = $payment->payer->id; // Ajustar según MP payer id
        $monto = $payment->transaction_amount;
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d', strtotime("+30 days"));

        // Insertar licencia socio
        $sql = "INSERT INTO licencias_socios (socio_id,gimnasio_id,fecha_inicio,fecha_fin,estado,pago_id)
                VALUES ($socio_id,1,'$fecha_inicio','$fecha_fin','activa','$payment->id')";
        $conn->query($sql);

        // Actualizar pagos
        $conn->query("UPDATE pagos SET estado='pagado' WHERE mp_id='".$payment->id."'");
    }
}
http_response_code(200);
