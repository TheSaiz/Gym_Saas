<?php
require 'db.php';

$maxAttempts = 5;
$lockoutTime = 900;

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = 0;
}

if ($_SESSION['login_attempts'] >= $maxAttempts) {
    $timeElapsed = time() - $_SESSION['last_attempt_time'];
    if ($timeElapsed < $lockoutTime) {
        $remainingTime = ceil(($lockoutTime - $timeElapsed) / 60);
        $error = "Demasiados intentos fallidos. Intente nuevamente en {$remainingTime} minutos.";
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido. Recargue la página e intente nuevamente.';
    } elseif ($_SESSION['login_attempts'] < $maxAttempts) {
        $email = cleanInput($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Por favor, complete todos los campos.';
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El formato del email no es válido.';
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
        } else {
            try {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['login_attempts'] = 0;
                    
                    logActivity($pdo, 'login', 'Usuario ingresó al sistema');
                    
                    if ($user['role'] === 'superadmin') {
                        header('Location: dashboard_super.php');
                    } else {
                        header('Location: dashboard_recep.php');
                    }
                    exit();
                } else {
                    $error = 'Email o contraseña incorrectos.';
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    logActivity($pdo, 'login_failed', "Intento de login con email: {$email}");
                }
            } catch (PDOException $e) {
                error_log('Login Error: ' . $e->getMessage());
                $error = 'Error del sistema. Intente nuevamente más tarde.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Login - Sistema Barbería</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --primary:#667eea;
            --primary-dark:#5568d3;
            --secondary:#764ba2;
            --dark:#0a0e27;
            --dark-light:#1a1f3a;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Poppins',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
            background:linear-gradient(135deg,#0a0e27 0%,#1a1f3a 100%);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:1rem;
            position:relative;
            overflow:hidden;
        }
        body::before{
            content:'';
            position:absolute;
            width:500px;
            height:500px;
            background:radial-gradient(circle,rgba(102,126,234,0.3) 0%,transparent 70%);
            border-radius:50%;
            top:-250px;
            right:-250px;
            animation:pulse 8s ease-in-out infinite;
        }
        body::after{
            content:'';
            position:absolute;
            width:400px;
            height:400px;
            background:radial-gradient(circle,rgba(118,75,162,0.2) 0%,transparent 70%);
            border-radius:50%;
            bottom:-200px;
            left:-200px;
            animation:pulse 10s ease-in-out infinite reverse;
        }
        @keyframes pulse{
            0%,100%{transform:scale(1);opacity:0.5;}
            50%{transform:scale(1.1);opacity:0.8;}
        }
        .login-container{
            width:100%;
            max-width:480px;
            position:relative;
            z-index:1;
        }
        .login-card{
            background:rgba(26,31,58,0.95);
            backdrop-filter:blur(10px);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:1.5rem;
            box-shadow:0 20px 60px rgba(0,0,0,0.5);
            padding:3rem;
            animation:slideUp 0.5s ease-out;
        }
        @keyframes slideUp{
            from{opacity:0;transform:translateY(30px);}
            to{opacity:1;transform:translateY(0);}
        }
        .logo-container{
            text-align:center;
            margin-bottom:2rem;
        }
        .logo-icon{
            width:80px;
            height:80px;
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);
            border-radius:1.5rem;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            margin-bottom:1rem;
            box-shadow:0 10px 30px rgba(102,126,234,0.4);
            animation:float 3s ease-in-out infinite;
        }
        @keyframes float{
            0%,100%{transform:translateY(0px);}
            50%{transform:translateY(-10px);}
        }
        .logo-icon i{
            font-size:2.5rem;
            color:white;
        }
        .login-title{
            font-size:1.75rem;
            font-weight:700;
            color:white;
            margin-bottom:0.5rem;
        }
        .login-subtitle{
            color:rgba(255,255,255,0.6);
            font-size:0.9375rem;
        }
        .form-group{
            margin-bottom:1.5rem;
        }
        .form-label{
            font-weight:600;
            color:white;
            margin-bottom:0.5rem;
            font-size:0.875rem;
        }
        .input-group{
            position:relative;
        }
        .input-group-icon{
            position:absolute;
            left:1rem;
            top:50%;
            transform:translateY(-50%);
            color:rgba(255,255,255,0.4);
            font-size:1.25rem;
            z-index:10;
        }
        .form-control{
            background:rgba(255,255,255,0.05);
            border:2px solid rgba(255,255,255,0.1);
            border-radius:0.75rem;
            padding:0.875rem 1rem 0.875rem 3rem;
            transition:all 0.3s ease;
            font-size:0.9375rem;
            color:white;
        }
        .form-control:focus{
            background:rgba(255,255,255,0.08);
            border-color:var(--primary);
            box-shadow:0 0 0 4px rgba(102,126,234,0.2);
            color:white;
        }
        .form-control::placeholder{
            color:rgba(255,255,255,0.4);
        }
        .btn-login{
            width:100%;
            padding:0.875rem;
            background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 100%);
            border:none;
            border-radius:0.75rem;
            color:white;
            font-weight:600;
            font-size:1rem;
            transition:all 0.3s ease;
            cursor:pointer;
        }
        .btn-login:hover{
            transform:translateY(-2px);
            box-shadow:0 10px 30px rgba(102,126,234,0.5);
        }
        .btn-login:active{
            transform:translateY(0);
        }
        .btn-login:disabled{
            opacity:0.6;
            cursor:not-allowed;
            transform:none;
        }
        .alert{
            border:none;
            border-radius:0.75rem;
            padding:1rem;
            margin-bottom:1.5rem;
            animation:shake 0.5s ease-in-out;
        }
        @keyframes shake{
            0%,100%{transform:translateX(0);}
            25%{transform:translateX(-10px);}
            75%{transform:translateX(10px);}
        }
        .alert-danger{
            background:rgba(255,107,107,0.2);
            color:#ff6b6b;
            border:1px solid rgba(255,107,107,0.3);
        }
        .test-credentials{
            background:rgba(255,255,255,0.05);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:0.75rem;
            padding:1rem;
            margin-top:1.5rem;
        }
        .test-credentials-title{
            font-size:0.75rem;
            font-weight:600;
            color:rgba(255,255,255,0.5);
            text-transform:uppercase;
            letter-spacing:0.05em;
            margin-bottom:0.75rem;
        }
        .credential-item{
            background:rgba(255,255,255,0.05);
            border-radius:0.5rem;
            padding:0.75rem;
            margin-bottom:0.5rem;
            border:1px solid rgba(255,255,255,0.1);
            font-size:0.8125rem;
        }
        .credential-item:last-child{
            margin-bottom:0;
        }
        .credential-role{
            font-weight:600;
            color:white;
            margin-bottom:0.2rem;
        }
        .credential-data{
            color:rgba(255,255,255,0.6);
            font-family:'Courier New',monospace;
        }
        .password-toggle{
            position:absolute;
            right:1rem;
            top:50%;
            transform:translateY(-50%);
            background:none;
            border:none;
            color:rgba(255,255,255,0.4);
            cursor:pointer;
            padding:0.25rem;
            z-index:10;
            transition:color 0.3s ease;
        }
        .password-toggle:hover{
            color:var(--primary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="fas fa-scissors"></i>
                </div>
                <h1 class="login-title">Bienvenido</h1>
                <p class="login-subtitle">Sistema de Gestión para Barbería</p>
            </div>
            
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="loginForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <div class="input-group">
                        <i class="bi bi-envelope input-group-icon"></i>
                        <input type="email" class="form-control" id="email" name="email" placeholder="correo@ejemplo.com" autocomplete="email" required <?= ($_SESSION['login_attempts']>=$maxAttempts)?'disabled':'' ?>>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <div class="input-group">
                        <i class="bi bi-lock input-group-icon"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required <?= ($_SESSION['login_attempts']>=$maxAttempts)?'disabled':'' ?>>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login" <?= ($_SESSION['login_attempts']>=$maxAttempts)?'disabled':'' ?>>
                    <i class="bi bi-box-arrow-in-right me-2"></i>
                    Iniciar Sesión
                </button>
            </form>
            
    <script>
        document.getElementById('togglePassword').addEventListener('click',function(){
            const pw=document.getElementById('password');
            const ic=document.getElementById('toggleIcon');
            if(pw.type==='password'){
                pw.type='text';
                ic.classList.remove('bi-eye');
                ic.classList.add('bi-eye-slash');
            }else{
                pw.type='password';
                ic.classList.remove('bi-eye-slash');
                ic.classList.add('bi-eye');
            }
        });
        
        document.getElementById('loginForm').addEventListener('submit',function(){
            const btn=this.querySelector('.btn-login');
            btn.disabled=true;
            btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Ingresando...';
        });
        
        function createParticle(){
            const p=document.createElement('div');
            p.style.cssText='position:fixed;width:3px;height:3px;background:rgba(102,126,234,0.4);border-radius:50%;pointer-events:none;z-index:0;left:'+Math.random()*window.innerWidth+'px;top:-10px;opacity:0';
            document.body.appendChild(p);
            const a=p.animate([
                {transform:'translateY(0px)',opacity:0},
                {transform:'translateY(100px)',opacity:0.6},
                {transform:'translateY('+window.innerHeight+'px)',opacity:0}
            ],{
                duration:4000+Math.random()*2000,
                easing:'linear'
            });
            a.onfinish=()=>p.remove();
        }
        
        setInterval(()=>{
            if(Math.random()>0.8){
                createParticle();
            }
        },500);
    </script>
</body>
</html>