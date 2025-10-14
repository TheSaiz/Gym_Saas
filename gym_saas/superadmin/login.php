<?php
/**
 * Login SuperAdmin
 * Sistema de autenticación seguro con protección contra ataques
 */

session_start();

// Si ya está logueado, redirigir al dashboard
if(isset($_SESSION['superadmin_id']) && !empty($_SESSION['superadmin_id'])){
    header("Location: /gym_saas/superadmin/index.php");
    exit;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";
$error_message = '';
$success_message = '';

// ============================================
// CREAR TABLA DE INTENTOS SI NO EXISTE
// ============================================

$conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    username VARCHAR(100),
    success TINYINT(1),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(ip_address),
    INDEX(attempt_time)
)");

// ============================================
// MANEJO DE INTENTOS DE LOGIN
// ============================================

// Límite de intentos por IP
$max_attempts = 5;
$lockout_time = 900; // 15 minutos en segundos

function getLoginAttempts($conn, $ip) {
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts 
                           FROM login_attempts 
                           WHERE ip_address = ? 
                           AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['attempts'] ?? 0;
}

function recordLoginAttempt($conn, $ip, $username, $success) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, username, success) VALUES (?, ?, ?)");
    $success_int = $success ? 1 : 0;
    $stmt->bind_param("ssi", $ip, $username, $success_int);
    $stmt->execute();
    $stmt->close();
}

function cleanOldAttempts($conn) {
    $conn->query("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
}

// ============================================
// PROCESAR LOGIN
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Limpiar intentos antiguos
    cleanOldAttempts($conn);
    
    // Verificar límite de intentos
    $attempts = getLoginAttempts($conn, $ip_address);
    
    if ($attempts >= $max_attempts) {
        $error_message = 'Demasiados intentos fallidos. Por favor, espera 15 minutos.';
        recordLoginAttempt($conn, $ip_address, $_POST['usuario'] ?? 'unknown', false);
    } else {
        
        // Validar CSRF token si existe
        if (isset($_SESSION['csrf_token_login'])) {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token_login']) {
                $error_message = 'Token de seguridad inválido. Recarga la página.';
            }
        }
        
        if (empty($error_message)) {
            $usuario = trim($_POST['usuario'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Validaciones básicas
            if (empty($usuario) || empty($password)) {
                $error_message = 'Por favor completa todos los campos.';
                recordLoginAttempt($conn, $ip_address, $usuario, false);
            } else {
                
                // Buscar usuario
                $stmt = $conn->prepare("SELECT id, usuario, password, nombre, email 
                                       FROM superadmin 
                                       WHERE usuario = ? 
                                       LIMIT 1");
                $stmt->bind_param("s", $usuario);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $admin = $result->fetch_assoc();
                    
                    // Verificar contraseña (MD5 por compatibilidad - considerar migrar a password_hash)
                    if (md5($password) === $admin['password']) {
                        
                        // Login exitoso
                        session_regenerate_id(true);
                        
                        $_SESSION['superadmin_id'] = $admin['id'];
                        $_SESSION['superadmin_usuario'] = $admin['usuario'];
                        $_SESSION['superadmin_nombre'] = $admin['nombre'];
                        $_SESSION['superadmin_email'] = $admin['email'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['last_activity'] = time();
                        
                        recordLoginAttempt($conn, $ip_address, $usuario, true);
                        
                        // Actualizar última conexión (agregar campo si no existe)
                        $conn->query("ALTER TABLE superadmin ADD COLUMN IF NOT EXISTS ultimo_acceso TIMESTAMP NULL");
                        $stmt_update = $conn->prepare("UPDATE superadmin SET ultimo_acceso = NOW() WHERE id = ?");
                        $stmt_update->bind_param("i", $admin['id']);
                        $stmt_update->execute();
                        $stmt_update->close();
                        
                        header("Location: $base_url/superadmin/index.php");
                        exit;
                        
                    } else {
                        $error_message = 'Usuario o contraseña incorrectos.';
                        recordLoginAttempt($conn, $ip_address, $usuario, false);
                        sleep(2); // Delay para prevenir fuerza bruta
                    }
                } else {
                    $error_message = 'Usuario o contraseña incorrectos.';
                    recordLoginAttempt($conn, $ip_address, $usuario, false);
                    sleep(2);
                }
                
                $stmt->close();
            }
        }
    }
}

// Generar CSRF token
$_SESSION['csrf_token_login'] = bin2hex(random_bytes(32));

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="Login SuperAdministrador">
<meta name="robots" content="noindex, nofollow">
<title>SuperAdmin - Login</title>

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
        padding: 2rem;
        position: relative;
        overflow: hidden;
    }

    /* Animated Background */
    body::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 50%);
        animation: rotate 20s linear infinite;
    }

    @keyframes rotate {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .login-container {
        width: 100%;
        max-width: 450px;
        position: relative;
        z-index: 1;
    }

    .login-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 25px;
        padding: 3rem;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.6s ease;
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

    .login-header {
        text-align: center;
        margin-bottom: 2.5rem;
    }

    .brand-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: #fff;
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .login-title {
        font-size: 2rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .login-subtitle {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.95rem;
    }

    /* Alert Messages */
    .alert-custom {
        padding: 1rem 1.2rem;
        border-radius: 15px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.8rem;
        animation: slideDown 0.4s ease;
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

    .alert-icon {
        font-size: 1.3rem;
    }

    /* Form */
    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        display: block;
        font-weight: 600;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.9);
        margin-bottom: 0.6rem;
    }

    .input-group-custom {
        position: relative;
    }

    .input-icon {
        position: absolute;
        left: 1.2rem;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.4);
        font-size: 1.1rem;
        z-index: 1;
    }

    .form-control-custom {
        width: 100%;
        padding: 1rem 1rem 1rem 3.2rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        color: #fff;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control-custom:focus {
        outline: none;
        background: rgba(255, 255, 255, 0.08);
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    .form-control-custom::placeholder {
        color: rgba(255, 255, 255, 0.3);
    }

    .password-toggle {
        position: absolute;
        right: 1.2rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: rgba(255, 255, 255, 0.4);
        cursor: pointer;
        font-size: 1rem;
        transition: color 0.3s ease;
        z-index: 1;
    }

    .password-toggle:hover {
        color: rgba(255, 255, 255, 0.8);
    }

    .btn-login {
        width: 100%;
        padding: 1.1rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border: none;
        border-radius: 15px;
        color: #fff;
        font-weight: 700;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
    }

    .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .btn-login:active {
        transform: translateY(0);
    }

    .btn-login:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    /* Footer */
    .login-footer {
        text-align: center;
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .footer-text {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
    }

    .footer-link {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }

    .footer-link:hover {
        color: var(--secondary);
    }

    /* Security Badge */
    .security-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 1.5rem;
        padding: 0.8rem;
        background: rgba(81, 207, 102, 0.1);
        border: 1px solid rgba(81, 207, 102, 0.3);
        border-radius: 12px;
        color: var(--success);
        font-size: 0.85rem;
    }

    /* Loading State */
    .spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* Responsive */
    @media (max-width: 576px) {
        .login-card {
            padding: 2rem 1.5rem;
        }

        .login-title {
            font-size: 1.6rem;
        }

        .brand-icon {
            width: 70px;
            height: 70px;
            font-size: 2rem;
        }
    }
</style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <!-- Header -->
        <div class="login-header">
            <div class="brand-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1 class="login-title">SuperAdmin</h1>
            <p class="login-subtitle">Panel de Administración Global</p>
        </div>

        <!-- Error Message -->
        <?php if(!empty($error_message)): ?>
            <div class="alert-custom alert-danger">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <div><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if(!empty($success_message)): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle alert-icon"></i>
                <div><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="" id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token_login'], ENT_QUOTES, 'UTF-8') ?>">
            
            <!-- Usuario -->
            <div class="form-group">
                <label class="form-label" for="usuario">
                    <i class="fas fa-user me-1"></i> Usuario
                </label>
                <div class="input-group-custom">
                    <i class="input-icon fas fa-user"></i>
                    <input 
                        type="text" 
                        id="usuario" 
                        name="usuario" 
                        class="form-control-custom" 
                        placeholder="Ingresa tu usuario"
                        required
                        autofocus
                        maxlength="100"
                        autocomplete="username"
                    >
                </div>
            </div>

            <!-- Contraseña -->
            <div class="form-group">
                <label class="form-label" for="password">
                    <i class="fas fa-lock me-1"></i> Contraseña
                </label>
                <div class="input-group-custom">
                    <i class="input-icon fas fa-lock"></i>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control-custom" 
                        placeholder="Ingresa tu contraseña"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-login" id="submitBtn">
                <i class="fas fa-sign-in-alt"></i>
                <span id="btnText">Iniciar Sesión</span>
                <span id="btnLoading" style="display: none;">
                    <span class="spinner"></span> Ingresando...
                </span>
            </button>

            <!-- Security Badge -->
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Conexión segura y cifrada</span>
            </div>
        </form>

        <!-- Footer -->
        <div class="login-footer">
            <p class="footer-text">
                © 2025 Gimnasio System SAAS | 
                <a href="<?= $base_url ?>" class="footer-link">Volver al sitio</a>
            </p>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    // Toggle Password Visibility
    function togglePassword() {
        const passwordField = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Form Submit Handler
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnLoading = document.getElementById('btnLoading');
        
        // Validaciones
        const usuario = document.getElementById('usuario').value.trim();
        const password = document.getElementById('password').value;
        
        if (!usuario || !password) {
            e.preventDefault();
            alert('Por favor completa todos los campos');
            return false;
        }
        
        // Loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
    });

    // Prevent multiple submissions
    let formSubmitted = false;
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        if (formSubmitted) {
            e.preventDefault();
            return false;
        }
        formSubmitted = true;
    });

    // Auto-dismiss alerts
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

    // Protección contra clickjacking
    if (window.top !== window.self) {
        window.top.location = window.self.location;
    }

    // Prevenir autocomplete en producción
    window.addEventListener('load', function() {
        setTimeout(function() {
            document.getElementById('usuario').setAttribute('autocomplete', 'off');
            document.getElementById('password').setAttribute('autocomplete', 'off');
        }, 500);
    });

    // Console warning
    console.log('%c⚠️ ADVERTENCIA DE SEGURIDAD', 'color: red; font-size: 24px; font-weight: bold;');
    console.log('%cEste panel es solo para administradores autorizados.', 'font-size: 14px;');
    console.log('%cEl acceso no autorizado está prohibido y será registrado.', 'font-size: 14px;');

    // Session timeout warning
    const SESSION_TIMEOUT = 1800000; // 30 minutos
    let timeoutWarning;

    function showTimeoutWarning() {
        if (confirm('Tu sesión está por expirar. ¿Deseas continuar?')) {
            // Hacer una petición para mantener la sesión activa
            fetch('<?= $base_url ?>/api/keep_alive.php')
                .catch(err => console.error('Error:', err));
        }
    }

    // Mostrar advertencia 2 minutos antes del timeout
    setTimeout(showTimeoutWarning, SESSION_TIMEOUT - 120000);

    // Focus en el primer campo
    document.getElementById('usuario').focus();
</script>

</body>
</html>
