<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Documento.php';
require_once __DIR__ . '/ServicioCategoria.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/ReglaNegocioException.php';

// Capa de negocio de documentos: validaciones, manejo del PDF y registro en logs
class ServicioDocumento
{
    public const MAX_BYTES = 10 * 1024 * 1024;           // 10 MB
    public const CARPETA   = __DIR__ . '/../../uploads/documentos/';

    private PDO $pdo;
    private Documento $documentos;
    private ServicioCategoria $categorias;

    public function __construct()
    {
        $this->pdo        = Database::getInstancia()->getConexion();
        $this->documentos = new Documento();
        $this->categorias = new ServicioCategoria();
    }

    public function listar(array $filtros): array
    {
        return $this->documentos->listar($filtros);
    }

    public function obtener(int $id): ?array
    {
        return $this->documentos->obtener($id);
    }

    // Ruta del PDF de un documento (para entregarlo por api/pdf.php)
    public function rutaPdf(int $id): string
    {
        $doc  = $id > 0 ? $this->documentos->obtener($id) : null;
        $ruta = $doc ? self::CARPETA . basename($doc['archivo']) : '';
        if ($ruta === '' || !is_file($ruta)) {
            throw new ReglaNegocioException('Documento no encontrado.', 404);
        }
        return $ruta;
    }

    // ALTA: $datos = $_POST, $archivo = $_FILES['archivo'] (obligatorio)
    public function crear(array $datos, ?array $archivo, array $usuario): int
    {
        $d = $this->validar($datos);
        if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new ReglaNegocioException('Adjuntá el archivo PDF.');
        }
        $this->validarPdf($archivo);

        $nombreArchivo = $this->guardarPdf($archivo);

        $this->pdo->beginTransaction();
        try {
            $d['id_categoria'] = $this->categorias->resolver($d['id_categoria'], $d['nueva_categoria'], $usuario);
            $id = $this->documentos->crear($d + ['archivo' => $nombreArchivo, 'usuario_ci' => $usuario['ci']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            @unlink(self::CARPETA . $nombreArchivo); // si falla la base, no queda el PDF huérfano
            throw $e;
        }

        Log::registrar($usuario['ci'], 'Crea', Log::DOCUMENTOS, $id);
        return $id;
    }

    // EDICIÓN: el PDF es opcional; si viene uno nuevo reemplaza al anterior
    public function editar(int $id, array $datos, ?array $archivo, array $usuario): void
    {
        $actual = $this->documentos->obtener($id);
        if ($actual === null) {
            throw new ReglaNegocioException('El documento no existe.', 404);
        }

        $d = $this->validar($datos);
        $nuevo = null;
        if ($archivo !== null && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->validarPdf($archivo);
            $nuevo = $this->guardarPdf($archivo);
            $d['archivo'] = $nuevo;
        }

        $this->pdo->beginTransaction();
        try {
            $d['id_categoria'] = $this->categorias->resolver($d['id_categoria'], $d['nueva_categoria'], $usuario);
            $this->documentos->actualizar($id, $d);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            if ($nuevo) @unlink(self::CARPETA . $nuevo);
            throw $e;
        }

        if ($nuevo) {
            @unlink(self::CARPETA . $actual['archivo']); // el PDF viejo ya no se usa
        }
        Log::registrar($usuario['ci'], 'Modifica', Log::DOCUMENTOS, $id);
    }

    public function eliminar(int $id, array $usuario): void
    {
        $actual = $this->documentos->obtener($id);
        if ($actual === null) {
            throw new ReglaNegocioException('El documento no existe.', 404);
        }
        $this->documentos->eliminar($id);
        @unlink(self::CARPETA . $actual['archivo']);
        Log::registrar($usuario['ci'], 'Elimina', Log::DOCUMENTOS, $id);
    }

    // ---------- Internos ----------

    private function validar(array $datos): array
    {
        $errores = [];
        $txt = fn($k) => trim((string) ($datos[$k] ?? ''));

        $d = [
            'nombre'       => $txt('nombre'),
            'descripcion'  => $txt('descripcion') === '' ? null : $txt('descripcion'),
            'subcategoria' => $txt('subcategoria') === '' ? null : $txt('subcategoria'),
            'id_categoria' => (int) ($datos['id_categoria'] ?? 0),
            'nueva_categoria' => $txt('nueva_categoria'),
        ];

        if ($d['nombre'] === '')                      $errores[] = 'Indicá el nombre del documento.';
        if (mb_strlen($d['nombre']) > 150)            $errores[] = 'El nombre admite hasta 150 caracteres.';
        if (mb_strlen($d['descripcion'] ?? '') > 255) $errores[] = 'La descripción admite hasta 255 caracteres.';
        if (mb_strlen($d['subcategoria'] ?? '') > 60) $errores[] = 'La subcategoría admite hasta 60 caracteres.';

        $errorCategoria = $this->categorias->validar($d['id_categoria'], $d['nueva_categoria']);
        if ($errorCategoria) $errores[] = $errorCategoria;

        if ($errores) {
            throw new ReglaNegocioException($errores);
        }
        return $d;
    }

    private function validarPdf(array $archivo): void
    {
        $error = $archivo['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || ($archivo['size'] ?? 0) > self::MAX_BYTES) {
            throw new ReglaNegocioException('El PDF no puede superar los 10 MB.');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            throw new ReglaNegocioException('No se pudo recibir el archivo. Probá de nuevo.');
        }

        // Se revisa el contenido real del archivo, no la extensión
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        $inicio = file_get_contents($archivo['tmp_name'], false, null, 0, 5);
        if ($tipo !== 'application/pdf' || $inicio !== '%PDF-') {
            throw new ReglaNegocioException('El archivo debe ser un PDF.');
        }
    }

    // Guarda el PDF con un nombre aleatorio (nunca el nombre original del usuario)
    private function guardarPdf(array $archivo): string
    {
        if (!is_dir(self::CARPETA)) {
            mkdir(self::CARPETA, 0755, true);
        }
        $nombre = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($archivo['tmp_name'], self::CARPETA . $nombre)) {
            throw new RuntimeException('No se pudo guardar el PDF en ' . self::CARPETA);
        }
        return $nombre;
    }
}
