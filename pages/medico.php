<?php
session_start();

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php'; // expone $conexion (PDO)

$error = '';

$roles = $conexion->query("SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);
$especialidades = $conexion->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $usuario   = trim($_POST['usuario'] ?? '');
    $password  = $_POST['password'] ?? '';
    $id_rol    = $_POST['id_rol'] ?? '';
    $id_especialidad = $_POST['id_especialidad'] ?? '';
    $numero_colegiado = trim($_POST['numero_colegiado'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0; // el interruptor "Estado de la Cuenta"

    if ($nombre === '' || $apellido === '' || $correo === '' || $usuario === '' || $password === '' || $id_rol === '') {
        $error = 'Todos los campos marcados son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = :correo OR usuario = :usuario");
        $stmt->execute([':correo' => $correo, ':usuario' => $usuario]);

        if ($stmt->fetchColumn() > 0) {
            $error = 'Ya existe un usuario con ese correo o nombre de usuario.';
        } else {
            try {
                $conexion->beginTransaction();

                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $conexion->prepare(
                    "INSERT INTO usuarios (nombre, apellido, correo, usuario, password_hash, id_rol, activo, fecha_creacion)
                     VALUES (:nombre, :apellido, :correo, :usuario, :password_hash, :id_rol, :activo, NOW())"
                );
                $stmt->execute([
                    ':nombre' => $nombre,
                    ':apellido' => $apellido,
                    ':correo' => $correo,
                    ':usuario' => $usuario,
                    ':password_hash' => $passwordHash,
                    ':id_rol' => $id_rol,
                    ':activo' => $activo,
                ]);

                $idUsuarioNuevo = $conexion->lastInsertId();

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

<div class="modal-fondo">
    <div class="modal-tarjeta">

        <div class="modal-header">
            <div class="modal-header-icono">👤➕</div>
            <div class="modal-header-texto">
                <h2>Nuevo Usuario</h2>
                <p>Registrar un nuevo usuario en el sistema</p>
            </div>
            <a href="admin.php" class="modal-cerrar" title="Cerrar">✕</a>
        </div>

        <div class="modal-cuerpo">
            <?php if ($error): ?>
                <div class="alerta alerta-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="crear_usuario.php" id="formNuevoUsuario">

                <div class="seccion-titulo">Información Básica</div>

                <div class="form-fila">
                    <div class="form-grupo">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ingresa el nombre" required
                               value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="apellido">Apellido</label>
                        <input type="text" id="apellido" name="apellido" placeholder="Ingresa el apellido" required
                               value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-fila una-columna">
                    <div class="form-grupo">
                        <label for="correo">Correo Electrónico</label>
                        <input type="email" id="correo" name="correo" placeholder="usuario@correo.com" required
                               value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-fila una-columna">
                    <div class="form-grupo">
                        <label for="usuario">Nombre de Usuario</label>
                        <input type="text" id="usuario" name="usuario" placeholder="nombreusuario" required
                               value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-fila una-columna">
                    <div class="form-grupo">
                        <label for="password">Contraseña</label>
                        <div class="campo-password">
                            <input type="password" id="password" name="password" placeholder="••••••••" required minlength="6">
                            <button type="button" onclick="alternarPassword()" id="botonOjo">👁️</button>
                        </div>
                        <div class="form-ayuda">Mínimo 6 caracteres. Se guarda cifrada, nunca en texto plano.</div>
                    </div>
                </div>

                <div class="form-fila una-columna">
                    <div class="form-grupo">
                        <label for="id_rol">Rol</label>
                        <select id="id_rol" name="id_rol" required onchange="mostrarCamposMedico()">
                            <option value="">Selecciona un rol</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id_rol']; ?>"
                                    data-rol="<?php echo htmlspecialchars($r['nombre_rol']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($r['nombre_rol'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Solo se muestra cuando el rol elegido es "medico" -->
                <div id="camposMedico" style="display:none;">
                    <div class="seccion-titulo">Información Adicional</div>
                    <div class="seccion-nota">Estos campos solo aparecen cuando el rol seleccionado es Médico.</div>

                    <div class="form-fila">
                        <div class="form-grupo">
                            <label for="id_especialidad">Especialidad</label>
                            <select id="id_especialidad" name="id_especialidad">
                                <option value="">Selecciona especialidad</option>
                                <?php foreach ($especialidades as $esp): ?>
                                    <option value="<?php echo $esp['id_especialidad']; ?>">
                                        <?php echo htmlspecialchars($esp['nombre_especialidad']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-grupo">
                            <label for="numero_colegiado">Número Colegiado</label>
                            <input type="text" id="numero_colegiado" name="numero_colegiado" placeholder="Ingresa número colegiado">
                        </div>
                    </div>
                </div>

                <div class="seccion-titulo">Estado de la Cuenta</div>
                <div class="bloque-estado-cuenta">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div class="icono-estado">🔒</div>
                        <div>
                            <strong>Estado de la Cuenta</strong>
                            <span>¿Podrá acceder al sistema?</span>
                        </div>
                    </div>
                    <label class="interruptor">
                        <input type="checkbox" name="activo" checked>
                        <span class="deslizador"></span>
                    </label>
                </div>

                <div class="form-acciones">
                    <a href="admin.php" class="btn-secundario">Cancelar</a>
                    <button type="submit" class="btn-primario btn-degradado">💾 Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function mostrarCamposMedico() {
    const select = document.getElementById('id_rol');
    const opcionElegida = select.options[select.selectedIndex];
    const rolTexto = opcionElegida ? opcionElegida.getAttribute('data-rol') : '';
    document.getElementById('camposMedico').style.display = (rolTexto === 'medico') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', mostrarCamposMedico);

function alternarPassword() {
    const campo = document.getElementById('password');
    const boton = document.getElementById('botonOjo');
    if (campo.type === 'password') {
        campo.type = 'text';
        boton.textContent = '🙈';
    } else {
        campo.type = 'password';
        boton.textContent = '👁️';
    }
}
</script>

</body>
</html>
