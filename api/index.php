<?php
// Router principal — todas las llamadas a /sigsm/api/* pasan por acá (ver .htaccess).
// Centraliza lo que antes se repetía en cada endpoint: el tipo de respuesta (JSON),
// la configuración de la cookie de sesión y el manejo de errores.
require_once __DIR__ . '/../backend/negocio/ReglaNegocioException.php';

header('Content-Type: application/json; charset=utf-8');

// Cookie de sesión: no accesible desde JavaScript (httponly) ni enviada desde otros sitios (samesite)
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);

// Cualquier excepción que no se atrape en el endpoint termina acá:
// - ReglaNegocioException: error esperado (validación o regla) → se le explica al usuario
// - cualquier otra: error inesperado → se guarda en el log de Apache y no se filtran detalles
set_exception_handler(function (Throwable $e) {
    if ($e instanceof ReglaNegocioException) {
        http_response_code($e->getHttp());
        echo json_encode(['error' => $e->getMessage(), 'errores' => $e->getErrores()]);
        return;
    }
    error_log('[sigsm] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
});

// /sigsm/api/traslados?id=5 → "traslados"
$segmentos = explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
$recurso   = $segmentos[array_search('api', $segmentos, true) + 1] ?? '';

$endpoints = [
    'login', 'logout', 'sesion', 'usuarios', 'logs',
    'traslados', 'vehiculos', 'pacientes',
    'documentos', 'categorias', 'pdf', 'encuestas',
];

if (!in_array($recurso, $endpoints, true)) {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint no encontrado']);
    exit;
}

require __DIR__ . "/$recurso.php";
