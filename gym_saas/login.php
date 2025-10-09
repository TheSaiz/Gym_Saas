<?php
session_start();
include_once(__DIR__ . "/includes/db_connect.php");

// Ruta base del proyecto
$base_url = "/gym_saas";

// Si ya está logueado, redirigir al panel
if(isset($_SESSION['gimnasio_id'])){
    header("Location: $base_url/gimnasios/index.php");
    exit;
}

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $conn->real_escape_string($_POST['email']);
    $password = md5($_POST['password']); // Encriptación simple

    $query = "SELECT * FROM gimnasios WHERE email='$email' AND password='$password' AND estado='activo'";
    $result = $conn->query($query);

    if($result->num_rows === 1){
        $gimnasio = $result->fetch_assoc();
        $_SESSION['gimnasio_id'] = $gimnasio['id'];
        $_SESSION['gimnasio_nombre'] = $gimnasio['nombre'];
        header("Location: $base_url/gimnasios/index.php");
        exit;
    } else {
        $error = "Correo electrónico o contraseña incorrectos, o la cuenta está suspendida.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Gimnasio System SAAS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= $base_url ?>/assets/css/style.css">
<style>
body {background: #f8f9fa; font-family: 'Poppins', sans-serif;}
.login-card {max-width: 450px; margin: 100px auto; padding: 40px; background: #fff; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);}
.login-card h3 {margin-bottom: 30px;}
.btn-login {background: #198754; color: #fff; font-weight: 500;}
.btn-login:hover {background: #157347;}
</style>
</head>
<body>
<div class="login-card">
    <h3 class="text-center"><i class="fas fa-dumbbell me-2 text-success"></i>Login Gimnasio</h3>
    <?php if($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="********" required>
        </div>
        <button type="submit" class="btn btn-login w-100"><i class="fas fa-sign-in-alt me-1"></i> Ingresar</button>
        <p class="mt-3 text-center">¿No tienes cuenta? <a href="<?= $base_url ?>/register.php">Regístrate aquí</a></p>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
