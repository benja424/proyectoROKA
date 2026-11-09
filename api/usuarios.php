<?php
// Endpoint de gestión de usuarios — solo root
//   GET   /api/usuarios   -> lista (sin root)
//   POST  /api/usuarios   -> crear
//   PATCH /api/usuarios   -> activar o inhabilitar, body {"ci": "...", "estado": "Activo|Inactivo"}
// Los usuarios no se borran: se inhabilitan, para conservar su historial.
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/negocio/ServicioUsuario.php';

$metodo   = Autorizacion::exigirMetodo('GET', 'POST', 'PATCH');
$admin    = Autorizacion::exigirRol(Autorizacion::ROLES_ROOT);
$servicio = new ServicioUsuario();

if ($metodo === 'GET') {
    echo json_encode($servicio->listar());

} elseif ($metodo === 'POST') {
    $servicio->crear(Autorizacion::leerJson(), $admin);
    http_response_code(201);
    echo json_encode(['mensaje' => 'Usuario creado correctamente']);

} else {
    $datos  = Autorizacion::leerJson();
    $estado = (string) ($datos['estado'] ?? '');
    $servicio->cambiarEstado(trim((string) ($datos['ci'] ?? '')), $estado, $admin);
    echo json_encode(['mensaje' => $estado === 'Activo' ? 'Usuario activado' : 'Usuario inhabilitado']);
}
