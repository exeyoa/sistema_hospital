<?php
session_start();

// Si ya inició sesión, lo mandamos directo a su panel
if (isset($_SESSION['id_usuario'])) {
    header("Location: " . $_SESSION['rol'] . ".php");
    exit;
}

$error = $_SESSION['error_login'] ?? null;
unset($_SESSION['error_login']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión - Sistema Hospital</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="pantalla-login">
        <div class="panel-marca">
            <svg class="icono-cruz" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11 2H13V11H22V13H13V22H11V13H2V11H11V2Z" fill="#fff"/>
            </svg>
            <h1>Sistema Hospital</h1>
            <p>Consultas, citas y recetas en un solo lugar para tu equipo médico.</p>
        </div>

        <div class="panel-formulario">
            <div class="tarjeta-login">
                <h2>Iniciar sesión</h2>
                <p class="subtitulo">Ingresa con tu usuario asignado.</p>

                <?php if ($error): ?>
                    <div class="mensaje-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="procesar_login.php" method="POST">
                    <div class="campo">
                        <label for="usuario">Usuario</label>
                        <input type="text" id="usuario" name="usuario" required autofocus>
                    </div>
                    <div class="campo">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
