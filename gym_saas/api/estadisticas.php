<?php
header('Content-Type: application/json');
include_once(__DIR__."/../db_connect.php");

$gimnasio_id = $_GET['gimnasio_id'] ?? 0;
if(!$gimnasio_id){
    echo json_encode(['error'=>true,'msg'=>'Gimnasio no definido']);
    exit;
}

// Cantidad socios activos
$res_socios = $conn->query("SELECT COUNT(*) AS total FROM socios WHERE gimnasio_id=$gimnasio_id AND estado='activo'");
$socios = $res_socios->fetch_assoc()['total'];

// Licencias activas
$res_lic = $conn->query("SELECT COUNT(*) AS total FROM licencias_socios WHERE gimnasio_id=$gimnasio_id AND estado='activa'");
$licencias = $res_lic->fetch_assoc()['total'];

// Pagos pendientes
$res_pagos = $conn->query("SELECT COUNT(*) AS total FROM pagos WHERE gimnasio_id=$gimnasio_id AND estado='pendiente'");
$pendientes = $res_pagos->fetch_assoc()['total'];

echo json_encode([
    'error'=>false,
    'socios_activos'=>$socios,
    'licencias_activas'=>$licencias,
    'pagos_pendientes'=>$pendientes
]);
