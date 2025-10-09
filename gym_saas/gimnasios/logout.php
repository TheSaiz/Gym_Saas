<?php
session_start();
session_destroy();

// Definir la base del proyecto
$base_url = "/gym_saas";

// Redirigir al index de la carpeta raíz del proyecto
header("Location: $base_url/index.php");
exit;
