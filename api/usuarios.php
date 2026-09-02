<?php
// Endpoint de gestión de usuarios — solo para root
require_once __DIR__ . '/../backend/models/Usuario.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

// Verificamos que haya sesión activa y que sea root
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'root') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

$usuario = new Usuario();
$metodo  = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {

    // GET — obtener lista de usuarios
    case 'GET':
        $usuarios = $usuario->obtenerTodos();
        echo json_encode($usuarios);
        break;

    // POST — crear nuevo usuario
    case 'POST':
        $datos = json_decode(file_get_contents('php://input'), true);

        if (!isset($datos['ci'], $datos['nombre'], $datos['apellido'], $datos['user_name'], $datos['pass'], $datos['rol'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Faltan campos obligatorios']);
            exit;
        }

        try {
            $resultado = $usuario->crear($datos);
            if ($resultado) {
                echo json_encode(['mensaje' => 'Usuario creado correctamente']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo crear el usuario']);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    // DELETE — eliminar usuario por CI
    case 'DELETE':
        $datos = json_decode(file_get_contents('php://input'), true);

        if (!isset($datos['ci'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta la CI del usuario']);
            exit;
        }

        // No permitimos eliminar al root
        if ($datos['ci'] === '00000000') {
            http_response_code(403);
            echo json_encode(['error' => 'No se puede eliminar al usuario root']);
            exit;
        }

        $resultado = $usuario->eliminar($datos['ci']);

        if ($resultado) {
            echo json_encode(['mensaje' => 'Usuario eliminado correctamente']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'No se pudo eliminar el usuario']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}