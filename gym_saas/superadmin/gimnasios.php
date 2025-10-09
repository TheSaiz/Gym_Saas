<?php
include_once(__DIR__ . "/../includes/auth_check.php");
include_once(__DIR__ . "/../includes/db_connect.php");
checkSuperAdminLogin();
include_once("sidebar.php");

// Función para calcular días restantes
function diasRestantes($fechaFin){
    $hoy = new DateTime();
    $fin = new DateTime($fechaFin);
    $diff = $hoy->diff($fin);
    $dias = (int)$diff->format("%r%a");
    return $dias > 0 ? $dias : 0;
}

// Obtener gimnasios con información de licencias
$gimnasios = $conn->query("
    SELECT g.*, l.nombre AS licencia_nombre, l.dias AS licencia_dias
    FROM gimnasios g
    LEFT JOIN licencias l ON g.licencia_id = l.id
    ORDER BY g.id DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-fluid p-4">

    <h2 class="mb-4"><i class="fas fa-dumbbell me-2 text-primary"></i>Gimnasios Registrados</h2>
    <a href="editar_gimnasio.php?accion=nuevo" class="btn btn-success mb-3"><i class="fas fa-plus me-1"></i> Nuevo Gimnasio</a>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Licencia</th>
                    <th>Vence</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($gimnasios as $gym): ?>
                <tr>
                    <td><?= $gym['id'] ?></td>
                    <td><?= $gym['nombre'] ?></td>
                    <td><?= $gym['email'] ?></td>
                    <td><?= $gym['licencia_nombre'] ?? 'Sin licencia' ?></td>
                    <td>
                        <?php if($gym['fecha_fin']): ?>
                            <?= diasRestantes($gym['fecha_fin']) ?> días
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($gym['estado']=='activo'): ?>
                            <span class="badge bg-success"><?= ucfirst($gym['estado']) ?></span>
                        <?php else: ?>
                            <span class="badge bg-danger"><?= ucfirst($gym['estado']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="editar_gimnasio.php?id=<?= $gym['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                        <a href="procesar_gimnasio.php?accion=eliminar&id=<?= $gym['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Eliminar gimnasio?')"><i class="fas fa-trash-alt"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Bootstrap & FontAwesome -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
