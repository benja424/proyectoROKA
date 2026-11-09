<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo de categorías de documentos (y de encuestas)
class Categoria
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    public function listar(): array
    {
        return $this->conexion
            ->query("SELECT id_categoria, nombre FROM categoria ORDER BY nombre")
            ->fetchAll();
    }

    public function existe(int $id): bool
    {
        $stmt = $this->conexion->prepare("SELECT 1 FROM categoria WHERE id_categoria = :id");
        $stmt->execute([':id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    // Busca la categoría por nombre (sin distinguir mayúsculas ni tildes, por la collation).
    // Si no existe la crea. Devuelve [id, creada]
    public function obtenerOCrear(string $nombre): array
    {
        $stmt = $this->conexion->prepare("SELECT id_categoria FROM categoria WHERE nombre = :nombre");
        $stmt->execute([':nombre' => $nombre]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return [(int) $id, false];
        }

        $stmt = $this->conexion->prepare("INSERT INTO categoria (nombre) VALUES (:nombre)");
        $stmt->execute([':nombre' => $nombre]);
        return [(int) $this->conexion->lastInsertId(), true];
    }
}
