<?php
// Endpoint de documentos
//   GET    /api/documentos                     -> listado PÚBLICO (filtros: texto, id_categoria)
//   GET    /api/documentos?id=5                -> un documento (público)
//   POST   /api/documentos                     -> crear  (multipart: datos + archivo)
//   POST   /api/documentos?id=5                -> editar (multipart; el archivo es opcional)
//   DELETE /api/documentos?id=5                -> eliminar
// Se usa POST para editar porque PHP solo procesa archivos subidos en peticiones POST.
require_once __DIR__ . '/../backend/negocio/Autorizacion.php';
require_once __DIR__ . '/../backend/negocio/ServicioDocumento.php';

$metodo   = Autorizacion::exigirMetodo('GET', 'POST', 'DELETE');
$id       = (int) ($_GET['id'] ?? 0);
$servicio = new ServicioDocumento();

// ---------- Lectura pública (portal de pacientes) ----------
if ($metodo === 'GET') {
    // Quién subió el documento solo lo ven los funcionarios
    $publico = !Autorizacion::tieneRol(Autorizacion::ROLES_DOCUMENTOS);
    $limpiar = function (array $d) use ($publico) {
        if ($publico) unset($d['usuario_ci'], $d['subido_por']);
        return $d;
    };

    if ($id > 0) {
        $doc = $servicio->obtener($id) ?? throw new ReglaNegocioException('El documento no existe.', 404);
        echo json_encode($limpiar($doc));
        exit;
    }
    echo json_encode(array_map($limpiar, $servicio->listar([
        'texto'        => trim($_GET['texto'] ?? ''),
        'id_categoria' => (int) ($_GET['id_categoria'] ?? 0),
    ])));
    exit;
}

// ---------- Escritura: solo funcionarios con permiso ----------
$usuario = Autorizacion::exigirRol(Autorizacion::ROLES_DOCUMENTOS);

if ($metodo === 'DELETE') {
    if ($id <= 0) throw new ReglaNegocioException('Falta el id del documento.', 400);
    $servicio->eliminar($id, $usuario);
    echo json_encode(['mensaje' => 'Documento eliminado correctamente']);
    exit;
}

// POST: si el archivo supera post_max_size de PHP, $_POST y $_FILES llegan vacíos
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    throw new ReglaNegocioException('El archivo es demasiado grande.', 413);
}
$archivo = $_FILES['archivo'] ?? null;

if ($id > 0) {
    $servicio->editar($id, $_POST, $archivo, $usuario);
    echo json_encode(['mensaje' => 'Documento actualizado correctamente']);
} else {
    $nuevo = $servicio->crear($_POST, $archivo, $usuario);
    http_response_code(201);
    echo json_encode(['mensaje' => 'Documento agregado correctamente', 'id_documento' => $nuevo]);
}
