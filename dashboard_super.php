<?php
require 'db.php';
requireAuth('superadmin');

$today = date('Y-m-d');

try {
    // Estadísticas del día
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as total_dia,
            COUNT(*) as ventas_dia
        FROM sales 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $stats_dia = $stmt->fetch();
    
    // Estadísticas del mes
    $primer_dia_mes = date('Y-m-01');
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total), 0) as total_mes,
            COUNT(*) as ventas_mes
        FROM sales 
        WHERE DATE(created_at) >= ?
    ");
    $stmt->execute([$primer_dia_mes]);
    $stats_mes = $stmt->fetch();
    
    // Total de artículos activos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM articles WHERE is_active = 1");
    $total_articulos = $stmt->fetch()['total'] ?? 0;
    
    // Total de barberos activos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM barbers WHERE is_active = 1");
    $total_barberos = $stmt->fetch()['total'] ?? 0;
    
    // Últimas 10 ventas del día con información del barbero
    $stmt = $pdo->prepare("
        SELECT 
            s.id, 
            s.total, 
            s.items, 
            s.payment_method,
            s.created_at,
            b.name as barber_name,
            b.id as barber_id
        FROM sales s
        LEFT JOIN barber_sales bs ON s.id = bs.sale_id
        LEFT JOIN barbers b ON bs.barber_id = b.id
        WHERE DATE(s.created_at) = ?
        ORDER BY s.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$today]);
    $ultimas_ventas = $stmt->fetchAll();
    
    // Ventas por barbero del día
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.name,
            COUNT(bs.id) as num_ventas,
            COALESCE(SUM(s.total), 0) as total_ventas,
            COALESCE(SUM(bs.commission_amount), 0) as total_comisiones
        FROM barbers b
        LEFT JOIN barber_sales bs ON b.id = bs.barber_id
        LEFT JOIN sales s ON bs.sale_id = s.id AND DATE(s.created_at) = ?
        WHERE b.is_active = 1
        GROUP BY b.id, b.name
        ORDER BY total_ventas DESC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $ventas_barberos = $stmt->fetchAll();
    
    // Productos más vendidos del día
    $productos_count = [];
    foreach ($ultimas_ventas as $venta) {
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
    $top_productos = array_slice($productos_count, 0, 3, true);
    
} catch (PDOException $e) {
    error_log('Dashboard Super Error: ' . $e->getMessage());
    $stats_dia = ['total_dia' => 0, 'ventas_dia' => 0];
    $stats_mes = ['total_mes' => 0, 'ventas_mes' => 0];
    $total_articulos = $total_barberos = 0;
    $ultimas_ventas = [];
    $ventas_barberos = [];
    $top_productos = [];
}

$pageTitle = 'Dashboard Administrador';
include 'header.php';
include 'sidebar_super.php';
?>

<div class="content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">
                <i class="fas fa-chart-line"></i>
                Dashboard Administrador
            </h3>
            <p class="text-muted mb-0" style="color: rgba(255, 255, 255, 0.6) !important;">
                <i class="bi bi-calendar-check"></i>
                <?= date('l, d \d\e F \d\e Y') ?>
            </p>
        </div>
        <button class="btn btn-success" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-2"></i>
            Actualizar
        </button>
    </div>
    
    <!-- Estadísticas principales -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card-success position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-currency-dollar"></i>
                    Ventas Hoy
                </div>
                <div class="stat-card-value">$<?= number_format($stats_dia['total_dia'], 2) ?></div>
                <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.5rem;">
                    <?= $stats_dia['ventas_dia'] ?> transacciones
                </div>
                <i class="bi bi-cash-stack stat-card-icon"></i>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card-primary position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-calendar-month"></i>
                    Ventas del Mes
                </div>
                <div class="stat-card-value">$<?= number_format($stats_mes['total_mes'], 2) ?></div>
                <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.5rem;">
                    <?= $stats_mes['ventas_mes'] ?> transacciones
                </div>
                <i class="bi bi-graph-up stat-card-icon"></i>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card-warning position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-box-seam"></i>
                    Artículos Activos
                </div>
                <div class="stat-card-value"><?= $total_articulos ?></div>
                <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.5rem;">
                    servicios disponibles
                </div>
                <i class="bi bi-boxes stat-card-icon"></i>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card stat-card-dark position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-people-fill"></i>
                    Barberos Activos
                </div>
                <div class="stat-card-value"><?= $total_barberos ?></div>
                <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.5rem;">
                    profesionales en equipo
                </div>
                <i class="bi bi-person-badge stat-card-icon"></i>
            </div>
        </div>
    </div>
    
    <!-- Ventas por barbero y productos -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-trophy-fill me-2"></i>
                    <strong>Top 5 Barberos del Día</strong>
                </div>
                <div class="card-body">
                    <?php if(empty($ventas_barberos)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                            <p class="text-muted mt-3 mb-0">No hay ventas con barberos asignados</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php 
                            $position = 1;
                            foreach($ventas_barberos as $vb): 
                            ?>
                                <div class="list-group-item" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 0.75rem; border-radius: 0.75rem; padding: 1rem;">
                                    <div class="d-flex align-items-center">
                                        <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: 700; color: white; font-size: 1.1rem;">
                                            <?= $position++ ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <strong style="color: white; display: block; margin-bottom: 0.25rem;">
                                                <?= e($vb['name']) ?>
                                            </strong>
                                            <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.6);">
                                                <?= $vb['num_ventas'] ?> ventas • 
                                                Comisión: <span style="color: #10b981;">$<?= number_format($vb['total_comisiones'], 2) ?></span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-success d-block" style="font-size: 1.1rem;">
                                                $<?= number_format($vb['total_ventas'], 2) ?>
                                            </strong>
                                            <small class="text-muted">generado</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-star-fill me-2"></i>
                    <strong>Top 3 Servicios del Día</strong>
                </div>
                <div class="card-body">
                    <?php if(empty($top_productos)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                            <p class="text-muted mt-3 mb-0">No hay servicios vendidos hoy</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php 
                            $position = 1;
                            foreach($top_productos as $producto => $cantidad): 
                            ?>
                                <div class="list-group-item" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 0.75rem; border-radius: 0.75rem; padding: 1rem;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: 700; color: white; font-size: 1.1rem;">
                                                <?= $position++ ?>
                                            </div>
                                            <strong style="color: white;"><?= e($producto) ?></strong>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-success" style="font-size: 1rem; padding: 0.5rem 0.75rem;">
                                                <?= $cantidad ?> unid.
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Últimas ventas del día -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                    <div>
                        <i class="bi bi-clock-history me-2"></i>
                        <strong>Últimas Ventas del Día</strong>
                    </div>
                    <a href="reports.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i>
                        Ver Reportes Completos
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($ultimas_ventas)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                            <p class="text-muted mt-3 mb-0" style="color: rgba(255, 255, 255, 0.6) !important;">
                                No hay ventas registradas hoy
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;">ID</th>
                                        <th style="width: 100px;">Hora</th>
                                        <th>Productos</th>
                                        <th style="width: 150px;">Barbero</th>
                                        <th style="width: 130px;">Método Pago</th>
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
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i>
                                                    <?= date('H:i', strtotime($venta['created_at'])) ?>
                                                </small>
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
                                            <td>
                                                <?php
                                                $metodo = $venta['payment_method'] ?? 'efectivo';
                                                $iconos = [
                                                    'efectivo' => 'cash-stack',
                                                    'tarjeta' => 'credit-card',
                                                    'transferencia' => 'arrow-left-right'
                                                ];
                                                $colores = [
                                                    'efectivo' => 'success',
                                                    'tarjeta' => 'primary',
                                                    'transferencia' => 'warning'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $colores[$metodo] ?? 'secondary' ?>">
                                                    <i class="bi bi-<?= $iconos[$metodo] ?? 'question' ?>"></i>
                                                    <?= ucfirst($metodo) ?>
                                                </span>
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
        <div class="col-12 col-sm-6 col-lg-3 mb-3">
            <a href="articles.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 quick-access-card">
                    <div class="card-body d-flex align-items-center p-3">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-box-seam" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="color: white; font-size: 0.95rem;">Artículos</h6>
                            <small class="text-muted">Gestionar servicios</small>
                        </div>
                        <i class="bi bi-chevron-right ms-2" style="font-size: 1.5rem; color: #cbd5e1;"></i>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 mb-3">
            <a href="barberos.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 quick-access-card">
                    <div class="card-body d-flex align-items-center p-3">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-people" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="color: white; font-size: 0.95rem;">Barberos</h6>
                            <small class="text-muted">Gestionar equipo</small>
                        </div>
                        <i class="bi bi-chevron-right ms-2" style="font-size: 1.5rem; color: #cbd5e1;"></i>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 mb-3">
            <a href="reports.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 quick-access-card">
                    <div class="card-body d-flex align-items-center p-3">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-bar-chart-line" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="color: white; font-size: 0.95rem;">Reportes</h6>
                            <small class="text-muted">Ver estadísticas</small>
                        </div>
                        <i class="bi bi-chevron-right ms-2" style="font-size: 1.5rem; color: #cbd5e1;"></i>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-lg-3 mb-3">
            <a href="configuration.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100 quick-access-card">
                    <div class="card-body d-flex align-items-center p-3">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border-radius: 1rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="bi bi-gear" style="font-size: 1.5rem; color: white;"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1" style="color: white; font-size: 0.95rem;">Configuración</h6>
                            <small class="text-muted">Ajustes del sistema</small>
                        </div>
                        <i class="bi bi-chevron-right ms-2" style="font-size: 1.5rem; color: #cbd5e1;"></i>
                    </div>
                </div>
            </a>
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
    
    .badge {
        font-size: 0.7rem !important;
    }
}

.quick-access-card {
    transition: all 0.3s ease;
}

.quick-access-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.2) !important;
}

.list-group-item {
    transition: all 0.2s ease;
}

.list-group-item:hover {
    transform: translateX(4px);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Animación de conteo para los números
    function animateValue(element, start, end, duration) {
        if (!element) return;
        
        let startTimestamp = null;
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const current = Math.floor(progress * (end - start) + start);
            const text = element.textContent;
            
            if (text.includes(')) {
                element.textContent = ' + current.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            } else {
                element.textContent = current;
            }
            
            if (progress < 1) {
                window.requestAnimationFrame(step);
            }
        };
        window.requestAnimationFrame(step);
    }
    
    // Aplicar animación a las tarjetas cuando se cargan
    document.addEventListener('DOMContentLoaded', function() {
        const statValues = document.querySelectorAll('.stat-card-value');
        statValues.forEach((el, index) => {
            setTimeout(() => {
                const text = el.textContent.replace(/[$,]/g, '');
                const value = parseFloat(text);
                if (!isNaN(value) && value > 0) {
                    const hasSign = el.textContent.includes(');
                    el.textContent = hasSign ? '$0.00' : '0';
                    animateValue(el, 0, value, 1500);
                }
            }, 100 * index);
        });
        
        // Hover effect para las tarjetas de acceso rápido
        document.querySelectorAll('.quick-access-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-4px)';
                this.style.boxShadow = '0 10px 15px -3px rgb(0 0 0 / 0.2)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 1px 3px 0 rgb(0 0 0 / 0.1)';
            });
        });
        
        // Animación para las filas de la tabla
        document.querySelectorAll('.table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
                this.style.boxShadow = '0 4px 12px rgba(102, 126, 234, 0.2)';
                this.style.transition = 'all 0.2s ease';
            });
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
            });
        });
    });
</script>
</body>
</html>