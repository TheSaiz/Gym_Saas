<?php
require 'db.php';
requireAuth('superadmin');

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$from = date('Y-m-d', strtotime($from));
$to = date('Y-m-d', strtotime($to));

try {
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
    $stmt->execute([$from, $to]);
    $sales = $stmt->fetchAll();
    
    $stmt2 = $pdo->prepare("SELECT COUNT(*) as total_cortes, COALESCE(SUM(total),0) as total_ganancias FROM sales WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt2->execute([$from, $to]);
    $totales = $stmt2->fetch();
    
    $total_cortes = $totales['total_cortes'] ?? 0;
    $total_ganancias = $totales['total_ganancias'] ?? 0;
    $promedio = $total_cortes > 0 ? ($total_ganancias / $total_cortes) : 0;
} catch (PDOException $e) {
    error_log('Reports Error: ' . $e->getMessage());
    $sales = [];
    $total_cortes = $total_ganancias = $promedio = 0;
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reporte_' . $from . '_a_' . $to . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Fecha', 'Hora', 'Total', 'Items']);
    foreach ($sales as $s) {
        fputcsv($out, [
            $s['id'],
            date('d/m/Y', strtotime($s['created_at'])),
            date('H:i', strtotime($s['created_at'])),
            $s['total'],
            $s['items']
        ]);
    }
    fclose($out);
    exit();
}

$pageTitle = 'Reportes de Ventas';
include 'header.php';
include 'sidebar_super.php';
?>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="fas fa-chart-bar"></i> Reportes de Ventas</h3>
            <p class="text-muted mb-0" style="color:rgba(255,255,255,0.6)!important;">
                <i class="bi bi-calendar-range"></i> Del <?= date('d/m/Y',strtotime($from)) ?> al <?= date('d/m/Y',strtotime($to)) ?>
            </p>
        </div>
        <a href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=csv" class="btn btn-success">
            <i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar CSV
        </a>
    </div>
    
    <form class="card mb-4" method="GET">
        <div class="card-header">
            <i class="fas fa-filter me-2"></i><strong>Filtros de Búsqueda</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha Desde</label>
                    <input type="date" name="from" class="form-control" value="<?= e($from) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha Hasta</label>
                    <input type="date" name="to" class="form-control" value="<?= e($to) ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-2"></i>Filtrar
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card stat-card-success position-relative">
                <div class="stat-card-title"><i class="bi bi-currency-dollar"></i> Total Ganancias</div>
                <div class="stat-card-value">$<?= number_format($total_ganancias,2) ?></div>
                <i class="bi bi-cash-stack stat-card-icon"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-card-primary position-relative">
                <div class="stat-card-title"><i class="bi bi-receipt"></i> Total Ventas</div>
                <div class="stat-card-value"><?= $total_cortes ?></div>
                <i class="bi bi-bag-check stat-card-icon"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card stat-card-warning position-relative">
                <div class="stat-card-title"><i class="bi bi-graph-up"></i> Promedio por Venta</div>
                <div class="stat-card-value">$<?= number_format($promedio,2) ?></div>
                <i class="bi bi-calculator stat-card-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <i class="fas fa-list me-2"></i>
                <strong>Detalle de Ventas</strong>
                <span class="badge bg-primary ms-auto"><?= count($sales) ?> registros</span>
            </div>
        </div>
        <div class="card-body">
            <?php if(empty($sales)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
                    <p class="text-muted mt-3 mb-0">No hay ventas en el rango seleccionado</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:80px;">ID</th>
                                <th style="width:120px;">Fecha</th>
                                <th style="width:100px;">Hora</th>
                                <th>Productos</th>
                                <th style="width:120px;" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($sales as $s): ?>
                            <tr>
                                <td><span class="badge bg-secondary">#<?= $s['id'] ?></span></td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i>
                                        <?= date('d/m/Y',strtotime($s['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i>
                                        <?= date('H:i',strtotime($s['created_at'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php
                                    $items = json_decode($s['items'], true);
                                    if (is_array($items)) {
                                        echo '<div class="d-flex flex-wrap gap-1">';
                                        foreach ($items as $item) {
                                            $iname = e($item['name'] ?? 'Producto');
                                            $iqty = intval($item['qty'] ?? 0);
                                            $iprice = number_format(floatval($item['price'] ?? 0), 2);
                                            echo '<span class="badge bg-info">' . $iname . ' x' . $iqty . ' ($' . $iprice . ')</span>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<small class="text-muted">Sin detalles</small>';
                                    }
                                    ?>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success" style="font-size:1.1rem;">
                                        $<?= number_format($s['total'], 2) ?>
                                    </strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot style="border-top:2px solid rgba(255,255,255,0.2);">
                            <tr>
                                <td colspan="4" class="text-end"><strong>TOTAL:</strong></td>
                                <td class="text-end">
                                    <strong class="text-success" style="font-size:1.3rem;">
                                        $<?= number_format($total_ganancias, 2) ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.table tbody tr').forEach(row=>{
    row.addEventListener('mouseenter',function(){
        this.style.transform='scale(1.01)';
        this.style.transition='all 0.2s ease';
    });
    row.addEventListener('mouseleave',function(){
        this.style.transform='scale(1)';
    });
});

function animateValue(el,start,end,dur){
    if(!el)return;
    let ts=null;
    const step=(t)=>{
        if(!ts)ts=t;
        const p=Math.min((t-ts)/dur,1);
        const c=Math.floor(p*(end-start)+start);
        const txt=el.textContent;
        if(txt.includes('$')){
            el.textContent='$'+c.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
        }else{
            el.textContent=c;
        }
        if(p<1){window.requestAnimationFrame(step);}
    };
    window.requestAnimationFrame(step);
}

document.addEventListener('DOMContentLoaded',function(){
    const sv=document.querySelectorAll('.stat-card-value');
    sv.forEach((el,i)=>{
        setTimeout(()=>{
            const t=el.textContent.replace(/[$,]/g,'');
            const v=parseFloat(t);
            if(!isNaN(v)&&v>0){
                const hs=el.textContent.includes('$');
                el.textContent=hs?'$0.00':'0';
                animateValue(el,0,v,1500);
            }
        },100*i);
    });
});
</script>
</body>
</html>