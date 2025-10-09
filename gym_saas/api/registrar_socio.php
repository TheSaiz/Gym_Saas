<?php
header('Content-Type: application/json');
include_once(__DIR__."/../db_connect.php");

$nombre = $_POST['nombre'] ?? '';
$apellido = $_POST['apellido'] ?? '';
$dni = $_POST['dni'] ?? '';
$telefono = $_POST['telefono'] ?? '';
$email = $_POST['email'] ?? '';
$gimnasio_id = $_POST['gimnasio_id'] ?? 0;

if(!$nombre || !$apellido || !$dni || !$gimnasio_id){
    echo json_encode(['error'=>true,'msg'=>'Faltan datos obligatorios']);
    exit;
}

// Verificar si ya existe
$check = $conn->query("SELECT id FROM socios WHERE dni='$dni' AND gimnasio_id=$gimnasio_id");
if($check->num_rows>0){
    echo json_encode(['error'=>true,'msg'=>'Socio ya registrado']);
    exit;
}

// Crear socio
$password = md5(substr($dni,-4)); // clave inicial simple
$sql = "INSERT INTO socios (gimnasio_id,nombre,apellido,dni,telefono,email,password)
        VALUES ($gimnasio_id,'$nombre','$apellido','$dni','$telefono','$email','$password')";

if($conn->query($sql)){
    echo json_encode(['error'=>false,'msg'=>'Socio registrado','socio_id'=>$conn->insert_id]);
}else{
    echo json_encode(['error'=>true,'msg'=>'Error: '.$conn->error]);
}
