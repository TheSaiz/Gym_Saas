<?php
require 'db.php';
requireAuth('superadmin');

$cfgfile = __DIR__ . '/config.json';
$uploadsDir = __DIR__ . '/uploads';
if(!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

// Cargar configuración existente
$config = [
    'site_name' => 'Barbería', 
    'logo' => '',
    'email_cierre' => '',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',
    'smtp_user' => '',
    'smtp_password' => '',
    'smtp_from_email' => '',
    'smtp_from_name' => 'Sistema Barbería'
];

if(file_exists($cfgfile)){
    $json = file_get_contents($cfgfile);
    $tmp = json_decode($json, true);
    if(is_array($tmp)) $config = array_merge($config, $tmp);
}

$msg = '';
$errors = [];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido';
    } else {
        // Nombre del sitio
        $site_name = trim($_POST['site_name'] ?? '');
        if($site_name === ''){
            $errors[] = "El nombre del sitio no puede estar vacío.";
        } else {
            $config['site_name'] = $site_name;
        }
        
        // Email para cierre de día
        $email_cierre = trim($_POST['email_cierre'] ?? '');
        if(!empty($email_cierre) && !filter_var($email_cierre, FILTER_VALIDATE_EMAIL)){
            $errors[] = "El formato del email de cierre no es válido.";
        } else {
            $config['email_cierre'] = $email_cierre;
        }

        // Configuración SMTP
        $config['smtp_host'] = trim($_POST['smtp_host'] ?? 'smtp.gmail.com');
        $config['smtp_port'] = intval($_POST['smtp_port'] ?? 587);
        $config['smtp_secure'] = trim($_POST['smtp_secure'] ?? 'tls');
        $config['smtp_user'] = trim($_POST['smtp_user'] ?? '');
        $config['smtp_password'] = trim($_POST['smtp_password'] ?? '');
        $config['smtp_from_email'] = trim($_POST['smtp_from_email'] ?? '');
        $config['smtp_from_name'] = trim($_POST['smtp_from_name'] ?? 'Sistema Barbería');

        // Validar email SMTP
        if(!empty($config['smtp_user']) && !filter_var($config['smtp_user'], FILTER_VALIDATE_EMAIL)){
            $errors[] = "El formato del email SMTP no es válido.";
        }

        if(!empty($config['smtp_from_email']) && !filter_var($config['smtp_from_email'], FILTER_VALIDATE_EMAIL)){
            $errors[] = "El formato del email remitente no es válido.";
        }

        // Manejo de logo (opcional)
        if(isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE){
            $file = $_FILES['logo'];
            if($file['error'] !== UPLOAD_ERR_OK){
                $errors[] = "Error al subir el archivo.";
            } else {
                if($file['size'] > 2 * 1024 * 1024){
                    $errors[] = "El archivo excede el tamaño máximo de 2 MB.";
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);

                    $allowed = [
                        'image/png' => 'png',
                        'image/jpeg' => 'jpg',
                        'image/jpg' => 'jpg',
                        'image/webp' => 'webp'
                    ];

                    if(!array_key_exists($mime, $allowed)){
                        $errors[] = "Formato no permitido. Usa PNG, JPG o WEBP.";
                    } else {
                        $ext = $allowed[$mime];
                        $basename = 'logo_' . time() . '.' . $ext;
                        $target = $uploadsDir . '/' . $basename;
                        if(!move_uploaded_file($file['tmp_name'], $target)){
                            $errors[] = "No se pudo guardar el archivo del logo.";
                        } else {
                            if(!empty($config['logo']) && file_exists($uploadsDir . '/' . $config['logo'])){
                                @unlink($uploadsDir . '/' . $config['logo']);
                            }
                            $config['logo'] = $basename;
                        }
                    }
                }
            }
        }

        // Si no hay errores, guardar config
        if(empty($errors)){
            file_put_contents($cfgfile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $msg = "Configuración guardada correctamente.";
            logActivity($pdo, 'config_updated', 'Configuración actualizada');
        }
    }
}

$csrfToken = generateCsrfToken();
$pageTitle = 'Configuración del Sistema';
include 'header.php';
include 'sidebar_super.php';
?>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="fas fa-cog"></i> Configuración del Sistema</h3>
            <p class="text-muted mb-0" style="color:rgba(255,255,255,0.6)!important;">
                <i class="bi bi-info-circle"></i> Ajustes generales de la aplicación
            </p>
        </div>
    </div>

    <?php if($msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <ul class="mb-0">
                <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-sliders me-2"></i>
                    <strong>Configuración General</strong>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        
                        <h5 class="mb-3" style="color: #667eea;">
                            <i class="bi bi-building me-2"></i>Información del Sitio
                        </h5>
                        
                        <div class="mb-4">
                            <label class="form-label">Nombre del Sitio</label>
                            <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($config['site_name']) ?>" required>
                            <small class="text-muted">Nombre que aparecerá en el sistema</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Email para Cierre de Día</label>
                            <input type="email" name="email_cierre" class="form-control" value="<?= htmlspecialchars($config['email_cierre']) ?>" placeholder="admin@barberia.com">
                            <small class="text-muted">Email donde se enviará el resumen diario automáticamente</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Logo Actual</label>
                            <div class="mb-3">
                                <?php if(!empty($config['logo']) && file_exists($uploadsDir . '/' . $config['logo'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($config['logo']) ?>" alt="Logo" style="max-height:120px; border-radius: 0.75rem; border: 2px solid rgba(255,255,255,0.1);">
                                <?php else: ?>
                                    <div class="text-muted" style="padding: 2rem; background: rgba(255,255,255,0.05); border-radius: 0.75rem; text-align: center;">
                                        <i class="bi bi-image" style="font-size: 3rem; color: #cbd5e1;"></i>
                                        <p class="mt-2 mb-0">No hay logo cargado</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Subir Nuevo Logo</label>
                            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="form-control">
                            <small class="text-muted">Formatos: PNG, JPG, WEBP - Tamaño máximo: 2 MB</small>
                        </div>

                        <hr style="border-color: rgba(255,255,255,0.2); margin: 2rem 0;">

                        <h5 class="mb-3" style="color: #10b981;">
                            <i class="bi bi-envelope-at me-2"></i>Configuración SMTP (Gmail)
                        </h5>

                        <div class="alert alert-info mb-4" style="background: rgba(74, 171, 247, 0.1); border: 1px solid rgba(74, 171, 247, 0.3);">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Importante:</strong> Para usar Gmail, debes generar una "Contraseña de aplicación" en tu cuenta de Google.
                            <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color: #4dabf7; text-decoration: underline;">Crear contraseña aquí</a>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Servidor SMTP</label>
                                <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($config['smtp_host']) ?>" placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Puerto</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($config['smtp_port']) ?>" placeholder="587">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Seguridad</label>
                            <select name="smtp_secure" class="form-select">
                                <option value="tls" <?= $config['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS (Recomendado para Gmail)</option>
                                <option value="ssl" <?= $config['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-person me-1"></i>Usuario SMTP (Email de Gmail)
                            </label>
                            <input type="email" name="smtp_user" class="form-control" value="<?= htmlspecialchars($config['smtp_user']) ?>" placeholder="tu_email@gmail.com">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-key me-1"></i>Contraseña SMTP (Contraseña de Aplicación)
                            </label>
                            <input type="password" name="smtp_password" class="form-control" value="<?= htmlspecialchars($config['smtp_password']) ?>" placeholder="••••••••••••••••">
                            <small class="text-muted">Contraseña de aplicación generada en Google</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email Remitente</label>
                            <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars($config['smtp_from_email']) ?>" placeholder="tu_email@gmail.com">
                            <small class="text-muted">Debe ser el mismo email de usuario SMTP</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Nombre del Remitente</label>
                            <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($config['smtp_from_name']) ?>" placeholder="Sistema Barbería">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Guardar Configuración
                            </button>
                            <a href="dashboard_super.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle me-2"></i>Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Guía de Configuración Gmail</strong>
                </div>
                <div class="card-body">
                    <h6 style="color: #667eea;">Pasos para configurar Gmail:</h6>
                    <ol class="text-muted" style="font-size: 0.875rem; padding-left: 1.25rem;">
                        <li class="mb-2">Activa la verificación en dos pasos en tu cuenta de Google</li>
                        <li class="mb-2">Ve a <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color: #4dabf7;">Contraseñas de aplicaciones</a></li>
                        <li class="mb-2">Genera una nueva contraseña de aplicación</li>
                        <li class="mb-2">Copia la contraseña generada (16 caracteres)</li>
                        <li class="mb-2">Pégala en el campo "Contraseña SMTP"</li>
                    </ol>

                    <hr style="border-color: rgba(255,255,255,0.1);">

                    <div class="alert alert-warning" style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b;">
                        <i class="bi bi-shield-exclamation me-2"></i>
                        <strong>Seguridad:</strong> Nunca uses tu contraseña personal de Gmail. Siempre usa contraseñas de aplicación.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>