<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo Item — elemento no humano que se traslada (órgano, muestra, insumo, documento, equipamiento)
class Item
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // Registra un item y devuelve su id
    public function crear(string $nombre, ?string $caracteristicas, string $tipoObjeto): int
    {
        $stmt = $this->conexion->prepare(
            "INSERT INTO item (nombre, caracteristicas, tipo_objeto) VALUES (:nombre, :caract, :tipo)"
        );
        $stmt->execute([
            ':nombre' => $nombre,
            ':caract' => $caracteristicas,
            ':tipo'   => $tipoObjeto,
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    public function eliminar(int $idItem): void
    {
        $stmt = $this->conexion->prepare("DELETE FROM item WHERE id_item = :id");
        $stmt->execute([':id' => $idItem]);
    }
}
