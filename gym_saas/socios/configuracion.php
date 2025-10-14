<?php
session_start();

// Regenerar ID de sesión
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificar sesión de socio
if(!isset($_SESSION['socio_id']) || empty($_SESSION['socio_id'])){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

// Sanitizar ID de socio
$socio_id = filter_var($_SESSION['socio_id'], FILTER_VALIDATE_INT);
if($socio_id === false){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

// Obtener datos del socio
$stmt = $conn->prepare("SELECT s.*, g.nombre as gimnasio_nombre 
                        FROM socios s 
                        JOIN gimnasios g ON s.gimnasio_id = g.id 
                        WHERE s.id = ? AND s.estado = 'activo' 
                        LIMIT 1");
$stmt->bind_param("i", $socio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    session_destroy();
    header("Location: $base_url/socios/login.php");
    exit;
}

$socio = $result->fetch_assoc();
$stmt->close();

// Mensajes de éxito/error
$success_message = '';
$error_message = '';

if(isset($_GET['success']) && $_GET['success'] == '1'){
    $success_message = '¡Tus datos han sido actualizados correctamente!';
}

if(isset($_GET['error'])){
    $error_messages = [
        '1' => 'El email ingresado ya está en uso por otro socio.',
        '2' => 'Hubo un error al actualizar los datos. Intenta nuevamente.',
        '3' => 'La contraseña actual es incorrecta.',
        '4' => 'Las contraseñas nuevas no coinciden.',
        '5' => 'Por favor completa todos los campos requeridos.'
    ];
    $error_message = $error_messages[$_GET['error']] ?? 'Error desconocido.';
}

// Generar CSRF token
if(!isset($_SESSION['csrf_token_config'])){
    $_SESSION['csrf_token_config'] = bin2hex(random_bytes(32));
}

$socio_nombre_completo = htmlspecialchars($socio['nombre'] . ' ' . $socio['apellido'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Configuración de perfil - Socio">
<meta name="robots" content="noindex, nofollow">
<title>Configuración - <?= $socio_nombre_completo ?></title>

<!-- CSS Libraries -->
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

    /* Navbar */
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

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
    }

    .user-info {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-size: 0.9rem;
        font-weight: 600;
    }

    .user-role {
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.5);
    }

    /* Main Content */
    .main-content {
        margin-left: var(--sidebar-width);
        margin-top: var(--navbar-height);
        padding: 2rem;
        min-height: calc(100vh - var(--navbar-height));
    }

    /* Form Card */
    .form-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2rem;
        max-width: 800px;
        margin: 0 auto;
    }

    .section-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .section-title i {
        color: var(--primary);
    }

    /* Alert Messages */
    .alert-custom {
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
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

    .alert-icon {
        font-size: 1.5rem;
    }

    /* Form Groups */
    .form-section {
        margin-bottom: 2rem;
    }

    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        color: rgba(255, 255, 255, 0.9);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-label {
        font-weight: 500;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .required {
        color: var(--danger);
    }

    .form-control-custom {
        width: 100%;
        padding: 0.9rem 1rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: #fff;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control-custom:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.08);
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-control-custom:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: rgba(255, 255, 255, 0.02);
    }

    .form-control-custom::placeholder {
        color: rgba(255, 255, 255, 0.3);
    }

    .form-help {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 0.3rem;
    }

    .input-icon-wrapper {
        position: relative;
    }

    .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.4);
    }

    .input-icon-wrapper .form-control-custom {
        padding-left: 2.8rem;
    }

    .password-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.4);
        cursor: pointer;
        font-size: 1rem;
        transition: color 0.3s ease;
    }

    .password-toggle:hover {
        color: rgba(255, 255, 255, 0.8);
    }

    /* Buttons */
    .btn-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .btn-primary-custom {
        flex: 1;
        padding: 1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-primary-custom:active {
        transform: translateY(0);
    }

    .btn-primary-custom:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-secondary-custom {
        padding: 1rem 2rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .btn-secondary-custom:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }

    /* Info Box */
    .info-box {
        background: rgba(77, 171, 247, 0.1);
        border: 1px solid rgba(77, 171, 247, 0.3);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 2rem;
        display: flex;
        gap: 1rem;
    }

    .info-box i {
        color: var(--info);
        font-size: 1.2rem;
    }

    .info-text {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.8);
    }

    /* Mobile Menu */
    .mobile-menu-btn {
        display: none;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        color: #fff;
        font-size: 1.2rem;
        cursor: pointer;
    }

    /* Responsive */
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

        .mobile-menu-btn {
            display: block !important;
        }
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }

        .btn-actions {
            flex-direction: column;
        }

        .user-info {
            display: none;
        }

        .form-card {
            padding: 1.5rem;
        }
    }
</style>
</head>
<body>

<?php include_once('sidebar.php'); ?>

<!-- Navbar -->
<nav class="navbar-custom">
    <div class="navbar-left">
        <button class="mobile-menu-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h2><i class="fas fa-cog me-2"></i>Configuración</h2>
    </div>
    <div class="navbar-right">
        <div class="user-menu">
            <div class="user-avatar">
                <?= strtoupper(substr($socio['nombre'], 0, 1) . substr($socio['apellido'], 0, 1)) ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?= $socio_nombre_completo ?></div>
                <div class="user-role">Socio</div>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<main class="main-content">
    
    <div class="form-card">
        <h3 class="section-title">
            <i class="fas fa-user-edit"></i>
            Editar Información Personal
        </h3>

        <!-- Success/Error Messages -->
        <?php if(!empty($success_message)): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle alert-icon"></i>
                <div>
                    <strong>¡Éxito!</strong>
                    <div><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if(!empty($error_message)): ?>
            <div class="alert-custom alert-danger">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <div>
                    <strong>Error</strong>
                    <div><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div class="info-text">
                <strong>Información importante:</strong> El DNI no puede ser modificado. Si necesitas cambiar este dato, contacta con tu gimnasio.
            </div>
        </div>

        <!-- Form -->
        <form action="<?= $base_url ?>/socios/procesar_config.php" method="POST" id="configForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_config'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="socio_id" value="<?= $socio_id ?>">

            <!-- Datos Personales -->
            <div class="form-section">
                <h4 class="form-section-title">
                    <i class="fas fa-user"></i>
                    Datos Personales
                </h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="nombre">
                            Nombre <span class="required">*</span>
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-user"></i>
                            <input 
                                type="text" 
                                id="nombre" 
                                name="nombre" 
                                class="form-control-custom" 
                                value="<?= htmlspecialchars($socio['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                required
                                maxlength="100"
                                pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+"
                                title="Solo letras y espacios"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="apellido">
                            Apellido <span class="required">*</span>
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-user"></i>
                            <input 
                                type="text" 
                                id="apellido" 
                                name="apellido" 
                                class="form-control-custom" 
                                value="<?= htmlspecialchars($socio['apellido'], ENT_QUOTES, 'UTF-8') ?>"
                                required
                                maxlength="100"
                                pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+"
                                title="Solo letras y espacios"
                            >
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="dni">
                            DNI
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-id-card"></i>
                            <input 
                                type="text" 
                                id="dni" 
                                class="form-control-custom" 
                                value="<?= htmlspecialchars($socio['dni'], ENT_QUOTES, 'UTF-8') ?>"
                                disabled
                            >
                        </div>
                        <small class="form-help">
                            <i class="fas fa-lock"></i> El DNI no puede ser modificado
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">
                            Email <span class="required">*</span>
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-envelope"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="form-control-custom" 
                                value="<?= htmlspecialchars($socio['email'], ENT_QUOTES, 'UTF-8') ?>"
                                required
                                maxlength="255"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos de Contacto -->
            <div class="form-section">
                <h4 class="form-section-title">
                    <i class="fas fa-phone"></i>
                    Datos de Contacto
                </h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="telefono">
                            Teléfono
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-phone"></i>
                            <input 
                                type="tel" 
                                id="telefono" 
                                name="telefono" 
                                class="form-control-custom" 
                                value="<?= htmlspecialchars($socio['telefono'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Ej: 1122334455"
                                maxlength="30"
                                pattern="[0-9+\-\s()]+"
                                title="Solo números, +, -, espacios y paréntesis"
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="telefono_emergencia">
                            Teléfono de Emergencia
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-phone-alt"></i>
                            <input 
                                type="tel" 
                                id="telefono_emergencia" 
                                name="telefono_emergencia" 
                                class="form-control-custom" 
                                value="<?= htmlspecialchars($socio['telefono_emergencia'], ENT_QUOTES, 'UTF-8') ?>"
                                placeholder="Contacto de emergencia"
                                maxlength="30"
                                pattern="[0-9+\-\s()]+"
                                title="Solo números, +, -, espacios y paréntesis"
                            >
                        </div>
                        <small class="form-help">
                            <i class="fas fa-info-circle"></i> Contacto en caso de emergencia
                        </small>
                    </div>
                </div>
            </div>

            <!-- Cambiar Contraseña -->
            <div class="form-section">
                <h4 class="form-section-title">
                    <i class="fas fa-lock"></i>
                    Cambiar Contraseña
                </h4>

                <div class="info-box">
                    <i class="fas fa-shield-alt"></i>
                    <div class="info-text">
                        Deja estos campos vacíos si no deseas cambiar tu contraseña actual.
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password_actual">
                            Contraseña Actual
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-key"></i>
                            <input 
                                type="password" 
                                id="password_actual" 
                                name="password_actual" 
                                class="form-control-custom" 
                                placeholder="••••••••"
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('password_actual')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password_nueva">
                            Nueva Contraseña
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="password_nueva" 
                                name="password_nueva" 
                                class="form-control-custom" 
                                placeholder="••••••••"
                                minlength="6"
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('password_nueva')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-help">
                            <i class="fas fa-info-circle"></i> Mínimo 6 caracteres
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password_confirmar">
                            Confirmar Nueva Contraseña
                        </label>
                        <div class="input-icon-wrapper">
                            <i class="input-icon fas fa-lock"></i>
                            <input 
                                type="password" 
                                id="password_confirmar" 
                                name="password_confirmar" 
                                class="form-control-custom" 
                                placeholder="••••••••"
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle" onclick="togglePassword('password_confirmar')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="btn-actions">
                <a href="<?= $base_url ?>/socios/index.php" class="btn-secondary-custom">
                    <i class="fas fa-times"></i>
                    Cancelar
                </a>
                <button type="submit" class="btn-primary-custom" id="submitBtn">
                    <i class="fas fa-save"></i>
                    <span id="btnText">Guardar Cambios</span>
                    <span id="btnLoading" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Guardando...
                    </span>
                </button>
            </div>
        </form>
    </div>

</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle Sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('active');
    }

    // Toggle Password Visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const button = field.nextElementSibling;
        const icon = button.querySelector('i');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Form Validation
    document.getElementById('configForm').addEventListener('submit', function(e) {
        const passwordActual = document.getElementById('password_actual').value;
        const passwordNueva = document.getElementById('password_nueva').value;
        const passwordConfirmar = document.getElementById('password_confirmar').value;

        // Si se intenta cambiar contraseña
        if (passwordActual || passwordNueva || passwordConfirmar) {
            if (!passwordActual) {
                e.preventDefault();
                alert('⚠️ Debes ingresar tu contraseña actual para cambiarla');
                document.getElementById('password_actual').focus();
                return false;
            }

            if (!passwordNueva) {
                e.preventDefault();
                alert('⚠️ Debes ingresar la nueva contraseña');
                document.getElementById('password_nueva').focus();
                return false;
            }

            if (passwordNueva.length < 6) {
                e.preventDefault();
                alert('⚠️ La nueva contraseña debe tener al menos 6 caracteres');
                document.getElementById('password_nueva').focus();
                return false;
            }

            if (passwordNueva !== passwordConfirmar) {
                e.preventDefault();
                alert('⚠️ Las contraseñas nuevas no coinciden');
                document.getElementById('password_confirmar').focus();
                return false;
            }
        }

        // Loading state
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoading = document.getElementById('btnLoading');
        
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
    });

    // Email validation
    document.getElementById('email').addEventListener('blur', function() {
        const email = this.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.style.borderColor = 'var(--danger)';
            alert('⚠️ Por favor ingresa un email válido');
        } else {
            this.style.borderColor = 'rgba(255, 255, 255, 0.1)';
        }
    });

    // Prevenir múltiples submissions
    let formSubmitted = false;
    document.getElementById('configForm').addEventListener('submit', function(e) {
        if (formSubmitted) {
            e.preventDefault();
            return false;
        }
        formSubmitted = true;
    });

    // Cerrar sidebar al hacer click fuera
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.mobile-menu-btn');
        
        if (window.innerWidth <= 1024) {
            if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });

    // Auto-dismiss alerts después de 5 segundos
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-custom');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);

    // Confirmación antes de salir si hay cambios sin guardar
    let formChanged = false;
    const formInputs = document.querySelectorAll('#configForm input:not([type="hidden"])');
    
    formInputs.forEach(input => {
        input.addEventListener('change', function() {
            formChanged = true;
        });
    });

    window.addEventListener('beforeunload', function(e) {
        if (formChanged && !formSubmitted) {
            e.preventDefault();
            e.returnValue = '';
            return '';
        }
    });

    // Marcar formulario como no cambiado al enviar
    document.getElementById('configForm').addEventListener('submit', function() {
        formChanged = false;
    });

    // Protección contra clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Timeout de sesión (30 minutos)
    let sessionTimeout;
    const TIMEOUT_DURATION = 1800000;

    function resetSessionTimeout() {
        clearTimeout(sessionTimeout);
        sessionTimeout = setTimeout(function() {
            alert('Tu sesión ha expirado por inactividad.');
            window.location.href = '<?= $base_url ?>/socios/logout.php';
        }, TIMEOUT_DURATION);
    }

    ['mousedown', 'keydown', 'scroll', 'touchstart'].forEach(function(event) {
        document.addEventListener(event, resetSessionTimeout, true);
    });

    resetSessionTimeout();

    // Console warning
    console.log('%c⚠️ ADVERTENCIA DE SEGURIDAD', 'color: red; font-size: 24px; font-weight: bold;');
    console.log('%cNo pegues código desconocido aquí.', 'font-size: 14px;');
</script>

</body>
</html>