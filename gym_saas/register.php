<?php
session_start();
include_once(__DIR__ . "/includes/db_connect.php");

// Si ya está logueado, redirigir al panel
if(isset($_SESSION['gimnasio_id'])){
    header("Location: /gimnasios/index.php");
    exit;
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $direccion = $conn->real_escape_string($_POST['direccion']);
    $altura = $conn->real_escape_string($_POST['altura']);
    $localidad = $conn->real_escape_string($_POST['localidad']);
    $partido = $conn->real_escape_string($_POST['partido']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = md5($_POST['password']); // Encriptación simple

    // Verificar email duplicado
    $check = $conn->query("SELECT id FROM gimnasios WHERE email='$email'");
    if($check->num_rows > 0){
        $error = "El correo electrónico ya está registrado.";
    } else {
        $fecha_inicio = date('Y-m-d');
        $fecha_fin = date('Y-m-d', strtotime('+30 days')); // licencia inicial ficticia

        $insert = $conn->query("INSERT INTO gimnasios 
        (nombre,direccion,altura,localidad,partido,email,password,fecha_inicio,fecha_fin) VALUES
        ('$nombre','$direccion','$altura','$localidad','$partido','$email','$password','$fecha_inicio','$fecha_fin')");

        if($insert){
            $success = "Registro exitoso. Ahora puedes iniciar sesión.";
        } else {
            $error = "Ocurrió un error al registrar el gimnasio.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro - Gimnasio System SAAS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
<style>
body {background: #f8f9fa; font-family: 'Poppins', sans-serif;}
.register-card {max-width: 600px; margin: 80px auto; padding: 40px; background: #fff; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);}
.register-card h3 {margin-bottom: 30px;}
.btn-register {background: #198754; color: #fff; font-weight: 500;}
.btn-register:hover {background: #157347;}
</style>
</head>
<body>
<div class="register-card">
    <h3 class="text-center"><i class="fas fa-dumbbell me-2 text-success"></i>Registro Gimnasio</h3>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row">
            <div class="mb-3 col-md-6">
                <label for="nombre" class="form-label">Nombre del Gimnasio</label>
                <input type="text" class="form-control" id="nombre" name="nombre" required>
            </div>
            <div class="mb-3 col-md-6">
                <label for="direccion" class="form-label">Dirección</label>
                <input type="text" class="form-control" id="direccion" name="direccion" required>
            </div>
            <div class="mb-3 col-md-4">
                <label for="altura" class="form-label">Altura</label>
                <input type="text" class="form-control" id="altura" name="altura" required>
            </div>
            <div class="mb-3 col-md-4">
                <label for="localidad" class="form-label">Localidad</label>
                <input type="text" class="form-control" id="localidad" name="localidad" required>
            </div>
            <div class="mb-3 col-md-4">
                <label for="partido" class="form-label">Partido</label>
                <input type="text" class="form-control" id="partido" name="partido" required>
            </div>
            <div class="mb-3 col-md-6">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3 col-md-6">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
        </div>
        <button type="submit" class="btn btn-register w-100"><i class="fas fa-user-plus me-1"></i> Registrarse</button>
        <p class="mt-3 text-center">¿Ya tienes cuenta? <a href="/login.php">Inicia sesión aquí</a></p>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
