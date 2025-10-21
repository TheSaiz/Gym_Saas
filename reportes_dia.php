<?php
require 'db.php';
requireAuth('recepcion');

$today = date('Y-m-d');

try {
    // Estadísticas generales del día
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_ventas,
            COALESCE(SUM(total), 0) as total_ingresos,
            COALESCE(AVG(total), 0) as promedio_venta
        FROM sales 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch();
    
    // Ventas por barbero
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
        ORDER BY total_ventas DESC
    ");
    $stmt->execute([$today]);
    $ventas_barberos = $stmt->fetchAll();
    
    // Todas las ventas del día con detalles
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.total,
            s.items,
            s.payment_method,
            s.created_at,
            b.name as barber_name
        FROM sales s
        LEFT JOIN barber_sales bs ON s.id = bs.sale_id
        LEFT JOIN barbers b ON bs.barber_id = b.id
        WHERE DATE(s.created_at) = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$today]);
    $todas_ventas = $stmt->fetchAll();
    
    // Productos más vendidos
    $productos_count = [];
    $productos_ingresos = [];
    
    foreach ($todas_ventas as $venta) {
        $items = json_decode($venta['items'], true);
        if (is_array($items)) {
            foreach ($items as $item) {
                $nombre = $item['name'] ?? 'Desconocido';
                $qty = intval($item['qty'] ?? 1);
                $price = floatval($item['price'] ?? 0);
                
                if (!isset($productos_count[$nombre])) {
                    $productos_count[$nombre] = 0;
                    $productos_ingresos[$nombre] = 0;
                }
                $productos_count[$nombre] += $qty;
                $productos_ingresos[$nombre] += ($price * $qty);
            }
        }
    }
    
    arsort($productos_count);
    $top_productos = array_slice($productos_count, 0, 5, true);
    
    // Ventas por método de pago
    $metodos_pago = ['efectivo' => 0, 'tarjeta' => 0, 'transferencia' => 0];
    foreach ($todas_ventas as $venta) {
        $metodo = $venta['payment_method'] ?? 'efectivo';
        if (isset($metodos_pago[$metodo])) {
            $metodos_pago[$metodo] += floatval($venta['total']);
        }
    }
    
} catch (PDOException $e) {
    error_log('Reportes Error: ' . $e->getMessage());
    $stats = ['total_ventas' => 0, 'total_ingresos' => 0, 'promedio_venta' => 0];
    $ventas_barberos = [];
    $todas_ventas = [];
    $top_productos = [];
    $metodos_pago = ['efectivo' => 0, 'tarjeta' => 0, 'transferencia' => 0];
}

$pageTitle = 'Reportes del Día';
include 'header.php';
include 'sidebar_recep.php';
?>

<div class="content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-1">
                <i class="bi bi-file-earmark-bar-graph"></i>
                Reportes del Día
            </h3>
            <p class="text-muted mb-0" style="color: rgba(255, 255, 255, 0.6) !important;">
                <i class="bi bi-calendar-check"></i>
                <?= date('l, d \d\e F \d\e Y') ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success" onclick="window.print()">
                <i class="bi bi-printer me-2"></i>
                Imprimir
            </button>
            <a href="dashboard_recep.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Volver
            </a>
        </div>
    </div>
    
    <!-- Estadísticas generales -->
    <div class="row g-3 g-lg-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="stat-card stat-card-success position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-currency-dollar"></i>
                    Total Ingresos
                </div>
                <div class="stat-card-value">$<?= number_format($stats['total_ingresos'], 2) ?></div>
                <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.5rem;">del día de hoy</div>
                <i class="bi bi-cash-stack stat-card-icon"></i>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="stat-card stat-card-primary position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-receipt"></i>
                    Total Ventas
                </div>
                <div class="stat-card-value"><?= $stats['total_ventas'] ?></div>
                <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.5rem;">transacciones completadas</div>
                <i class="bi bi-bag-check stat-card-icon"></i>
            </div>
        </div>
        
        <div class="col-12 col-sm-12 col-lg-4">
            <div class="stat-card stat-card-warning position-relative">
                <div class="stat-card-title">
                    <i class="bi bi-graph-up"></i>
                    Promedio por Venta
                </div>
                <div class="stat-card-value">$<?= number_format($stats['promedio_venta'], 2) ?></div>
                <div style="font-size: 0.875rem; opacity: 0.9; margin-top: 0.5rem;">ticket promedio</div>
                <i class="bi bi-calculator stat-card-icon"></i>
            </div>
        </div>
    </div>
    
    <!-- Ventas por barbero -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-people-fill me-2"></i>
                    <strong>Ventas por Barbero</strong>
                    <span class="badge bg-primary ms-2"><?= count($ventas_barberos) ?> barberos</span>
                </div>
                <div class="card-body">
                    <?php if(empty($ventas_barberos)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                            <p class="text-muted mt-3 mb-0">No hay datos de barberos</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Barbero</th>
                                        <th class="text-center">Ventas</th>
                                        <th class="text-end">Total Generado</th>
                                        <th class="text-center">Comisión %</th>
                                        <th class="text-end">Comisiones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($ventas_barberos as $vb): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem; font-weight: 700; color: white;">
                                                        <?= strtoupper(substr($vb['name'], 0, 1)) ?>
                                                    </div>
                                                    <strong><?= e($vb['name']) ?></strong>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">
                                                    <?= $vb['num_ventas'] ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <strong class="text-success">$<?= number_format($vb['total_ventas'], 2) ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning text-dark">
                                                    <?= number_format($vb['commission_percentage'], 2) ?>%
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <strong style="color: #10b981;">$<?= number_format($vb['total_comisiones'], 2) ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot style="border-top: 2px solid rgba(255, 255, 255, 0.2);">
                                    <tr>
                                        <td colspan="2" class="text-end"><strong>TOTALES:</strong></td>
                                        <td class="text-end">
                                            <strong class="text-success">
                                                $<?= number_format(array_sum(array_column($ventas_barberos, 'total_ventas')), 2) ?>
                                            </strong>
                                        </td>
                                        <td></td>
                                        <td class="text-end">
                                            <strong style="color: #10b981;">
                                                $<?= number_format(array_sum(array_column($ventas_barberos, 'total_comisiones')), 2) ?>
                                            </strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Productos más vendidos y métodos de pago -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-star-fill me-2"></i>
                    <strong>Top 5 Productos Más Vendidos</strong>
                </div>
                <div class="card-body">
                    <?php if(empty($top_productos)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox" style="font-size: 2.5rem; color: #cbd5e1;"></i>
                            <p class="text-muted mt-3 mb-0">No hay productos vendidos</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php 
                            $position = 1;
                            foreach($top_productos as $producto => $cantidad): 
                                $ingreso = $productos_ingresos[$producto] ?? 0;
                            ?>
                                <div class="list-group-item" style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 0.5rem; border-radius: 0.5rem;">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center flex-grow-1">
                                            <div style="width: 35px; height: 35px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: 700; color: white;">
                                                <?= $position++ ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <strong><?= e($producto) ?></strong>
                                                <div style="font-size: 0.875rem; color: rgba(255, 255, 255, 0.6);">
                                                    <?= $cantidad ?> unidades vendidas
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <strong class="text-success d-block">$<?= number_format($ingreso, 2) ?></strong>
                                            <small class="text-muted">ingresos</small>
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
                    <i class="bi bi-credit-card me-2"></i>
                    <strong>Ventas por Método de Pago</strong>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-cash-stack me-2" style="font-size: 1.5rem; color: #10b981;"></i>
                                <strong>Efectivo</strong>
                            </div>
                            <strong class="text-success">$<?= number_format($metodos_pago['efectivo'], 2) ?></strong>
                        </div>
                        <div class="progress" style="height: 25px; background: rgba(255, 255, 255, 0.1);">
                            <?php 
                            $total_pagos = array_sum($metodos_pago);
                            $porcentaje_efectivo = $total_pagos > 0 ? ($metodos_pago['efectivo'] / $total_pagos * 100) : 0;
                            ?>
                            <div class="progress-bar" style="width: <?= $porcentaje_efectivo ?>%; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <?= number_format($porcentaje_efectivo, 1) ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-credit-card me-2" style="font-size: 1.5rem; color: #3b82f6;"></i>
                                <strong>Tarjeta</strong>
                            </div>
                            <strong class="text-primary">$<?= number_format($metodos_pago['tarjeta'], 2) ?></strong>
                        </div>
                        <div class="progress" style="height: 25px; background: rgba(255, 255, 255, 0.1);">
                            <?php 
                            $porcentaje_tarjeta = $total_pagos > 0 ? ($metodos_pago['tarjeta'] / $total_pagos * 100) : 0;
                            ?>
                            <div class="progress-bar" style="width: <?= $porcentaje_tarjeta ?>%; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <?= number_format($porcentaje_tarjeta, 1) ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-arrow-left-right me-2" style="font-size: 1.5rem; color: #f59e0b;"></i>
                                <strong>Transferencia</strong>
                            </div>
                            <strong style="color: #f59e0b;">$<?= number_format($metodos_pago['transferencia'], 2) ?></strong>
                        </div>
                        <div class="progress" style="height: 25px; background: rgba(255, 255, 255, 0.1);">
                            <?php 
                            $porcentaje_transferencia = $total_pagos > 0 ? ($metodos_pago['transferencia'] / $total_pagos * 100) : 0;
                            ?>
                            <div class="progress-bar" style="width: <?= $porcentaje_transferencia ?>%; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <?= number_format($porcentaje_transferencia, 1) ?>%
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 text-center" style="background: rgba(16, 185, 129, 0.1); border-radius: 0.75rem; border: 1px solid rgba(16, 185, 129, 0.3);">
                        <div style="color: rgba(255, 255, 255, 0.8); font-size: 0.875rem; margin-bottom: 0.25rem;">Total en Pagos</div>
                        <div style="color: #10b981; font-size: 1.5rem; font-weight: 700;">$<?= number_format($total_pagos, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detalle de todas las ventas -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-list-ul me-2"></i>
                    <strong>Detalle de Todas las Ventas</strong>
                    <span class="badge bg-primary ms-2"><?= count($todas_ventas) ?> ventas</span>
                </div>
                <div class="card-body">
                    <?php if(empty($todas_ventas)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #cbd5e1;"></i>
                            <p class="text-muted mt-3 mb-0">No hay ventas registradas hoy</p>
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
                                        <th style="width: 130px;">Método Pago</th>
                                        <th style="width: 120px;" class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($todas_ventas as $venta): ?>
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
                                                    echo '<div class="d-flex flex-wrap gap-1">';
                                                    foreach($items as $item) {
                                                        $nombre = e($item['name'] ?? 'Producto');
                                                        $qty = intval($item['qty'] ?? 1);
                                                        echo '<span class="badge bg-info" style="font-size: 0.75rem;">' . $nombre . ' x' . $qty . '</span>';
                                                    }
                                                    echo '</div>';
                                                } else {
                                                    echo '<small class="text-muted">Sin detalles</small>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if(!empty($venta['barber_name'])): ?>
                                                    <span class="badge bg-primary">
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
</div>

<style>
@media print {
    .btn, .sidebar, .navbar-custom {
        display: none !important;
    }
    
    .content {
        margin-left: 0 !important;
        margin-top: 0 !important;
        padding: 1rem !important;
    }
    
    .card {
        page-break-inside: avoid;
        margin-bottom: 1rem !important;
    }
}

@media (max-width: 575.98px) {
    .stat-card-value {
        font-size: 2rem !important;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .badge {
        font-size: 0.7rem !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
    
    // Animación para las barras de progreso
    setTimeout(() => {
        document.querySelectorAll('.progress-bar').forEach(bar => {
            bar.style.transition = 'width 1s ease-in-out';
        });
    }, 500);
});
</script>
</body>
</html>