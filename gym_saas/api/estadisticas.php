<?php
/**
 * API: Estadísticas del Gimnasio
 * Versión optimizada con seguridad mejorada
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once(__DIR__ . "/../db_connect.php");
require_once(__DIR__ . "/../config/security.php");

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'error' => true,
        'msg' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Sanitizar y validar entrada
    $gimnasio_id = filter_input(INPUT_GET, 'gimnasio_id', FILTER_VALIDATE_INT);

    // Validaciones
    if (!$gimnasio_id || $gimnasio_id <= 0) {
        throw new Exception('ID de gimnasio inválido');
    }

    // Verificar que el gimnasio existe
    $stmt_gym = $conn->prepare("
        SELECT id, nombre, estado, fecha_fin 
        FROM gimnasios 
        WHERE id = ?
    ");
    $stmt_gym->bind_param("i", $gimnasio_id);
    $stmt_gym->execute();
    $result_gym = $stmt_gym->get_result();
    
    if ($result_gym->num_rows === 0) {
        throw new Exception('Gimnasio no encontrado');
    }
    
    $gimnasio = $result_gym->fetch_assoc();
    $stmt_gym->close();

    // ESTADÍSTICA 1: Socios totales y activos
    $stmt_socios = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
            SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos
        FROM socios 
        WHERE gimnasio_id = ?
    ");
    $stmt_socios->bind_param("i", $gimnasio_id);
    $stmt_socios->execute();
    $socios = $stmt_socios->get_result()->fetch_assoc();
    $stmt_socios->close();

    // ESTADÍSTICA 2: Licencias por estado
    $stmt_licencias = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'activa' AND fecha_fin >= CURDATE() THEN 1 ELSE 0 END) as activas,
            SUM(CASE WHEN estado = 'vencida' OR fecha_fin < CURDATE() THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
        FROM licencias_socios 
        WHERE gimnasio_id = ?
    ");
    $stmt_licencias->bind_param("i", $gimnasio_id);
    $stmt_licencias->execute();
    $licencias = $stmt_licencias->get_result()->fetch_assoc();
    $stmt_licencias->close();

    // ESTADÍSTICA 3: Pagos y facturación
    $stmt_pagos = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'pagado' THEN 1 ELSE 0 END) as pagados,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidos,
            SUM(CASE WHEN estado = 'pagado' THEN monto ELSE 0 END) as total_recaudado,
            SUM(CASE WHEN estado = 'pendiente' THEN monto ELSE 0 END) as total_pendiente
        FROM pagos 
        WHERE gimnasio_id = ?
    ");
    $stmt_pagos->bind_param("i", $gimnasio_id);
    $stmt_pagos->execute();
    $pagos = $stmt_pagos->get_result()->fetch_assoc();
    $stmt_pagos->close();

    // ESTADÍSTICA 4: Ingresos del mes actual
    $stmt_mes = $conn->prepare("
        SELECT 
            COUNT(*) as cantidad,
            SUM(monto) as total
        FROM pagos 
        WHERE gimnasio_id = ? 
            AND estado = 'pagado'
            AND MONTH(fecha) = MONTH(CURDATE())
            AND YEAR(fecha) = YEAR(CURDATE())
    ");
    $stmt_mes->bind_param("i", $gimnasio_id);
    $stmt_mes->execute();
    $mes_actual = $stmt_mes->get_result()->fetch_assoc();
    $stmt_mes->close();

    // ESTADÍSTICA 5: Próximos vencimientos (próximos 7 días)
    $stmt_venc = $conn->prepare("
        SELECT COUNT(*) as proximos_vencimientos
        FROM licencias_socios 
        WHERE gimnasio_id = ? 
            AND estado = 'activa'
            AND fecha_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt_venc->bind_param("i", $gimnasio_id);
    $stmt_venc->execute();
    $vencimientos = $stmt_venc->get_result()->fetch_assoc();
    $stmt_venc->close();

    // ESTADÍSTICA 6: Membresías más vendidas
    $stmt_top = $conn->prepare("
        SELECT 
            m.nombre,
            COUNT(ls.id) as cantidad
        FROM licencias_socios ls
        INNER JOIN membresias m ON m.id = ls.membresia_id
        WHERE ls.gimnasio_id = ?
        GROUP BY m.id, m.nombre
        ORDER BY cantidad DESC
        LIMIT 5
    ");
    $stmt_top->bind_param("i", $gimnasio_id);
    $stmt_top->execute();
    $result_top = $stmt_top->get_result();
    
    $top_membresias = [];
    while ($row = $result_top->fetch_assoc()) {
        $top_membresias[] = [
            'nombre' => htmlspecialchars($row['nombre'], ENT_QUOTES, 'UTF-8'),
            'cantidad' => (int)$row['cantidad']
        ];
    }
    $stmt_top->close();

    // Calcular estado del gimnasio
    $gimnasio_activo = $gimnasio['estado'] === 'activo';
    $licencia_vigente = false;
    $dias_restantes_licencia = null;
    
    if ($gimnasio['fecha_fin']) {
        $fecha_fin = new DateTime($gimnasio['fecha_fin']);
        $hoy = new DateTime();
        
        if ($fecha_fin >= $hoy) {
            $licencia_vigente = true;
            $dias_restantes_licencia = $hoy->diff($fecha_fin)->days;
        }
    }

    // Respuesta exitosa
    echo json_encode([
        'error' => false,
        'gimnasio' => [
            'id' => (int)$gimnasio['id'],
            'nombre' => htmlspecialchars($gimnasio['nombre'], ENT_QUOTES, 'UTF-8'),
            'estado' => $gimnasio['estado'],
            'licencia_vigente' => $licencia_vigente,
            'dias_restantes' => $dias_restantes_licencia
        ],
        'socios' => [
            'total' => (int)$socios['total'],
            'activos' => (int)$socios['activos'],
            'inactivos' => (int)$socios['inactivos']
        ],
        'licencias' => [
            'total' => (int)$licencias['total'],
            'activas' => (int)$licencias['activas'],
            'vencidas' => (int)$licencias['vencidas'],
            'pendientes' => (int)$licencias['pendientes'],
            'proximos_vencimientos' => (int)$vencimientos['proximos_vencimientos']
        ],
        'pagos' => [
            'total' => (int)$pagos['total'],
            'pagados' => (int)$pagos['pagados'],
            'pendientes' => (int)$pagos['pendientes'],
            'fallidos' => (int)$pagos['fallidos']
        ],
        'facturacion' => [
            'total_recaudado' => number_format((float)$pagos['total_recaudado'], 2, '.', ''),
            'total_pendiente' => number_format((float)$pagos['total_pendiente'], 2, '.', ''),
            'mes_actual' => [
                'cantidad' => (int)$mes_actual['cantidad'],
                'total' => number_format((float)$mes_actual['total'], 2, '.', '')
            ]
        ],
        'top_membresias' => $top_membresias
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'msg' => $e->getMessage()
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
