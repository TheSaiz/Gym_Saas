<?php
include_once(__DIR__ . "/../includes/auth_check.php");
checkSuperAdminLogin();

if($_SERVER['REQUEST_METHOD']=='POST'){
    $configJson = file_get_contents(__DIR__."/../data/config.json");
    $config = json_decode($configJson,true);

    foreach($_POST as $key => $value){
        $config[$key] = $value;
    }

    file_put_contents(__DIR__."/../data/config.json", json_encode($config, JSON_PRETTY_PRINT));
    header("Location: configuracion.php");
    exit;
}
?>
