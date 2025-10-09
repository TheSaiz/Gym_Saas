<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");
if(!isset($_SESSION['gimnasio_id'])){ header("Location: /login.php"); exit; }

$gimnasio_id = $_SESSION['gimnasio_id'];
$gimnasio = $conn->query("SELECT * FROM gimnasios WHERE id=$gimnasio_id")->fetch_assoc();

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $conn->real_escape_string($_POST['email']);
    $mp_key = $conn->real_escape_string($_POST['mp_key']);
    $mp_token = $conn->real_escape_string($_POST['mp_token']);

    $conn->query("UPDATE gimnasios SET email='$email', mp_key='$mp_key', mp_token='$mp_token' WHERE id=$gimnasio_id");
    $mensaje = "Configuración actualizada correctamente.";
}

include_once(__DIR__ . "/sidebar.php");
?>

<div class="container mt-5 pt-4">
    <h2>Configuración General del Gimnasio</h2>
    <?php if(isset($mensaje)) echo "<div class='alert alert-success'>$mensaje</div>"; ?>
    <form method="POST" class="mt-4">
        <div class="mb-3">
            <label>Email de contacto</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($gimnasio['email']) ?>" required>
        </div>
        <div class="mb-3">
            <label>MercadoPago Public Key</label>
            <input type="text" name="mp_key" class="form-control" value="<?= htmlspecialchars($gimnasio['mp_key']) ?>">
        </div>
        <div class="mb-3">
            <label>MercadoPago Access Token</label>
            <input type="text" name="mp_token" class="form-control" value="<?= htmlspecialchars($gimnasio['mp_token']) ?>">
        </div>
        <button class="btn btn-success">Guardar Configuración</button>
    </form>
</div>
