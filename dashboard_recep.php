<?php
require 'db.php';
requireAuth('recepcion');

$today = date('Y-m-d');

// Procesar apertura de día
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abrir_dia'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido';
    } else {
        try {
            // Verificar si el día anterior está cerrado
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $stmt = $pdo->prepare("SELECT id FROM cierres_dia WHERE fecha = ?");
            $stmt->execute([$yesterday]);
            $dia_anterior_cerrado = $stmt->fetch();
            
            // Verificar si ya se abrió el día hoy
            $stmt = $pdo->prepare("SELECT id FROM cierres_dia WHERE fecha = ? AND email_enviado = 2");
            $stmt->execute([$today]);
            $dia_ya_abierto = $stmt->fetch();
            
            if ($dia_ya_abierto) {
                $error = 'El día ya ha sido abierto anteriormente.';
            } else {
                $pdo->beginTransaction();
                
                // Crear registro de apertura (usando email_enviado = 2 como marcador de apertura)
                $stmt = $pdo->prepare("
                    INSERT INTO cierres_dia 
                    (fecha, user_id, total_ventas, total_ingresos, efectivo, tarjeta, transferencia, email_enviado, ventas_detalle, barberos_detalle) 
                    VALUES (?, ?, 0, 0, 0, 0, 0, 2, '[]', '[]')
                ");
                $stmt->execute([$today, $_SESSION['user_id']]);
                
                $pdo->commit();
                
                // Registrar actividad
                logActivity($pdo, 'apertura_dia', "Apertura del día: {$today}");
                
                $success = '¡Día abierto exitosamente! Puedes comenzar a registrar ventas.';
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Abrir Dia Error: ' . $e->getMessage());
            $error = 'Error al abrir el día: ' . $e->getMessage();
        }
    }
}

try {
    // Verificar si el día está abierto
    $stmt = $pdo->prepare("SELECT id, email_enviado FROM cierres_dia WHERE fecha = ?");
    $stmt->execute([$today]);
    $estado_dia = $stmt->fetch();
    
    $dia_abierto = $estado_dia && $estado_dia['email_enviado'] == 2;
    $dia_cerrado = $estado_dia && $estado_dia['email_enviado'] != 2;
    
    // Obtener estadísticas del día
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as total_dia,
            COUNT(*) as cortes_dia
        FROM sales 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $data = $stmt->fetch();
    
    $total_dia = floatval($data['total_dia'] ?? 0);
    $cortes_dia = intval($data['cortes_dia'] ?? 0);
    
    // Promedio por corte
    $promedio_corte = $cortes_dia > 0 ? ($total_dia / $cortes_dia) : 0;
    
    // Últimas 5 ventas del día
    $stmt = $pdo->prepare("
        SELECT s.id, s.total, s.items, s.created_at, b.name as barber_name
        FROM sales s
        LEFT JOIN barber_sales bs ON s.id = bs.sale_id
        LEFT JOIN barbers b ON bs.barber_id = b.id
        WHERE DATE(s.created_at) = ?
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $ultimas_ventas = $stmt->fetchAll();
    
    // Producto más vendido del día
    $stmt = $pdo->prepare("SELECT items FROM sales WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $ventas_del_dia = $stmt->fetchAll();
    
    $productos_count = [];
    foreach ($ventas_del_dia as $venta) {
        $items = json_decode($venta['items'], true);
        if (is_array($items)) {
            foreach ($items as $item) {
                $nombre = $item['name'] ?? 'Desconocido';
                if (!isset($productos_count[$nombre])) {
                    $productos_count[$nombre] = 0;
                }
                $productos_count[$nombre] += intval($item['qty'] ?? 1);
            }
        }
    }
    
    arsort($productos_count);
    $producto_mas_vendido = !empty($productos_count) ? array_key_first($productos_count) : 'N/A';
    $cantidad_mas_vendido = !empty($productos_count) ? reset($productos_count) : 0;
    
} catch (PDOException $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    $total_dia = $cortes_dia = $promedio_corte = 0;
    $ultimas_ventas = [];
    $producto_mas_vendido = 'N/A';
    $cantidad_mas_vendido = 0;
    $dia_abierto = false;
    $dia_cerrado = false;
}

$csrfToken = generateCsrfToken();
include 'header.php';
include 'sidebar_recep.php';
?>

<div class="content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard Recepción
            </h3>
            <p class="text-muted mb-0" style="color: rgba(255, 255, 255, 0.6) !important;">
                <i class="bi bi-calendar-check"></i>
                <?= date('l, d \d\e F \d\e Y') ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$dia_abierto && !$dia_cerrado): ?>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#abrirDiaModal">
                    <i class="bi bi-sunrise me-2"></i>
                    Abrir Día
                </button>
            <?php endif; ?>
            
            <?php if ($dia_abierto): ?>
                <span class="badge bg-success d-flex align-items-center" style="font-size: 0.9rem; padding: 0.6rem 1rem;">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Día Abierto
                </span>
            <?php endif; ?>
            
            <?php if ($dia_cerrado): ?>
                <span class="badge bg-danger d-flex align-items-center" style="font-size: 0.9rem; padding: 0.6rem 1rem;">
                    <i class="bi bi-lock-fill me-2"></i>
                    Día Cerrado
                </span>
            <?php endif; ?>
            
            <button class="btn btn-info" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-2"></i>
                Actualizar
            </button>
        </div>
    </div>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!$dia_abierto && !$dia_cerrado): ?>
        <div class="alert alert-warning mb-4" style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #fbbf24;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>¡Importante!</strong> Debes abrir el día antes de comenzar a registrar ventas.
        </div>
    <?php endif; ?>
    
    <!-- Tarjetas de estadísticas -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card-success position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-currency-dollar"></i>
                    Ganancias del Día
                </div>
                <div class="stat-card-value" data-target="<?= $total_dia ?>" data-prefix="$" data-decimals="2">$0.00</div>
                <i class="bi bi-cash-stack stat-card-icon"></i>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card-primary position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-scissors"></i>
                    Cortes del Día
                </div>
                <div class="stat-card-value" data-target="<?= $cortes_dia ?>" data-prefix="" data-decimals="0">0</div>
                <i class="bi bi-people stat-card-icon"></i>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card-warning position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-graph-up"></i>
                    Promedio por Corte
                </div>
                <div class="stat-card-value" data-target="<?= $promedio_corte ?>" data-prefix="$" data-decimals="2">$0.00</div>
                <i class="bi bi-calculator stat-card-icon"></i>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card-dark position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-star-fill"></i>
                    Más Vendido
                </div>
                <div class="stat-card-value" style="font-size: 1.25rem; word-wrap: break-word;">
                    <?= e($producto_mas_vendido) ?>
                </div>
                <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.25rem;">
                    <?= $cantidad_mas_vendido ?> unidades
                </div>
                <i class="bi bi-trophy stat-card-icon"></i>
            </div>
        </div>
    </div>
    
    <!-- Últimas ventas -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <i class="bi bi-clock-history me-2"></i>
                        <strong>Últimas Ventas del Día</strong>
                    </div>
                    <a href="pos.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>
                        Nueva Venta
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($ultimas_ventas)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                            <p class="text-muted mt-3 mb-0" style="color: rgba(255, 255, 255, 0.6) !important;">No hay ventas registradas hoy</p>
                            <?php if ($dia_abierto): ?>
                                <a href="pos.php" class="btn btn-primary btn-sm mt-3">
                                    Realizar primera venta
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ID</th>
                                        <th style="width: 120px;">Hora</th>
                                        <th>Productos</th>
                                        <th style="width: 150px;">Barbero</th>
                                        <th style="width: 120px;" class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimas_ventas as $venta): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary">#<?= $venta['id'] ?></span>
                                            </td>
                                            <td>
                                                <i class="bi bi-clock text-muted me-1"></i>
                                                <?= date('H:i', strtotime($venta['created_at'])) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $items = json_decode($venta['items'], true);
                                                if (is_array($items) && !empty($items)) {
                                                    $items_text = [];
                                                    foreach ($items as $item) {
                                                        $nombre = e($item['name'] ?? 'Producto');
                                                        $qty = intval($item['qty'] ?? 1);
                                                        $items_text[] = "{$nombre} x{$qty}";
                                                    }
                                                    echo '<small>' . implode(', ', array_slice($items_text, 0, 2)) . '</small>';
                                                    if (count($items_text) > 2) {
                                                        echo '<small class="text-muted"> +' . (count($items_text) - 2) . ' más</small>';
                                                    }
                                                } else {
                                                    echo '<small class="text-muted">Sin detalles</small>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if(!empty($venta['barber_name'])): ?>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-person-badge me-1"></i>
                                                        <?= e($venta['barber_name']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <small class="text-muted">Sin asignar</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <strong class="text-success">$<?= number_format($venta['total'], 2) ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Accesos rápidos -->
    <div class="row mt-4">
        <div class="col-12">
            <h5 class="mb-3">
                <i class="bi bi-lightning-fill"></i>
                Accesos Rápidos
            </h5>
        </div>
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            <a href="pos.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 quick-access-card">
                    <div class="card-body d-flex align-items-center p-3 p-lg-4">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); border-radius: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-cart-plus" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="color: white;">Punto de Venta</h6>
                            <small class="text-muted">Registrar nueva venta</small>
                        </div>
                        <i class="bi bi-chevron-right ms-2" style="font-size: 1.5rem; color: #cbd5e1;"></i>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            <a href="reportes_dia.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 quick-access-card">
                    <div class="card-body d-flex align-items-center p-3 p-lg-4">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-file-earmark-bar-graph" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="color: white;">Reportes del Día</h6>
                            <small class="text-muted">Ver estadísticas</small>
                        </div>
                        <i class="bi bi-chevron-right ms-2" style="font-size: 1.5rem; color: #cbd5e1;"></i>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-md-6 col-lg-4 mb-3">
            <a href="cerrar_dia.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 quick-access-card">
                    <div class="card-body d-flex align-items-center p-3 p-lg-4">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border-radius: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-door-closed" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="color: white;">Cerrar Día</h6>
                            <small class="text-muted">Finalizar jornada</small>
                        </div>
                        <i class="bi bi-chevron-right ms-2" style="font-size: 1.5rem; color: #cbd5e1;"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Modal Abrir Día -->
<div class="modal fade" id="abrirDiaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #1e293b; border: 1px solid #334155;">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-bottom: none;">
                <h5 class="modal-title" style="color: white; font-weight: 700;">
                    <i class="bi bi-sunrise me-2"></i>
                    Abrir Día
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 2rem;">
                <div class="text-center mb-4">
                    <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);">
                        <i class="bi bi-sunrise" style="font-size: 2.5rem; color: white;"></i>
                    </div>
                </div>
                
                <h5 class="text-center mb-3" style="color: white;">¿Deseas abrir el día?</h5>
                
                <p class="text-center mb-4" style="color: rgba(255, 255, 255, 0.7);">
                    Al abrir el día, se inicializarán todos los contadores en <strong style="color: #10b981;">$0.00</strong> y podrás comenzar a registrar las ventas del día.
                </p>
                
                <div class="alert alert-info mb-4" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa;">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Nota:</strong> Esta acción marca el inicio de un nuevo período de contabilidad.
                </div>
                
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center" style="padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border-radius: 0.5rem;">
                        <i class="bi bi-check-circle text-success me-2" style="font-size: 1.25rem;"></i>
                        <span style="color: rgba(255, 255, 255, 0.8);">Contadores en $0.00</span>
                    </div>
                    <div class="d-flex align-items-center" style="padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border-radius: 0.5rem;">
                        <i class="bi bi-check-circle text-success me-2" style="font-size: 1.25rem;"></i>
                        <span style="color: rgba(255, 255, 255, 0.8);">Sistema listo para ventas</span>
                    </div>
                    <div class="d-flex align-items-center" style="padding: 0.75rem; background: rgba(255, 255, 255, 0.05); border-radius: 0.5rem;">
                        <i class="bi bi-check-circle text-success me-2" style="font-size: 1.25rem;"></i>
                        <span style="color: rgba(255, 255, 255, 0.8);">Registro de apertura creado</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #334155;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>
                    Cancelar
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <button type="submit" name="abrir_dia" class="btn btn-success">
                        <i class="bi bi-sunrise me-1"></i>
                        Sí, Abrir Día
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 575.98px) {
    .stat-card-value {
        font-size: 2rem !important;
    }
    .stat-card-icon {
        font-size: 2.5rem !important;
    }
    .table {
        font-size: 0.875rem;
    }
}

.quick-access-card {
    transition: all 0.3s ease;
}

.quick-access-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.2) !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Función de animación mejorada y funcional
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
    // Animar contadores
    const counters = document.querySelectorAll('.stat-card-value[data-target]');
    counters.forEach((counter, index) => {
        setTimeout(() => {
            animateCounter(counter);
        }, index * 100);
    });
    
    // Hover effects
    document.querySelectorAll('.quick-access-card').forEach(card => {
        card.parentElement.addEventListener('mouseenter', function() {
            this.querySelector('.quick-access-card').style.transform = 'translateY(-4px)';
        });
        card.parentElement.addEventListener('mouseleave', function() {
            this.querySelector('.quick-access-card').style.transform = 'translateY(0)';
        });
    });
});
</script>
</body>
</html>