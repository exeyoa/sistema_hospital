<?php
session_start();

// Protección de sesión (igual que en admin.php)
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php'; // expone $conexion (PDO)

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
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
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
                                <input type="password" id="password" name="password" required minlength="6">
                                <div class="form-ayuda">Mínimo 6 caracteres. Se guarda cifrada, nunca en texto plano.</div>
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
</script>

</body>
</html>
