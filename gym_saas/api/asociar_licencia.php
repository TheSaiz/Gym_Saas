<?php
header('Content-Type: application/json');
include_once(__DIR__."/../db_connect.php");

$socio_id = $_POST['socio_id'] ?? 0;
$membresia_id = $_POST['membresia_id'] ?? 0;
$gimnasio_id = $_POST['gimnasio_id'] ?? 0;

if(!$socio_id || !$membresia_id || !$gimnasio_id){
    echo json_encode(['error'=>true,'msg'=>'Faltan datos']);
    exit;
}

// Verificar membresía
$res = $conn->query("SELECT dias FROM membresias WHERE id=$membresia_id");
if($res->num_rows==0){
    echo json_encode(['error'=>true,'msg'=>'Membresía no encontrada']);
    exit;
}
$membresia = $res->fetch_assoc();
$fecha_inicio = date('Y-m-d');
$fecha_fin = date('Y-m-d', strtotime("+{$membresia['dias']} days"));

// Insertar licencia socio
$sql = "INSERT INTO licencias_socios (socio_id,gimnasio_id,membresia_id,fecha_inicio,fecha_fin,estado)
        VALUES ($socio_id,$gimnasio_id,$membresia_id,'$fecha_inicio','$fecha_fin','activa')";

if($conn->query($sql)){
    echo json_encode(['error'=>false,'msg'=>'Licencia asociada']);
}else{
    echo json_encode(['error'=>true,'msg'=>'Error: '.$conn->error]);
}
