<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

<div class="container">
    <div class="login-box">
        
        <img src="assets/logo.jpeg" alt="Logo Empresa" class="logo">

        <h2>Iniciar Sesión</h2>

        <form action="php/login.php" method="POST">
            
            <div class="input-group">
                <input type="text" name="username" required>
                <label>Usuario</label>
            </div>

            <div class="input-group">
                <input type="password" name="password" required>
                <label>Contraseña</label>
            </div>

            <button type="submit">Ingresar</button>
        </form>

        <?php
        if (isset($_GET['error'])) {
            echo "<p class='error'>Usuario o contraseña incorrectos</p>";
        }
        ?>

    </div>
</div>

</body>
</html>