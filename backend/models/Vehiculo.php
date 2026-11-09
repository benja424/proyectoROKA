<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo Vehiculo — acceso a datos de ambulancias y autos del hospital
class Vehiculo
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // Todos los vehículos con su disponibilidad calculada (vista v_vehiculo_estado)
    public function listar(): array
    {
        $stmt = $this->conexion->query("SELECT * FROM v_vehiculo_estado ORDER BY tipo, matricula");
        return $stmt->fetchAll();
    }

    // Un vehículo por matrícula, o null si no existe
    public function obtener(string $matricula): ?array
    {
        $stmt = $this->conexion->prepare("SELECT * FROM v_vehiculo_estado WHERE matricula = :matricula");
        $stmt->execute([':matricula' => $matricula]);
        return $stmt->fetch() ?: null;
    }

    // ¿El vehículo no tiene otro traslado activo con salida dentro de la ventana de minutos?
    // $excluirPeticion permite ignorar el propio traslado cuando se está editando.
    public function estaLibre(string $matricula, string $salida, int $ventanaMinutos, ?int $excluirPeticion = null): bool
    {
        $sql = "SELECT COUNT(*) FROM peticion
                WHERE matricula = :matricula
                  AND estado IN ('Solicitado','En_curso','En_destino')
                  AND ABS(TIMESTAMPDIFF(MINUTE, hora_salida, :salida)) < :ventana";
        $params = [
            ':matricula' => $matricula,
            ':salida'    => $salida,
            ':ventana'   => $ventanaMinutos,
        ];

        if ($excluirPeticion !== null) {
            $sql .= " AND id_peticion <> :excluir";
            $params[':excluir'] = $excluirPeticion;
        }

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() === 0;
    }
}
