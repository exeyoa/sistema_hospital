<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'recepcionista') {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel recepcionista - Sistema Hospital</title>
    <link rel="stylesheet" href="../css/estilo.css">
</head>
<body>
    <div class="barra-superior">
        <span class="marca">Sistema Hospital</span>
        <div>
            <span><?= htmlspecialchars($_SESSION['nombre']) ?> · Recepcionista</span>
            &nbsp;·&nbsp;<a href="logout.php">Cerrar sesión</a>
        </div>
    </div>
    <div class="contenido-panel">
        <h2>Panel de recepción</h2>
        <p>Aquí irá el registro de pacientes, agendar citas y los turnos del día.</p>
    </div>
</body>
</html>
