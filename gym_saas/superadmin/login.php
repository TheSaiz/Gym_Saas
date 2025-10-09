<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $user = $conn->real_escape_string($_POST['usuario']);
    $pass = md5($_POST['password']); // Ajustar hashing según cómo guardaste las contraseñas

    $query = "SELECT * FROM superadmin WHERE usuario='$user' AND password='$pass'";
    $result = $conn->query($query);

    if($result->num_rows === 1){
        $admin = $result->fetch_assoc();
        $_SESSION['superadmin_id'] = $admin['id'];
        $_SESSION['superadmin_usuario'] = $admin['usuario'];
        $_SESSION['superadmin_nombre'] = $admin['nombre'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login SuperAdmin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body {background: #f8f9fa; font-family: 'Poppins', sans-serif;}
.login-card {max-width: 400px; margin: 120px auto; padding: 35px; background: #fff; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);}
.btn-login {background: #0d6efd; color: #fff;}
.btn-login:hover {background: #0b5ed7;}
</style>
</head>
<body>
<div class="login-card">
    <h3 class="text-center mb-4"><i class="fas fa-user-shield me-2 text-primary"></i>SuperAdmin Login</h3>
    <?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="usuario" class="form-label">Usuario</label>
            <input type="text" class="form-control" id="usuario" name="usuario" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button class="btn btn-login w-100"><i class="fas fa-sign-in-alt me-1"></i> Ingresar</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
