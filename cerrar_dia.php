<?php
require 'db.php';
requireAuth('recepcion');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    if (file_exists(__DIR__ . '/PHPMailer/src/Exception.php')) {
        require __DIR__ . '/PHPMailer/src/Exception.php';
        require __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require __DIR__ . '/PHPMailer/src/SMTP.php';
    }
}

$today = date('Y-m-d');

// Función para generar HTML del email RESPONSIVE
function generarHTMLEmail($fecha_formateada, $stats, $metodos_pago, $ventas_barberos, $todas_ventas) {
    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; background: #f4f7fa; padding: 20px; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { font-size: 26px; margin: 0 0 8px 0; font-weight: 700; }
            .header p { font-size: 16px; margin: 0; opacity: 0.95; }
            .content { padding: 30px 20px; }
            .section-title { font-size: 20px; color: #333; margin: 25px 0 15px 0; font-weight: 700; display: flex; align-items: center; }
            .section-title::before { content: ""; width: 4px; height: 24px; background: #667eea; margin-right: 10px; border-radius: 2px; }
            .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; }
            .stat-box { background: #f8f9fc; border-left: 4px solid #667eea; padding: 16px; border-radius: 8px; }
            .stat-box h3 { font-size: 13px; color: #666; margin: 0 0 8px 0; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
            .stat-box .value { font-size: 28px; font-weight: 700; color: #667eea; line-height: 1; }
            .table-responsive { overflow-x: auto; margin: 15px 0; }
            table { width: 100%; border-collapse: collapse; min-width: 100%; }
            th { background: #667eea; color: white; padding: 12px 10px; text-align: left; font-size: 13px; font-weight: 600; white-space: nowrap; }
            td { padding: 12px 10px; border-bottom: 1px solid #e5e7eb; font-size: 14px; }
            tr:last-child td { border-bottom: none; }
            tr:hover { background: #f9fafb; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
            .badge-success { background: #d1fae5; color: #065f46; }
            .badge-primary { background: #dbeafe; color: #1e40af; }
            .badge-warning { background: #fef3c7; color: #92400e; }
            .footer { background: #f8f9fc; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
            .footer p { margin: 5px 0; }
            .highlight { color: #667eea; font-weight: 700; }
            
            @media only screen and (max-width: 600px) {
                body { padding: 10px; }
                .container { border-radius: 8px; }
                .header { padding: 20px 15px; }
                .header h1 { font-size: 22px; }
                .header p { font-size: 14px; }
                .content { padding: 20px 15px; }
                .stats-grid { grid-template-columns: 1fr; }
                .stat-box .value { font-size: 24px; }
                .section-title { font-size: 18px; }
                th, td { padding: 10px 8px; font-size: 12px; }
                table { font-size: 12px; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✂️ Cierre de Día - Barbería</h1>
                <p>Fecha: ' . htmlspecialchars($fecha_formateada) . '</p>
            </div>
            
            <div class="content">
                <div class="section-title">📊 Resumen General</div>
                
                <div class="stats-grid">
                    <div class="stat-box">
                        <h3>Total Ventas</h3>
                        <div class="value">' . intval($stats['total_ventas']) . '</div>
                    </div>
                    <div class="stat-box">
                        <h3>Total Ingresos</h3>
                        <div class="value">$' . number_format(floatval($stats['total_ingresos']), 2) . '</div>
                    </div>
                </div>
                
                <div class="section-title">💳 Métodos de Pago</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>';
    
    $total_general = floatval($stats['total_ingresos']);
    $metodos = [
        ['name' => '💵 Efectivo', 'key' => 'efectivo'],
        ['name' => '💳 Tarjeta', 'key' => 'tarjeta'],
        ['name' => '🔄 Transferencia', 'key' => 'transferencia']
    ];
    
    foreach ($metodos as $m) {
        $valor = floatval($metodos_pago[$m['key']] ?? 0);
        $porcentaje = $total_general > 0 ? ($valor / $total_general * 100) : 0;
        $html .= '
                            <tr>
                                <td>' . $m['name'] . '</td>
                                <td class="text-right"><strong>$' . number_format($valor, 2) . '</strong></td>
                                <td class="text-right">' . number_format($porcentaje, 1) . '%</td>
                            </tr>';
    }
    
    $html .= '
                        </tbody>
                    </table>
                </div>';
    
    if (!empty($ventas_barberos)) {
        $html .= '
                <div class="section-title">👨‍💼 Comisiones por Barbero</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Barbero</th>
                                <th class="text-center">Ventas</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Comisión</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        foreach ($ventas_barberos as $vb) {
            $html .= '
                            <tr>
                                <td><strong>' . htmlspecialchars($vb['name']) . '</strong></td>
                                <td class="text-center"><span class="badge badge-primary">' . intval($vb['num_ventas']) . '</span></td>
                                <td class="text-right">$' . number_format(floatval($vb['total_ventas']), 2) . '</td>
                                <td class="text-right"><strong class="highlight">$' . number_format(floatval($vb['total_comisiones']), 2) . '</strong></td>
                            </tr>';
        }
        
        $html .= '
                        </tbody>
                    </table>
                </div>';
    }
    
    $html .= '
                <div class="section-title">📋 Detalle de Ventas</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hora</th>
                                <th>Método</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>';
    
    foreach ($todas_ventas as $venta) {
        $metodo_icons = [
            'efectivo' => '💵',
            'tarjeta' => '💳',
            'transferencia' => '🔄'
        ];
        $icon = $metodo_icons[$venta['payment_method']] ?? '💰';
        
        $html .= '
                            <tr>
                                <td><strong>#' . intval($venta['id']) . '</strong></td>
                                <td>' . date('H:i', strtotime($venta['created_at'])) . '</td>
                                <td>' . $icon . ' ' . ucfirst(htmlspecialchars($venta['payment_method'])) . '</td>
                                <td class="text-right"><strong>$' . number_format(floatval($venta['total']), 2) . '</strong></td>
                            </tr>';
    }
    
    $html .= '
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="footer">
                <p><strong>Sistema de Gestión de Barbería</strong></p>
                <p>Correo automático generado el ' . date('d/m/Y H:i:s') . '</p>
                <p style="color: #9ca3af; margin-top: 10px;">Este correo contiene información confidencial</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Función para enviar email con PHPMailer
function enviarEmailCierre($email_destino, $fecha, $stats, $metodos_pago, $ventas_barberos, $todas_ventas, $config) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'] ?? '';
        $mail->Password   = $config['smtp_password'] ?? '';
        $mail->SMTPSecure = ($config['smtp_secure'] ?? 'tls') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = intval($config['smtp_port'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $from_email = $config['smtp_from_email'] ?? $config['smtp_user'];
        $from_name = $config['smtp_from_name'] ?? 'Sistema Barbería';
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($email_destino);
        
        $mail->isHTML(true);
        
        $fecha_formateada = date('d/m/Y', strtotime($fecha));
        $mail->Subject = "✂️ Cierre de Día - Barbería - " . $fecha_formateada;
        
        $html = generarHTMLEmail($fecha_formateada, $stats, $metodos_pago, $ventas_barberos, $todas_ventas);
        $mail->Body = $html;
        
        $mail->AltBody = "Cierre del día " . $fecha_formateada . "\n\n" .
                        "Total Ventas: " . $stats['total_ventas'] . "\n" .
                        "Total Ingresos: $" . number_format($stats['total_ingresos'], 2);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Error al enviar email: " . $mail->ErrorInfo);
        throw new Exception($mail->ErrorInfo);
    }
}

// Procesar cierre de día
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cerrar_dia'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido';
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_ventas,
                    COALESCE(SUM(total), 0) as total_ingresos
                FROM sales 
                WHERE DATE(created_at) = ?
            ");
            $stmt->execute([$today]);
            $stats = $stmt->fetch();
            
            $stmt = $pdo->prepare("
                SELECT payment_method, COALESCE(SUM(total), 0) as total
                FROM sales 
                WHERE DATE(created_at) = ?
                GROUP BY payment_method
            ");
            $stmt->execute([$today]);
            $metodos_pago = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $stmt = $pdo->prepare("
                SELECT 
                    b.id,
                    b.name,
                    b.commission_percentage,
                    COUNT(bs.id) as num_ventas,
                    COALESCE(SUM(s.total), 0) as total_ventas,
                    COALESCE(SUM(bs.commission_amount), 0) as total_comisiones
                FROM barbers b
                LEFT JOIN barber_sales bs ON b.id = bs.barber_id
                LEFT JOIN sales s ON bs.sale_id = s.id AND DATE(s.created_at) = ?
                WHERE b.is_active = 1
                GROUP BY b.id, b.name, b.commission_percentage
                HAVING num_ventas > 0
                ORDER BY total_ventas DESC
            ");
            $stmt->execute([$today]);
            $ventas_barberos = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("
                SELECT id, total, items, payment_method, created_at
                FROM sales 
                WHERE DATE(created_at) = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$today]);
            $todas_ventas = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("
                INSERT INTO cierres_dia 
                (fecha, user_id, total_ventas, total_ingresos, efectivo, tarjeta, transferencia, ventas_detalle, barberos_detalle) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $today,
                $_SESSION['user_id'],
                $stats['total_ventas'],
                $stats['total_ingresos'],
                $metodos_pago['efectivo'] ?? 0,
                $metodos_pago['tarjeta'] ?? 0,
                $metodos_pago['transferencia'] ?? 0,
                json_encode($todas_ventas, JSON_UNESCAPED_UNICODE),
                json_encode($ventas_barberos, JSON_UNESCAPED_UNICODE)
            ]);
            
            $cierre_id = $pdo->lastInsertId();
            
            $config = array();
            $configFile = __DIR__ . '/config.json';
            if (file_exists($configFile)) {
                $config_json = file_get_contents($configFile);
                $config = json_decode($config_json, true);
                if (!is_array($config)) {
                    $config = array();
                }
            }
            
            $email_destino = $config['email_cierre'] ?? '';
            
            $email_enviado = false;
            $email_error = '';
            
            if (!empty($email_destino) && !empty($config['smtp_user']) && !empty($config['smtp_password'])) {
                try {
                    $email_enviado = enviarEmailCierre(
                        $email_destino,
                        $today,
                        $stats,
                        $metodos_pago,
                        $ventas_barberos,
                        $todas_ventas,
                        $config
                    );
                } catch (Exception $e) {
                    $email_error = $e->getMessage();
                    error_log('Error enviando email de cierre: ' . $email_error);
                }
            }
            
            $stmt = $pdo->prepare("UPDATE sales SET dia_cerrado = 1 WHERE DATE(created_at) = ?");
            $stmt->execute([$today]);
            
            $stmt = $pdo->prepare("UPDATE cierres_dia SET email_enviado = ? WHERE id = ?");
            $stmt->execute([$email_enviado ? 1 : 0, $cierre_id]);
            
            $pdo->commit();
            
            logActivity($pdo, 'cierre_dia', "Cierre del dia: {$today}. Email: " . ($email_enviado ? 'enviado' : 'no enviado'));
            
            if ($email_enviado) {
                $success = 'Día cerrado exitosamente. Email enviado correctamente.';
            } else {
                $success = 'Día cerrado exitosamente. ';
                if (!empty($email_error)) {
                    $success .= 'Error al enviar email: ' . htmlspecialchars($email_error);
                } elseif (empty($config['smtp_user'])) {
                    $success .= 'No se ha configurado el servidor SMTP. Contacta al administrador.';
                } else {
                    $success .= 'No se pudo enviar el email. Verifica la configuración SMTP.';
                }
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Cierre Dia Error: ' . $e->getMessage());
            $error = 'Error al cerrar el día: ' . $e->getMessage();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Cierre Dia Error: ' . $e->getMessage());
            $error = 'Error al cerrar el día: ' . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT id FROM cierres_dia WHERE fecha = ?");
    $stmt->execute([$today]);
    $dia_cerrado = $stmt->fetch();
} catch (PDOException $e) {
    $dia_cerrado = null;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            COALESCE(SUM(total), 0) as total_ingresos
        FROM sales 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch();
    
    $stats['total_ventas'] = intval($stats['total_ventas']);
    $stats['total_ingresos'] = floatval($stats['total_ingresos']);
    
    $stmt = $pdo->prepare("
        SELECT payment_method, COALESCE(SUM(total), 0) as total
        FROM sales 
        WHERE DATE(created_at) = ?
        GROUP BY payment_method
    ");
    $stmt->execute([$today]);
    $metodos_pago = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt = $pdo->prepare("
        SELECT 
            b.name,
            b.commission_percentage,
            COUNT(bs.id) as num_ventas,
            COALESCE(SUM(s.total), 0) as total_ventas,
            COALESCE(SUM(bs.commission_amount), 0) as total_comisiones
        FROM barbers b
        LEFT JOIN barber_sales bs ON b.id = bs.barber_id
        LEFT JOIN sales s ON bs.sale_id = s.id AND DATE(s.created_at) = ?
        WHERE b.is_active = 1
        GROUP BY b.id, b.name, b.commission_percentage
        HAVING num_ventas > 0
        ORDER BY total_ventas DESC
    ");
    $stmt->execute([$today]);
    $ventas_barberos = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Cerrar Dia Stats Error: ' . $e->getMessage());
    $stats = array('total_ventas' => 0, 'total_ingresos' => 0);
    $metodos_pago = array();
    $ventas_barberos = array();
}

$csrfToken = generateCsrfToken();
include 'header.php';
include 'sidebar_recep.php';
?>

<div class="content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">
                <i class="bi bi-door-closed"></i>
                Cerrar Día
            </h3>
            <p class="text-muted mb-0" style="color: rgba(255, 255, 255, 0.6) !important;">
                <i class="bi bi-calendar-check"></i>
                <?php echo date('l, d \d\e F \d\e Y'); ?>
            </p>
        </div>
        <a href="dashboard_recep.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>
            Volver al Dashboard
        </a>
    </div>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e($error); ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($dia_cerrado): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-circle me-2"></i>
            <strong>El día ya ha sido cerrado.</strong> No puedes cerrarlo nuevamente.
        </div>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-door-closed" style="font-size: 4rem; color: #667eea; margin-bottom: 1.5rem;"></i>
                <h4 class="mb-3">¿Deseas cerrar el día?</h4>
                <p class="text-muted mb-4">
                    Al cerrar el día se generará un resumen completo y se enviará por email.<br>
                    Esta acción no se puede deshacer.
                </p>
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#cerrarDiaModal">
                    <i class="bi bi-door-closed me-2"></i>
                    Cerrar Día
                </button>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-graph-up me-2"></i>
                    <strong>Resumen del Día</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="stat-card stat-card-success">
                                <div class="stat-card-title">Total Ingresos</div>
                                <div class="stat-card-value" data-target="<?php echo $stats['total_ingresos']; ?>" data-prefix="$" data-decimals="2">$0.00</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card stat-card-primary">
                                <div class="stat-card-title">Total Ventas</div>
                                <div class="stat-card-value" data-target="<?php echo $stats['total_ventas']; ?>" data-prefix="" data-decimals="0">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cerrarDiaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="background: #1e293b; border: 1px solid #334155;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-bottom: none;">
                <h5 class="modal-title" style="color: white; font-weight: 700;">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i>
                    Resumen de Cierre del Día
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 0.75rem; padding: 1.5rem;">
                            <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.7); margin-bottom: 0.5rem;">Total Ingresos</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #10b981;">$<?php echo number_format($stats['total_ingresos'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 0.75rem; padding: 1.5rem;">
                            <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.7); margin-bottom: 0.5rem;">Total Ventas</div>
                            <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['total_ventas']; ?></div>
                        </div>
                    </div>
                </div>
                
                <h6 class="mb-3" style="color: white; font-weight: 600;">
                    <i class="bi bi-credit-card me-2"></i>
                    Métodos de Pago
                </h6>
                <div class="table-responsive mb-4">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><i class="bi bi-cash-stack me-2 text-success"></i> Efectivo</td>
                                <td class="text-end"><strong class="text-success">$<?php echo number_format($metodos_pago['efectivo'] ?? 0, 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-credit-card me-2 text-primary"></i> Tarjeta</td>
                                <td class="text-end"><strong class="text-primary">$<?php echo number_format($metodos_pago['tarjeta'] ?? 0, 2); ?></strong></td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-arrow-left-right me-2 text-warning"></i> Transferencia</td>
                                <td class="text-end"><strong class="text-warning">$<?php echo number_format($metodos_pago['transferencia'] ?? 0, 2); ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <?php if(!empty($ventas_barberos)): ?>
                <h6 class="mb-3" style="color: white; font-weight: 600;">
                    <i class="bi bi-people-fill me-2"></i>
                    Comisiones por Barbero
                </h6>
                <div class="table-responsive mb-4">
                    <table class="table table-dark table-hover">
                        <thead>
                            <tr>
                                <th>Barbero</th>
                                <th class="text-center">Ventas</th>
                                <th class="text-end">Total Generado</th>
                                <th class="text-center">Comisión %</th>
                                <th class="text-end">A Cobrar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ventas_barberos as $vb): ?>
                            <tr>
                                <td><strong><?php echo e($vb['name']); ?></strong></td>
                                <td class="text-center"><span class="badge bg-info"><?php echo $vb['num_ventas']; ?></span></td>
                                <td class="text-end">$<?php echo number_format($vb['total_ventas'], 2); ?></td>
                                <td class="text-center"><span class="badge bg-warning text-dark"><?php echo number_format($vb['commission_percentage'], 2); ?>%</span></td>
                                <td class="text-end"><strong style="color: #10b981; font-size: 1.1rem;">$<?php echo number_format($vb['total_comisiones'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-warning" style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Atención:</strong> Al confirmar, se enviará este resumen por email y se cerrará el día. Esta acción no se puede deshacer.
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #334155;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>
                    Cancelar
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
                    <button type="submit" name="cerrar_dia" class="btn btn-danger">
                        <i class="bi bi-check-circle me-1"></i>
                        Confirmar Cierre
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function animateCounter(element) {
    const target = parseFloat(element.getAttribute('data-target'));
    const prefix = element.getAttribute('data-prefix') || '';
    const decimals = parseInt(element.getAttribute('data-decimals')) || 0;
    
    if (isNaN(target)) return;
    
    const duration = 1500;
    const start = 0;
    const increment = target / (duration / 16);
    let current = 0;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        
        if (decimals > 0) {
            element.textContent = prefix + current.toFixed(decimals);
        } else {
            element.textContent = prefix + Math.floor(current);
        }
    }, 16);
}

document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.stat-card-value[data-target]');
    counters.forEach((counter, index) => {
        setTimeout(() => {
            animateCounter(counter);
        }, index * 100);
    });
});
</script>
</body>
</html>