<?php
// Endpoint de login — recibe usuario y contraseña, devuelve datos de sesión
require_once __DIR__ . '/../backend/models/Usuario.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

// Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Leemos el body JSON que manda el frontend
$datos = json_decode(file_get_contents('php://input'), true);

// Validamos que vengan los dos campos
if (!isset($datos['user']) || !isset($datos['pass'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Debe ingresar usuario y contraseña']);
    exit;
}

$usuario   = new Usuario();
$resultado = $usuario->login($datos['user'], $datos['pass']);

// Si no encontró el usuario devolvemos 401
if ($resultado === null) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario o contraseña incorrectos']);
    exit;
}

// Guardamos el usuario en la sesión y respondemos
$_SESSION['usuario'] = $resultado;
echo json_encode([
    'mensaje' => 'Login correcto',
    'usuario' => $resultado
]);