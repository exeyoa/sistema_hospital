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
    $cedula    = trim($_POST['cedula'] ?? '');
    $password  = $_POST['password'] ?? '';
    $id_rol    = $_POST['id_rol'] ?? '';
    $id_especialidad = $_POST['id_especialidad'] ?? '';
    $numero_colegiado = trim($_POST['numero_colegiado'] ?? '');

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
                             VALUES (:nombre, :apellido, :correo, :usuario, :cedula, :password_hash, :id_rol, 1, NOW())"
                        );
                        $stmt->execute([
                            ':nombre' => $nombre,
                            ':apellido' => $apellido,
                            ':correo' => $correo,
                            ':usuario' => $usuario,
                            ':cedula' => $cedula,
                            ':password_hash' => $passwordHash,
                            ':id_rol' => $id_rol,
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
