<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo Paciente — solo guarda la identidad (id y documento).
// Los requisitos del traslado (oxígeno, camilla, etc.) viven en traslado_paciente.
class Paciente
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // Busca un paciente por documento, o null si no está registrado
    public function buscarPorDocumento(string $documento): ?array
    {
        $stmt = $this->conexion->prepare("SELECT id_paciente, documento FROM paciente WHERE documento = :doc");
        $stmt->execute([':doc' => $documento]);
        return $stmt->fetch() ?: null;
    }

    // Devuelve el id del paciente; si no existe todavía lo registra
    public function obtenerOCrear(string $documento): int
    {
        $existente = $this->buscarPorDocumento($documento);
        if ($existente !== null) {
            return (int) $existente['id_paciente'];
        }

        $stmt = $this->conexion->prepare("INSERT INTO paciente (documento) VALUES (:doc)");
        $stmt->execute([':doc' => $documento]);
        return (int) $this->conexion->lastInsertId();
    }
}
