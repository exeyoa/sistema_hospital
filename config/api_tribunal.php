<?php
// Configuración del API del Tribunal Electoral (consumo interno).
// La API_KEY nunca debe enviarse al navegador: solo la usa este servidor
// al llamar al API externo desde endpoints PHP internos.
// API_TRIBUNAL_URL y API_TRIBUNAL_KEY se leen desde config/entorno.php.

require_once __DIR__ . '/entorno.php';

// Consulta una cédula en el API externo del Tribunal Electoral (solo servidor).
// Devuelve ['codigo' => int, 'cuerpo' => string]; 'codigo' es 0 si hubo
// fallo de red, timeout o respuesta no interpretable. Quien llama decide
// cómo tratar cada código (200/404/401/502...).
function consultarCedulaTribunal(string $cedula): array
{
    $url = API_TRIBUNAL_URL . '?cedula=' . urlencode($cedula) . '&api_key=' . urlencode(API_TRIBUNAL_KEY);

    $contexto = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => 5,
            'ignore_errors' => true, // permite leer el cuerpo aunque el API responda 4xx/5xx
        ],
    ]);

    $cuerpo = @file_get_contents($url, false, $contexto);

    // Fallo de red, host inalcanzable o timeout
    if ($cuerpo === false) {
        error_log('No se pudo contactar el API del Tribunal Electoral: ' . $url);
        return ['codigo' => 0, 'cuerpo' => ''];
    }

    $codigo = 0;
    if (!empty($http_response_header) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $coincidencias)) {
        $codigo = (int)$coincidencias[1];
    }

    return ['codigo' => $codigo, 'cuerpo' => $cuerpo];
}