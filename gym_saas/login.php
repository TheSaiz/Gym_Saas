<?php
session_start();
include_once(__DIR__ . "/includes/db_connect.php");

// Ruta base del proyecto
$base_url = "/gym_saas";

// Si ya está logueado, redirigir al panel
if(isset($_SESSION['gimnasio_id'])){
    header("Location: $base_url/gimnasios/index.php");
    exit;
}

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    // Buscar usuario
    $query = "SELECT * FROM gimnasios WHERE email=? AND estado='activo'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1){
        $gimnasio = $result->fetch_assoc();
        
        // Verificar contraseña (MD5 para compatibilidad con DB actual)
        if(md5($password) === $gimnasio['password']){
            $_SESSION['gimnasio_id'] = $gimnasio['id'];
            $_SESSION['gimnasio_nombre'] = $gimnasio['nombre'];
            header("Location: $base_url/gimnasios/index.php");
            exit;
        } else {
            $error = "Correo electrónico o contraseña incorrectos.";
        }
    } else {
        $error = "Correo electrónico o contraseña incorrectos, o la cuenta está suspendida.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Gimnasio System SAAS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #2d1b3d 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow: hidden;
    }

    /* Animated Background */
    body::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 50%, rgba(102, 126, 234, 0.1) 0%, transparent 50%),
            radial-gradient(circle at 80% 80%, rgba(118, 75, 162, 0.1) 0%, transparent 50%);
        animation: gradientShift 10s ease infinite;
    }

    @keyframes gradientShift {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.8; }
    }

    /* Floating Icons */
    .floating-icon {
        position: absolute;
        color: rgba(102, 126, 234, 0.1);
        animation: float 6s ease-in-out infinite;
    }

    .icon-1 {
        top: 10%;
        left: 10%;
        font-size: 60px;
        animation-delay: 0s;
    }

    .icon-2 {
        top: 70%;
        left: 15%;
        font-size: 80px;
        animation-delay: 2s;
    }

    .icon-3 {
        top: 20%;
        right: 10%;
        font-size: 70px;
        animation-delay: 1s;
    }

    .icon-4 {
        bottom: 15%;
        right: 15%;
        font-size: 90px;
        animation-delay: 3s;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-30px) rotate(10deg); }
    }

    /* Login Container */
    .login-container {
        width: 100%;
        max-width: 450px;
        position: relative;
        z-index: 10;
    }

    .login-card {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 30px;
        padding: 50px 40px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.8s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(50px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .logo-section {
        text-align: center;
        margin-bottom: 35px;
    }

    .logo-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
    }

    .logo-icon i {
        font-size: 40px;
        color: #fff;
    }

    .login-title {
        font-size: 2rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }

    .login-subtitle {
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.95rem;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 25px;
        position: relative;
    }

    .form-label {
        color: #fff;
        font-weight: 500;
        margin-bottom: 10px;
        display: block;
        font-size: 0.9rem;
    }

    .input-group-custom {
        position: relative;
    }

    .input-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.4);
        font-size: 18px;
        z-index: 2;
    }

    .form-control {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 15px;
        padding: 15px 20px 15px 50px;
        color: #fff;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        background: rgba(255, 255, 255, 0.12);
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        color: #fff;
        outline: none;
    }

    .form-control::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    /* Password Toggle */
    .password-toggle {
        position: absolute;
        right: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: rgba(255, 255, 255, 0.4);
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 2;
    }

    .password-toggle:hover {
        color: #667eea;
    }

    /* Alert */
    .alert-custom {
        background: rgba(220, 53, 69, 0.15);
        border: 1px solid rgba(220, 53, 69, 0.3);
        border-radius: 15px;
        color: #ff6b6b;
        padding: 15px 20px;
        margin-bottom: 25px;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        animation: shake 0.5s ease;
    }

    .alert-custom i {
        margin-right: 12px;
        font-size: 1.2rem;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-10px); }
        75% { transform: translateX(10px); }
    }

    /* Remember Me */
    .remember-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .custom-checkbox {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .custom-checkbox input {
        width: 20px;
        height: 20px;
        margin-right: 10px;
        cursor: pointer;
    }

    .custom-checkbox label {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        cursor: pointer;
        margin: 0;
    }

    .forgot-link {
        color: #667eea;
        text-decoration: none;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .forgot-link:hover {
        color: #764ba2;
    }

    /* Button */
    .btn-login {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border: none;
        border-radius: 15px;
        padding: 15px;
        font-size: 1.1rem;
        font-weight: 600;
        width: 100%;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-login:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 35px rgba(102, 126, 234, 0.5);
    }

    .btn-login:active {
        transform: translateY(-1px);
    }

    .btn-login i {
        margin-right: 10px;
    }

    /* Divider */
    .divider {
        display: flex;
        align-items: center;
        margin: 30px 0;
    }

    .divider::before,
    .divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(255, 255, 255, 0.2);
    }

    .divider span {
        padding: 0 15px;
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
    }

    /* Register Section */
    .register-section {
        text-align: center;
        margin-top: 25px;
    }

    .register-text {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.95rem;
    }

    .register-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .register-link:hover {
        color: #764ba2;
        text-decoration: underline;
    }

    /* Back Button */
    .back-button {
        position: absolute;
        top: 30px;
        left: 30px;
        z-index: 100;
    }

    .btn-back {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #fff;
        padding: 12px 25px;
        border-radius: 50px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
    }

    .btn-back:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        transform: translateX(-5px);
    }

    .btn-back i {
        margin-right: 8px;
    }

    /* Responsive */
    @media (max-width: 576px) {
        .login-card {
            padding: 40px 30px;
            border-radius: 25px;
        }

        .login-title {
            font-size: 1.6rem;
        }

        .logo-icon {
            width: 70px;
            height: 70px;
        }

        .logo-icon i {
            font-size: 35px;
        }

        .back-button {
            top: 15px;
            left: 15px;
        }
    }
</style>
</head>
<body>

<!-- Floating Icons -->
<i class="fas fa-dumbbell floating-icon icon-1"></i>
<i class="fas fa-heartbeat floating-icon icon-2"></i>
<i class="fas fa-running floating-icon icon-3"></i>
<i class="fas fa-fire floating-icon icon-4"></i>

<!-- Back Button -->
<div class="back-button">
    <a href="<?= $base_url ?>/index.php" class="btn-back">
        <i class="fas fa-arrow-left"></i>
        Volver al inicio
    </a>
</div>

<!-- Login Container -->
<div class="login-container">
    <div class="login-card">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-dumbbell"></i>
            </div>
            <h2 class="login-title">Bienvenido de nuevo</h2>
            <p class="login-subtitle">Ingresa a tu panel de control</p>
        </div>

        <!-- Error Alert -->
        <?php if($error): ?>
            <div class="alert-custom">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" id="loginForm">
            <!-- Email Input -->
            <div class="form-group">
                <label for="email" class="form-label">Correo electrónico</label>
                <div class="input-group-custom">
                    <i class="fas fa-envelope input-icon"></i>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        placeholder="correo@ejemplo.com" 
                        required
                        autocomplete="email"
                    >
                </div>
            </div>

            <!-- Password Input -->
            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group-custom">
                    <i class="fas fa-lock input-icon"></i>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password" 
                        name="password" 
                        placeholder="Tu contraseña" 
                        required
                        autocomplete="current-password"
                    >
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="remember-section">
                <div class="custom-checkbox">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Recordarme</label>
                </div>
                <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i>
                Iniciar Sesión
            </button>
        </form>

        <!-- Divider -->
        <div class="divider">
            <span>o</span>
        </div>

        <!-- Register Section -->
        <div class="register-section">
            <p class="register-text">
                ¿No tienes una cuenta? 
                <a href="<?= $base_url ?>/register.php" class="register-link">Regístrate gratis</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle Password Visibility
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');

    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Form Validation
    const loginForm = document.getElementById('loginForm');
    
    loginForm.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;

        if (!email || !password) {
            e.preventDefault();
            alert('Por favor completa todos los campos');
        }
    });

    // Add focus animation to inputs
    const formControls = document.querySelectorAll('.form-control');
    
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        control.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
</script>
</body>
</html>
