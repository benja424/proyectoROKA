<?php
// Endpoint de encuestas
//   GET    /api/encuestas                    -> encuestas PÚBLICAS (portal de pacientes)
//   GET    /api/encuestas?todas=1            -> todas, con cantidad de respuestas (funcionarios)
//   GET    /api/encuestas?id=5               -> una encuesta pública con sus preguntas
//                                               (los funcionarios también ven las privadas)
//   GET    /api/encuestas?id=5&resultados=1  -> resultados (funcionarios)
//   POST   /api/encuestas                    -> crear (funcionarios)
//   POST   /api/encuestas?id=5&responder=1   -> responder, ANÓNIMO (portal)
//   PUT    /api/encuestas?id=5               -> editar (funcionarios; solo si no tiene respuestas)
//   PATCH  /api/encuestas?id=5               -> cambiar estado, body {"estado":"Publica"}
//   DELETE /api/encuestas?id=5               -> eliminar (solo si no tiene respuestas)
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/negocio/ServicioEncuesta.php';

$metodo   = Autorizacion::exigirMetodo('GET', 'POST', 'PUT', 'PATCH', 'DELETE');
$id       = (int) ($_GET['id'] ?? 0);
$servicio = new ServicioEncuesta();

// ---------- Acceso público ----------
if ($metodo === 'GET' && !isset($_GET['todas']) && !isset($_GET['resultados'])) {
    if ($id > 0) {
        $funcionario = Autorizacion::tieneRol(Autorizacion::ROLES_DOCUMENTOS);
        $e = $servicio->obtenerCompleta($id, !$funcionario)
            ?? throw new ReglaNegocioException('La encuesta no está disponible.', 404);
        unset($e['creada_por'], $e['respuestas']);
        echo json_encode($e);
        exit;
    }
    echo json_encode(array_map(fn($e) => [
        'id_encuesta' => $e['id_encuesta'], 'titulo' => $e['titulo'],
        'descripcion' => $e['descripcion'], 'categoria' => $e['categoria'],
    ], $servicio->listar(true)));
    exit;
}

if ($metodo === 'POST' && isset($_GET['responder'])) {
    $servicio->responder($id, Autorizacion::leerJson());
    http_response_code(201);
    echo json_encode(['mensaje' => '¡Gracias! Tu respuesta fue enviada.']);
    exit;
}

// ---------- Solo funcionarios ----------
$usuario = Autorizacion::exigirRol(Autorizacion::ROLES_DOCUMENTOS);

if (in_array($metodo, ['PUT', 'PATCH', 'DELETE'], true) && $id <= 0) {
    throw new ReglaNegocioException('Falta el id de la encuesta.', 400);
}

switch ($metodo) {
    case 'GET':
        echo json_encode(isset($_GET['resultados']) ? $servicio->resultados($id) : $servicio->listar(false));
        break;

    case 'POST':
        $nuevo = $servicio->crear(Autorizacion::leerJson(), $usuario);
        http_response_code(201);
        echo json_encode(['mensaje' => 'Encuesta creada correctamente', 'id_encuesta' => $nuevo]);
        break;

    case 'PUT':
        $servicio->editar($id, Autorizacion::leerJson(), $usuario);
        echo json_encode(['mensaje' => 'Encuesta actualizada correctamente']);
        break;

    case 'PATCH':
        $servicio->cambiarEstado($id, (string) (Autorizacion::leerJson()['estado'] ?? ''), $usuario);
        echo json_encode(['mensaje' => 'Estado actualizado']);
        break;

    case 'DELETE':
        $servicio->eliminar($id, $usuario);
        echo json_encode(['mensaje' => 'Encuesta eliminada']);
        break;
}
