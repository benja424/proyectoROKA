<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo de encuestas y sus preguntas — capa de datos
class Encuesta
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // Filtro opcional: solo públicas
    public function listar(bool $soloPublicas): array
    {
        $sql = "SELECT * FROM v_encuestas" . ($soloPublicas ? " WHERE estado = 'Publica'" : "")
             . " ORDER BY categoria, titulo";
        return $this->conexion->query($sql)->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->conexion->prepare("SELECT * FROM v_encuestas WHERE id_encuesta = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function preguntas(int $id): array
    {
        $stmt = $this->conexion->prepare(
            "SELECT id_pregunta, enunciado, tipo, orden FROM pregunta WHERE id_encuesta = :id ORDER BY orden"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetchAll();
    }

    public function crear(array $e): int
    {
        $stmt = $this->conexion->prepare(
            "INSERT INTO encuesta (titulo, descripcion, estado, id_categoria, usuario_ci)
             VALUES (:titulo, :descripcion, :estado, :categoria, :usuario)"
        );
        $stmt->execute([
            ':titulo'      => $e['titulo'],
            ':descripcion' => $e['descripcion'],
            ':estado'      => $e['estado'],
            ':categoria'   => $e['id_categoria'],
            ':usuario'     => $e['usuario_ci'],
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function actualizar(int $id, array $e): void
    {
        $stmt = $this->conexion->prepare(
            "UPDATE encuesta SET titulo = :titulo, descripcion = :descripcion, estado = :estado,
                                 id_categoria = :categoria
             WHERE id_encuesta = :id"
        );
        $stmt->execute([
            ':titulo'      => $e['titulo'],
            ':descripcion' => $e['descripcion'],
            ':estado'      => $e['estado'],
            ':categoria'   => $e['id_categoria'],
            ':id'          => $id,
        ]);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $this->conexion->prepare("UPDATE encuesta SET estado = :estado WHERE id_encuesta = :id")
            ->execute([':estado' => $estado, ':id' => $id]);
    }

    // Reemplaza todas las preguntas (solo se usa en encuestas sin respuestas)
    public function guardarPreguntas(int $id, array $preguntas): void
    {
        $this->conexion->prepare("DELETE FROM pregunta WHERE id_encuesta = :id")->execute([':id' => $id]);

        $stmt = $this->conexion->prepare(
            "INSERT INTO pregunta (id_encuesta, enunciado, tipo, orden) VALUES (:id, :enunciado, :tipo, :orden)"
        );
        foreach ($preguntas as $i => $p) {
            $stmt->execute([':id' => $id, ':enunciado' => $p['enunciado'], ':tipo' => $p['tipo'], ':orden' => $i + 1]);
        }
    }

    // Las preguntas se borran solas (ON DELETE CASCADE)
    public function eliminar(int $id): void
    {
        $this->conexion->prepare("DELETE FROM encuesta WHERE id_encuesta = :id")->execute([':id' => $id]);
    }
}
