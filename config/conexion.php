<?php
/**
 * config/conexion.php
 * Lee las credenciales desde el archivo .env
 * El .env NUNCA se sube a GitHub
 */

function cargarEnv($rutaEnv) {
    if (!file_exists($rutaEnv)) return;
    $lineas = file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if (str_starts_with($linea, '#') || !str_contains($linea, '=')) continue;
        [$clave, $valor] = explode('=', $linea, 2);
        $clave = trim($clave);
        $valor = trim(trim($valor), '"\'');
        if (!array_key_exists($clave, $_ENV)) {
            $_ENV[$clave] = $valor;
            putenv("$clave=$valor");
        }
    }
}

// Buscar .env en la raíz del proyecto (un nivel arriba de config/)
cargarEnv(dirname(__DIR__) . '/.env');

$host     = $_ENV['DB_HOST'] ?? 'localhost';
$port     = (int)($_ENV['DB_PORT'] ?? 3306);
$usuario  = $_ENV['DB_USER'] ?? '';
$password = $_ENV['DB_PASS'] ?? '';
$base     = $_ENV['DB_NAME'] ?? 'recursoshumanos';

$conexion = new mysqli($host, $usuario, $password, $base, $port);

if ($conexion->connect_error) {
    error_log('Error BD: ' . $conexion->connect_error);
    $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    die(json_encode([
        'error'   => 'Error de conexión con la base de datos',
        'detalle' => $debug ? $conexion->connect_error : 'Contacta al administrador',
    ]));
}

$conexion->set_charset('utf8mb4');
?>