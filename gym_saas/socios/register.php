<?php
session_start();

// Regenerar ID de sesión
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Si ya está logueado, redirigir
if(isset($_SESSION['socio_id'])){
    header("Location: index.php");
    exit;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";
$error = '';
$success = '';

// Obtener lista de gimnasios activos
$gimnasios = [];
$stmt = $conn->prepare("SELECT id, nombre, localidad FROM gimnasios WHERE estado = 'activo' ORDER BY nombre ASC");
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $gimnasios[] = $row;
}
$stmt->close();

// Procesar registro
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    
    // Validar CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token_register'] ?? '')){
        $error = "Token de seguridad inválido.";
    } else {
        // Sanitizar datos
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $telefono_emergencia = trim($_POST['telefono_emergencia'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $gimnasio_id = filter_var($_POST['gimnasio_id'] ?? 0, FILTER_VALIDATE_INT);
        
        // Validaciones
        if(empty($nombre) || empty($apellido) || empty($dni) || empty($email) || empty($password)){
            $error = "Por favor completa todos los campos requeridos.";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error = "El email ingresado no es válido.";
        } elseif(strlen($password) < 6){
            $error = "La contraseña debe tener al menos 6 caracteres.";
        } elseif($password !== $password_confirm){
            $error = "Las contraseñas no coinciden.";
        } elseif(!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u", $nombre)){
            $error = "El nombre solo puede contener letras.";
        } elseif(!preg_match("/^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/u", $apellido)){
            $error = "El apellido solo puede contener letras.";
        } elseif(!preg_match("/^[0-9]+$/", $dni)){
            $error = "El DNI solo puede contener números.";
        } elseif(strlen($dni) < 7 || strlen($dni) > 8){
            $error = "El DNI debe tener entre 7 y 8 dígitos.";
        } elseif(!$gimnasio_id){
            $error = "Debes seleccionar un gimnasio.";
        } else {
            // Verificar que el email no exista
            $stmt = $conn->prepare("SELECT id FROM socios WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0){
                $error = "El email ya está registrado.";
                $stmt->close();
            } else {
                $stmt->close();
                
                // Verificar que el DNI no exista
                $stmt = $conn->prepare("SELECT id FROM socios WHERE dni = ? LIMIT 1");
                $stmt->bind_param("s", $dni);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if($result->num_rows > 0){
                    $error = "El DNI ya está registrado.";
                    $stmt->close();
                } else {
                    $stmt->close();
                    
                    // Verificar que el gimnasio existe
                    $stmt = $conn->prepare("SELECT id FROM gimnasios WHERE id = ? AND estado = 'activo' LIMIT 1");
                    $stmt->bind_param("i", $gimnasio_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if($result->num_rows !== 1){
                        $error = "El gimnasio seleccionado no es válido.";
                        $stmt->close();
                    } else {
                        $stmt->close();
                        
                        // Todo validado, proceder con el registro
                        $password_hash = md5($password);
                        
                        $stmt = $conn->prepare("INSERT INTO socios (gimnasio_id, nombre, apellido, dni, telefono, telefono_emergencia, email, password, estado, creado) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo', NOW())");
                        $stmt->bind_param("isssssss", $gimnasio_id, $nombre, $apellido, $dni, $telefono, $telefono_emergencia, $email, $password_hash);
                        
                        if($stmt->execute()){
                            $socio_id = $conn->insert_id;
                            $stmt->close();
                            
                            // Login automático
                            session_regenerate_id(true);
                            $_SESSION['socio_id'] = $socio_id;
                            $_SESSION['socio_nombre'] = $nombre;
                            $_SESSION['socio_apellido'] = $apellido;
                            $_SESSION['gimnasio_id'] = $gimnasio_id;
                            $_SESSION['login_time'] = time();
                            $_SESSION['regenerated'] = true;
                            
                            header("Location: index.php?welcome=1");
                            exit;
                        } else {
                            $error = "Error al registrar. Por favor intenta nuevamente.";
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
    
    // Regenerar token después de intento
    unset($_SESSION['csrf_token_register']);
}

// Generar CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token_register'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Registro de nuevo socio">
<meta name="robots" content="noindex, nofollow">
<title>Registro Socio - Sistema Gimnasio</title>

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
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        color: #fff;
    }

    .register-container {
        width: 100%;
        max-width: 600px;
    }

    .brand-section {
        text-align: center;
        margin-bottom: 2rem;
    }

    .brand-logo {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        margin-bottom: 1rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .brand-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .brand-subtitle {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.95rem;
    }

    .register-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .card-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        text-align: center;
    }

    .card-subtitle {
        text-align: center;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.9rem;
        margin-bottom: 2rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .required {
        color: var(--danger);
    }

    .input-group-custom {
        position: relative;
    }

    .input-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.4);
        font-size: 1.1rem;
        z-index: 2;
    }

    .form-control-custom {
        width: 100%;
        padding: 0.9rem 1rem 0.9rem 3rem;
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

    .form-control-custom::placeholder {
        color: rgba(255, 255, 255, 0.3);
    }

    select.form-control-custom {
        cursor: pointer;
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
        z-index: 2;
        transition: color 0.3s ease;
    }

    .password-toggle:hover {
        color: rgba(255, 255, 255, 0.8);
    }

    .form-help {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.5);
        margin-top: 0.3rem;
    }

    .btn-register {
        width: 100%;
        padding: 1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 12px;
        color: #fff;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 1rem;
    }

    .btn-register:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-register:active {
        transform: translateY(0);
    }

    .btn-register:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .alert-custom {
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        font-size: 0.9rem;
    }

    .alert-danger {
        background: rgba(255, 107, 107, 0.1);
        border: 1px solid rgba(255, 107, 107, 0.3);
        color: var(--danger);
    }

    .alert-success {
        background: rgba(81, 207, 102, 0.1);
        border: 1px solid rgba(81, 207, 102, 0.3);
        color: var(--success);
    }

    .divider {
        text-align: center;
        margin: 1.5rem 0;
        position: relative;
    }

    .divider::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        width: 100%;
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
    }

    .divider span {
        background: rgba(10, 14, 39, 0.95);
        padding: 0 1rem;
        position: relative;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
    }

    .login-link {
        text-align: center;
        margin-top: 1.5rem;
    }

    .login-link a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .login-link a:hover {
        color: #fff;
        text-decoration: underline;
    }

    .back-link {
        text-align: center;
        margin-top: 1rem;
    }

    .back-link a {
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .back-link a:hover {
        color: #fff;
    }

    .loading {
        display: inline-block;
        width: 18px;
        height: 18px;
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    .security-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 1.5rem;
        padding: 0.8rem;
        background: rgba(81, 207, 102, 0.1);
        border: 1px solid rgba(81, 207, 102, 0.3);
        border-radius: 10px;
        color: var(--success);
        font-size: 0.85rem;
    }

    .security-badge i {
        font-size: 1rem;
    }

    @media (max-width: 576px) {
        .register-card {
            padding: 2rem 1.5rem;
        }

        .brand-title {
            font-size: 1.5rem;
        }

        .brand-logo {
            width: 70px;
            height: 70px;
            font-size: 2rem;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>
</head>
<body>

<div class="register-container">
    <!-- Brand Section -->
    <div class="brand-section">
        <div class="brand-logo">
            <i class="fas fa-user-plus"></i>
        </div>
        <h1 class="brand-title">Registro de Socio</h1>
        <p class="brand-subtitle">Crea tu cuenta y comienza tu entrenamiento</p>
    </div>

    <!-- Register Card -->
    <div class="register-card">
        <h2 class="card-title">Crear Cuenta</h2>
        <p class="card-subtitle">Completa tus datos para registrarte</p>

        <?php if(!empty($error)): ?>
            <div class="alert-custom alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <?php if(!empty($success)): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="registerForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            
            <!-- Datos Personales -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="nombre">
                        Nombre <span class="required">*</span>
                    </label>
                    <div class="input-group-custom">
                        <i class="input-icon fas fa-user"></i>
                        <input 
                            type="text" 
                            id="nombre" 
                            name="nombre" 
                            class="form-control-custom" 
                            placeholder="Juan"
                            required
                            maxlength="100"
                            pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+"
                            title="Solo letras y espacios"
                            value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="apellido">
                        Apellido <span class="required">*</span>
                    </label>
                    <div class="input-group-custom">
                        <i class="input-icon fas fa-user"></i>
                        <input 
                            type="text" 
                            id="apellido" 
                            name="apellido" 
                            class="form-control-custom" 
                            placeholder="Pérez"
                            required
                            maxlength="100"
                            pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+"
                            title="Solo letras y espacios"
                            value="<?= htmlspecialchars($_POST['apellido'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="dni">
                        DNI <span class="required">*</span>
                    </label>
                    <div class="input-group-custom">
                        <i class="input-icon fas fa-id-card"></i>
                        <input 
                            type="text" 
                            id="dni" 
                            name="dni" 
                            class="form-control-custom" 
                            placeholder="40123456"
                            required
                            maxlength="8"
                            pattern="[0-9]{7,8}"
                            title="DNI de 7 u 8 dígitos"
                            value="<?= htmlspecialchars($_POST['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                    <small class="form-help">
                        <i class="fas fa-info-circle"></i> Sin puntos ni espacios
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gimnasio_id">
                        Gimnasio <span class="required">*</span>
                    </label>
                    <div class="input-group-custom">
                        <i class="input-icon fas fa-dumbbell"></i>
                        <select 
                            id="gimnasio_id" 
                            name="gimnasio_id" 
                            class="form-control-custom" 
                            required
                        >
                            <option value="">Seleccionar...</option>
                            <?php foreach($gimnasios as $gym): ?>
                                <option value="<?= $gym['id'] ?>" <?= (isset($_POST['gimnasio_id']) && $_POST['gimnasio_id'] == $gym['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($gym['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    <?= !empty($gym['localidad']) ? ' - ' . htmlspecialchars($gym['localidad'], ENT_QUOTES, 'UTF-8') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Contacto -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="telefono">
                        Teléfono
                    </label>
                    <div class="input-group-custom">
                        <i class="input-icon fas fa-phone"></i>
                        <input 
                            type="tel" 
                            id="telefono" 
                            name="telefono" 
                            class="form-control-custom" 
                            placeholder="1122334455"
                            maxlength="30"
                            pattern="[0-9+\-\s()]+"
                            value="<?= htmlspecialchars($_POST['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="telefono_emergencia">
                        Tel. Emergencia
                    </label>
                    <div class="input-group-custom">
                        <i class="input-icon fas fa-phone-alt"></i>
                        <input 
                            type="tel" 
                            id="telefono_emergencia" 
                            name="telefono_emergencia" 
                            class="form-control-custom" 
                            placeholder="1199887766"
                            maxlength="30"
                            pattern="[0-9+\-\s()]+"
                            value="<?= htmlspecialchars($_POST['telefono_emergencia'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>
                </div>
            </div>

            <!-- Email -->
            <div class="form-group full-width">
                <label class="form-label" for="email">
                    Email <span class="required">*</span>
                </label>
                <div class="input-group-custom">
                    <i class="input-icon fas fa-envelope"></i>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control-custom" 
                        placeholder="tu@email.com"
                        required
                        maxlength="255"
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
            </div>

            <!-- Contraseñas -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="password">
                        Contraseña <span class="required">*</span>
                    </label>
                    <div class="input-group-custom">
                        <i class="input-icon fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-control-custom" 
                            placeholder="••••••••"
                            required
                            minlength="6"
                            autocomplete="new-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small class="form-help">
                        <i class="fas fa-info-circle"></i> Mínimo 6 caracteres
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirm">
                        Confirmar Contraseña <span class="required">*</span>
                    </label>
                    <div class="input-group-custom">
                        <i class="input-icon fas fa-lock"></i>
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            class="form-control-custom" 
                            placeholder="••••••••"
                            required
                            autocomplete="new-password"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirm')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-register" id="submitBtn">
                <span id="btnText"><i class="fas fa-user-plus"></i> Registrarse</span>
                <span id="btnLoading" style="display: none;">
                    <span class="loading"></span> Registrando...
                </span>
            </button>

            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Tus datos están protegidos y encriptados</span>
            </div>
        </form>

        <div class="divider">
            <span>o</span>
        </div>

        <div class="login-link">
            <p style="color: rgba(255, 255, 255, 0.7); margin: 0;">
                ¿Ya tienes cuenta? 
                <a href="<?= $base_url ?>/socios/login.php">Inicia sesión aquí</a>
            </p>
        </div>

        <div class="back-link">
            <a href="<?= $base_url ?>/index.php">
                <i class="fas fa-arrow-left"></i>
                Volver al inicio
            </a>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const button = field.parentElement.querySelector('.password-toggle');
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

    // Form validation
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const passwordConfirm = document.getElementById('password_confirm').value;
        
        if (password !== passwordConfirm) {
            e.preventDefault();
            alert('⚠️ Las contraseñas no coinciden');
            document.getElementById('password_confirm').focus();
            return false;
        }
        
        if (password.length < 6) {
            e.preventDefault();
            alert('⚠️ La contraseña debe tener al menos 6 caracteres');
            document.getElementById('password').focus();
            return false;
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

    // DNI validation
    document.getElementById('dni').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Phone validation
    ['telefono', 'telefono_emergencia'].forEach(function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
            });
        }
    });

    // Prevenir múltiples submissions
    let formSubmitted = false;
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        if (formSubmitted) {
            e.preventDefault();
            return false;
        }
        formSubmitted = true;
    });

    // Auto-focus en primer campo
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('nombre').focus();
    });

    // Protección contra clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Console warning
    console.log('%c⚠️ ADVERTENCIA DE SEGURIDAD', 'color: red; font-size: 24px; font-weight: bold;');
    console.log('%cNo pegues código desconocido aquí. Podrías comprometer tu cuenta.', 'font-size: 14px;');

    // Password strength indicator
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        const length = password.length;
        
        // Simple strength check
        if (length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
            this.style.borderColor = 'var(--success)';
        } else if (length >= 6) {
            this.style.borderColor = 'var(--warning)';
        } else {
            this.style.borderColor = 'rgba(255, 255, 255, 0.1)';
        }
    });
</script>

</body>
</html>