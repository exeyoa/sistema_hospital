<?php
session_start();

// ---------------------------------------------------------------
// 1) PROTECCIÓN DE SESIÓN (RF-11)
// Si no hay sesión activa o el rol no es 'admin', regresa al login.
// ---------------------------------------------------------------
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../config/conexion.php'; // expone $conexion (PDO)

// ---------------------------------------------------------------
// 2) DATOS PARA LAS TARJETAS DE ESTADÍSTICAS (RF-09)
// ---------------------------------------------------------------

// Consultas registradas hoy
$stmt = $conexion->prepare("SELECT COUNT(*) FROM consultas WHERE fecha_consulta = CURDATE()");
$stmt->execute();
$consultasHoy = (int) $stmt->fetchColumn();

// Total de pacientes registrados
$stmt = $conexion->prepare("SELECT COUNT(*) FROM pacientes");
$stmt->execute();
$totalPacientes = (int) $stmt->fetchColumn();

// Recetas emitidas hoy
$stmt = $conexion->prepare("SELECT COUNT(*) FROM recetas WHERE fecha_emision = CURDATE()");
$stmt->execute();
$recetasHoy = (int) $stmt->fetchColumn();

// ---------------------------------------------------------------
// 3) LISTA DE USUARIOS CON PAGINACIÓN (RF-08)
// ---------------------------------------------------------------
$porPagina = 5;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$inicio = ($paginaActual - 1) * $porPagina;

// Total de usuarios (para calcular cuántas páginas hay)
$stmt = $conexion->prepare("SELECT COUNT(*) FROM usuarios");
$stmt->execute();
$totalUsuarios = (int) $stmt->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalUsuarios / $porPagina));

// Usuarios de la página actual, unidos con roles
$sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.usuario,
               u.activo, r.nombre_rol
        FROM usuarios u
        INNER JOIN roles r ON u.id_rol = r.id_rol
        ORDER BY u.nombre, u.apellido
        LIMIT :limite OFFSET :inicio";
$stmt = $conexion->prepare($sql);
$stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':inicio', $inicio, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------
// Función pequeña para elegir la clase CSS del badge según el rol
// ---------------------------------------------------------------
function claseRol($nombreRol) {
    $mapa = [
        'admin' => 'rol-administrador',
        'medico' => 'rol-medico',
        'recepcionista' => 'rol-recepcionista',
    ];
    return $mapa[$nombreRol] ?? 'rol-recepcionista';
}

function nombreRolMostrar($nombreRol) {
    $mapa = [
        'admin' => 'Administrador',
        'medico' => 'Médico',
        'recepcionista' => 'Recepcionista',
    ];
    return $mapa[$nombreRol] ?? ucfirst($nombreRol);
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
    <title>Panel de Administrador</title>
    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body class="admin-body">

<div class="admin-layout">

    <!-- ================= SIDEBAR ================= -->
    <aside class="admin-sidebar">
        <div class="admin-logo">
            <span class="icono-logo">🩺</span>
            Hospital San Rafael
        </div>

        <nav class="admin-nav">
            <a href="admin.php" class="activo">🏠 Panel de Control <span class="punto-activo"></span></a>

            <div class="admin-nav-seccion">Gestión</div>
            <a href="admin.php">👤 Usuarios</a>
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

        <!-- Barra superior -->
        <div class="admin-topbar">
            <h1>☰ Panel de Administrador</h1>
            <div class="admin-topbar-derecha">
                <div class="admin-campana">🔔<span class="badge-num">3</span></div>
                <div class="admin-usuario-topbar">
                    <div class="avatar-mini"><?php echo htmlspecialchars(iniciales($_SESSION['nombre'], '')); ?></div>
                    <?php echo htmlspecialchars($_SESSION['nombre']); ?> ▾
                </div>
            </div>
        </div>

        <div class="admin-contenido">

            <!-- Tarjetas de estadísticas -->
            <div class="admin-stats-row">
                <div class="tarjeta-stat">
                    <div class="tarjeta-stat-encabezado">
                        <div class="tarjeta-stat-icono icono-azul">📅</div>
                        <div class="tarjeta-stat-titulo">Consultas hoy</div>
                    </div>
                    <div class="tarjeta-stat-numero"><?php echo $consultasHoy; ?></div>
                    <div class="tarjeta-stat-pie">
                        <span>Registros de hoy</span>
                    </div>
                </div>

                <div class="tarjeta-stat">
                    <div class="tarjeta-stat-encabezado">
                        <div class="tarjeta-stat-icono icono-verde">👥</div>
                        <div class="tarjeta-stat-titulo">Pacientes activos</div>
                    </div>
                    <div class="tarjeta-stat-numero"><?php echo $totalPacientes; ?></div>
                    <div class="tarjeta-stat-pie">
                        <span>Total registrados</span>
                    </div>
                </div>

                <div class="tarjeta-stat">
                    <div class="tarjeta-stat-encabezado">
                        <div class="tarjeta-stat-icono icono-morado">📄</div>
                        <div class="tarjeta-stat-titulo">Recetas hoy</div>
                    </div>
                    <div class="tarjeta-stat-numero"><?php echo $recetasHoy; ?></div>
                    <div class="tarjeta-stat-pie">
                        <span>Emitidas hoy</span>
                    </div>
                </div>
            </div>

            <!-- Tarjeta de gestión de usuarios -->
            <div class="tarjeta-usuarios">
                <div class="tarjeta-usuarios-header">
                    <div>
                        <h2>Gestión de Usuarios</h2>
                        <p>Administra los usuarios del sistema</p>
                    </div>
                    <div class="tarjeta-usuarios-acciones">
                        <input type="text" class="input-buscar" placeholder="Buscar usuario...">
                        <button class="btn-secundario">Filtrar</button>
                        <a href="crear_usuario.php" class="btn-primario">+ Nuevo Usuario</a>
                    </div>
                </div>

                <table class="tabla-usuarios">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#6b7280; padding:20px;">
                                No hay usuarios registrados todavía.
                            </td>
                        </tr>
                        <?php endif; ?>

                        <?php foreach ($usuarios as $u):
                            $rolClase = claseRol($u['nombre_rol']);
                            $rolTexto = nombreRolMostrar($u['nombre_rol']);
                            $iniciales = iniciales($u['nombre'], $u['apellido']);
                            $colorAvatar = ['#2563eb', '#16a34a', '#7c3aed', '#d97706', '#0891b2'][$u['id_usuario'] % 5];
                        ?>
                        <tr>
                            <td><?php echo $u['id_usuario']; ?></td>
                            <td>
                                <div class="celda-nombre">
                                    <div class="avatar-usuario" style="background: <?php echo $colorAvatar; ?>;">
                                        <?php echo htmlspecialchars($iniciales); ?>
                                    </div>
                                    <div class="info-nombre">
                                        <strong><?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?></strong>
                                        <span><?php echo htmlspecialchars($rolTexto); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($u['correo']); ?></td>
                            <td><?php echo htmlspecialchars($u['usuario']); ?></td>
                            <td><span class="badge-rol <?php echo $rolClase; ?>"><?php echo htmlspecialchars($rolTexto); ?></span></td>
                            <td>
                                <?php if ($u['activo']): ?>
                                    <span class="estado-punto estado-activo">Activo</span>
                                <?php else: ?>
                                    <span class="estado-punto estado-inactivo">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td class="acciones-fila">
                                <a href="editar_usuario.php?id=<?php echo $u['id_usuario']; ?>" title="Editar">✏️</a>
                                <a href="cambiar_estado_usuario.php?id=<?php echo $u['id_usuario']; ?>" title="Más opciones">⋯</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Paginación -->
                <div class="paginacion">
                    <span>
                        Mostrando <?php echo min($inicio + 1, $totalUsuarios); ?>
                        a <?php echo min($inicio + $porPagina, $totalUsuarios); ?>
                        de <?php echo $totalUsuarios; ?> usuarios
                    </span>
                    <div class="paginacion-botones">
                        <?php if ($paginaActual > 1): ?>
                            <a href="?pagina=<?php echo $paginaActual - 1; ?>">‹</a>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                            <?php if ($p == $paginaActual): ?>
                                <span class="pagina-actual"><?php echo $p; ?></span>
                            <?php else: ?>
                                <a href="?pagina=<?php echo $p; ?>"><?php echo $p; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($paginaActual < $totalPaginas): ?>
                            <a href="?pagina=<?php echo $paginaActual + 1; ?>">›</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Columna lateral derecha -->
            <div class="panel-lateral">
                <div class="tarjeta-lateral">
                    <h3>Acciones Rápidas</h3>
                    <a href="crear_usuario.php" class="accion-rapida">➕ Nuevo Usuario</a>
                    <a href="crear_usuario.php?rol=medico" class="accion-rapida">🧑‍⚕️ Registrar Médico</a>
                    <a href="admin_reportes.php" class="accion-rapida">📊 Ver Reportes</a>
                    <a href="admin_configuracion.php" class="accion-rapida">⚙️ Configuración</a>
                </div>

                <div class="tarjeta-lateral">
                    <h3>Actividad Reciente</h3>
                    <!--
                        NOTA para ti: esta sección todavía es de ejemplo (fija).
                        Para que sea real necesitarías una tabla tipo "actividad"
                        o "bitacora" que registre eventos. Si quieres, en el
                        siguiente paso te ayudo a crear esa tabla y a conectarla.
                    -->
                    <div class="actividad-item">
                        <span class="punto-actividad punto-azul"></span>
                        <div>Nuevo usuario registrado<span class="hora">Hace 15 min</span></div>
                    </div>
                    <div class="actividad-item">
                        <span class="punto-actividad punto-verde"></span>
                        <div>Consulta registrada<span class="hora">Hace 32 min</span></div>
                    </div>
                    <div class="actividad-item">
                        <span class="punto-actividad punto-azul"></span>
                        <div>Receta emitida<span class="hora">Hace 1 hora</span></div>
                    </div>
                    <div class="actividad-item">
                        <span class="punto-actividad punto-rojo"></span>
                        <div>Usuario desactivado<span class="hora">Hace 2 horas</span></div>
                    </div>
                    <a href="#" class="ver-todas">Ver todas las actividades</a>
                </div>
            </div>

        </div>

        <div class="admin-footer">
            © <?php echo date('Y'); ?> Hospital San Rafael. Todos los derechos reservados.
        </div>
    </div>
</div>

</body>
</html>
