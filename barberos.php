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
                $email = cleanInput($_POST['email']);
                $phone = cleanInput($_POST['phone']);
                $specialty = cleanInput($_POST['specialty']);
                $commission = floatval($_POST['commission_percentage']);
                
                if (empty($name)) {
                    $error = 'El nombre del barbero es requerido';
                } elseif (empty($email)) {
                    $error = 'El email es requerido';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'El formato del email no es válido';
                } elseif ($commission < 0 || $commission > 100) {
                    $error = 'La comisión debe estar entre 0 y 100';
                } else {
                    // Verificar si el email ya existe
                    $stmt = $pdo->prepare("SELECT id FROM barbers WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'El email ya está registrado';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO barbers (name, email, phone, specialty, commission_percentage) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $phone, $specialty, $commission]);
                        $message = 'Barbero agregado exitosamente';
                        logActivity($pdo, 'barber_created', "Barbero creado: {$name}");
                    }
                }
            }
            
            if (isset($_POST['delete'])) {
                $id = intval($_POST['id']);
                $stmt = $pdo->prepare("UPDATE barbers SET is_active = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Barbero eliminado exitosamente';
                logActivity($pdo, 'barber_deleted', "Barbero ID: {$id}");
            }
            
            if (isset($_POST['edit'])) {
                $id = intval($_POST['id']);
                $name = cleanInput($_POST['name']);
                $email = cleanInput($_POST['email']);
                $phone = cleanInput($_POST['phone']);
                $specialty = cleanInput($_POST['specialty']);
                $commission = floatval($_POST['commission_percentage']);
                
                if (empty($name)) {
                    $error = 'El nombre del barbero es requerido';
                } elseif (empty($email)) {
                    $error = 'El email es requerido';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'El formato del email no es válido';
                } elseif ($commission < 0 || $commission > 100) {
                    $error = 'La comisión debe estar entre 0 y 100';
                } else {
                    // Verificar si el email ya existe en otro barbero
                    $stmt = $pdo->prepare("SELECT id FROM barbers WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id]);
                    if ($stmt->fetch()) {
                        $error = 'El email ya está registrado por otro barbero';
                    } else {
                        $stmt = $pdo->prepare("UPDATE barbers SET name=?, email=?, phone=?, specialty=?, commission_percentage=? WHERE id=?");
                        $stmt->execute([$name, $email, $phone, $specialty, $commission, $id]);
                        $message = 'Barbero actualizado exitosamente';
                        logActivity($pdo, 'barber_updated', "Barbero ID: {$id}");
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Barbers Error: ' . $e->getMessage());
            $error = 'Error al procesar la solicitud';
        }
    }
}

$barbers = $pdo->query("SELECT * FROM barbers WHERE is_active = 1 ORDER BY id DESC")->fetchAll();
$csrfToken = generateCsrfToken();

// Calcular estadísticas
$totalBarbers = count($barbers);
$avgCommission = $totalBarbers > 0 ? array_sum(array_column($barbers, 'commission_percentage')) / $totalBarbers : 0;
$maxCommission = $totalBarbers > 0 ? max(array_column($barbers, 'commission_percentage')) : 0;

$pageTitle = 'Gestión de Barberos';
include 'header.php';
include 'sidebar_super.php';
?>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><i class="fas fa-user-tie"></i> Gestión de Barberos</h3>
            <p class="text-muted mb-0" style="color:rgba(255,255,255,0.6)!important;">
                <i class="bi bi-info-circle"></i> Administra el equipo de profesionales
            </p>
        </div>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle me-2"></i>Nuevo Barbero
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
                <div class="stat-card-title"><i class="bi bi-people-fill"></i> Total Barberos</div>
                <div class="stat-card-value"><?= $totalBarbers ?></div>
                <div style="font-size:0.875rem;opacity:0.9;margin-top:0.5rem;">profesionales activos</div>
                <i class="bi bi-people stat-card-icon"></i>
            </div>
        </div>
        <div class="col-md-6 col-xl-4">
            <div class="stat-card stat-card-success position-relative">
                <div class="stat-card-title"><i class="bi bi-percent"></i> Comisión Promedio</div>
                <div class="stat-card-value"><?= number_format($avgCommission, 2) ?>%</div>
                <div style="font-size:0.875rem;opacity:0.9;margin-top:0.5rem;">promedio del equipo</div>
                <i class="bi bi-graph-up stat-card-icon"></i>
            </div>
        </div>
        <div class="col-md-12 col-xl-4">
            <div class="stat-card stat-card-warning position-relative">
                <div class="stat-card-title"><i class="bi bi-star-fill"></i> Mayor Comisión</div>
                <div class="stat-card-value"><?= number_format($maxCommission, 2) ?>%</div>
                <div style="font-size:0.875rem;opacity:0.9;margin-top:0.5rem;">comisión máxima</div>
                <i class="bi bi-award stat-card-icon"></i>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center">
                <i class="fas fa-list me-2"></i>
                <strong>Listado de Barberos</strong>
                <span class="badge bg-primary ms-auto"><?= count($barbers) ?> registros</span>
            </div>
        </div>
        <div class="card-body">
            <?php if(empty($barbers)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size:3rem;color:#cbd5e1;"></i>
                    <p class="text-muted mt-3 mb-0">No hay barberos registrados</p>
                    <button class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-circle me-1"></i>Registrar primer barbero
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:80px;">ID</th>
                                <th>Nombre</th>
                                <th style="width:200px;">Email</th>
                                <th style="width:150px;">Teléfono</th>
                                <th style="width:180px;">Especialidad</th>
                                <th style="width:120px;">Comisión</th>
                                <th style="width:240px;" class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($barbers as $b): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary">#<?= $b['id'] ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div style="width:45px;height:45px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-right:0.75rem;font-weight:700;font-size:1.1rem;color:white;">
                                            <?= strtoupper(substr($b['name'], 0, 1)) ?>
                                        </div>
                                        <strong><?= e($b['name']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-envelope"></i>
                                        <?= e($b['email']) ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="bi bi-telephone"></i>
                                        <?= e($b['phone'] ?? 'N/A') ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if(!empty($b['specialty'])): ?>
                                        <span class="badge bg-info">
                                            <i class="bi bi-scissors"></i>
                                            <?= e($b['specialty']) ?>
                                        </span>
                                    <?php else: ?>
                                        <small class="text-muted">Sin especialidad</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success" style="font-size:0.9rem;padding:0.5rem 0.75rem;">
                                        <i class="bi bi-percent"></i>
                                        <?= number_format($b['commission_percentage'], 2) ?>%
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary" onclick='editBarber(<?= json_encode($b) ?>)' title="Editar">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteBarber(<?= $b['id'] ?>,'<?= addslashes(e($b['name'])) ?>')" title="Eliminar">
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Barbero
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="name" class="form-control" placeholder="Ej: Carlos Rodríguez" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" placeholder="email@ejemplo.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="phone" class="form-control" placeholder="+54 11 1234-5678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Especialidad</label>
                            <input type="text" name="specialty" class="form-control" placeholder="Ej: Cortes Clásicos">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Comisión (%) *</label>
                            <div class="input-group">
                                <input type="number" name="commission_percentage" step="0.01" min="0" max="100" class="form-control" placeholder="40.00" value="0" required>
                                <span class="input-group-text" style="background:rgba(255,255,255,0.05);border:2px solid rgba(255,255,255,0.1);color:white;">
                                    <i class="bi bi-percent"></i>
                                </span>
                            </div>
                            <small class="text-muted">Porcentaje de comisión por venta (0-100)</small>
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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil-square me-2"></i>Editar Barbero
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre Completo *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Especialidad</label>
                            <input type="text" name="specialty" id="edit_specialty" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Comisión (%) *</label>
                            <div class="input-group">
                                <input type="number" name="commission_percentage" id="edit_commission" step="0.01" min="0" max="100" class="form-control" required>
                                <span class="input-group-text" style="background:rgba(255,255,255,0.05);border:2px solid rgba(255,255,255,0.1);color:white;">
                                    <i class="bi bi-percent"></i>
                                </span>
                            </div>
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
                    <p>¿Estás seguro de eliminar al barbero <strong id="delete_name" style="color:#667eea;"></strong>?</p>
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
function editBarber(barber){
    document.getElementById('edit_id').value = barber.id;
    document.getElementById('edit_name').value = barber.name;
    document.getElementById('edit_email').value = barber.email;
    document.getElementById('edit_phone').value = barber.phone || '';
    document.getElementById('edit_specialty').value = barber.specialty || '';
    document.getElementById('edit_commission').value = barber.commission_percentage;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function deleteBarber(id, name){
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.querySelectorAll('.table tbody tr').forEach(row => {
    row.addEventListener('mouseenter', function(){
        this.style.transform = 'scale(1.01)';
        this.style.boxShadow = '0 4px 12px rgba(102,126,234,0.2)';
        this.style.transition = 'all 0.2s ease';
    });
    row.addEventListener('mouseleave', function(){
        this.style.transform = 'scale(1)';
        this.style.boxShadow = 'none';
    });
});

function animateValue(el, start, end, dur){
    if(!el) return;
    let ts = null;
    const step = (t) => {
        if(!ts) ts = t;
        const p = Math.min((t - ts) / dur, 1);
        const c = Math.floor(p * (end - start) + start);
        const txt = el.textContent;
        if(txt.includes('%')){
            el.textContent = c.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%';
        } else {
            el.textContent = c;
        }
        if(p < 1){ window.requestAnimationFrame(step); }
    };
    window.requestAnimationFrame(step);
}

document.addEventListener('DOMContentLoaded', function(){
    const sv = document.querySelectorAll('.stat-card-value');
    sv.forEach((el, i) => {
        setTimeout(() => {
            const t = el.textContent.replace(/[%,]/g, '');
            const v = parseFloat(t);
            if(!isNaN(v) && v > 0){
                const hasPer = el.textContent.includes('%');
                el.textContent = hasPer ? '0.00%' : '0';
                animateValue(el, 0, v, 1500);
            }
        }, 100 * i);
    });
});

document.querySelectorAll('.btn').forEach(btn => {
    btn.addEventListener('click', function(e){
        if(this.type === 'submit') return;
        const r = document.createElement('span');
        const rc = this.getBoundingClientRect();
        const s = Math.max(rc.width, rc.height);
        const x = e.clientX - rc.left - s/2;
        const y = e.clientY - rc.top - s/2;
        r.style.cssText = 'width:'+s+'px;height:'+s+'px;left:'+x+'px;top:'+y+'px;position:absolute;border-radius:50%;background:rgba(255,255,255,0.4);transform:scale(0);pointer-events:none';
        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(r);
        r.animate([{transform:'scale(0)',opacity:1},{transform:'scale(2)',opacity:0}],{duration:600,easing:'ease-out'}).onfinish = () => r.remove();
    });
});
</script>
</body>
</html>