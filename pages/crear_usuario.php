<?php
require_once __DIR__ . '/../config/sesion.php';

// Protección de sesión (igual que en admin.php)
verificarSesion(['admin']);

$csrfToken = generarTokenCSRF();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php'; // expone $conexion (PDO)
require_once __DIR__ . '/../config/politica_password.php';

$error = '';

$roles = $conexion->query("SELECT id_rol, nombre_rol FROM roles ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);
$especialidades = $conexion->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 0) Validación CSRF (RNF-08) — revierte solicitudes no autorizadas
    if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
        header('Location: crear_usuario.php');
        exit;
    }

    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $correo    = trim($_POST['correo'] ?? '');
    $usuario   = trim($_POST['usuario'] ?? '');
    $cedula    = trim($_POST['cedula'] ?? '');
    $password  = $_POST['password'] ?? '';
    $id_rol    = $_POST['id_rol'] ?? '';
    $id_especialidad = $_POST['id_especialidad'] ?? '';
    $numero_colegiado = trim($_POST['numero_colegiado'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    // 2) Validaciones básicas
    if ($nombre === '' || $apellido === '' || $correo === '' || $usuario === '' || $cedula === '' || $password === '' || $id_rol === '') {
        $error = 'Todos los campos marcados son obligatorios.';
    } elseif (!preg_match('/^(?:[A-Z]{1,2}-)?\d{1,4}-\d{1,4}(?:-\d{1,4})?$/', $cedula)) {
        $error = 'La cédula no tiene un formato válido (ejemplo: 8-123-456).';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } elseif ($erroresPolitica = validarPoliticaPassword($password)) {
        $error = 'La contraseña debe ' . implode(', ', $erroresPolitica) . '.';
    } else {
        // 3) Verificación de identidad: la cédula DEBE existir en el Tribunal
        // Electoral (fail-closed: si no se puede verificar positivamente,
        // no se crea la cuenta).
        require_once __DIR__ . '/../config/api_tribunal.php';
        $respuestaTribunal = consultarCedulaTribunal($cedula);

        if ($respuestaTribunal['codigo'] === 404) {
            $error = 'La cédula ' . htmlspecialchars($cedula, ENT_QUOTES, 'UTF-8') . ' no está registrada en el Tribunal Electoral; no se puede crear el usuario.';
        } elseif ($respuestaTribunal['codigo'] !== 200) {
            $error = 'No se pudo verificar la cédula con el Tribunal Electoral; inténtalo más tarde.';
        } else {
            // 4) Verificamos que el correo y el usuario no existan ya
            $stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios WHERE correo = :correo OR usuario = :usuario");
            $stmt->execute([':correo' => $correo, ':usuario' => $usuario]);

            if ($stmt->fetchColumn() > 0) {
                $error = 'Ya existe un usuario con ese correo o nombre de usuario.';
            } else {
                // 5) Validación de los campos propios del médico (fuera de la transacción)
                $stmtRol = $conexion->prepare("SELECT nombre_rol FROM roles WHERE id_rol = :id_rol");
                $stmtRol->execute([':id_rol' => $id_rol]);
                $nombreRolElegido = $stmtRol->fetchColumn();

                if ($nombreRolElegido === 'medico' && ($id_especialidad === '' || $numero_colegiado === '')) {
                    $error = 'Debes indicar especialidad y número de colegiado para un médico.';
                } else {
                    // 6) Todo bien -> insertamos
                    try {
                        $conexion->beginTransaction();

                        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                        $stmt = $conexion->prepare(
                            "INSERT INTO usuarios (nombre, apellido, correo, usuario, cedula, password_hash, id_rol, activo, fecha_creacion)
                             VALUES (:nombre, :apellido, :correo, :usuario, :cedula, :password_hash, :id_rol, :activo, NOW())"
                        );
                        $stmt->execute([
                            ':nombre' => $nombre,
                            ':apellido' => $apellido,
                            ':correo' => $correo,
                            ':usuario' => $usuario,
                            ':cedula' => $cedula,
                            ':password_hash' => $passwordHash,
                            ':id_rol' => $id_rol,
                            ':activo' => $activo,
                        ]);

                        $idUsuarioNuevo = $conexion->lastInsertId();

                        // Si el rol elegido corresponde a 'medico', también guardamos en la tabla medicos
                        if ($nombreRolElegido === 'medico') {
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

                    } catch (PDOException $e) {
                        $conexion->rollBack();
                        if ($e->getCode() === '23000') {
                            $error = 'Ya existe un usuario con esa cédula.';
                        } else {
                            error_log('Error al crear usuario: ' . $e->getMessage());
                            $error = 'No se pudo crear el usuario. Intenta nuevamente o contacta al administrador del sistema.';
                        }
                    } catch (Exception $e) {
                        $conexion->rollBack();
                        error_log('Error al crear usuario: ' . $e->getMessage());
                        $error = 'No se pudo crear el usuario. Intenta nuevamente o contacta al administrador del sistema.';
                    }
                }
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

    <!-- ================= SIDEBAR ================= -->
    <aside class="admin-sidebar">
        <div class="admin-logo">
            <span class="icono-logo"><?php echo icono('cruz-medica', 22); ?></span>
            Hospital San Rafael
        </div>

        <nav class="admin-nav">
            <a href="admin.php"><?php echo icono('home'); ?> Panel de Control</a>

            <div class="admin-nav-seccion">Gestión</div>
            <a href="admin.php" class="activo"><?php echo icono('usuarios'); ?> Usuarios <span class="punto-activo"></span></a>
            <a href="admin_medicos.php"><?php echo icono('medico'); ?> Médicos</a>
            <a href="admin_pacientes.php"><?php echo icono('paciente'); ?> Pacientes</a>
            <a href="admin_especialidades.php"><?php echo icono('estrella'); ?> Especialidades</a>
            <a href="admin_consultas.php"><?php echo icono('calendario'); ?> Consultas</a>
            <a href="admin_recetas.php"><?php echo icono('archivo'); ?> Recetas</a>

            <div class="admin-nav-seccion">Sistema</div>
            <a href="admin_reportes.php"><?php echo icono('grafico'); ?> Reportes</a>
            <a href="admin_configuracion.php"><?php echo icono('engranaje'); ?> Configuración</a>
            <a href="logout.php"><?php echo icono('salir'); ?> Cerrar Sesión</a>
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
            <div class="admin-topbar-izquierda">
                <a href="admin.php" class="admin-back-btn"><?php echo icono('flecha-izq', 18); ?></a>
                <div>
                    <h1>Nuevo usuario</h1>
                    <p class="admin-topbar-subtitulo">Crea una nueva cuenta de usuario en el sistema</p>
                </div>
            </div>
            <div class="admin-topbar-derecha">
                <div class="admin-campana"><?php echo icono('campana', 20); ?><span class="badge-num"></span></div>
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
                        <div class="form-card-header-icono"><?php echo icono('usuario-mas', 26); ?></div>
                        <div>
                            <h2>Registrar nuevo usuario</h2>
                            <p>Completa los datos para crear una cuenta de médico, recepcionista o administrador.</p>
                        </div>
                    </div>

                    <?php if ($error): ?>
                        <div class="alerta alerta-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="crear_usuario.php" id="formNuevoUsuario">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                        <div class="form-fila">
                            <div class="form-grupo">
                                <label for="nombre"><?php echo icono('usuario', 15); ?> Nombre</label>
                                <div class="campo-icono">
                                    <?php echo icono('usuario', 16); ?>
                                    <input type="text" id="nombre" name="nombre" placeholder="Ingresa el nombre" required
                                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-grupo">
                                <label for="apellido"><?php echo icono('usuario', 15); ?> Apellido</label>
                                <div class="campo-icono">
                                    <?php echo icono('usuario', 16); ?>
                                    <input type="text" id="apellido" name="apellido" placeholder="Ingresa el apellido" required
                                           value="<?php echo htmlspecialchars($_POST['apellido'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-fila">
                            <div class="form-grupo form-grupo-cedula">
                                <label for="cedula">Cédula</label>
                                <?php
                                $sel_cedula_id = 'cedula';
                                $sel_cedula_nombre = 'cedula';
                                $sel_cedula_valor = $_POST['cedula'] ?? '';
                                include __DIR__ . '/../componentes/selector_cedula.php';
                                ?>
                                <button type="button" class="btn" id="btn-buscar-cedula">Buscar</button>
                                <div id="estado-cedula" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="form-fila">
                            <div class="form-grupo">
                                <label for="correo"><?php echo icono('correo', 15); ?> Correo electrónico</label>
                                <div class="campo-icono">
                                    <?php echo icono('correo', 16); ?>
                                    <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" required
                                           value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="form-grupo">
                                <label for="usuario"><?php echo icono('usuario', 15); ?> Nombre de usuario</label>
                                <div class="campo-icono">
                                    <?php echo icono('usuario', 16); ?>
                                    <input type="text" id="usuario" name="usuario" placeholder="Ingresa el nombre de usuario" required
                                           value="<?php echo htmlspecialchars($_POST['usuario'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-fila">
                            <div class="form-grupo">
                                <label for="password">Contraseña</label>
                                <input type="password" id="password" name="password" required minlength="8">
                                <div class="form-ayuda">Mínimo 8 caracteres. Se guarda cifrada, nunca en texto plano.</div>
                            </div>
                            <div class="form-grupo">
                                <label for="id_rol"><?php echo icono('escudo', 15); ?> Rol</label>
                                <div class="campo-icono">
                                    <?php echo icono('escudo', 16); ?>
                                    <select id="id_rol" name="id_rol" required onchange="mostrarCamposMedico()">
                                        <option value="">Selecciona un rol</option>
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?php echo $r['id_rol']; ?>"
                                                data-rol="<?php echo htmlspecialchars($r['nombre_rol']); ?>"
                                                <?php echo (($_GET['rol'] ?? '') === $r['nombre_rol']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucfirst($r['nombre_rol'])); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-ayuda"><?php echo icono('escudo', 13); ?> Mínimo 8 caracteres. Se guarda cifrada, nunca en texto plano.</div>

                        <!-- Solo se muestra cuando el rol elegido es "medico" -->
                        <div id="camposMedico" style="display:none;">
                            <div class="seccion-titulo">Información adicional</div>
                            <div class="seccion-nota">Estos campos solo aparecen cuando el rol seleccionado es Médico.</div>

                            <div class="form-fila">
                                <div class="form-grupo">
                                    <label for="id_especialidad"><?php echo icono('estrella', 15); ?> Especialidad</label>
                                    <div class="campo-icono">
                                        <?php echo icono('estrella', 16); ?>
                                        <select id="id_especialidad" name="id_especialidad">
                                            <option value="">Selecciona especialidad</option>
                                            <?php foreach ($especialidades as $esp): ?>
                                                <option value="<?php echo $esp['id_especialidad']; ?>">
                                                    <?php echo htmlspecialchars($esp['nombre_especialidad']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-grupo">
                                    <label for="numero_colegiado"><?php echo icono('archivo', 15); ?> Número de colegiado</label>
                                    <div class="campo-icono">
                                        <?php echo icono('archivo', 16); ?>
                                        <input type="text" id="numero_colegiado" name="numero_colegiado" placeholder="Ingresa número de colegiado">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="seccion-titulo">Estado de la cuenta</div>
                        <div class="bloque-estado-cuenta">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="icono-estado"><?php echo icono('candado', 17); ?></div>
                                <div>
                                    <strong>Estado de la cuenta</strong>
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
                            <button type="submit" class="btn-primario"><?php echo icono('guardar', 16); ?> Guardar usuario</button>
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
function mostrarCamposMedico() {
    const select = document.getElementById('id_rol');
    const opcionElegida = select.options[select.selectedIndex];
    const rolTexto = opcionElegida ? opcionElegida.getAttribute('data-rol') : '';
    document.getElementById('camposMedico').style.display = (rolTexto === 'medico') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', mostrarCamposMedico);
// Verificación de identidad de la cédula contra el Tribunal Electoral.
// Fail-closed: solo autocompleta si la cédula existe; si no se encuentra
// o el servicio no responde, se muestra el bloqueo (el servidor también
// bloquea la creación al enviar el formulario).
var btnBuscarCedula = document.getElementById('btn-buscar-cedula');
var estadoCedula = document.getElementById('estado-cedula');
var campoCedula = document.getElementById('cedula');

function mostrarEstadoCedula(mensaje, clase) {
    estadoCedula.textContent = mensaje;
    estadoCedula.className = clase;
}

btnBuscarCedula.addEventListener('click', function () {
    var cedula = campoCedula.value.trim();

    // Validación de formato en el cliente antes de llamar al servidor
    if (!/^(?:[A-Z]{1,2}-)?\d{1,4}-\d{1,4}(?:-\d{1,4})?$/.test(cedula)) {
        mostrarEstadoCedula('Formato de cédula no válido (ejemplo: 8-123-456).', 'mensaje-info');
        return;
    }

    var csrf = document.querySelector('input[name="csrf_token"]').value;

    mostrarEstadoCedula('Buscando cédula…', 'mensaje-info');
    btnBuscarCedula.disabled = true;

    var datos = new URLSearchParams();
    datos.append('cedula', cedula);
    datos.append('csrf_token', csrf);

    fetch('ajax/consultar_cedula.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: datos
    })
    .then(function (resp) {
        return resp.json().then(function (datosResp) {
            return { estado: resp.status, datos: datosResp };
        });
    })
    .then(function (resultado) {
        if (resultado.estado === 200) {
            document.getElementById('nombre').value = resultado.datos.nombres;
            document.getElementById('apellido').value = resultado.datos.apellidos;
            mostrarEstadoCedula('Datos encontrados en el Tribunal Electoral', 'mensaje-exito');
        } else if (resultado.estado === 404) {
            document.getElementById('nombre').value = '';
            document.getElementById('apellido').value = '';
            mostrarEstadoCedula('La cédula no está registrada en el Tribunal Electoral; el usuario no podrá ser creado.', 'mensaje-error');
        } else {
            console.error('Error al consultar el Tribunal Electoral:', resultado.estado, resultado.datos);
            mostrarEstadoCedula('No se pudo verificar la cédula; inténtalo más tarde.', 'mensaje-error');
        }
    })
    .catch(function (error) {
        console.error('No se pudo contactar el servicio del Tribunal Electoral:', error);
        mostrarEstadoCedula('No se pudo verificar la cédula; inténtalo más tarde.', 'mensaje-error');
    })
    .finally(function () {
        btnBuscarCedula.disabled = false;
    });
});
</script>

</body>
</html>
