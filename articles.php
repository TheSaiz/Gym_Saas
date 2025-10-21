<?php

require 'db.php';
requireAuth('superadmin');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Token de seguridad inválido';
    } else {
        try {
            if (isset($_POST['add'])) {
                $name = cleanInput($_POST['name']);
                $price = floatval($_POST['price']);
                
                if (empty($name)) {
                    $error = 'El nombre del artículo es requerido';
                } elseif ($price < 0) {
                    $error = 'El precio no puede ser negativo';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO articles (name, price) VALUES (?, ?)");
                    $stmt->execute([$name, $price]);
                    $message = 'Artículo agregado exitosamente';
                    logActivity($pdo, 'article_created', "Artículo creado: {$name}");
                }
            }
            
            if (isset($_POST['delete'])) {
                $id = intval($_POST['id']);
                $stmt = $pdo->prepare("UPDATE articles SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Artículo eliminado exitosamente';
                logActivity($pdo, 'article_deleted', "Artículo ID: {$id}");
            }
            
            if (isset($_POST['edit'])) {
                $id = intval($_POST['id']);
                $name = cleanInput($_POST['name']);
                $price = floatval($_POST['price']);
                
                if (empty($name)) {
                    $error = 'El nombre del artículo es requerido';
                } elseif ($price < 0) {
                    $error = 'El precio no puede ser negativo';
                } else {
                    $stmt = $pdo->prepare("UPDATE articles SET name=?, price=? WHERE id=?");
                    $stmt->execute([$name, $price, $id]);
                    $message = 'Artículo actualizado exitosamente';
                    logActivity($pdo, 'article_updated', "Artículo ID: {$id}");
                }
            }
        } catch (PDOException $e) {
            error_log('Articles Error: ' . $e->getMessage());
            $error = 'Error al procesar la solicitud';
        }
    }
}

$articles = $pdo->query("SELECT * FROM articles WHERE is_active = 1 ORDER BY id DESC")->fetchAll();
$csrfToken = generateCsrfToken();

$pageTitle = 'Gestión de Artículos';
include 'header.php';
include 'sidebar_super.php';
?>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="fas fa-box-open"></i> Gestión de Artículos</h3>
            <p class="text-muted mb-0" style="color:rgba(255,255,255,0.6)!important;">
                <i class="bi bi-info-circle"></i> Administra los productos y servicios
            </p>
        </div>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-2"></i>Nuevo Artículo
        </button>
    </div>
    
    <?php if($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= e($message) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-xl-4">
            <div class="stat-card stat-card-primary position-relative">
                <div class="stat-card-title"><i class="bi bi-box-seam"></i> Total Artículos</div>
                <div class="stat-card-value"><?= count($articles) ?></div>
                <div style="font-size:0.875rem;opacity:0.9;margin-top:0.5rem;">productos activos</div>
                <i class="bi bi-boxes stat-card-icon"></i>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="stat-card stat-card-success position-relative">
                <div class="stat-card-title"><i class="bi bi-currency-dollar"></i> Precio Promedio</div>
                <div class="stat-card-value">$<?= count($articles)>0?number_format(array_sum(array_column($articles,'price'))/count($articles),2):'0.00' ?></div>
                <div style="font-size:0.875rem;opacity:0.9;margin-top:0.5rem;">promedio por artículo</div>
                <i class="bi bi-graph-up stat-card-icon"></i>
            </div>
        </div>
        <div class="col-md-12 col-xl-4">
            <div class="stat-card stat-card-warning position-relative">
                <div class="stat-card-title"><i class="bi bi-star-fill"></i> Más Caro</div>
                <div class="stat-card-value">$<?= count($articles)>0?number_format(max(array_column($articles,'price')),2):'0.00' ?></div>
                <div style="font-size:0.875rem;opacity:0.9;margin-top:0.5rem;">precio máximo</div>
                <i class="bi bi-trophy stat-card-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <i class="fas fa-list me-2"></i>
                <strong>Listado de Artículos</strong>
                <span class="badge bg-primary ms-auto"><?= count($articles) ?> registros</span>
            </div>
        </div>
        <div class="card-body">
            <?php if(empty($articles)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
                    <p class="text-muted mt-3 mb-0">No hay artículos registrados</p>
                    <button class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-circle me-1"></i>Crear primer artículo
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:80px;">ID</th>
                                <th>Nombre</th>
                                <th style="width:150px;">Precio</th>
                                <th style="width:180px;">Fecha Creación</th>
                                <th style="width:220px;" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($articles as $a): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">#<?= $a['id'] ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div style="width:40px;height:40px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:0.5rem;display:flex;align-items:center;justify-content:center;margin-right:0.75rem;">
                                            <i class="fas fa-cut" style="color:white;"></i>
                                        </div>
                                        <strong><?= e($a['name']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-success" style="font-size:0.9rem;padding:0.5rem 0.75rem;">
                                        <i class="bi bi-currency-dollar"></i>
                                        <?= number_format($a['price'],2) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i>
                                        <?= date('d/m/Y',strtotime($a['created_at'])) ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary" onclick="editArticle(<?= $a['id'] ?>,'<?= addslashes(e($a['name'])) ?>',<?= $a['price'] ?>)" title="Editar">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteArticle(<?= $a['id'] ?>,'<?= addslashes(e($a['name'])) ?>')" title="Eliminar">
                                        <i class="bi bi-trash3"></i> Eliminar
                                    </button>
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

<!-- Modal Agregar -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Artículo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Artículo</label>
                        <input type="text" name="name" class="form-control" placeholder="Ej: Corte de Cabello Clásico" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Precio</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:rgba(255,255,255,0.05);border:2px solid rgba(255,255,255,0.1);color:white;">$</span>
                            <input type="number" name="price" step="0.01" min="0" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <button type="submit" name="add" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Editar Artículo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Artículo</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Precio</label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:rgba(255,255,255,0.05);border:2px solid rgba(255,255,255,0.1);color:white;">$</span>
                            <input type="number" name="price" id="edit_price" step="0.01" min="0" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <button type="submit" name="edit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Eliminar -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2 text-danger"></i>Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-body">
                    <p>¿Estás seguro de eliminar el artículo <strong id="delete_name" style="color:#667eea;"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Atención:</strong> Esta acción no se puede deshacer
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <button type="submit" name="delete" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Sí, Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editArticle(id,name,price){
    document.getElementById('edit_id').value=id;
    document.getElementById('edit_name').value=name;
    document.getElementById('edit_price').value=price;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteArticle(id,name){
    document.getElementById('delete_id').value=id;
    document.getElementById('delete_name').textContent=name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.querySelectorAll('.table tbody tr').forEach(row=>{
    row.addEventListener('mouseenter',function(){
        this.style.transform='scale(1.01)';
        this.style.boxShadow='0 4px 12px rgba(102,126,234,0.2)';
        this.style.transition='all 0.2s ease';
    });
    row.addEventListener('mouseleave',function(){
        this.style.transform='scale(1)';
        this.style.boxShadow='none';
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
        if(txt.includes(')){
            el.textContent='+c.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
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
                const hs=el.textContent.includes(');
                el.textContent=hs?'$0.00':'0';
                animateValue(el,0,v,1500);
            }
        },100*i);
    });
});

document.querySelectorAll('.btn').forEach(btn=>{
    btn.addEventListener('click',function(e){
        if(this.type==='submit')return;
        const r=document.createElement('span');
        const rc=this.getBoundingClientRect();
        const s=Math.max(rc.width,rc.height);
        const x=e.clientX-rc.left-s/2;
        const y=e.clientY-rc.top-s/2;
        r.style.cssText='width:'+s+'px;height:'+s+'px;left:'+x+'px;top:'+y+'px;position:absolute;border-radius:50%;background:rgba(255,255,255,0.4);transform:scale(0);pointer-events:none';
        this.style.position='relative';
        this.style.overflow='hidden';
        this.appendChild(r);
        r.animate([{transform:'scale(0)',opacity:1},{transform:'scale(2)',opacity:0}],{duration:600,easing:'ease-out'}).onfinish=()=>r.remove();
    });
});
</script>
</body>
</html>