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

// Cargar SDK de MercadoPago
require_once(__DIR__ . "/../vendor/autoload.php");

// Importar clases necesarias
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

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
$stmt = $conn->prepare("SELECT nombre, mp_token FROM gimnasios WHERE id = ? AND estado = 'activo' LIMIT 1");
$stmt->bind_param("i", $gimnasio_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows !== 1){
    session_destroy();
    header("Location: $base_url/login.php");
    exit;
}

$gimnasio_data = $result->fetch_assoc();
$gimnasio_nombre = htmlspecialchars($gimnasio_data['nombre'], ENT_QUOTES, 'UTF-8');
$mp_access_token = $gimnasio_data['mp_token'];
$stmt->close();

// Variables
$mensaje = '';
$mensaje_tipo = '';
$payment_status = $_GET['status'] ?? '';

// Procesar retorno de MercadoPago
if(!empty($payment_status)){
    switch($payment_status){
        case 'success':
            $mensaje = "¡Pago procesado exitosamente! Tu licencia ha sido renovada.";
            $mensaje_tipo = "success";
            break;
        case 'pending':
            $mensaje = "El pago está pendiente de aprobación. Te notificaremos cuando se confirme.";
            $mensaje_tipo = "warning";
            break;
        case 'failure':
            $mensaje = "El pago no pudo ser procesado. Por favor, intenta nuevamente.";
            $mensaje_tipo = "danger";
            break;
    }
}

// Obtener licencias disponibles
$stmt = $conn->prepare("SELECT id, nombre, precio, dias, estado FROM licencias WHERE estado = 'activo' ORDER BY precio ASC");
$stmt->execute();
$licencias_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Verificar si hay MercadoPago configurado
$mp_configurado = !empty($mp_access_token);

// Procesar pago
if($_SERVER['REQUEST_METHOD'] === 'POST' && $mp_configurado){
    
    // Validar token CSRF
    if(!isset($_POST['form_token']) || $_POST['form_token'] !== ($_SESSION['form_token'] ?? '')){
        $mensaje = "Solicitud inválida. Por favor, intenta nuevamente.";
        $mensaje_tipo = "danger";
    } else {
        
        $licencia_id = filter_var($_POST['licencia_id'] ?? 0, FILTER_VALIDATE_INT);
        
        if($licencia_id === false || $licencia_id <= 0){
            $mensaje = "Licencia no válida.";
            $mensaje_tipo = "danger";
        } else {
            
            // Obtener datos de la licencia
            $stmt = $conn->prepare("SELECT nombre, precio, dias FROM licencias WHERE id = ? AND estado = 'activo' LIMIT 1");
            $stmt->bind_param("i", $licencia_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows === 1){
                $licencia = $result->fetch_assoc();
                $stmt->close();
                
                // Registrar pago pendiente en la base de datos
                $stmt = $conn->prepare("INSERT INTO pagos (tipo, usuario_id, gimnasio_id, monto, estado) 
                                       VALUES ('licencia_gimnasio', NULL, ?, ?, 'pendiente')");
                $stmt->bind_param("id", $gimnasio_id, $licencia['precio']);
                
                if($stmt->execute()){
                    $pago_id = $conn->insert_id;
                    $stmt->close();
                    
                    try {
                        // Configurar MercadoPago con el nuevo SDK
                        MercadoPagoConfig::setAccessToken($mp_access_token);
                        
                        // URLs de retorno
                        $site_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                        
                        // Crear preferencia
                        $preference_data = [
                            "items" => [
                                [
                                    "id" => "LIC-" . $licencia_id,
                                    "title" => "Licencia: " . $licencia['nombre'],
                                    "description" => "Licencia de " . $licencia['dias'] . " días para " . $gimnasio_nombre,
                                    "quantity" => 1,
                                    "currency_id" => "ARS",
                                    "unit_price" => (float)$licencia['precio']
                                ]
                            ],
                            "back_urls" => [
                                "success" => $site_url . $base_url . "/gimnasios/checkout.php?status=success&payment_id=" . $pago_id,
                                "failure" => $site_url . $base_url . "/gimnasios/checkout.php?status=failure&payment_id=" . $pago_id,
                                "pending" => $site_url . $base_url . "/gimnasios/checkout.php?status=pending&payment_id=" . $pago_id
                            ],
                            "auto_return" => "approved",
                            "external_reference" => "GYM-" . $gimnasio_id . "-" . $pago_id,
                            "payer" => [
                                "email" => "gimnasio" . $gimnasio_id . "@sistema.com"
                            ],
                            "notification_url" => $site_url . $base_url . "/gimnasios/notificar_pago.php",
                            "statement_descriptor" => "GIMNASIO-" . strtoupper(substr($gimnasio_nombre, 0, 10))
                        ];
                        
                        // Crear cliente de preferencias
                        $client = new PreferenceClient();
                        $preference = $client->create($preference_data);
                        
                        // Actualizar pago con ID de MercadoPago
                        $stmt = $conn->prepare("UPDATE pagos SET mp_id = ? WHERE id = ?");
                        $mp_preference_id = $preference->id;
                        $stmt->bind_param("si", $mp_preference_id, $pago_id);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Redireccionar a MercadoPago
                        header("Location: " . $preference->init_point);
                        exit;
                        
                    } catch (MPApiException $e) {
                        $mensaje = "Error de MercadoPago: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                        $mensaje_tipo = "danger";
                        error_log("Error MPApiException: " . $e->getMessage());
                        error_log("Status code: " . $e->getApiResponse()->getStatusCode());
                        error_log("Content: " . json_encode($e->getApiResponse()->getContent()));
                    } catch (Exception $e) {
                        $mensaje = "Error al procesar el pago: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                        $mensaje_tipo = "danger";
                        error_log("Error Exception: " . $e->getMessage());
                    }
                    
                } else {
                    $mensaje = "Error al registrar el pago. Por favor, intenta nuevamente.";
                    $mensaje_tipo = "danger";
                    $stmt->close();
                }
                
            } else {
                $mensaje = "Licencia no encontrada o inactiva.";
                $mensaje_tipo = "danger";
                if($result) $stmt->close();
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
    <title>Checkout - Renovar Licencia - <?= $gimnasio_nombre ?></title>
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
        
        .pricing-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .pricing-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        }
        
        .pricing-card.featured {
            border: 2px solid var(--primary);
            background: rgba(102, 126, 234, 0.1);
        }
        
        .pricing-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .pricing-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: #fff;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .pricing-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.5rem;
        }
        
        .pricing-price {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
        }
        
        .pricing-period {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .pricing-features {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
            flex: 1;
        }
        
        .pricing-features li {
            padding: 0.8rem 0;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }
        
        .pricing-features li i {
            color: var(--success);
            font-size: 1.1rem;
        }
        
        .btn-checkout {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 600;
            color: #fff;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
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
        
        .alert-warning {
            background: rgba(255, 212, 59, 0.15);
            color: var(--warning);
            border: 1px solid rgba(255, 212, 59, 0.3);
        }
        
        .alert-danger {
            background: rgba(255, 107, 107, 0.15);
            color: var(--danger);
            border: 1px solid rgba(255, 107, 107, 0.3);
        }
        
        .mp-logo {
            max-width: 150px;
            margin: 2rem auto;
            display: block;
            opacity: 0.8;
        }
        
        .security-badge {
            background: rgba(81, 207, 102, 0.1);
            border: 1px solid rgba(81, 207, 102, 0.3);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin-top: 2rem;
        }
        
        .security-badge i {
            font-size: 2.5rem;
            color: var(--success);
            margin-bottom: 1rem;
        }
        
        .no-mp-warning {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
        }
        
        .no-mp-warning i {
            font-size: 3rem;
            color: var(--danger);
            margin-bottom: 1rem;
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
            <i class="fas fa-shopping-cart me-3"></i>
            Renovar Licencia
        </h2>
    </div>
    
    <?php if(!empty($mensaje)): ?>
        <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?= $mensaje_tipo === 'success' ? 'check-circle' : ($mensaje_tipo === 'warning' ? 'exclamation-triangle' : 'times-circle') ?> me-2"></i>
            <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($mp_configurado): ?>
        
        <div class="row g-4">
            <?php foreach($licencias_disponibles as $index => $licencia): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="pricing-card <?= $index === 1 ? 'featured' : '' ?>">
                        <div class="pricing-header">
                            <?php if($index === 1): ?>
                                <div class="pricing-badge">
                                    <i class="fas fa-star me-1"></i> Más Popular
                                </div>
                            <?php endif; ?>
                            
                            <div class="pricing-name"><?= htmlspecialchars($licencia['nombre'], ENT_QUOTES, 'UTF-8') ?></div>
                            
                            <div class="pricing-price">
                                $<?= number_format($licencia['precio'], 0, ',', '.') ?>
                            </div>
                            
                            <div class="pricing-period">
                                Por <?= $licencia['dias'] ?> días
                                <?php 
                                    if($licencia['dias'] == 30) echo '(≈ 1 mes)';
                                    elseif($licencia['dias'] == 60) echo '(≈ 2 meses)';
                                    elseif($licencia['dias'] == 90) echo '(≈ 3 meses)';
                                    elseif($licencia['dias'] == 365) echo '(≈ 1 año)';
                                ?>
                            </div>
                        </div>
                        
                        <ul class="pricing-features">
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Acceso completo al sistema</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Socios ilimitados</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Gestión de membresías</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Validador QR</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Reportes y estadísticas</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span>Soporte técnico</span>
                            </li>
                            <?php if($licencia['dias'] >= 365): ?>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span><strong>Ahorro del 20%</strong></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                        
                        <form method="POST">
                            <input type="hidden" name="form_token" value="<?= $_SESSION['form_token'] ?>">
                            <input type="hidden" name="licencia_id" value="<?= $licencia['id'] ?>">
                            <button type="submit" class="btn-checkout">
                                <i class="fas fa-lock me-2"></i>
                                Pagar con MercadoPago
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="security-badge">
            <i class="fas fa-shield-alt"></i>
            <h5 style="color: rgba(255,255,255,0.9); font-weight: 600; margin-bottom: 0.5rem;">
                Pago 100% Seguro
            </h5>
            <p style="color: rgba(255,255,255,0.7); margin: 0;">
                Procesado por MercadoPago con la máxima seguridad. No almacenamos datos de tarjetas.
            </p>
            <img src="https://http2.mlstatic.com/storage/logos-api-admin/a5f047d0-9be0-11ec-aad4-c3381f368aaf-xl@2x.png" 
                 alt="MercadoPago" class="mp-logo">
        </div>
        
    <?php else: ?>
        
        <div class="no-mp-warning">
            <i class="fas fa-exclamation-circle"></i>
            <h3 style="color: rgba(255,255,255,0.9); font-weight: 600; margin-bottom: 1rem;">
                MercadoPago no configurado
            </h3>
            <p style="color: rgba(255,255,255,0.7); font-size: 1.1rem; margin-bottom: 1.5rem;">
                Para poder procesar pagos, necesitas configurar tu token de acceso de MercadoPago.
            </p>
            <a href="<?= $base_url ?>/gimnasios/configuracion.php" class="btn btn-primary">
                <i class="fas fa-cog me-2"></i>
                Ir a Configuración
            </a>
        </div>
        
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
