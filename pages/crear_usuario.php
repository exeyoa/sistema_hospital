<?php
require_once __DIR__ . '/../config/sesion.php';

// Protección de sesión (igual que en admin.php)
verificarSesion(['admin']);

$csrfToken = generarTokenCSRF();

require_once __DIR__ . '/../config/conexion.php'; // expone $conexion (PDO)
require_once __DIR__ . '/../config/politica_password.php';

$error = '';
$exito = '';

// -----------------------------------------------------------
// Traemos la lista de roles y especialidades para los <select>
// -----------------------------------------------------------
$roles = $conexion->query("SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);
$especialidades = $conexion->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad")->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------------------------------------
// Cuando se envía el formulario (botón "Guardar usuario")
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 0) Validación CSRF (RNF-08) — revierte solicitudes no autorizadas
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        header('Location: crear_usuario.php');
        exit;
    }

    // 1) Recogemos y limpiamos los datos del formulario
    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $usuario   = trim($_POST['usuario'] ?? '');
    $password  = $_POST['password'] ?? '';
    $id_rol    = $_POST['id_rol'] ?? '';
    $id_especialidad = $_POST['id_especialidad'] ?? '';
    $numero_colegiado = trim($_POST['numero_colegiado'] ?? '');

    // 2) Validaciones básicas
    if ($nombre === '' || $apellido === '' || $correo === '' || $usuario === '' || $password === '' || $id_rol === '') {
        $error = 'Todos los campos marcados son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } elseif ($erroresPolitica = validarPoliticaPassword($password)) {
        $error = 'La contraseña debe ' . implode(', ', $erroresPolitica) . '.';
    } else {
        // 3) Verificamos que el correo y el usuario no existan ya
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = :correo OR usuario = :usuario");
        $stmt->execute([':correo' => $correo, ':usuario' => $usuario]);

        if ($stmt->fetchColumn() > 0) {
            $error = 'Ya existe un usuario con ese correo o nombre de usuario.';
        } else {
            // 4) Todo bien -> insertamos
            try {
                $conexion->beginTransaction();

                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $conexion->prepare(
                    "INSERT INTO usuarios (nombre, apellido, correo, usuario, password_hash, id_rol, activo, fecha_creacion)
                     VALUES (:nombre, :apellido, :correo, :usuario, :password_hash, :id_rol, 1, NOW())"
                );
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':apellido' => $apellido,
                    ':correo' => $correo,
                    ':usuario' => $usuario,
                    ':password_hash' => $passwordHash,
                    ':id_rol' => $id_rol,
                ]);

                $idUsuarioNuevo = $conexion->lastInsertId();

                // Si el rol elegido corresponde a 'medico', también guardamos en la tabla medicos
                $stmtRol = $conexion->prepare("SELECT nombre_rol FROM roles WHERE id_rol = :id_rol");
                $stmtRol->execute([':id_rol' => $id_rol]);
                $nombreRolElegido = $stmtRol->fetchColumn();

                if ($nombreRolElegido === 'medico') {
                    if ($id_especialidad === '' || $numero_colegiado === '') {
                        throw new Exception('Debes indicar especialidad y número de colegiado para un médico.');
                    }
                    $stmt = $conexion->prepare(
                        "INSERT INTO medicos (id_usuario, id_especialidad, numero_colegiado)
                         VALUES (:id_usuario, :id_especialidad, :numero_colegiado)"
                    );
                    $stmt->execute([
                        ':id_usuario' => $idUsuarioNuevo,
                        ':id_especialidad' => $id_especialidad,
                        ':numero_colegiado' => $numero_colegiado,
                    ]);
                }

                $conexion->commit();
                header('Location: admin.php?creado=1');
                exit;

            } catch (Exception $e) {
                $conexion->rollBack();
                $error = 'No se pudo crear el usuario: ' . $e->getMessage();
            }
        }
    }
}

function iniciales($nombre, $apellido) {
    $n = mb_strtoupper(mb_substr($nombre, 0, 1));
    $a = mb_strtoupper(mb_substr($apellido, 0, 1));
    return $n . $a;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Usuario</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">

    <!-- ================= SIDEBAR (igual que admin.php) ================= -->
    <aside class="admin-sidebar">
        <div class="admin-logo">
            <span class="icono-logo">🩺</span>
            Hospital San Rafael
        </div>

        <nav class="admin-nav">
            <a href="admin.php">🏠 Panel de Control</a>

            <div class="admin-nav-seccion">Gestión</div>
            <a href="admin.php" class="activo">👤 Usuarios <span class="punto-activo"></span></a>
            <a href="admin_medicos.php">🧑‍⚕️ Médicos</a>
            <a href="admin_pacientes.php">🧍 Pacientes</a>
            <a href="admin_especialidades.php">🏷️ Especialidades</a>
            <a href="admin_consultas.php">📅 Consultas</a>
            <a href="admin_recetas.php">📄 Recetas</a>

            <div class="admin-nav-seccion">Sistema</div>
            <a href="admin_reportes.php">📊 Reportes</a>
            <a href="admin_configuracion.php">⚙️ Configuración</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="avatar-mini"><?php echo htmlspecialchars(iniciales($_SESSION['nombre'], '')); ?></div>
            <div>
                <div style="font-size:0.85rem; font-weight:600;"><?php echo htmlspecialchars($_SESSION['nombre']); ?></div>
                <div class="estado-linea">En línea</div>
            </div>
        </div>
    </aside>

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <div class="admin-main">

        <div class="admin-topbar">
            <h1>☰ Nuevo Usuario</h1>
            <div class="admin-topbar-derecha">
                <div class="admin-usuario-topbar">
                    <div class="avatar-mini"><?php echo htmlspecialchars(iniciales($_SESSION['nombre'], '')); ?></div>
                    <?php echo htmlspecialchars($_SESSION['nombre']); ?> ▾
                </div>
            </div>
        </div>

        <div class="admin-contenido">
            <div class="form-contenedor">
                <div class="form-card">
                    <div class="form-card-header">
                        <h2>Registrar nuevo usuario</h2>
                        <p>Completa los datos para crear una cuenta de médico, recepcionista o administrador.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alerta alerta-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="crear_usuario.php" id="formNuevoUsuario">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                        <div class="form-fila">
                            <div class="form-grupo">
                                <label for="nombre">Nombre</label>
                                <input type="text" id="nombre" name="nombre" required
                                       value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                            </div>
                            <div class="form-grupo">
                                <label for="apellido">Apellido</label>
                                <input type="text" id="apellido" name="apellido" required
                                       value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-fila">
                            <div class="form-grupo">
                                <label for="correo">Correo electrónico</label>
                                <input type="email" id="correo" name="correo" required
                                       value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>">
                            </div>
                            <div class="form-grupo">
                                <label for="usuario">Nombre de usuario</label>
                                <input type="text" id="usuario" name="usuario" required
                                       value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-fila">
                            <div class="form-grupo">
                                <label for="password">Contraseña</label>
                                <div class="input-con-icono">
                                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                                    <button type="button" class="boton-ver-password" id="botonVerPassword" aria-label="Mostrar contraseña">
                                        <svg id="iconoOjo" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M2 12C2 12 5.5 5 12 5C18.5 5 22 12 22 12C22 12 18.5 19 12 19C5.5 19 2 12 2 12Z" stroke="currentColor" stroke-width="1.6"/>
                                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6"/>
                                        </svg>
                                    </button>
                                </div>
                                <ul class="lista-requisitos-password" id="listaRequisitos">
                                    <li data-regla="longitud">Mínimo 8 caracteres</li>
                                    <li data-regla="minuscula">Incluye una letra minúscula</li>
                                    <li data-regla="mayuscula">Incluye una letra mayúscula</li>
                                    <li data-regla="especial">Incluye un carácter especial (!@#$%^&*()[\]{};:,.<>?…)</li>
                                    <li data-regla="espacios">Sin espacios en blanco</li>
                                </ul>
                            </div>
                            <div class="form-grupo">
                                <label for="id_rol">Rol</label>
                                <select id="id_rol" name="id_rol" required onchange="mostrarCamposMedico()">
                                    <option value="">-- Selecciona un rol --</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r['id_rol']; ?>"
                                            data-rol="<?php echo htmlspecialchars($r['nombre_rol']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($r['nombre_rol'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Estos campos solo se necesitan si el rol elegido es "medico" -->
                        <div class="campos-medico" id="camposMedico" style="display:none;">
                            <div class="form-fila">
                                <div class="form-grupo">
                                    <label for="id_especialidad">Especialidad</label>
                                    <select id="id_especialidad" name="id_especialidad">
                                        <option value="">-- Selecciona --</option>
                                        <?php foreach ($especialidades as $esp): ?>
                                            <option value="<?php echo $esp['id_especialidad']; ?>">
                                                <?php echo htmlspecialchars($esp['nombre_especialidad']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-grupo">
                                    <label for="numero_colegiado">Número de colegiado</label>
                                    <input type="text" id="numero_colegiado" name="numero_colegiado">
                                </div>
                            </div>
                        </div>

                        <div class="form-acciones">
                            <a href="admin.php" class="btn-secundario">Cancelar</a>
                            <button type="submit" class="btn-primario">Guardar usuario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="admin-footer">
            © <?php echo date('Y'); ?> Hospital San Rafael. Todos los derechos reservados.
        </div>
    </div>
</div>

<script>
// Muestra u oculta los campos de especialidad/colegiado según el rol elegido
function mostrarCamposMedico() {
    const select = document.getElementById('id_rol');
    const opcionElegida = select.options[select.selectedIndex];
    const rolTexto = opcionElegida ? opcionElegida.getAttribute('data-rol') : '';
    const bloque = document.getElementById('camposMedico');
    bloque.style.display = (rolTexto === 'medico') ? 'block' : 'none';
}
// Por si la página se recarga con un rol ya elegido (cuando hay un error)
document.addEventListener('DOMContentLoaded', mostrarCamposMedico);

// Botón "ver contraseña" (mismo patrón que login.php)
const botonVer = document.getElementById('botonVerPassword');
const campoPassword = document.getElementById('password');
const iconoOjo = document.getElementById('iconoOjo');

const ojoAbierto = iconoOjo.innerHTML;
const ojoCerrado = `
    <path d="M3 3L21 21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    <path d="M10.6 5.1C11.06 5.03 11.53 5 12 5C18.5 5 22 12 22 12C21.4 13.2 20.5 14.5 19.3 15.7M6.5 6.6C4 8.3 2 12 2 12C2 12 5.5 19 12 19C13.9 19 15.5 18.5 16.8 17.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    <path d="M9.9 10C9.3 10.6 9 11.3 9 12C9 13.7 10.3 15 12 15C12.7 15 13.4 14.7 14 14.1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
`;

botonVer.addEventListener('click', () => {
    const oculto = campoPassword.type === 'password';
    campoPassword.type = oculto ? 'text' : 'password';
    iconoOjo.innerHTML = oculto ? ojoCerrado : ojoAbierto;
    botonVer.setAttribute('aria-label', oculto ? 'Ocultar contraseña' : 'Mostrar contraseña');
});

// Validación en tiempo real de la política de contraseñas (solo capa visual;
// la validación definitiva la hace el servidor en config/politica_password.php)
const requisitosPassword = [
    { regla: 'longitud',  cumple: (v) => v.length >= 8 },
    { regla: 'minuscula', cumple: (v) => /[a-z]/.test(v) },
    { regla: 'mayuscula', cumple: (v) => /[A-Z]/.test(v) },
    { regla: 'especial',  cumple: (v) => /[!@#$%^&*()_+\-=\[\]{};:,.<>?]/.test(v) },
    { regla: 'espacios',  cumple: (v) => !/\s/.test(v) },
];

function actualizarRequisitosPassword(valor) {
    requisitosPassword.forEach(({ regla, cumple }) => {
        const item = document.querySelector(`#listaRequisitos li[data-regla="${regla}"]`);
        if (!item) return;
        item.classList.toggle('cumple', cumple(valor));
    });
}

const campoContrasena = document.getElementById('password');
const listaRequisitos = document.getElementById('listaRequisitos');
if (campoContrasena && listaRequisitos) {
    campoContrasena.addEventListener('input', () => actualizarRequisitosPassword(campoContrasena.value));
    actualizarRequisitosPassword(campoContrasena.value);
}
</script>

</body>
</html>
