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
function upload_image($file, $directory = '../uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'];
    $max_size = 2 * 1024 * 1024; // 2MB

    // Validar tipo
    if (!in_array($file['type'], $allowed_types)) {
        return ['error' => 3]; // Formato no válido
    }

    // Validar tamaño
    if ($file['size'] > $max_size) {
        return ['error' => 2]; // Tamaño excedido
    }

    // Crear directorio si no existe
    $full_directory = __DIR__ . '/' . $directory;
    if (!is_dir($full_directory)) {
        mkdir($full_directory, 0755, true);
    }

    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . $extension;
    $filepath = $full_directory . $filename;

    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Retornar ruta relativa para guardar en BD
        return $directory . $filename;
    }

    return ['error' => 2];
}

// Función para actualizar configuración
function update_config($conn, $clave, $valor) {
    $stmt = $conn->prepare("INSERT INTO config (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
    $stmt->bind_param("sss", $clave, $valor, $valor);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Procesar según la acción
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'perfil':
            // Actualizar perfil del superadmin
            $nombre = sanitize_input($_POST['nombre']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'] ?? '';

            // Validar email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                header('Location: /gym_saas/superadmin/configuracion.php?error=4');
                exit;
            }

            // Actualizar con o sin contraseña
            if (!empty($password)) {
                $password_hash = md5($password);
                $stmt = $conn->prepare("UPDATE superadmin SET nombre = ?, email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("sssi", $nombre, $email, $password_hash, $_SESSION['superadmin_id']);
            } else {
                $stmt = $conn->prepare("UPDATE superadmin SET nombre = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $nombre, $email, $_SESSION['superadmin_id']);
            }

            if ($stmt->execute()) {
                $stmt->close();
                // Actualizar nombre en sesión
                $_SESSION['superadmin_nombre'] = $nombre;
                header('Location: /gym_saas/superadmin/configuracion.php?success=2');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/configuracion.php?error=1');
            }
            break;

        case 'sitio':
            // Actualizar configuración del sitio
            $nombre_sitio = sanitize_input($_POST['nombre_sitio']);
            $contacto_email = filter_var($_POST['contacto_email'], FILTER_SANITIZE_EMAIL);
            $contacto_telefono = sanitize_input($_POST['contacto_telefono']);
            $footer_texto = sanitize_input($_POST['footer_texto']);

            // Validar email de contacto
            if (!filter_var($contacto_email, FILTER_VALIDATE_EMAIL)) {
                header('Location: /gym_saas/superadmin/configuracion.php?error=4');
                exit;
            }

            // Actualizar configuraciones en tabla config
            update_config($conn, 'nombre_sitio', $nombre_sitio);
            update_config($conn, 'contacto_email', $contacto_email);
            update_config($conn, 'contacto_telefono', $contacto_telefono);
            update_config($conn, 'footer_texto', $footer_texto);

            // Procesar logo
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logo_path = upload_image($_FILES['logo']);
                
                if (is_array($logo_path) && isset($logo_path['error'])) {
                    header('Location: /gym_saas/superadmin/configuracion.php?error=' . $logo_path['error']);
                    exit;
                }
                
                if ($logo_path) {
                    $stmt = $conn->prepare("UPDATE superadmin SET logo = ? WHERE id = ?");
                    $stmt->bind_param("si", $logo_path, $_SESSION['superadmin_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Procesar favicon
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $favicon_path = upload_image($_FILES['favicon']);
                
                if (is_array($favicon_path) && isset($favicon_path['error'])) {
                    header('Location: /gym_saas/superadmin/configuracion.php?error=' . $favicon_path['error']);
                    exit;
                }
                
                if ($favicon_path) {
                    $stmt = $conn->prepare("UPDATE superadmin SET favicon = ? WHERE id = ?");
                    $stmt->bind_param("si", $favicon_path, $_SESSION['superadmin_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            header('Location: /gym_saas/superadmin/configuracion.php?success=1');
            break;

        case 'mercadopago':
            // Actualizar credenciales de MercadoPago
            $mp_public_key = sanitize_input($_POST['mp_public_key']);
            $mp_access_token = sanitize_input($_POST['mp_access_token']);

            // Actualizar en tabla superadmin
            $stmt = $conn->prepare("UPDATE superadmin SET mp_public_key = ?, mp_access_token = ? WHERE id = ?");
            $stmt->bind_param("ssi", $mp_public_key, $mp_access_token, $_SESSION['superadmin_id']);

            if ($stmt->execute()) {
                $stmt->close();
                
                // También actualizar en tabla config para uso global
                update_config($conn, 'mp_public_key', $mp_public_key);
                update_config($conn, 'mp_access_token', $mp_access_token);
                
                header('Location: /gym_saas/superadmin/configuracion.php?success=1');
            } else {
                $stmt->close();
                header('Location: /gym_saas/superadmin/configuracion.php?error=1');
            }
            break;

        default:
            header('Location: /gym_saas/superadmin/configuracion.php?error=1');
            break;
    }
} else {
    header('Location: /gym_saas/superadmin/configuracion.php');
}

$conn->close();
exit;
?>
