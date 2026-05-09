<?php
session_start();
require_once "../config/conexion.php";

$username = $_POST['username'];
$password = $_POST['password'];

// Consulta preparada (evita inyección SQL)
$sql  = "SELECT * FROM asesores WHERE UserName = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $usuario = $resultado->fetch_assoc();

    // ⚠️ Comparación de contraseña (texto plano por ahora)
    if ($password === $usuario['Password']) {

        // Guardar sesión
        $_SESSION['id']      = $usuario['Id_Dalvi'];
        $_SESSION['usuario'] = $usuario['UserName'];
        $_SESSION['rol']     = $usuario['Rol'];

        // Normaliza el rol por si viene con mayúsculas/minúsculas distintas
        $rol = strtolower(trim($usuario['Rol']));

        if ($rol === "recursos humanos") {
            $_SESSION['rol'] = "Administrador"; // tratar como admin
            header("Location: ../views/admin/homeadmin.php");
        } else {
            $_SESSION['rol'] = "Usuario";
            header("Location: ../views/usuarios/homeuser.php"); // ← corregido
        }
        exit();
    } else {
        header("Location: ../index.php?error=password");
        exit();
    }
} else {
    header("Location: ../index.php?error=usuario");
    exit();
}
?>