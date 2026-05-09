<?php
// ARCHIVO TEMPORAL DE DIAGNÓSTICO
// Coloca en: php/test_kpis.php
// Abre en: http://localhost/RecursosHumanos/php/test_kpis.php
// BORRA este archivo después de solucionar

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
echo "<h3>1. Sesión:</h3>";
echo "id: " . ($_SESSION['id'] ?? 'NO HAY') . "<br>";
echo "rol: " . ($_SESSION['rol'] ?? 'NO HAY') . "<br>";

require_once "../config/conexion.php";
echo "<h3>2. Conexión BD:</h3> OK<br>";

echo "<h3>3. Tabla kps - columnas:</h3>";
$cols = $conexion->query("SHOW COLUMNS FROM kps");
while($c = $cols->fetch_assoc()) {
    echo $c['Field'] . " (" . $c['Type'] . ")<br>";
}

echo "<h3>4. Query areas:</h3>";
$res = $conexion->query("SELECT Id_Area, Nombre FROM areas ORDER BY Nombre ASC");
if (!$res) {
    echo "ERROR: " . $conexion->error;
} else {
    while($r = $res->fetch_assoc()) {
        echo $r['Id_Area'] . " - " . $r['Nombre'] . "<br>";
    }
}

echo "<h3>5. Query kps list:</h3>";
$res2 = $conexion->query("
    SELECT k.Id_KPs, k.Nombre, k.Tipo, k.Id_Area, k.Metas, k.Dato_Ingreso,
           a.Nombre AS area_nombre
    FROM kps k
    INNER JOIN areas a ON a.Id_Area = k.Id_Area
    ORDER BY a.Nombre ASC, k.Nombre ASC
");
if (!$res2) {
    echo "ERROR: " . $conexion->error;
} else {
    echo "OK - " . $res2->num_rows . " registros";
}
?>