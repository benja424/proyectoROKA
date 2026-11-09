<?php
// Endpoint de traslados
//   GET    /api/traslados            -> historial (filtros: documento, matricula, estado)
//   GET    /api/traslados?id=5       -> un traslado
//   POST   /api/traslados            -> crear
//   PUT    /api/traslados?id=5       -> editar (solo estado Solicitado)
//   PATCH  /api/traslados?id=5       -> cambiar estado, body: {"estado": "En_curso"}
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/negocio/ServicioTraslado.php';

$metodo   = Autorizacion::exigirMetodo('GET', 'POST', 'PUT', 'PATCH');
$usuario  = Autorizacion::exigirRol(Autorizacion::ROLES_TRASLADOS);
$id       = (int) ($_GET['id'] ?? 0);
$servicio = new ServicioTraslado();

if ($metodo !== 'GET' && $metodo !== 'POST' && $id <= 0) {
    throw new ReglaNegocioException('Falta el id del traslado.', 400);
}

switch ($metodo) {
    case 'GET':
        if ($id > 0) {
            echo json_encode($servicio->obtener($id)
                ?? throw new ReglaNegocioException('El traslado no existe.', 404));
            break;
        }
        echo json_encode($servicio->listar([
            'documento' => trim($_GET['documento'] ?? ''),
            'matricula' => trim($_GET['matricula'] ?? ''),
            'estado'    => trim($_GET['estado'] ?? ''),
        ]));
        break;

    case 'POST':
        $nuevoId = $servicio->crear(Autorizacion::leerJson(), $usuario);
        http_response_code(201);
        echo json_encode(['mensaje' => 'Traslado registrado correctamente', 'id_peticion' => $nuevoId]);
        break;

    case 'PUT':
        $servicio->editar($id, Autorizacion::leerJson(), $usuario);
        echo json_encode(['mensaje' => 'Traslado actualizado correctamente']);
        break;

    case 'PATCH':
        $servicio->cambiarEstado($id, (string) (Autorizacion::leerJson()['estado'] ?? ''), $usuario);
        echo json_encode(['mensaje' => 'Estado actualizado correctamente']);
        break;
}
