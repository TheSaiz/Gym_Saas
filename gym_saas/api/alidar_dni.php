<?php
header('Content-Type: application/json');
include_once(__DIR__."/../db_connect.php");

$dni = $_GET['dni'] ?? '';
$gimnasio_id = $_GET['gimnasio_id'] ?? 0;

if(!$dni || !$gimnasio_id){
    echo json_encode(['error'=>true,'msg'=>'Parámetros incompletos']);
    exit;
}

$sql = "SELECT s.id, s.nombre, s.apellido, ls.estado AS licencia_estado, ls.fecha_fin
        FROM socios s
        LEFT JOIN licencias_socios ls ON ls.socio_id=s.id AND ls.gimnasio_id=$gimnasio_id
        WHERE s.dni='$dni' AND s.gimnasio_id=$gimnasio_id";
$res = $conn->query($sql);

if($res->num_rows>0){
    $data = $res->fetch_assoc();
    echo json_encode(['error'=>false,'socio'=>$data]);
}else{
    echo json_encode(['error'=>true,'msg'=>'Socio no encontrado']);
}
