<?php
session_start();
include_once(__DIR__."/db_connect.php");

if($_SERVER['REQUEST_METHOD']=='POST'){
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $dni = $_POST['dni'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $telefono_emergencia = $_POST['telefono_emergencia'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = md5($_POST['password'] ?? '');
    $gimnasio_id = $_POST['gimnasio_id'] ?? 1; // Default a gimnasio de prueba

    // Verificar email o DNI
    $sql_check = "SELECT id FROM socios WHERE email='$email' OR dni='$dni'";
    $res_check = $conn->query($sql_check);
    if($res_check->num_rows > 0){
        die("Email o DNI ya registrado.");
    }

    $sql = "INSERT INTO socios (gimnasio_id,nombre,apellido,dni,telefono,telefono_emergencia,email,password)
            VALUES ($gimnasio_id,'$nombre','$apellido','$dni','$telefono','$telefono_emergencia','$email','$password')";

    if($conn->query($sql)){
        $socio_id = $conn->insert_id;
        $_SESSION['socio_id'] = $socio_id;
        header("Location: index.php");
    }else{
        die("Error al registrar socio: ".$conn->error);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registro Socio</title>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<style>
body { background:#f4f4f4; display:flex; align-items:center; justify-content:center; height:100vh; }
.card { padding:20px; width:450px; }
</style>
</head>
<body>
<div class="card shadow-sm">
<h4 class="mb-3">Registro Socio</h4>
<form method="POST">
<div class="mb-3"><label>Nombre</label><input type="text" name="nombre" class="form-control" required></div>
<div class="mb-3"><label>Apellido</label><input type="text" name="apellido" class="form-control" required></div>
<div class="mb-3"><label>DNI</label><input type="text" name="dni" class="form-control" required></div>
<div class="mb-3"><label>Teléfono</label><input type="text" name="telefono" class="form-control"></div>
<div class="mb-3"><label>Teléfono Emergencia</label><input type="text" name="telefono_emergencia" class="form-control"></div>
<div class="mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label>Contraseña</label><input type="password" name="password" class="form-control" required></div>
<button type="submit" class="btn btn-success w-100">Registrarse</button>
<a href="login.php" class="d-block mt-2 text-center">Ya tengo cuenta</a>
</form>
</div>
</body>
</html>
