<?php
// Endpoint intermedio (AJAX) para consultar una cédula en el API del
// Tribunal Electoral. Recibe el csrf_token y la cédula por POST, valida
// la sesión y llama al API externo desde el servidor (nunca desde el
// navegador, para no exponer la API_KEY).

require_once __DIR__ . '/../../config/sesion.php';

// Todas las respuestas son JSON.
header('Content-Type: application/json; charset=utf-8');

// Envía una respuesta JSON con su código HTTP y termina la ejecución.
function responderJson(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}

// 0) Solo se acepta POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responderJson(405, ['error' => 'Método no permitido']);
}

iniciarSesionSegura();

// 1) Sesión activa y rol con permiso (recepcionista o administrador)
$rolSesion = $_SESSION['rol'] ?? '';
if (!isset($_SESSION['id_usuario']) || !in_array($rolSesion, ['recepcionista', 'admin'], true)) {
    responderJson(401, ['error' => 'No autorizado']);
}

// 2) Validación CSRF (mismo patrón que los demás formularios del panel)
if (!validarTokenCSRF($_POST['csrf_token'] ?? null)) {
    responderJson(403, ['error' => 'Solicitud no válida']);
}

// 3) Cédula presente y con formato válido
$cedula = trim($_POST['cedula'] ?? '');
if (!preg_match('/^(?:[A-Z]{1,2}-)?\d{1,4}-\d{1,4}(?:-\d{1,4})?$/', $cedula)) {
    responderJson(400, ['error' => 'Debe proporcionar una cédula válida']);
}

// 4) Llamada HTTP interna al API del Tribunal (helper compartido)
require_once __DIR__ . '/../../config/api_tribunal.php';

$respuestaTribunal = consultarCedulaTribunal($cedula);
$codigoApi = $respuestaTribunal['codigo'];

// Fallo de red, host inalcanzable o timeout
if ($codigoApi === 0) {
    responderJson(502, ['error' => 'No se pudo contactar el servicio de verificación']);
}
$cuerpo = $respuestaTribunal['cuerpo'];

// 6) Manejo de los casos del API externo
if ($codigoApi === 200) {
    $datos = json_decode($cuerpo, true);
    if (!is_array($datos) || !isset($datos['nombres'], $datos['apellidos'], $datos['fecha_nacimiento'], $datos['sexo'])) {
        error_log('Respuesta inesperada del API del Tribunal Electoral: ' . $cuerpo);
        responderJson(502, ['error' => 'No se pudo contactar el servicio de verificación']);
    }
    // Solo los campos necesarios para el panel de recepción
    responderJson(200, [
        'nombres'          => $datos['nombres'],
        'apellidos'        => $datos['apellidos'],
        'fecha_nacimiento' => $datos['fecha_nacimiento'],
        'sexo'             => $datos['sexo'],
    ]);
}

if ($codigoApi === 404) {
    responderJson(404, ['error' => 'Cédula no encontrada']);
}

if ($codigoApi === 401) {
    // El API rechazó nuestra API_KEY: error de configuración interna.
    error_log('El API del Tribunal Electoral rechazó la API_KEY interna (HTTP 401). Revisar config/api_tribunal.php.');
    responderJson(500, ['error' => 'Error interno del servicio']);
}

// Cualquier otro código (400/500/...) se trata como fallo de contacto
error_log('Respuesta inesperada del API del Tribunal Electoral (HTTP ' . $codigoApi . '): ' . $cuerpo);
responderJson(502, ['error' => 'No se pudo contactar el servicio de verificación']);