<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo Peticion — acceso a datos de las peticiones de traslado.
// Una petición guarda los datos comunes; el paciente o el item se vinculan
// por separado (traslado_paciente / traslado_item), como en el MER.
class Peticion
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // Inserta los datos comunes de la petición y devuelve su id
    public function crear(array $p): int
    {
        $sql = "INSERT INTO peticion (usuario_ci, matricula, chofer, enfermero, razon, destino, hora_salida, observaciones)
                VALUES (:usuario_ci, :matricula, :chofer, :enfermero, :razon, :destino, :hora_salida, :observaciones)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':usuario_ci'    => $p['usuario_ci'],
            ':matricula'     => $p['matricula'],
            ':chofer'        => $p['chofer'],
            ':enfermero'     => $p['enfermero'],
            ':razon'        => $p['razon'],
            ':destino'       => $p['destino'],
            ':hora_salida'   => $p['hora_salida'],
            ':observaciones' => $p['observaciones'],
        ]);
        return (int) $this->conexion->lastInsertId();
    }

    // Actualiza los datos comunes (no toca el estado ni el solicitante original)
    public function actualizar(int $id, array $p): void
    {
        $sql = "UPDATE peticion
                SET matricula = :matricula, chofer = :chofer, enfermero = :enfermero,
                    razon = :razon, destino = :destino, hora_salida = :hora_salida,
                    observaciones = :observaciones
                WHERE id_peticion = :id";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':matricula'     => $p['matricula'],
            ':chofer'        => $p['chofer'],
            ':enfermero'     => $p['enfermero'],
            ':razon'        => $p['razon'],
            ':destino'       => $p['destino'],
            ':hora_salida'   => $p['hora_salida'],
            ':observaciones' => $p['observaciones'],
            ':id'            => $id,
        ]);
    }

    // Vincula la petición con un paciente y los requisitos de ESE viaje
    public function vincularPaciente(int $idPeticion, int $idPaciente, array $requisitos): void
    {
        $sql = "INSERT INTO traslado_paciente
                    (id_peticion, id_paciente, requiere_oxigeno, requiere_aislamiento, requiere_camilla, autonomo)
                VALUES (:peticion, :paciente, :oxigeno, :aislamiento, :camilla, :autonomo)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':peticion'    => $idPeticion,
            ':paciente'    => $idPaciente,
            ':oxigeno'     => (int) $requisitos['requiere_oxigeno'],
            ':aislamiento' => (int) $requisitos['requiere_aislamiento'],
            ':camilla'     => (int) $requisitos['requiere_camilla'],
            ':autonomo'    => (int) $requisitos['autonomo'],
        ]);
    }

    // Vincula la petición con un item
    public function vincularItem(int $idPeticion, int $idItem): void
    {
        $stmt = $this->conexion->prepare("INSERT INTO traslado_item (id_peticion, id_item) VALUES (:peticion, :item)");
        $stmt->execute([':peticion' => $idPeticion, ':item' => $idItem]);
    }

    // Quita el paciente o el item vinculado. Devuelve el id del item que tenía (o null)
    // para que quien llama pueda borrarlo y no queden items huérfanos.
    public function desvincularElemento(int $idPeticion): ?int
    {
        $stmt = $this->conexion->prepare("SELECT id_item FROM traslado_item WHERE id_peticion = :id");
        $stmt->execute([':id' => $idPeticion]);
        $idItem = $stmt->fetchColumn();

        $this->conexion->prepare("DELETE FROM traslado_item WHERE id_peticion = :id")->execute([':id' => $idPeticion]);
        $this->conexion->prepare("DELETE FROM traslado_paciente WHERE id_peticion = :id")->execute([':id' => $idPeticion]);

        return $idItem === false ? null : (int) $idItem;
    }

    // Una petición completa (vista de historial), o null si no existe
    public function obtener(int $id): ?array
    {
        $stmt = $this->conexion->prepare("SELECT * FROM v_historial_traslados WHERE id_peticion = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // Historial filtrable por documento del paciente, vehículo y/o estado (máximo 200 filas)
    public function listar(array $filtros = []): array
    {
        $sql    = "SELECT * FROM v_historial_traslados WHERE 1 = 1";
        $params = [];

        if (!empty($filtros['documento'])) {
            $sql .= " AND documento LIKE :documento";
            $params[':documento'] = '%' . $filtros['documento'] . '%';
        }
        if (!empty($filtros['matricula'])) {
            $sql .= " AND matricula = :matricula";
            $params[':matricula'] = $filtros['matricula'];
        }
        if (!empty($filtros['estado'])) {
            $sql .= " AND estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        $sql .= " ORDER BY hora_salida DESC, id_peticion DESC LIMIT 200";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Cambia el estado del traslado
    public function cambiarEstado(int $id, string $estado): void
    {
        $stmt = $this->conexion->prepare("UPDATE peticion SET estado = :estado WHERE id_peticion = :id");
        $stmt->execute([':estado' => $estado, ':id' => $id]);
    }
}
