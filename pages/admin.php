<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel administrador - Sistema Hospital</title>
    <link rel="stylesheet" href="../css/estilo.css">
</head>
<body>
    <div class="barra-superior">
        <span class="marca">Sistema Hospital</span>
        <div>
            <span><?= htmlspecialchars($_SESSION['nombre']) ?> · Administrador</span>
            &nbsp;·&nbsp;<a href="logout.php">Cerrar sesión</a>
        </div>
    </div>
    <div class="contenido-panel">
        <h2>Panel de administrador</h2>
        <p>Aquí irá la gestión de usuarios y los reportes del sistema.</p>
    </div>
</body>
</html>
