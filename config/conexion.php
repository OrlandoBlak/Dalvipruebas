<?php
$host     = 'fusion-investigators-spouse-falls.trycloudflare.com';
$port     = 3306;
$usuario  = 'Dalvi';
$password = 'gd2026';
$base     = 'recursoshumanos';

$conexion = new mysqli($host, $usuario, $password, $base, $port);

if ($conexion->connect_error) {
    error_log('Error BD: ' . $conexion->connect_error);
    die(json_encode([
        'error'   => 'Error de conexión con la base de datos',
        'detalle' => $conexion->connect_error
    ]));
}

$conexion->set_charset('utf8mb4');
?>