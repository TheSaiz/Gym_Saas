<?php
header('Content-Type: application/json');
include_once(__DIR__."/../db_connect.php");

$gimnasio_id = $_GET['gimnasio_id'] ?? 0;
if(!$gimnasio_id){
    echo json_encode(['error'=>true,'msg'=>'Gimnasio no definido']);
    exit;
}

$sql = "SELECT id,nombre,precio,dias FROM membresias 
        WHERE gimnasio_id=$gimnasio_id AND estado='activo'";
$res = $conn->query($sql);

$licencias = [];
while($row = $res->fetch_assoc()){
    $licencias[] = $row;
}

echo json_encode(['error'=>false,'licencias'=>$licencias]);
