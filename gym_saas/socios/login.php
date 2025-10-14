<?php
session_start();

// Regenerar ID de sesión para prevenir session fixation
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

// Si ya está logueado, redirigir al dashboard
if(isset($_SESSION['socio_id'])){
    header("Location: index.php");
    exit;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";
$error = '';
$intentos_login = isset($_SESSION['intentos_login']) ? $_SESSION['intentos_login'] : 0;
$ultimo_intento = isset($_SESSION['ultimo_intento']) ? $_SESSION['ultimo_intento'] : 0;

// Rate limiting: máximo 5 intentos en 15 minutos
if($intentos_login >= 5 && (time() - $ultimo_intento) < 900) {
    $tiempo_restante = ceil((900 - (time() - $ultimo_intento)) / 60);
    $error = "Demasiados intentos fallidos. Intenta nuevamente en $tiempo_restante minutos.";
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)){
    // Validar CSRF token
    if(!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']){
        $error = "Token de seguridad inválido.";
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        
        // Validar formato de email
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $error = "Email inválido.";
        } elseif(empty($password)){
            $error = "La contraseña es requerida.";
        } else {
            // Preparar consulta para prevenir SQL injection
            $stmt = $conn->prepare("SELECT id, nombre, apellido, gimnasio_id, password, estado FROM socios WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows === 1){
                $socio = $result->fetch_assoc();
                
                // Verificar estado activo
                if($socio['estado'] !== 'activo'){
                    $error = "Tu cuenta está inactiva. Contacta al gimnasio.";
                    $intentos_login++;
                } elseif(md5($password) === $socio['password']){
                    // Login exitoso
                    session_regenerate_id(true);
                    $_SESSION['socio_id'] = $socio['id'];
                    $_SESSION['socio_nombre'] = $socio['nombre'];
                    $_SESSION['socio_apellido'] = $socio['apellido'];
                    $_SESSION['gimnasio_id'] = $socio['gimnasio_id'];
                    $_SESSION['login_time'] = time();
                    
                    // Resetear intentos
                    unset($_SESSION['intentos_login']);
                    unset($_SESSION['ultimo_intento']);
                    
                    // Registrar login en logs (opcional)
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    // Aquí podrías insertar en una tabla de logs si la tienes
                    
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Email o contraseña incorrectos.";
                    $intentos_login++;
                }
            } else {
                $error = "Email o contraseña incorrectos.";
                $intentos_login++;
            }
            $stmt->close();
        }
    }
    
    // Actualizar intentos de login
    $_SESSION['intentos_login'] = $intentos_login;
    $_SESSION['ultimo_intento'] = time();
}

// Generar nuevo CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Login para socios del gimnasio">
<meta name="robots" content="noindex, nofollow">
<title>Login Socio - Sistema Gimnasio</title>

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

    .login-container {
        width: 100%;
        max-width: 450px;
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

    .login-card {
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

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.9);
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

    .password-toggle {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.4);
        cursor: pointer;
        font-size: 1.1rem;
        z-index: 2;
        transition: color 0.3s ease;
    }

    .password-toggle:hover {
        color: rgba(255, 255, 255, 0.8);
    }

    .btn-login {
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

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    .btn-login:disabled {
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

    .alert-warning {
        background: rgba(255, 212, 59, 0.1);
        border: 1px solid rgba(255, 212, 59, 0.3);
        color: var(--warning);
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

    .register-link {
        text-align: center;
        margin-top: 1.5rem;
    }

    .register-link a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .register-link a:hover {
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

    /* Loading animation */
    @keyframes spin {
        to { transform: rotate(360deg); }
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

    /* Responsive */
    @media (max-width: 576px) {
        .login-card {
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
    }

    /* Security indicator */
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
</style>
</head>
<body>

<div class="login-container">
    <!-- Brand Section -->
    <div class="brand-section">
        <div class="brand-logo">
            <i class="fas fa-dumbbell"></i>
        </div>
        <h1 class="brand-title">Portal Socio</h1>
        <p class="brand-subtitle">Accede a tu panel personal</p>
    </div>

    <!-- Login Card -->
    <div class="login-card">
        <h2 class="card-title">Iniciar Sesión</h2>
        <p class="card-subtitle">Ingresa tus credenciales para continuar</p>

        <?php if(!empty($error)): ?>
            <div class="alert-custom alert-<?= $intentos_login >= 5 ? 'warning' : 'danger' ?>">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="form-group">
                <label class="form-label" for="email">
                    <i class="fas fa-envelope me-1"></i> Email
                </label>
                <div class="input-group-custom">
                    <i class="input-icon fas fa-user"></i>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control-custom" 
                        placeholder="tu@email.com"
                        required
                        autocomplete="email"
                        <?= $intentos_login >= 5 && (time() - $ultimo_intento) < 900 ? 'disabled' : '' ?>
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    <i class="fas fa-lock me-1"></i> Contraseña
                </label>
                <div class="input-group-custom">
                    <i class="input-icon fas fa-key"></i>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control-custom" 
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                        <?= $intentos_login >= 5 && (time() - $ultimo_intento) < 900 ? 'disabled' : '' ?>
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button 
                type="submit" 
                class="btn-login" 
                id="submitBtn"
                <?= $intentos_login >= 5 && (time() - $ultimo_intento) < 900 ? 'disabled' : '' ?>
            >
                <span id="btnText">Ingresar</span>
                <span id="btnLoading" style="display: none;">
                    <span class="loading"></span> Verificando...
                </span>
            </button>

            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Conexión segura y encriptada</span>
            </div>
        </form>

        <div class="divider">
            <span>o</span>
        </div>

        <div class="register-link">
            <p style="color: rgba(255, 255, 255, 0.7); margin: 0;">
                ¿No tienes cuenta? 
                <a href="<?= $base_url ?>/socios/register.php">Regístrate aquí</a>
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
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Form submission con loading
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoading = document.getElementById('btnLoading');
        
        if (!submitBtn.disabled) {
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'inline';
        }
    });

    // Auto-focus en el primer input
    document.addEventListener('DOMContentLoaded', function() {
        const emailInput = document.getElementById('email');
        if (!emailInput.disabled) {
            emailInput.focus();
        }
    });

    // Prevenir múltiples submissions
    let formSubmitted = false;
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        if (formSubmitted) {
            e.preventDefault();
            return false;
        }
        formSubmitted = true;
    });

    // Protección contra clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Validación del lado del cliente
    document.getElementById('email').addEventListener('blur', function() {
        const email = this.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailRegex.test(email)) {
            this.style.borderColor = 'var(--danger)';
        } else {
            this.style.borderColor = 'rgba(255, 255, 255, 0.1)';
        }
    });

    // Limpiar mensajes de error después de 5 segundos
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

    // Enter key en email pasa a password
    document.getElementById('email').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('password').focus();
        }
    });

    // Security console warning
    console.log('%c⚠️ ADVERTENCIA DE SEGURIDAD', 'color: red; font-size: 24px; font-weight: bold;');
    console.log('%cNo pegues código desconocido aquí. Podrías comprometer tu cuenta.', 'font-size: 14px;');
</script>

</body>
</html>