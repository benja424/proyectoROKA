<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo de documentos — capa de datos
class Documento
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    public function crear(array $d): int
    {
        $sql = "INSERT INTO documento (nombre, descripcion, subcategoria, archivo, id_categoria, usuario_ci)
                VALUES (:nombre, :descripcion, :subcategoria, :archivo, :categoria, :usuario)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':nombre'       => $d['nombre'],
            ':descripcion'  => $d['descripcion'],
            ':subcategoria' => $d['subcategoria'],
            ':archivo'      => $d['archivo'],
            ':categoria'    => $d['id_categoria'],
            ':usuario'      => $d['usuario_ci'],
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    // Actualiza los datos; el archivo solo si viene uno nuevo
    public function actualizar(int $id, array $d): void
    {
        $sql = "UPDATE documento
                SET nombre = :nombre, descripcion = :descripcion, subcategoria = :subcategoria,
                    id_categoria = :categoria"
             . (isset($d['archivo']) ? ", archivo = :archivo" : "")
             . " WHERE id_documento = :id";

        $params = [
            ':nombre'       => $d['nombre'],
            ':descripcion'  => $d['descripcion'],
            ':subcategoria' => $d['subcategoria'],
            ':categoria'    => $d['id_categoria'],
            ':id'           => $id,
        ];
        if (isset($d['archivo'])) {
            $params[':archivo'] = $d['archivo'];
        }

        $this->conexion->prepare($sql)->execute($params);
    }

    public function eliminar(int $id): void
    {
        $this->conexion->prepare("DELETE FROM documento WHERE id_documento = :id")->execute([':id' => $id]);
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->conexion->prepare("SELECT * FROM v_documentos WHERE id_documento = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // Filtros opcionales: texto (busca en nombre, subcategoría y descripción) e id_categoria
    public function listar(array $filtros = []): array
    {
        $sql    = "SELECT * FROM v_documentos WHERE 1 = 1";
        $params = [];

        if (!empty($filtros['texto'])) {
            $sql .= " AND (nombre LIKE :t1 OR subcategoria LIKE :t2 OR descripcion LIKE :t3)";
            $like = '%' . $filtros['texto'] . '%';
            $params += [':t1' => $like, ':t2' => $like, ':t3' => $like];
        }
        if (!empty($filtros['id_categoria'])) {
            $sql .= " AND id_categoria = :cat";
            $params[':cat'] = (int) $filtros['id_categoria'];
        }

        $sql .= " ORDER BY nombre";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
