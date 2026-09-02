<?php
// Endpoint de verificación de sesión
// El frontend lo consulta al cargar páginas protegidas
header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['autenticado' => false]);
    exit;
}

echo json_encode([
    'autenticado' => true,
    'usuario'     => $_SESSION['usuario']
]);