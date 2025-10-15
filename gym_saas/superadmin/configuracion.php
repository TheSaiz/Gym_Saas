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

$superadmin_id = filter_var($_SESSION['superadmin_id'], FILTER_VALIDATE_INT);
if($superadmin_id === false){
    session_destroy();
    header("Location: $base_url/superadmin/login.php");
    exit;
}

// Obtener datos del superadmin
$stmt = $conn->prepare("SELECT * FROM superadmin WHERE id = ?");
$stmt->bind_param("i", $superadmin_id);
$stmt->execute();
$superadmin = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Obtener configuración global
$stmt = $conn->prepare("SELECT * FROM config");
$stmt->execute();
$config_result = $stmt->get_result();
$config = [];
while ($row = $config_result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Configuración - SuperAdmin</title>
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

        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--navbar-height);
            padding: 2rem;
            min-height: calc(100vh - var(--navbar-height));
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

        .upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            background: rgba(255, 255, 255, 0.03);
            transition: all 0.3s ease;
            cursor: pointer;
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

        .alert-info {
            background: rgba(77, 171, 247, 0.1);
            border: 1px solid rgba(77, 171, 247, 0.3);
            color: var(--info);
        }

        .text-muted {
            color: rgba(255, 255, 255, 0.5) !important;
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
        <h2><i class="fas fa-cog me-2"></i>Configuración</h2>
    </div>
</nav>

<main class="main-content">

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_GET['success'] == 1 ? 'Configuración actualizada correctamente' : 'Perfil actualizado correctamente'; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php 
            if ($_GET['error'] == 1) echo 'Error al actualizar la configuración';
            elseif ($_GET['error'] == 2) echo 'Error al subir la imagen';
            elseif ($_GET['error'] == 3) echo 'Formato de imagen no válido';
            else echo htmlspecialchars($_GET['error']);
            ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Perfil del SuperAdmin -->
    <div class="config-section">
        <h5 class="section-title">
            <i class="fas fa-user-circle"></i>
            Mi Perfil
        </h5>
        <form action="procesar_config.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="perfil">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Usuario</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($superadmin['usuario']); ?>" readonly>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre Completo</label>
                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($superadmin['nombre']); ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($superadmin['email']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nueva Contraseña (dejar vacío para no cambiar)</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••">
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save me-2"></i>Guardar Perfil
                </button>
            </div>
        </form>
    </div>

    <!-- Configuración del Sitio -->
    <div class="config-section">
        <h5 class="section-title">
            <i class="fas fa-globe"></i>
            Configuración del Sitio Público
        </h5>
        <form action="procesar_config.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="sitio">
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre del Sitio</label>
                    <input type="text" name="nombre_sitio" class="form-control" value="<?php echo htmlspecialchars($config['nombre_sitio'] ?? 'Gimnasio System SAAS'); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email de Contacto</label>
                    <input type="email" name="contacto_email" class="form-control" value="<?php echo htmlspecialchars($config['contacto_email'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono de Contacto</label>
                    <input type="text" name="contacto_telefono" class="form-control" value="<?php echo htmlspecialchars($config['contacto_telefono'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Texto del Footer</label>
                    <input type="text" name="footer_texto" class="form-control" value="<?php echo htmlspecialchars($config['footer_texto'] ?? ''); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Logo del Sitio</label>
                    <div class="upload-area">
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <small class="text-muted d-block mt-2">Formatos: JPG, PNG, GIF. Máx: 2MB</small>
                    </div>
                    <?php if (!empty($superadmin['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($superadmin['logo']); ?>" alt="Logo actual" class="preview-img">
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Favicon del Sitio</label>
                    <div class="upload-area">
                        <input type="file" name="favicon" class="form-control" accept="image/*">
                        <small class="text-muted d-block mt-2">Formatos: JPG, PNG, ICO. Máx: 1MB</small>
                    </div>
                    <?php if (!empty($superadmin['favicon'])): ?>
                        <img src="<?php echo htmlspecialchars($superadmin['favicon']); ?>" alt="Favicon actual" class="preview-img">
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save me-2"></i>Guardar Configuración
                </button>
            </div>
        </form>
    </div>

    <!-- Configuración de MercadoPago -->
    <div class="config-section">
        <h5 class="section-title">
            <i class="fas fa-credit-card"></i>
            Configuración de MercadoPago
        </h5>
        <form action="procesar_config.php" method="POST">
            <input type="hidden" name="action" value="mercadopago">
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Estas credenciales serán usadas para cobrar las licencias de los gimnasios.
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Public Key</label>
                    <input type="text" name="mp_public_key" class="form-control" value="<?php echo htmlspecialchars($superadmin['mp_public_key'] ?? ''); ?>" placeholder="APP_USR-xxxxx">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Access Token</label>
                    <input type="text" name="mp_access_token" class="form-control" value="<?php echo htmlspecialchars($superadmin['mp_access_token'] ?? ''); ?>" placeholder="APP_USR-xxxxx">
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save me-2"></i>Guardar Credenciales
                </button>
            </div>
        </form>
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
</script>

</body>
</html>
