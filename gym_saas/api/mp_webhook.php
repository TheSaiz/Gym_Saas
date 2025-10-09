<?php
header('Content-Type: application/json');
include_once(__DIR__."/../db_connect.php");
require_once(__DIR__."/../vendor/autoload.php");

MercadoPago\SDK::setAccessToken('TU_ACCESS_TOKEN');

$topic = $_GET['topic'] ?? '';
$id = $_GET['id'] ?? '';

if($topic=='payment' && $id){
    $payment = MercadoPago\Payment::find_by_id($id);
    if($payment->status=='approved'){
        $socio_email = $payment->payer->email;
        $res = $conn->query("SELECT id,gimnasio_id FROM socios WHERE email='$socio_email'");
        if($res->num_rows>0){
            $socio = $res->fetch_assoc();
            $fecha_inicio = date('Y-m-d');
            $fecha_fin = date('Y-m-d', strtotime("+30 days"));
            $conn->query("INSERT INTO licencias_socios (socio_id,gimnasio_id,fecha_inicio,fecha_fin,estado,pago_id)
                        VALUES ({$socio['id']},{$socio['gimnasio_id']},'$fecha_inicio','$fecha_fin','activa','$payment->id')");
            $conn->query("UPDATE pagos SET estado='pagado' WHERE mp_id='$payment->id'");
        }
    }
}
echo json_encode(['status'=>'ok']);
