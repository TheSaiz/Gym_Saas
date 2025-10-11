<?php
session_start();

// Seguridad: Regenerar ID de sesión
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificar sesión de gimnasio
if(!isset($_SESSION['gimnasio_id']) || empty($_SESSION['gimnasio_id'])){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Sanitizar ID de gimnasio
$gimnasio_id = filter_var($_SESSION['gimnasio_id'], FILTER_VALIDATE_INT);
if($gimnasio_id === false || $gimnasio_id <= 0){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

$gimnasio_nombre = isset($_SESSION['gimnasio_nombre']) ? htmlspecialchars($_SESSION['gimnasio_nombre'], ENT_QUOTES, 'UTF-8') : 'Gimnasio';

$resultado = null;
$error = '';

// Procesar validación
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $dni = $conn->real_escape_string(trim($_POST['dni']));
    
    if(empty($dni)){
        $error = "Por favor ingresa un DNI.";
    } else {
        // Buscar socio con prepared statement
        $stmt = $conn->prepare("
            SELECT 
                s.id,
                s.nombre,
                s.apellido,
                s.dni,
                s.telefono,
                s.email,
                s.estado as socio_estado,
                ls.id as licencia_id,
                ls.estado as licencia_estado,
                ls.fecha_inicio,
                ls.fecha_fin,
                m.nombre as membresia_nombre,
                DATEDIFF(ls.fecha_fin, CURDATE()) as dias_restantes
            FROM socios s
            LEFT JOIN licencias_socios ls ON s.id = ls.socio_id AND ls.gimnasio_id = ? AND ls.estado = 'activa'
            LEFT JOIN membresias m ON ls.membresia_id = m.id
            WHERE s.dni = ? AND s.gimnasio_id = ?
            ORDER BY ls.fecha_fin DESC
            LIMIT 1
        ");
        $stmt->bind_param("isi", $gimnasio_id, $dni, $gimnasio_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0){
            $resultado = $result->fetch_assoc();
        } else {
            $error = "No se encontró ningún socio con el DNI ingresado.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="noindex, nofollow">
<title>Validador de Acceso - <?= $gimnasio_nombre ?></title>

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
        --secondary: #764ba2;
        --success: #51cf66;
        --danger: #ff6b6b;
        --warning: #ffd43b;
        --info: #4dabf7;
        --dark: #0a0e27;
        --sidebar-width: 280px;
        --navbar-height: 70px;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
        color: #fff;
    }

    .content-wrapper {
        margin-left: var(--sidebar-width);
        margin-top: var(--navbar-height);
        padding: 2rem;
        min-height: calc(100vh - var(--navbar-height));
    }

    .page-header {
        margin-bottom: 2rem;
    }

    .page-title {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .page-subtitle {
        color: rgba(255, 255, 255, 0.6);
        font-size: 1rem;
    }

    /* Validator Card */
    .validator-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 3rem;
        max-width: 600px;
        margin: 0 auto 2rem;
    }

    .search-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 2rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .search-form {
        position: relative;
    }

    .dni-input {
        width: 100%;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: 15px;
        padding: 1.5rem 1.5rem 1.5rem 4rem;
        color: #fff;
        font-size: 1.5rem;
        font-weight: 600;
        text-align: center;
        transition: all 0.3s ease;
        letter-spacing: 2px;
    }

    .dni-input:focus {
        outline: none;
        border-color: var(--primary);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 6px rgba(102, 126, 234, 0.1);
    }

    .dni-input::placeholder {
        color: rgba(255, 255, 255, 0.4);
        letter-spacing: normal;
    }

    .input-icon {
        position: absolute;
        left: 1.5rem;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.4);
        font-size: 1.5rem;
    }

    .btn-validate {
        width: 100%;
        padding: 1.2rem;
        margin-top: 1.5rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 15px;
        color: #fff;
        font-size: 1.2rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-validate:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
    }

    .btn-validate:active {
        transform: translateY(-1px);
    }

    /* Result Card */
    .result-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2.5rem;
        max-width: 700px;
        margin: 0 auto;
        animation: slideUp 0.5s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .result-card.success {
        border: 2px solid var(--success);
        background: rgba(81, 207, 102, 0.08);
    }

    .result-card.warning {
        border: 2px solid var(--warning);
        background: rgba(255, 212, 59, 0.08);
    }

    .result-card.danger {
        border: 2px solid var(--danger);
        background: rgba(255, 107, 107, 0.08);
    }

    .result-header {
        text-align: center;
        margin-bottom: 2rem;
    }

    .status-icon {
        width: 100px;
        height: 100px;
        margin: 0 auto 1.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        animation: scaleIn 0.5s ease;
    }

    @keyframes scaleIn {
        from {
            transform: scale(0);
        }
        to {
            transform: scale(1);
        }
    }

    .status-icon.success {
        background: linear-gradient(135deg, var(--success) 0%, #37b24d 100%);
        box-shadow: 0 15px 40px rgba(81, 207, 102, 0.4);
    }

    .status-icon.warning {
        background: linear-gradient(135deg, var(--warning) 0%, #fab005 100%);
        box-shadow: 0 15px 40px rgba(255, 212, 59, 0.4);
    }

    .status-icon.danger {
        background: linear-gradient(135deg, var(--danger) 0%, #fa5252 100%);
        box-shadow: 0 15px 40px rgba(255, 107, 107, 0.4);
    }

    .status-title {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .status-message {
        font-size: 1.1rem;
        color: rgba(255, 255, 255, 0.7);
    }

    .socio-info {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        padding: 2rem;
        margin-top: 2rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 600;
        color: rgba(255, 255, 255, 0.7);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-value {
        font-weight: 700;
        font-size: 1.1rem;
    }

    .badge-status {
        padding: 0.5rem 1.2rem;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 700;
        display: inline-block;
    }

    .badge-active {
        background: rgba(81, 207, 102, 0.2);
        color: var(--success);
        border: 2px solid var(--success);
    }

    .badge-expired {
        background: rgba(255, 107, 107, 0.2);
        color: var(--danger);
        border: 2px solid var(--danger);
    }

    .badge-inactive {
        background: rgba(255, 212, 59, 0.2);
        color: var(--warning);
        border: 2px solid var(--warning);
    }

    .days-remaining {
        text-align: center;
        padding: 1.5rem;
        background: rgba(102, 126, 234, 0.1);
        border-radius: 12px;
        margin: 1.5rem 0;
    }

    .days-number {
        font-size: 3rem;
        font-weight: 800;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        line-height: 1;
    }

    .days-label {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.6);
        margin-top: 0.5rem;
    }

    .btn-new-search {
        width: 100%;
        padding: 1rem;
        margin-top: 2rem;
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-new-search:hover {
        background: rgba(255, 255, 255, 0.12);
        transform: translateY(-2px);
    }

    /* Error Card */
    .error-card {
        background: rgba(255, 107, 107, 0.15);
        border: 2px solid rgba(255, 107, 107, 0.3);
        border-radius: 15px;
        padding: 1.5rem;
        max-width: 600px;
        margin: 2rem auto;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: shake 0.5s ease;
    }

    .error-card i {
        font-size: 2rem;
        color: var(--danger);
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }

    /* Quick Actions */
    .quick-actions {
        max-width: 600px;
        margin: 2rem auto 0;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }

    .quick-action {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1rem;
        text-align: center;
        text-decoration: none;
        color: #fff;
        transition: all 0.3s ease;
    }

    .quick-action:hover {
        background: rgba(102, 126, 234, 0.1);
        border-color: var(--primary);
        transform: translateY(-3px);
        color: #fff;
    }

    .quick-action i {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
        color: var(--primary);
    }

    @media (max-width: 1024px) {
        .content-wrapper {
            margin-left: 0;
        }
    }

    @media (max-width: 768px) {
        .validator-card,
        .result-card {
            padding: 2rem 1.5rem;
        }

        .dni-input {
            font-size: 1.2rem;
            padding: 1.2rem 1rem 1.2rem 3.5rem;
        }

        .status-icon {
            width: 80px;
            height: 80px;
            font-size: 2.5rem;
        }

        .status-title {
            font-size: 1.5rem;
        }
    }
</style>
</head>
<body>

<?php include_once(__DIR__ . "/sidebar.php"); ?>

<div class="content-wrapper">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-qrcode me-2"></i>Validador de Acceso
        </h1>
        <p class="page-subtitle">Verifica el estado de las licencias de tus socios</p>
    </div>

    <!-- Validator Card -->
    <?php if(!$resultado && !$error): ?>
        <div class="validator-card">
            <div class="search-icon">
                <i class="fas fa-search"></i>
            </div>
            
            <form method="POST" class="search-form" id="validatorForm">
                <i class="fas fa-id-card input-icon"></i>
                <input 
                    type="text" 
                    name="dni" 
                    class="dni-input" 
                    placeholder="Ingresa DNI"
                    autofocus
                    required
                    pattern="[0-9]{7,8}"
                    title="Ingresa un DNI válido (7-8 dígitos)"
                    maxlength="8"
                >
                <button type="submit" class="btn-validate">
                    <i class="fas fa-check-circle me-2"></i>Validar Acceso
                </button>
            </form>

            <div class="quick-actions">
                <a href="<?= $base_url ?>/gimnasios/socios.php" class="quick-action">
                    <i class="fas fa-users"></i>
                    <div>Ver Socios</div>
                </a>
                <a href="<?= $base_url ?>/gimnasios/membresias.php" class="quick-action">
                    <i class="fas fa-id-card-alt"></i>
                    <div>Membresías</div>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Error Card -->
    <?php if($error): ?>
        <div class="error-card">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Socio no encontrado</strong>
                <p style="margin: 0.5rem 0 0 0;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div style="max-width: 600px; margin: 0 auto;">
            <button onclick="window.location.reload()" class="btn-new-search">
                <i class="fas fa-search me-2"></i>Realizar nueva búsqueda
            </button>
        </div>
    <?php endif; ?>

    <!-- Result Card -->
    <?php if($resultado): ?>
        <?php
        $tiene_licencia = !empty($resultado['licencia_id']);
        $licencia_activa = $tiene_licencia && $resultado['licencia_estado'] === 'activa';
        $socio_activo = $resultado['socio_estado'] === 'activo';
        $dias_restantes = (int)($resultado['dias_restantes'] ?? 0);
        
        // Determinar estado general
        if($licencia_activa && $socio_activo && $dias_restantes > 0) {
            $estado = 'success';
            $icono = 'fa-check-circle';
            $titulo = '¡ACCESO PERMITIDO!';
            $mensaje = 'El socio tiene una licencia activa y vigente';
        } elseif($tiene_licencia && $dias_restantes <= 0) {
            $estado = 'danger';
            $icono = 'fa-times-circle';
            $titulo = 'ACCESO DENEGADO';
            $mensaje = 'La licencia del socio ha vencido';
        } elseif(!$socio_activo) {
            $estado = 'warning';
            $icono = 'fa-exclamation-circle';
            $titulo = 'SOCIO INACTIVO';
            $mensaje = 'El socio está deshabilitado en el sistema';
        } else {
            $estado = 'warning';
            $icono = 'fa-exclamation-circle';
            $titulo = 'SIN LICENCIA';
            $mensaje = 'El socio no tiene ninguna licencia activa';
        }
        ?>

        <div class="result-card <?= $estado ?>">
            <div class="result-header">
                <div class="status-icon <?= $estado ?>">
                    <i class="fas <?= $icono ?>"></i>
                </div>
                <h2 class="status-title"><?= $titulo ?></h2>
                <p class="status-message"><?= $mensaje ?></p>
            </div>

            <?php if($tiene_licencia && $dias_restantes > 0): ?>
                <div class="days-remaining">
                    <div class="days-number"><?= $dias_restantes ?></div>
                    <div class="days-label">días restantes</div>
                </div>
            <?php endif; ?>

            <div class="socio-info">
                <div class="info-row">
                    <span class="info-label">
                        <i class="fas fa-user"></i>
                        Nombre Completo
                    </span>
                    <span class="info-value">
                        <?= htmlspecialchars($resultado['nombre'] . ' ' . $resultado['apellido'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label">
                        <i class="fas fa-id-card"></i>
                        DNI
                    </span>
                    <span class="info-value">
                        <?= htmlspecialchars($resultado['dni'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <?php if(!empty($resultado['telefono'])): ?>
                    <div class="info-row">
                        <span class="info-label">
                            <i class="fas fa-phone"></i>
                            Teléfono
                        </span>
                        <span class="info-value">
                            <?= htmlspecialchars($resultado['telefono'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                <?php endif; ?>

                <div class="info-row">
                    <span class="info-label">
                        <i class="fas fa-user-check"></i>
                        Estado Socio
                    </span>
                    <span class="badge-status <?= $socio_activo ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $socio_activo ? 'ACTIVO' : 'INACTIVO' ?>
                    </span>
                </div>

                <?php if($tiene_licencia): ?>
                    <div class="info-row">
                        <span class="info-label">
                            <i class="fas fa-id-card-alt"></i>
                            Membresía
                        </span>
                        <span class="info-value">
                            <?= htmlspecialchars($resultado['membresia_nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">
                            <i class="fas fa-calendar-alt"></i>
                            Vigencia
                        </span>
                        <span class="info-value">
                            <?= date('d/m/Y', strtotime($resultado['fecha_inicio'])) ?> - 
                            <?= date('d/m/Y', strtotime($resultado['fecha_fin'])) ?>
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="info-label">
                            <i class="fas fa-check-circle"></i>
                            Estado Licencia
                        </span>
                        <span class="badge-status <?= $licencia_activa && $dias_restantes > 0 ? 'badge-active' : 'badge-expired' ?>">
                            <?= $licencia_activa && $dias_restantes > 0 ? 'VIGENTE' : 'VENCIDA' ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <button onclick="window.location.reload()" class="btn-new-search">
                <i class="fas fa-redo me-2"></i>Validar otro socio
            </button>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Solo números en el input DNI
    const dniInput = document.querySelector('.dni-input');
    if(dniInput) {
        dniInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        dniInput.addEventListener('keypress', function(e) {
            if(e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('validatorForm').submit();
            }
        });
    }

    // Sonido de éxito/error (opcional)
    <?php if($resultado): ?>
        const estado = '<?= $estado ?>';
        if(estado === 'success') {
            console.log('✅ Acceso permitido');
        } else {
            console.log('❌ Acceso denegado');
        }
    <?php endif; ?>

    // Session timeout
    let sessionTimeout;
    const TIMEOUT_DURATION = 1800000;

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(function() {
            alert('Tu sesión ha expirado por inactividad.');
            window.location.href = '<?= $base_url ?>/gimnasios/logout.php';
        }, TIMEOUT_DURATION);
    }

    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    resetSessionTimeout();
</script>

</body>
</html>
