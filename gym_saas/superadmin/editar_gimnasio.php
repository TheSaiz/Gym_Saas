<?php
session_start();

if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

if(!isset($_SESSION['superadmin_id']) || empty($_SESSION['superadmin_id'])){
    session_destroy();
    header("Location: $base_url/superadmin/login.php");
    exit;
}

$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: $base_url/superadmin/login.php?timeout=1");
    exit;
}

$_SESSION['last_activity'] = time();

// Obtener ID del gimnasio
$gimnasio_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($gimnasio_id <= 0) {
    header("Location: $base_url/superadmin/gimnasios.php?error=invalid_id");
    exit;
}

// Obtener datos del gimnasio
$stmt = $conn->prepare("SELECT g.*, l.nombre as licencia_nombre FROM gimnasios g LEFT JOIN licencias l ON g.licencia_id = l.id WHERE g.id = ?");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$gimnasio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$gimnasio) {
    header("Location: $base_url/superadmin/gimnasios.php?error=not_found");
    exit;
}

// Obtener licencias disponibles
$licencias_query = "SELECT * FROM licencias WHERE estado = 'activo' ORDER BY nombre";
$licencias_result = $conn->query($licencias_query);

// Obtener estadísticas del gimnasio
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM socios WHERE gimnasio_id = ? AND estado = 'activo'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_socios = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM membresias WHERE gimnasio_id = ? AND estado = 'activo'");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$total_membresias = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Editar Gimnasio - SuperAdmin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #764ba2;
            --success: #51cf66;
            --danger: #ff6b6b;
            --warning: #ffd43b;
            --info: #4dabf7;
            --dark: #0a0e27;
            --dark-light: #1a1f3a;
            --sidebar-width: 280px;
            --navbar-height: 70px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            color: #fff;
            overflow-x: hidden;
        }

        .navbar-custom {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--navbar-height);
            background: rgba(10, 14, 39, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 999;
        }

        .navbar-left h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .navbar-right .btn-back {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 12px;
            color: #fff;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .navbar-right .btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--navbar-height);
            padding: 2rem;
            min-height: calc(100vh - var(--navbar-height));
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #51cf66 0%, #37b24d 100%);
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #fa5252 100%);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.3rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .config-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .section-title i {
            color: var(--primary);
        }

        .form-label {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            padding: 0.8rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            color: #fff;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .form-select option {
            background: var(--dark);
            color: #fff;
        }

        .upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            background: rgba(255, 255, 255, 0.03);
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background: rgba(102, 126, 234, 0.1);
        }

        .preview-img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 12px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            padding: 5px;
            margin-top: 1rem;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 0.8rem 2rem;
            font-weight: 600;
            border-radius: 12px;
            color: #fff;
            transition: all 0.3s ease;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: #fff;
        }

        .btn-secondary-custom {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            padding: 0.8rem 2rem;
            font-weight: 600;
            border-radius: 12px;
            color: #fff;
            transition: all 0.3s ease;
        }

        .btn-secondary-custom:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: rgba(81, 207, 102, 0.1);
            border: 1px solid rgba(81, 207, 102, 0.3);
            color: var(--success);
        }

        .alert-danger {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: var(--danger);
        }

        .alert-warning {
            background: rgba(255, 212, 59, 0.1);
            border: 1px solid rgba(255, 212, 59, 0.3);
            color: var(--warning);
        }

        .licencia-info {
            background: rgba(77, 171, 247, 0.1);
            border: 1px solid rgba(77, 171, 247, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .licencia-info.vencida {
            background: rgba(255, 107, 107, 0.1);
            border-color: rgba(255, 107, 107, 0.3);
        }

        .licencia-info.proxima {
            background: rgba(255, 212, 59, 0.1);
            border-color: rgba(255, 212, 59, 0.3);
        }

        .action-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            height: 100%;
        }

        .action-card.warning-card {
            border-color: rgba(255, 212, 59, 0.3);
        }

        .action-card.danger-card {
            border-color: rgba(255, 107, 107, 0.3);
        }

        .badge-custom {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .badge-active {
            background: rgba(81, 207, 102, 0.2);
            color: var(--success);
        }

        .badge-suspended {
            background: rgba(255, 107, 107, 0.2);
            color: var(--danger);
        }

        .text-muted {
            color: rgba(255, 255, 255, 0.5) !important;
        }

        .text-success {
            color: var(--success) !important;
        }

        .text-danger {
            color: var(--danger) !important;
        }

        .text-warning {
            color: var(--warning) !important;
        }

        @media (max-width: 1024px) {
            :root {
                --sidebar-width: 0;
            }

            .navbar-custom {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<?php include_once('sidebar.php'); ?>

<nav class="navbar-custom">
    <div class="navbar-left">
        <h2><i class="fas fa-edit me-2"></i>Editar Gimnasio</h2>
    </div>
    <div class="navbar-right">
        <a href="<?= $base_url ?>/superadmin/gimnasios.php" class="btn-back">
            <i class="fas fa-arrow-left me-2"></i>Volver
        </a>
    </div>
</nav>

<main class="main-content">

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            Gimnasio actualizado correctamente
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php 
            if ($_GET['error'] == 'db_error') echo 'Error al actualizar el gimnasio';
            elseif ($_GET['error'] == 'email_exists') echo 'El email ya está registrado';
            elseif ($_GET['error'] == 'invalid_id') echo 'ID de gimnasio no válido';
            else echo htmlspecialchars($_GET['error']);
            ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo $total_socios; ?></div>
            <div class="stat-label">Socios Activos</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-id-card"></i>
            </div>
            <div class="stat-value"><?php echo $total_membresias; ?></div>
            <div class="stat-label">Membresías Activas</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon <?php echo $gimnasio['estado'] === 'activo' ? 'success' : 'danger'; ?>">
                <i class="fas fa-circle"></i>
            </div>
            <div class="stat-value">
                <span class="badge-custom badge-<?php echo $gimnasio['estado'] === 'activo' ? 'active' : 'suspended'; ?>">
                    <?php echo ucfirst($gimnasio['estado']); ?>
                </span>
            </div>
            <div class="stat-label">Estado Actual</div>
        </div>
    </div>

    <!-- Información de Licencia -->
    <?php if ($gimnasio['licencia_id'] && $gimnasio['fecha_fin']): 
        $hoy = new DateTime();
        $fecha_fin = new DateTime($gimnasio['fecha_fin']);
        $dias_restantes = $hoy->diff($fecha_fin)->days;
        $vencida = $fecha_fin < $hoy;
        $proxima_vencer = !$vencida && $dias_restantes <= 7;
    ?>
        <div class="licencia-info <?php echo $vencida ? 'vencida' : ($proxima_vencer ? 'proxima' : ''); ?>">
            <h6 class="mb-3">
                <i class="fas fa-certificate me-2"></i>
                <strong>Licencia: <?php echo htmlspecialchars($gimnasio['licencia_nombre']); ?></strong>
            </h6>
            <div class="row">
                <div class="col-md-4">
                    <small class="text-muted d-block">Inicio</small>
                    <strong><?php echo date('d/m/Y', strtotime($gimnasio['fecha_inicio'])); ?></strong>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Vencimiento</small>
                    <strong><?php echo date('d/m/Y', strtotime($gimnasio['fecha_fin'])); ?></strong>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Estado</small>
                    <strong class="<?php echo $vencida ? 'text-danger' : ($proxima_vencer ? 'text-warning' : 'text-success'); ?>">
                        <?php 
                        if ($vencida) echo 'VENCIDA';
                        elseif ($proxima_vencer) echo 'Vence en ' . $dias_restantes . ' días';
                        else echo $dias_restantes . ' días restantes';
                        ?>
                    </strong>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Formulario -->
    <form action="procesar_gimnasio.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="editar">
        <input type="hidden" name="id" value="<?php echo $gimnasio_id; ?>">

        <!-- Datos Básicos -->
        <div class="config-section">
            <h5 class="section-title">
                <i class="fas fa-building"></i>
                Datos Básicos
            </h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre del Gimnasio *</label>
                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($gimnasio['nombre']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($gimnasio['email']); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Contraseña (dejar vacío para no cambiar)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••">
                    <small class="text-muted">Solo completa si deseas cambiar la contraseña</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="activo" <?php echo $gimnasio['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="suspendido" <?php echo $gimnasio['estado'] === 'suspendido' ? 'selected' : ''; ?>>Suspendido</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Dirección -->
        <div class="config-section">
            <h5 class="section-title">
                <i class="fas fa-map-marker-alt"></i>
                Dirección
            </h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($gimnasio['direccion']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Altura</label>
                    <input type="text" name="altura" class="form-control" value="<?php echo htmlspecialchars($gimnasio['altura']); ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Localidad</label>
                    <input type="text" name="localidad" class="form-control" value="<?php echo htmlspecialchars($gimnasio['localidad']); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Partido/Departamento</label>
                    <input type="text" name="partido" class="form-control" value="<?php echo htmlspecialchars($gimnasio['partido']); ?>">
                </div>
            </div>
        </div>

        <!-- Licencia -->
        <div class="config-section">
            <h5 class="section-title">
                <i class="fas fa-certificate"></i>
                Licencia
            </h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Plan de Licencia</label>
                    <select name="licencia_id" class="form-select">
                        <option value="">Sin licencia</option>
                        <?php while ($lic = $licencias_result->fetch_assoc()): ?>
                            <option value="<?php echo $lic['id']; ?>" <?php echo $gimnasio['licencia_id'] == $lic['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lic['nombre']); ?> - $<?php echo number_format($lic['precio'], 2); ?> (<?php echo $lic['dias']; ?> días)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Fecha de Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $gimnasio['fecha_inicio']; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Fecha de Fin</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?php echo $gimnasio['fecha_fin']; ?>">
                </div>
            </div>
        </div>

        <!-- Imágenes -->
        <div class="config-section">
            <h5 class="section-title">
                <i class="fas fa-image"></i>
                Imágenes
            </h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Logo del Gimnasio</label>
                    <div class="upload-area">
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <small class="text-muted d-block mt-2">Formatos: JPG, PNG, GIF. Máx: 2MB</small>
                    </div>
                    <?php if (!empty($gimnasio['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($gimnasio['logo']); ?>" alt="Logo" class="preview-img">
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Favicon del Gimnasio</label>
                    <div class="upload-area">
                        <input type="file" name="favicon" class="form-control" accept="image/*">
                        <small class="text-muted d-block mt-2">Formatos: JPG, PNG, ICO. Máx: 1MB</small>
                    </div>
                    <?php if (!empty($gimnasio['favicon'])): ?>
                        <img src="<?php echo htmlspecialchars($gimnasio['favicon']); ?>" alt="Favicon" class="preview-img">
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Botones -->
        <div class="config-section">
            <div class="d-flex justify-content-between">
                <a href="<?= $base_url ?>/superadmin/gimnasios.php" class="btn btn-secondary-custom">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save me-2"></i>Guardar Cambios
                </button>
            </div>
        </div>
    </form>

    <!-- Acciones Adicionales -->
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="action-card warning-card">
                <h6><i class="fas fa-sync-alt me-2"></i>Cambiar Estado</h6>
                <p class="text-muted mb-3">Estado actual: <span class="badge-custom badge-<?php echo $gimnasio['estado'] === 'activo' ? 'active' : 'suspended'; ?>"><?php echo ucfirst($gimnasio['estado']); ?></span></p>
                <form action="procesar_gimnasio.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="toggle_estado">
                    <input type="hidden" name="id" value="<?php echo $gimnasio_id; ?>">
                    <button type="submit" class="btn btn-save" onclick="return confirm('¿Cambiar el estado del gimnasio?')">
                        <i class="fas fa-sync-alt me-2"></i><?php echo $gimnasio['estado'] === 'activo' ? 'Suspender' : 'Activar'; ?>
                    </button>
                </form>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="action-card danger-card">
                <h6><i class="fas fa-trash me-2"></i>Eliminar Gimnasio</h6>
                <p class="text-muted mb-3">Esta acción eliminará todos los datos relacionados</p>
                <form action="procesar_gimnasio.php" method="POST" onsubmit="return confirm('¿ESTÁS SEGURO? Esta acción NO se puede deshacer.')">
                    <input type="hidden" name="action" value="eliminar">
                    <input type="hidden" name="id" value="<?php echo $gimnasio_id; ?>">
                    <button type="submit" class="btn btn-save" style="background: linear-gradient(135deg, #ff6b6b 0%, #fa5252 100%);">
                        <i class="fas fa-trash me-2"></i>Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Validación de fechas
    const fechaInicio = document.querySelector('input[name="fecha_inicio"]');
    const fechaFin = document.querySelector('input[name="fecha_fin"]');

    if (fechaInicio && fechaFin) {
        fechaFin.addEventListener('change', function() {
            if (fechaInicio.value && fechaFin.value) {
                if (new Date(fechaFin.value) < new Date(fechaInicio.value)) {
                    alert('La fecha de fin no puede ser anterior a la fecha de inicio');
                    fechaFin.value = '';
                }
            }
        });
    }
</script>

</body>
</html>
