<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");
if(!isset($_SESSION['gimnasio_id'])){
    header("Location: /login.php"); exit;
}
$gimnasio_id = $_SESSION['gimnasio_id'];
$socios = $conn->query("SELECT * FROM socios WHERE gimnasio_id=$gimnasio_id")->fetch_all(MYSQLI_ASSOC);
?>
<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="container mt-5 pt-4">
    <h2>Gestión de Socios</h2>
    <a href="procesar_socio.php" class="btn btn-success mb-3">Agregar Nuevo Socio</a>
    <table class="table table-striped table-hover shadow-sm">
        <thead class="table-dark">
            <tr>
                <th>DNI</th>
                <th>Nombre</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($socios as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['dni']) ?></td>
                    <td><?= htmlspecialchars($s['nombre'].' '.$s['apellido']) ?></td>
                    <td><?= htmlspecialchars($s['telefono']) ?></td>
                    <td><?= htmlspecialchars($s['email']) ?></td>
                    <td><?= ucfirst($s['estado']) ?></td>
                    <td>
                        <a href="procesar_socio.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
