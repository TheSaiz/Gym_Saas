<?php
session_start();

// Configuración de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Regenerar ID de sesión si es necesario
if (!isset($_SESSION['regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['regenerated'] = true;
}

include_once(__DIR__ . "/../includes/db_connect.php");

$base_url = "/gym_saas";

// Verificar sesión con seguridad mejorada
if(!isset($_SESSION['gimnasio_id']) || empty($_SESSION['gimnasio_id'])){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Sanitizar y validar ID de gimnasio
$gimnasio_id = filter_var($_SESSION['gimnasio_id'], FILTER_VALIDATE_INT);
if($gimnasio_id === false || $gimnasio_id <= 0){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

// Verificar que el gimnasio está activo
$stmt = $conn->prepare("SELECT estado FROM gimnasios WHERE id = ? AND estado = 'activo' LIMIT 1");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}
$stmt->close();

// Variables
$staff = null;
$mensaje = '';
$mensaje_tipo = '';
$id = null;

// Obtener ID para edición
if(isset($_GET['id'])){
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if($id !== false && $id > 0){
        $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ? AND gimnasio_id = ? LIMIT 1");
        $stmt->bind_param("ii", $id, $gimnasio_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows === 1){
            $staff = $result->fetch_assoc();
        } else {
            $mensaje = "Miembro del staff no encontrado o no tienes permisos para editarlo.";
            $mensaje_tipo = "danger";
            $id = null;
        }
        $stmt->close();
    } else {
        $id = null;
    }
}

// Procesar formulario
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    
    // Validar token CSRF
    if(!isset($_POST['form_token']) || $_POST['form_token'] !== ($_SESSION['form_token'] ?? '')){
        $mensaje = "Solicitud inválida. Por favor, intenta nuevamente.";
        $mensaje_tipo = "danger";
    } else {
        
        // Sanitizar y validar datos
        $nombre = trim($_POST['nombre'] ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol = $_POST['rol'] ?? 'validador';
        $estado = $_POST['estado'] ?? 'activo';
        
        // Validaciones
        $errores = [];
        
        if(empty($nombre) || strlen($nombre) < 3 || strlen($nombre) > 100){
            $errores[] = "El nombre debe tener entre 3 y 100 caracteres.";
        }
        
        if(empty($usuario) || strlen($usuario) < 4 || strlen($usuario) > 100){
            $errores[] = "El usuario debe tener entre 4 y 100 caracteres.";
        }
        
        // Validar que el usuario solo contenga caracteres alfanuméricos y guiones bajos
        if(!empty($usuario) && !preg_match('/^[a-zA-Z0-9_]+$/', $usuario)){
            $errores[] = "El usuario solo puede contener letras, números y guiones bajos.";
        }
        
        if(!in_array($rol, ['validador', 'admin'])){
            $rol = 'validador';
        }
        
        if(!in_array($estado, ['activo', 'inactivo'])){
            $estado = 'activo';
        }
        
        // Validar contraseña para nuevos usuarios
        if(!$id && empty($password)){
            $errores[] = "La contraseña es obligatoria para nuevos miembros del staff.";
        }
        
        if(!empty($password) && strlen($password) < 6){
            $errores[] = "La contraseña debe tener al menos 6 caracteres.";
        }
        
        // Verificar usuario duplicado
        if(empty($errores)){
            if($id){
                // Editando: verificar que usuario no esté usado por otro
                $stmt = $conn->prepare("SELECT id FROM staff WHERE usuario = ? AND gimnasio_id = ? AND id != ? LIMIT 1");
                $stmt->bind_param("sii", $usuario, $gimnasio_id, $id);
            } else {
                // Creando: verificar que usuario no exista
                $stmt = $conn->prepare("SELECT id FROM staff WHERE usuario = ? AND gimnasio_id = ? LIMIT 1");
                $stmt->bind_param("si", $usuario, $gimnasio_id);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0){
                $errores[] = "Ya existe un miembro del staff con ese nombre de usuario.";
            }
            $stmt->close();
        }
        
        // Si hay errores, mostrarlos
        if(!empty($errores)){
            $mensaje = implode('<br>', $errores);
            $mensaje_tipo = "danger";
        } else {
            // Procesar según acción
            
            if($id){
                // EDITAR STAFF
                if(!empty($password)){
                    // Con cambio de contraseña (hash seguro)
                    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    $stmt = $conn->prepare("UPDATE staff SET 
                                          nombre = ?, 
                                          usuario = ?, 
                                          password = ?, 
                                          rol = ?, 
                                          estado = ?
                                          WHERE id = ? AND gimnasio_id = ?");
                    $stmt->bind_param("sssssii", 
                                     $nombre, 
                                     $usuario, 
                                     $password_hash, 
                                     $rol, 
                                     $estado, 
                                     $id, 
                                     $gimnasio_id);
                } else {
                    // Sin cambio de contraseña
                    $stmt = $conn->prepare("UPDATE staff SET 
                                          nombre = ?, 
                                          usuario = ?, 
                                          rol = ?, 
                                          estado = ?
                                          WHERE id = ? AND gimnasio_id = ?");
                    $stmt->bind_param("ssssii", 
                                     $nombre, 
                                     $usuario, 
                                     $rol, 
                                     $estado, 
                                     $id, 
                                     $gimnasio_id);
                }
                
                if($stmt->execute()){
                    $_SESSION['mensaje'] = "Miembro del staff actualizado correctamente.";
                    $_SESSION['mensaje_tipo'] = "success";
                    header("Location: staff.php");
                    exit;
                } else {
                    $mensaje = "Error al actualizar. Por favor, intenta nuevamente.";
                    $mensaje_tipo = "danger";
                }
                $stmt->close();
                
            } else {
                // CREAR NUEVO STAFF
                $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                $stmt = $conn->prepare("INSERT INTO staff 
                                      (gimnasio_id, nombre, usuario, password, rol, estado) 
                                      VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", 
                                 $gimnasio_id, 
                                 $nombre, 
                                 $usuario, 
                                 $password_hash, 
                                 $rol, 
                                 $estado);
                
                if($stmt->execute()){
                    $_SESSION['mensaje'] = "Miembro del staff agregado correctamente.";
                    $_SESSION['mensaje_tipo'] = "success";
                    header("Location: staff.php");
                    exit;
                } else {
                    $mensaje = "Error al agregar al staff. Por favor, intenta nuevamente.";
                    $mensaje_tipo = "danger";
                }
                $stmt->close();
            }
        }
    }
}

// Generar token CSRF
$_SESSION['form_token'] = bin2hex(random_bytes(32));

include_once(__DIR__ . "/sidebar.php");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= $id ? 'Editar' : 'Agregar' ?> Staff - Gimnasio System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        .content-wrapper {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .form-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
        }
        
        .form-label {
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .input-group-text {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.7);
            border-radius: 12px 0 0 12px;
        }
        
        .input-group .form-control {
            border-radius: 0 12px 12px 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
        }
        
        .alert {
            border-radius: 12px;
            border: none;
            backdrop-filter: blur(10px);
        }
        
        .alert-success {
            background: rgba(81, 207, 102, 0.15);
            color: var(--success);
            border: 1px solid rgba(81, 207, 102, 0.3);
        }
        
        .alert-danger {
            background: rgba(255, 107, 107, 0.15);
            color: var(--danger);
            border: 1px solid rgba(255, 107, 107, 0.3);
        }
        
        .required::after {
            content: ' *';
            color: var(--danger);
        }
        
        .role-info {
            background: rgba(77, 171, 247, 0.1);
            border: 1px solid rgba(77, 171, 247, 0.3);
            border-radius: 12px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .role-info .info-item {
            display: flex;
            align-items: start;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
        }
        
        .role-info .info-item:last-child {
            margin-bottom: 0;
        }
        
        .role-info .icon {
            width: 24px;
            height: 24px;
            background: rgba(77, 171, 247, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 0.2rem;
        }
        
        .role-info .text {
            flex: 1;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .role-info .title {
            font-weight: 600;
            color: var(--info);
            margin-bottom: 0.3rem;
        }
        
        .password-strength {
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .password-strength-text {
            font-size: 0.85rem;
            margin-top: 0.3rem;
        }
        
        @media (max-width: 1024px) {
            .content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<div class="content-wrapper">
    <div class="page-header">
        <h2>
            <i class="fas fa-user-shield me-3"></i>
            <?= $id ? 'Editar' : 'Agregar Nuevo' ?> Miembro del Staff
        </h2>
    </div>
    
    <?php if(!empty($mensaje)): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $mensaje_tipo === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="form-card">
                <form method="POST" id="staffForm" novalidate>
                    <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
                    
                    <div class="mb-3">
                        <label class="form-label required">Nombre Completo</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" 
                                   name="nombre" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($staff['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                   placeholder="Ej: Juan Pérez"
                                   maxlength="100"
                                   required>
                        </div>
                        <div class="invalid-feedback">El nombre es obligatorio (mínimo 3 caracteres).</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required">Usuario</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-at"></i>
                            </span>
                            <input type="text" 
                                   name="usuario" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($staff['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                                   placeholder="Ej: juanperez o validador1"
                                   pattern="[a-zA-Z0-9_]+"
                                   maxlength="100"
                                   id="usuarioInput"
                                   required>
                        </div>
                        <div class="invalid-feedback">El usuario debe tener entre 4 y 100 caracteres (solo letras, números y guión bajo).</div>
                        <small class="text-muted d-block mt-1">Solo letras, números y guión bajo (_)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label <?= !$id ? 'required' : '' ?>">
                            Contraseña <?= $id ? '(dejar en blanco para no cambiar)' : '' ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" 
                                   name="password" 
                                   class="form-control" 
                                   placeholder="Mínimo 6 caracteres"
                                   minlength="6"
                                   id="passwordInput"
                                   <?= !$id ? 'required' : '' ?>>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">La contraseña debe tener al menos 6 caracteres.</div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <small class="password-strength-text text-muted" id="strengthText"></small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Rol</label>
                            <select name="rol" class="form-select" id="rolSelect">
                                <option value="validador" <?= (!isset($staff['rol']) || $staff['rol'] === 'validador') ? 'selected' : '' ?>>
                                    <i class="fas fa-qrcode"></i> Validador
                                </option>
                                <option value="admin" <?= (isset($staff['rol']) && $staff['rol'] === 'admin') ? 'selected' : '' ?>>
                                    <i class="fas fa-user-cog"></i> Administrador
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" class="form-select">
                                <option value="activo" <?= (!isset($staff['estado']) || $staff['estado'] === 'activo') ? 'selected' : '' ?>>
                                    <i class="fas fa-check-circle"></i> Activo
                                </option>
                                <option value="inactivo" <?= (isset($staff['estado']) && $staff['estado'] === 'inactivo') ? 'selected' : '' ?>>
                                    <i class="fas fa-times-circle"></i> Inactivo
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $id ? 'Actualizar' : 'Agregar' ?> Staff
                        </button>
                        <a href="staff.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="form-card">
                <h5 style="color: rgba(255,255,255,0.9); font-weight: 600; margin-bottom: 1.5rem;">
                    <i class="fas fa-info-circle me-2" style="color: var(--info);"></i>
                    Información de Roles
                </h5>
                
                <div class="role-info">
                    <div class="info-item">
                        <div class="icon">
                            <i class="fas fa-qrcode" style="color: var(--info); font-size: 0.9rem;"></i>
                        </div>
                        <div class="text">
                            <div class="title">Validador</div>
                            Puede validar el acceso de socios mediante QR, consultar estado de membresías y ver información básica.
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="icon">
                            <i class="fas fa-user-cog" style="color: var(--info); font-size: 0.9rem;"></i>
                        </div>
                        <div class="text">
                            <div class="title">Administrador</div>
                            Tiene acceso completo al panel: gestionar socios, membresías, staff, reportes y configuración.
                        </div>
                    </div>
                </div>
                
                <div class="mt-4" style="background: rgba(255, 212, 59, 0.1); border: 1px solid rgba(255, 212, 59, 0.3); border-radius: 12px; padding: 1rem;">
                    <div style="display: flex; align-items: start; gap: 0.8rem;">
                        <i class="fas fa-shield-alt" style="color: var(--warning); font-size: 1.5rem; margin-top: 0.2rem;"></i>
                        <div>
                            <div style="font-weight: 600; color: var(--warning); margin-bottom: 0.3rem;">Seguridad</div>
                            <div style="font-size: 0.9rem; color: rgba(255,255,255,0.8);">
                                Las contraseñas se almacenan encriptadas y no pueden ser recuperadas. Asegúrate de compartir las credenciales de forma segura.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        'use strict';
        
        const form = document.getElementById('staffForm');
        const usuarioInput = document.getElementById('usuarioInput');
        const passwordInput = document.getElementById('passwordInput');
        const togglePassword = document.getElementById('togglePassword');
        const toggleIcon = document.getElementById('toggleIcon');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        
        // Toggle password visibility
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if(type === 'text') {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
        
        // Password strength meter
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if(password.length >= 6) strength += 20;
            if(password.length >= 8) strength += 20;
            if(password.length >= 10) strength += 10;
            if(/[a-z]/.test(password)) strength += 15;
            if(/[A-Z]/.test(password)) strength += 15;
            if(/[0-9]/.test(password)) strength += 10;
            if(/[^a-zA-Z0-9]/.test(password)) strength += 10;
            
            strengthBar.style.width = strength + '%';
            
            if(strength < 30) {
                strengthBar.style.background = 'var(--danger)';
                strengthText.textContent = 'Débil';
                strengthText.style.color = 'var(--danger)';
            } else if(strength < 60) {
                strengthBar.style.background = 'var(--warning)';
                strengthText.textContent = 'Media';
                strengthText.style.color = 'var(--warning)';
            } else if(strength < 80) {
                strengthBar.style.background = 'var(--info)';
                strengthText.textContent = 'Buena';
                strengthText.style.color = 'var(--info)';
            } else {
                strengthBar.style.background = 'var(--success)';
                strengthText.textContent = 'Fuerte';
                strengthText.style.color = 'var(--success)';
            }
            
            if(password.length === 0) {
                strengthBar.style.width = '0%';
                strengthText.textContent = '';
            }
        });
        
        // Validar caracteres del usuario
        usuarioInput.addEventListener('input', function() {
            // Eliminar caracteres no permitidos
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
            
            // Convertir a minúsculas para consistencia
            this.value = this.value.toLowerCase();
        });
        
        // Prevenir espacios en usuario
        usuarioInput.addEventListener('keypress', function(e) {
            if(e.key === ' ') {
                e.preventDefault();
            }
        });
        
        // Validación del formulario
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Validación adicional de usuario
            const usuario = usuarioInput.value.trim();
            if(usuario.length < 4) {
                event.preventDefault();
                usuarioInput.setCustomValidity('El usuario debe tener al menos 4 caracteres');
            } else {
                usuarioInput.setCustomValidity('');
            }
            
            form.classList.add('was-validated');
        }, false);
        
        // Limpiar validación al escribir
        usuarioInput.addEventListener('input', function() {
            this.setCustomValidity('');
        });
        
    })();
</script>

</body>
</html>
