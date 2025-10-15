<?php
session_start();

if (!isset($_SESSION['superadmin_id'])) {
    header('Location: /gym_saas/superadmin/login.php');
    exit;
}

require_once __DIR__ . '/../includes/db_connect.php';

// Función para sanitizar entrada
function sanitize_input($data) {
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

// Función para subir imágenes
function upload_image($file, $directory = '../uploads/gimnasios/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed_types)) {
        return ['error' => 'Formato de imagen no válido'];
    }

    if ($file['size'] > $max_size) {
        return ['error' => 'El archivo es demasiado grande'];
    }

    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('gym_', true) . '.' . $extension;
    $filepath = $directory . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filepath;
    }

    return ['error' => 'Error al subir el archivo'];
}

// Validar datos del gimnasio
function validate_gimnasio_data($nombre, $email, $licencia_id = null, $fecha_inicio = null, $fecha_fin = null) {
    $errors = [];

    if (empty($nombre)) {
        $errors[] = "El nombre del gimnasio es obligatorio";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "El email no es válido";
    }

    if ($licencia_id !== null && !is_numeric($licencia_id)) {
        $errors[] = "La licencia seleccionada no es válida";
    }

    if (!empty($fecha_inicio) && !empty($fecha_fin)) {
        if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
            $errors[] = "La fecha de fin no puede ser anterior a la fecha de inicio";
        }
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'crear':
            $nombre = sanitize_input($_POST['nombre']);
            $direccion = sanitize_input($_POST['direccion'] ?? '');
            $altura = sanitize_input($_POST['altura'] ?? '');
            $localidad = sanitize_input($_POST['localidad'] ?? '');
            $partido = sanitize_input($_POST['partido'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';
            $licencia_id = !empty($_POST['licencia_id']) ? intval($_POST['licencia_id']) : null;
            $estado = in_array($_POST['estado'] ?? 'activo', ['activo', 'suspendido']) ? $_POST['estado'] : 'activo';
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $fecha_fin = $_POST['fecha_fin'] ?? null;

            $errors = validate_gimnasio_data($nombre, $email, $licencia_id, $fecha_inicio, $fecha_fin);
            if (!empty($errors)) {
                header('Location: /gym_saas/superadmin/gimnasios.php?error=' . urlencode(implode(', ', $errors)));
                exit;
            }

            if (!empty($email)) {
                $stmt = $conn->prepare("SELECT id FROM gimnasios WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    header('Location: /gym_saas/superadmin/gimnasios.php?error=email_exists');
                    exit;
                }
                $stmt->close();
            }

            $password_hash = !empty($password) ? md5($password) : null;

            $stmt = $conn->prepare("INSERT INTO gimnasios (nombre, direccion, altura, localidad, partido, email, password, licencia_id, estado, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", $nombre, $direccion, $altura, $localidad, $partido, $email, $password_hash, $licencia_id, $estado, $fecha_inicio, $fecha_fin);

            if ($stmt->execute()) {
                $gimnasio_id = $stmt->insert_id;
                $stmt->close();
                
                $gym_dir = __DIR__ . '/../subdominios/gimnasio_' . $gimnasio_id;
                if (!is_dir($gym_dir)) {
                    mkdir($gym_dir, 0755, true);
                    mkdir($gym_dir . '/assets', 0755, true);
                }

                header('Location: /gym_saas/superadmin/gimnasios.php?success=created');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/gimnasios.php?error=db_error');
            }
            break;

        case 'editar':
            $id = intval($_POST['id']);
            $nombre = sanitize_input($_POST['nombre']);
            $direccion = sanitize_input($_POST['direccion'] ?? '');
            $altura = sanitize_input($_POST['altura'] ?? '');
            $localidad = sanitize_input($_POST['localidad'] ?? '');
            $partido = sanitize_input($_POST['partido'] ?? '');
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';
            $licencia_id = !empty($_POST['licencia_id']) ? intval($_POST['licencia_id']) : null;
            $estado = in_array($_POST['estado'] ?? 'activo', ['activo', 'suspendido']) ? $_POST['estado'] : 'activo';
            $fecha_inicio = $_POST['fecha_inicio'] ?? null;
            $fecha_fin = $_POST['fecha_fin'] ?? null;

            if ($id <= 0) {
                header('Location: /gym_saas/superadmin/gimnasios.php?error=invalid_id');
                exit;
            }

            $errors = validate_gimnasio_data($nombre, $email, $licencia_id, $fecha_inicio, $fecha_fin);
            if (!empty($errors)) {
                header('Location: /gym_saas/superadmin/editar_gimnasio.php?id=' . $id . '&error=' . urlencode(implode(', ', $errors)));
                exit;
            }

            if (!empty($email)) {
                $stmt = $conn->prepare("SELECT id FROM gimnasios WHERE email = ? AND id != ?");
                $stmt->bind_param("si", $email, $id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $stmt->close();
                    header('Location: /gym_saas/superadmin/editar_gimnasio.php?id=' . $id . '&error=email_exists');
                    exit;
                }
                $stmt->close();
            }

            if (!empty($password)) {
                $password_hash = md5($password);
                $stmt = $conn->prepare("UPDATE gimnasios SET nombre = ?, direccion = ?, altura = ?, localidad = ?, partido = ?, email = ?, password = ?, licencia_id = ?, estado = ?, fecha_inicio = ?, fecha_fin = ? WHERE id = ?");
                $stmt->bind_param("sssssssisssi", $nombre, $direccion, $altura, $localidad, $partido, $email, $password_hash, $licencia_id, $estado, $fecha_inicio, $fecha_fin, $id);
            } else {
                $stmt = $conn->prepare("UPDATE gimnasios SET nombre = ?, direccion = ?, altura = ?, localidad = ?, partido = ?, email = ?, licencia_id = ?, estado = ?, fecha_inicio = ?, fecha_fin = ? WHERE id = ?");
                $stmt->bind_param("sssssissssi", $nombre, $direccion, $altura, $localidad, $partido, $email, $licencia_id, $estado, $fecha_inicio, $fecha_fin, $id);
            }

            if ($stmt->execute()) {
                $stmt->close();
                
                if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                    $logo_path = upload_image($_FILES['logo']);
                    if (is_array($logo_path) && isset($logo_path['error'])) {
                        header('Location: /gym_saas/superadmin/editar_gimnasio.php?id=' . $id . '&error=' . urlencode($logo_path['error']));
                        exit;
                    }
                    if ($logo_path) {
                        $stmt = $conn->prepare("UPDATE gimnasios SET logo = ? WHERE id = ?");
                        $stmt->bind_param("si", $logo_path, $id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                    $favicon_path = upload_image($_FILES['favicon']);
                    if (is_array($favicon_path) && isset($favicon_path['error'])) {
                        header('Location: /gym_saas/superadmin/editar_gimnasio.php?id=' . $id . '&error=' . urlencode($favicon_path['error']));
                        exit;
                    }
                    if ($favicon_path) {
                        $stmt = $conn->prepare("UPDATE gimnasios SET favicon = ? WHERE id = ?");
                        $stmt->bind_param("si", $favicon_path, $id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }

                header('Location: /gym_saas/superadmin/editar_gimnasio.php?id=' . $id . '&success=updated');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/editar_gimnasio.php?id=' . $id . '&error=db_error');
            }
            break;

        case 'eliminar':
            $id = intval($_POST['id']);

            if ($id <= 0) {
                header('Location: /gym_saas/superadmin/gimnasios.php?error=invalid_id');
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM gimnasios WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $stmt->close();
                
                $gym_dir = __DIR__ . '/../subdominios/gimnasio_' . $id;
                if (is_dir($gym_dir)) {
                    function deleteDirectory($dir) {
                        if (!file_exists($dir)) return true;
                        if (!is_dir($dir)) return unlink($dir);
                        foreach (scandir($dir) as $item) {
                            if ($item == '.' || $item == '..') continue;
                            if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
                        }
                        return rmdir($dir);
                    }
                    deleteDirectory($gym_dir);
                }

                header('Location: /gym_saas/superadmin/gimnasios.php?success=deleted');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/gimnasios.php?error=not_found');
            }
            break;

        case 'toggle_estado':
            $id = intval($_POST['id']);

            if ($id <= 0) {
                header('Location: /gym_saas/superadmin/gimnasios.php?error=invalid_id');
                exit;
            }

            $stmt = $conn->prepare("SELECT estado FROM gimnasios WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$result) {
                header('Location: /gym_saas/superadmin/gimnasios.php?error=not_found');
                exit;
            }

            $nuevo_estado = ($result['estado'] === 'activo') ? 'suspendido' : 'activo';
            $stmt = $conn->prepare("UPDATE gimnasios SET estado = ? WHERE id = ?");
            $stmt->bind_param("si", $nuevo_estado, $id);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: /gym_saas/superadmin/gimnasios.php?success=status_changed');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/gimnasios.php?error=db_error');
            }
            break;

        default:
            header('Location: /gym_saas/superadmin/gimnasios.php?error=invalid_action');
            break;
    }
} else {
    header('Location: /gym_saas/superadmin/gimnasios.php');
}

$conn->close();
exit;
?>
