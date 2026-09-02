<?php
// Router principal — todas las llamadas a /sigsm/api/* pasan por acá
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Obtenemos la URL y la dividimos en segmentos
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = explode('/', trim($uri, '/'));

// Removemos "sigsm" y "api" del inicio para quedarnos con el recurso
if ($segments[0] === 'sigsm') array_shift($segments);
if ($segments[0] === 'api')   array_shift($segments);

$resource = $segments[0] ?? '';

// Redirigimos al archivo correspondiente según el recurso
switch ($resource) {
    case 'login':
        require_once __DIR__ . '/login.php';
        exit;

    case 'logout':
        require_once __DIR__ . '/logout.php';
        exit;

    case 'sesion':
        require_once __DIR__ . '/sesion.php';
        exit;

    case 'usuarios':
        require_once __DIR__ . '/usuarios.php';
        exit;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint no encontrado']);
}