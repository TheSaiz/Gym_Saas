<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");
if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /login.php"); exit;
}
$gimnasio_id = $_SESSION['gimnasio_id'];
$staff = $conn->query("SELECT * FROM staff WHERE gimnasio_id=$gimnasio_id")->fetch_all(MYSQLI_ASSOC);
?>
<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="container mt-5 pt-4">
    <h2>Subusuarios / Staff</h2>
    <a href="procesar_staff.php" class="btn btn-success mb-3">Agregar Nuevo</a>
    <table class="table table-striped table-hover shadow-sm">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($staff as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['nombre']) ?></td>
                    <td><?= htmlspecialchars($s['usuario']) ?></td>
                    <td><?= ucfirst($s['rol']) ?></td>
                    <td><?= ucfirst($s['estado']) ?></td>
                    <td>
                        <a href="procesar_staff.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
