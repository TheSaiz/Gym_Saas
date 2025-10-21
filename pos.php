<?php
// pos.php - Punto de Venta (Recepción)
require 'db.php';

// Verificar autenticación y rol
requireAuth('recepcion');

// Manejo de creación de venta por POST
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    header('Content-Type: application/json; charset=utf-8');

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $items = null;
    $total = 0.0;
    $barber_id = null;
    $payment_method = 'efectivo';

    if(stripos($contentType, 'application/json') !== false){
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if(!is_array($data)){
            echo json_encode(['status'=>'error','msg'=>'JSON inválido']);
            exit();
        }
        $items = $data['items'] ?? null;
        $total = floatval($data['total'] ?? 0);
        $barber_id = isset($data['barber_id']) && $data['barber_id'] !== '' ? intval($data['barber_id']) : null;
        $payment_method = $data['payment_method'] ?? 'efectivo';
    }

    if(!is_array($items) || count($items) === 0){
        echo json_encode(['status'=>'error','msg'=>'No se recibieron items válidos.']);
        exit();
    }

    // Sanitizar items
    $cleanItems = [];
    foreach($items as $it){
        $iname = isset($it['name']) ? trim($it['name']) : 'Artículo';
        $iprice = isset($it['price']) ? floatval($it['price']) : 0.0;
        $iqty = isset($it['qty']) ? intval($it['qty']) : 1;
        if($iqty <= 0) $iqty = 1;
        $cleanItems[] = [
            'id' => isset($it['id']) ? intval($it['id']) : null,
            'name' => $iname,
            'price' => $iprice,
            'qty' => $iqty
        ];
    }

    // Guardar en DB
    try{
        $pdo->beginTransaction();
        
        // Insertar venta
        $stmt = $pdo->prepare("INSERT INTO sales (user_id, total, items, payment_method) VALUES (?, ?, ?, ?)");
        $stmt->execute([ 
            $_SESSION['user_id'], 
            $total, 
            json_encode($cleanItems, JSON_UNESCAPED_UNICODE),
            $payment_method
        ]);
        $saleId = $pdo->lastInsertId();
        
        // Si hay barbero seleccionado, calcular y registrar comisión
        if($barber_id && $barber_id > 0){
            $barberStmt = $pdo->prepare("SELECT commission_percentage FROM barbers WHERE id = ? AND is_active = 1");
            $barberStmt->execute([$barber_id]);
            $barber = $barberStmt->fetch(PDO::FETCH_ASSOC);
            
            if($barber){
                $commission = ($total * $barber['commission_percentage']) / 100;
                $commStmt = $pdo->prepare("INSERT INTO barber_sales (sale_id, barber_id, commission_amount) VALUES (?, ?, ?)");
                $commStmt->execute([$saleId, $barber_id, $commission]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['status'=>'ok','sale_id'=>$saleId]);
        exit();
    } catch(PDOException $e){
        $pdo->rollBack();
        echo json_encode(['status'=>'error','msg'=>'Error al guardar la venta: '.$e->getMessage()]);
        exit();
    }
}

// Obtener artículos y barberos
try {
    $articles = $pdo->query("SELECT * FROM articles WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $barbers = $pdo->query("SELECT * FROM barbers WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log('POS Error: ' . $e->getMessage());
    $articles = [];
    $barbers = [];
}

$pageTitle = 'Punto de Venta';
include 'header.php';
include 'sidebar_recep.php';
?>

<div class="content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">
                <i class="bi bi-cart-plus"></i>
                Punto de Venta
            </h3>
            <p class="text-muted mb-0" style="color: rgba(255, 255, 255, 0.6) !important;">
                <i class="bi bi-calendar-check"></i>
                <?= date('l, d \d\e F \d\e Y H:i') ?>
            </p>
        </div>
        <a href="dashboard_recep.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>
            Volver al Dashboard
        </a>
    </div>

    <div class="row">
        <!-- Columna de Artículos -->
        <div class="col-lg-8 mb-4">
            <?php if(empty($articles)): ?>
                <div class="alert alert-warning" style="background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #fbbf24; border-radius: 0.75rem;">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    No hay artículos disponibles. Contacta al administrador para agregar servicios.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach($articles as $a): ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="card h-100 border-0 shadow-sm article-card" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 1rem; cursor: pointer; transition: all 0.3s ease;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <div style="width: 45px; height: 45px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; margin-right: 0.75rem;">
                                            <i class="bi bi-scissors" style="color: white; font-size: 1.25rem;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0" style="color: white; font-weight: 600; font-size: 0.95rem;"><?= e($a['name']) ?></h6>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="badge bg-success" style="font-size: 1rem; padding: 0.5rem 0.75rem;">
                                            $<?= number_format($a['price'], 2) ?>
                                        </span>
                                        <button class="btn btn-sm btn-primary add-item-btn" 
                                                data-id="<?= $a['id'] ?>" 
                                                data-name="<?= e($a['name']) ?>" 
                                                data-price="<?= $a['price'] ?>">
                                            <i class="bi bi-plus-circle me-1"></i>
                                            Agregar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Columna de Ticket -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); border-radius: 1rem; position: sticky; top: 20px;">
                <div class="card-body p-4">
                    <h5 class="mb-3" style="color: white; font-weight: 700;">
                        <i class="bi bi-receipt me-2"></i>
                        Ticket de Venta
                    </h5>

                    <!-- Selección de Barbero -->
                    <div class="mb-3">
                        <label class="form-label" style="color: rgba(255, 255, 255, 0.8); font-size: 0.875rem; font-weight: 600;">
                            <i class="bi bi-person-badge me-1"></i>
                            Barbero
                        </label>
                        <select id="barber-select" class="form-select" style="background: #0f172a; border: 1px solid #334155; color: white; border-radius: 0.5rem;">
                            <option value="">Sin barbero asignado</option>
                            <?php foreach($barbers as $b): ?>
                                <option value="<?= $b['id'] ?>">
                                    <?= e($b['name']) ?> (<?= $b['commission_percentage'] ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Lista de Items -->
                    <div id="ticket-items" style="max-height: 350px; overflow-y: auto; margin-bottom: 1rem;">
                        <div class="text-center py-5" style="color: rgba(255, 255, 255, 0.4);">
                            <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                            <div class="mt-2">Carrito vacío</div>
                            <small>Selecciona servicios para comenzar</small>
                        </div>
                    </div>

                    <!-- Total -->
                    <div class="mb-3 p-3 text-center" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 0.75rem;">
                        <div style="color: white; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">TOTAL</div>
                        <div style="color: white; font-size: 2rem; font-weight: 700;">$<span id="total">0.00</span></div>
                    </div>

                    <!-- Botón Finalizar -->
                    <button id="complete-sale-btn" class="btn w-100" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; font-weight: 700; padding: 1rem; border-radius: 0.75rem; border: none;" disabled>
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Finalizar Venta
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Pago -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: #1e293b; border: 1px solid #334155; border-radius: 1rem;">
            <div class="modal-header" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-bottom: none; border-radius: 1rem 1rem 0 0;">
                <h5 class="modal-title" style="color: white; font-weight: 700;">
                    <i class="bi bi-credit-card me-2"></i>
                    Método de Pago
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p style="color: rgba(255, 255, 255, 0.8); text-align: center; margin-bottom: 1.5rem;">
                    Selecciona el método de pago para esta venta
                </p>

                <div class="payment-option" data-payment="efectivo" style="background: #0f172a; border: 2px solid #334155; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; cursor: pointer; text-align: center; transition: all 0.3s ease;">
                    <i class="bi bi-cash-stack" style="font-size: 2.5rem; color: #10b981; display: block; margin-bottom: 0.75rem;"></i>
                    <div style="color: white; font-weight: 700; font-size: 1.1rem; margin-bottom: 0.25rem;">Efectivo</div>
                    <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem;">Pago en efectivo</div>
                </div>

                <div class="payment-option" data-payment="tarjeta" style="background: #0f172a; border: 2px solid #334155; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; cursor: pointer; text-align: center; transition: all 0.3s ease;">
                    <i class="bi bi-credit-card" style="font-size: 2.5rem; color: #3b82f6; display: block; margin-bottom: 0.75rem;"></i>
                    <div style="color: white; font-weight: 700; font-size: 1.1rem; margin-bottom: 0.25rem;">Tarjeta</div>
                    <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem;">Débito o Crédito</div>
                </div>

                <div class="payment-option" data-payment="transferencia" style="background: #0f172a; border: 2px solid #334155; border-radius: 0.75rem; padding: 1.5rem; margin-bottom: 1rem; cursor: pointer; text-align: center; transition: all 0.3s ease;">
                    <i class="bi bi-arrow-left-right" style="font-size: 2.5rem; color: #f59e0b; display: block; margin-bottom: 0.75rem;"></i>
                    <div style="color: white; font-weight: 700; font-size: 1.1rem; margin-bottom: 0.25rem;">Transferencia</div>
                    <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.875rem;">Transferencia bancaria</div>
                </div>

                <div style="background: #0f172a; border: 1px solid #334155; border-radius: 0.75rem; padding: 1.25rem; margin-top: 1.5rem; text-align: center;">
                    <div style="color: rgba(255, 255, 255, 0.8); margin-bottom: 0.5rem;">Total a cobrar</div>
                    <div style="color: #10b981; font-size: 1.5rem; font-weight: 700;">$<span id="modal-total">0.00</span></div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #334155;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>
                    Cancelar
                </button>
                <button type="button" id="confirm-payment-btn" class="btn btn-primary" disabled>
                    <i class="bi bi-check-circle me-1"></i>
                    Confirmar Venta
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.article-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 25px rgba(59, 130, 246, 0.2) !important;
}

.payment-option:hover {
    border-color: #3b82f6 !important;
    transform: translateY(-2px);
}

.payment-option.selected {
    border-color: #10b981 !important;
    background: rgba(16, 185, 129, 0.1) !important;
}

#ticket-items::-webkit-scrollbar {
    width: 6px;
}

#ticket-items::-webkit-scrollbar-track {
    background: #0f172a;
    border-radius: 3px;
}

#ticket-items::-webkit-scrollbar-thumb {
    background: #334155;
    border-radius: 3px;
}

#ticket-items::-webkit-scrollbar-thumb:hover {
    background: #475569;
}

.ticket-item {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
    transition: all 0.2s ease;
}

.ticket-item:hover {
    border-color: #334155;
}

.btn-qty {
    background: #334155;
    border: none;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 0.375rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-qty:hover {
    background: #475569;
}

.btn-qty.btn-remove {
    background: #dc2626;
}

.btn-qty.btn-remove:hover {
    background: #b91c1c;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    let cart = [];
    let selectedPayment = null;
    let paymentModal = null;

    // Inicializar modal
    document.addEventListener('DOMContentLoaded', function() {
        paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    });

    function renderCart(){
        const ticketContainer = document.getElementById('ticket-items');
        const completeBtn = document.getElementById('complete-sale-btn');
        
        if(cart.length === 0){
            ticketContainer.innerHTML = `
                <div class="text-center py-5" style="color: rgba(255, 255, 255, 0.4);">
                    <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                    <div class="mt-2">Carrito vacío</div>
                    <small>Selecciona servicios para comenzar</small>
                </div>
            `;
            completeBtn.disabled = true;
        } else {
            ticketContainer.innerHTML = '';
            
            cart.forEach((item, idx) => {
                const subtotal = parseFloat(item.price) * parseInt(item.qty);
                
                const itemDiv = document.createElement('div');
                itemDiv.className = 'ticket-item';
                itemDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div style="flex: 1;">
                            <div style="color: white; font-weight: 600; margin-bottom: 0.25rem; font-size: 0.95rem;">${item.name}</div>
                            <div style="color: rgba(255, 255, 255, 0.6); font-size: 0.8rem;">
                                $${parseFloat(item.price).toFixed(2)} × ${item.qty} = $${subtotal.toFixed(2)}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn-qty dec-btn" data-idx="${idx}">
                            <i class="bi bi-dash"></i>
                        </button>
                        <span style="background: #1e293b; color: white; padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-weight: 600; font-size: 0.875rem; min-width: 40px; text-align: center;">${item.qty}</span>
                        <button class="btn-qty inc-btn" data-idx="${idx}">
                            <i class="bi bi-plus"></i>
                        </button>
                        <div style="flex: 1;"></div>
                        <button class="btn-qty btn-remove rm-btn" data-idx="${idx}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                ticketContainer.appendChild(itemDiv);
            });
            
            completeBtn.disabled = false;
        }
        
        const total = cart.reduce((sum, item) => sum + (parseFloat(item.price) * parseInt(item.qty)), 0);
        document.getElementById('total').textContent = total.toFixed(2);
        document.getElementById('modal-total').textContent = total.toFixed(2);
    }

    // Agregar item al carrito
    document.addEventListener('click', function(e){
        const addBtn = e.target.closest('.add-item-btn');
        if(addBtn){
            e.preventDefault();
            e.stopPropagation();
            
            const id = addBtn.getAttribute('data-id');
            const name = addBtn.getAttribute('data-name');
            const price = parseFloat(addBtn.getAttribute('data-price'));
            
            const found = cart.find(c => c.id == id);
            if(found){
                found.qty++;
            } else {
                cart.push({id: id, name: name, price: price, qty: 1});
            }
            
            const originalHTML = addBtn.innerHTML;
            addBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Agregado';
            setTimeout(() => {
                addBtn.innerHTML = originalHTML;
            }, 600);
            
            renderCart();
        }

        // Incrementar
        const incBtn = e.target.closest('.inc-btn');
        if(incBtn){
            const idx = parseInt(incBtn.getAttribute('data-idx'));
            cart[idx].qty++;
            renderCart();
        }
        
        // Decrementar
        const decBtn = e.target.closest('.dec-btn');
        if(decBtn){
            const idx = parseInt(decBtn.getAttribute('data-idx'));
            if(cart[idx].qty > 1){
                cart[idx].qty--;
            } else {
                if(confirm('¿Eliminar este servicio del carrito?')){
                    cart.splice(idx, 1);
                }
            }
            renderCart();
        }
        
        // Remover
        const rmBtn = e.target.closest('.rm-btn');
        if(rmBtn){
            const idx = parseInt(rmBtn.getAttribute('data-idx'));
            if(confirm('¿Eliminar este servicio del carrito?')){
                cart.splice(idx, 1);
                renderCart();
            }
        }

        // Abrir modal de pago
        const completeBtn = e.target.closest('#complete-sale-btn');
        if(completeBtn){
            if(cart.length === 0){
                alert('El carrito está vacío.');
                return;
            }
            selectedPayment = null;
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.getElementById('confirm-payment-btn').disabled = true;
            paymentModal.show();
        }

        // Seleccionar método de pago
        const paymentOpt = e.target.closest('.payment-option');
        if(paymentOpt){
            selectedPayment = paymentOpt.getAttribute('data-payment');
            
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            paymentOpt.classList.add('selected');
            document.getElementById('confirm-payment-btn').disabled = false;
        }

        // Confirmar venta
        const confirmBtn = e.target.closest('#confirm-payment-btn');
        if(confirmBtn){
            if(!selectedPayment){
                alert('Selecciona un método de pago');
                return;
            }

            const total = parseFloat(document.getElementById('total').textContent);
            const barberId = document.getElementById('barber-select').value;
            
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Procesando...';

            fetch('pos.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ 
                    items: cart, 
                    total: total,
                    barber_id: barberId || null,
                    payment_method: selectedPayment
                })
            })
            .then(r => r.json())
            .then(j => {
                if(j.status === 'ok'){
                    paymentModal.hide();
                    
                    const paymentText = {
                        'efectivo': 'EFECTIVO',
                        'tarjeta': 'TARJETA',
                        'transferencia': 'TRANSFERENCIA'
                    };
                    
                    alert('✅ Venta registrada exitosamente!\n\n' +
                          '📋 ID de Venta: #' + j.sale_id + '\n' +
                          '💵 Total: $' + total.toFixed(2) + '\n' +
                          '💳 Método: ' + paymentText[selectedPayment]);
                    
                    cart = [];
                    selectedPayment = null;
                    document.getElementById('barber-select').value = '';
                    renderCart();
                } else {
                    alert('❌ Error: ' + (j.msg || 'No se pudo registrar la venta.'));
                }
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Confirmar Venta';
            })
            .catch(err => {
                alert('❌ Error de conexión: ' + err.message);
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Confirmar Venta';
            });
        }
    });

    // Render inicial
    renderCart();
})();
</script>

</body>
</html>