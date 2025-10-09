<?php
session_start();
include_once(__DIR__."/db_connect.php");

if($_SERVER['REQUEST_METHOD']=='POST'){
    $email = $_POST['email'] ?? '';
    $password = md5($_POST['password'] ?? '');

    $sql = "SELECT * FROM socios WHERE email='$email' AND password='$password' AND estado='activo'";
    $res = $conn->query($sql);
    if($res->num_rows > 0){
        $socio = $res->fetch_assoc();
        $_SESSION['socio_id'] = $socio['id'];
        header("Location: index.php");
        exit;
    }else{
        $error = "Email o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login Socio</title>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<style>
body { background:#f4f4f4; display:flex; align-items:center; justify-content:center; height:100vh; }
.card { padding:20px; width:400px; }
</style>
</head>
<body>
<div class="card shadow-sm">
<h4 class="mb-3">Ingreso Socio</h4>
<?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
<form method="POST">
<div class="mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control" required>
</div>
<div class="mb-3">
<label>Contraseña</label>
<input type="password" name="password" class="form-control" required>
</div>
<button type="submit" class="btn btn-primary w-100">Ingresar</button>
</form>
<a href="register.php" class="d-block mt-2 text-center">Registrarse</a>
</div>
</body>
</html>
