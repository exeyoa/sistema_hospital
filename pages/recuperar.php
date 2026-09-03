<?php
session_start();
$enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enviado = true; // En este proyecto no se envía correo real; solo confirma la solicitud.
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña - Sistema Hospital</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="pantalla-login">
        <div class="panel-marca">
            <div class="patron-decorativo" aria-hidden="true"></div>
            <div class="contenido-marca">
                <svg class="icono-cruz" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11 2H13V11H22V13H13V22H11V13H2V11H11V2Z" fill="#fff"/>
                </svg>
                <h1>Sistema Hospital</h1>
                <p>Consultas, citas y recetas en un solo lugar para tu equipo médico.</p>
            </div>
        </div>

        <div class="panel-formulario">
            <div class="tarjeta-login">
                <?php if ($enviado): ?>
                    <h2>Solicitud enviada</h2>
                    <p class="subtitulo">
                        Se notificó al administrador del sistema para que restablezca tu contraseña.
                        Te contactará a través de tu correo institucional.
                    </p>
                    <a href="login.php" class="btn" style="display:block; text-align:center; text-decoration:none;">Volver al inicio de sesión</a>
                <?php else: ?>
                    <h2>Recuperar contraseña</h2>
                    <p class="subtitulo">Ingresa tu usuario y le avisaremos al administrador para restablecerla.</p>

                    <form method="POST">
                        <div class="campo">
                            <label for="usuario">Usuario</label>
                            <div class="input-con-icono">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 12C14.7614 12 17 9.76142 17 7C17 4.23858 14.7614 2 12 2C9.23858 2 7 4.23858 7 7C7 9.76142 9.23858 12 12 12Z" stroke="currentColor" stroke-width="1.6"/>
                                    <path d="M3 21C3 16.5817 7.02944 13 12 13C16.9706 13 21 16.5817 21 21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                                </svg>
                                <input type="text" id="usuario" name="usuario" required autofocus>
                            </div>
                        </div>
                        <button type="submit" class="btn">Enviar solicitud</button>
                    </form>

                    <p class="subtitulo" style="margin-top:18px; margin-bottom:0;">
                        <a href="login.php" class="link-recuperar">Volver al inicio de sesión</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
