<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Vehiculo.php';
require_once __DIR__ . '/../models/Paciente.php';
require_once __DIR__ . '/../models/Item.php';
require_once __DIR__ . '/../models/Peticion.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/ReglasTraslado.php';
require_once __DIR__ . '/ReglaNegocioException.php';

// Capa de negocio de los traslados.
// Recibe los datos del endpoint, aplica las reglas (ReglasTraslado) y usa los modelos
// (capa de datos) para guardar todo junto en una transacción.
class ServicioTraslado
{
    private PDO $pdo;
    private Vehiculo $vehiculos;
    private Paciente $pacientes;
    private Item $items;
    private Peticion $peticiones;

    public function __construct()
    {
        $this->pdo        = Database::getInstancia()->getConexion();
        $this->vehiculos  = new Vehiculo();
        $this->pacientes  = new Paciente();
        $this->items      = new Item();
        $this->peticiones = new Peticion();
    }

    // ALTA — devuelve el id de la nueva petición
    public function crear(array $datos, array $usuario): int
    {
        $datos = $this->validar($datos, null);

        $this->pdo->beginTransaction();
        try {
            $id = $this->peticiones->crear($this->datosComunes($datos) + ['usuario_ci' => $usuario['ci']]);
            $this->vincularElemento($id, $datos);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->registrar('Crea', $id, $usuario);
        return $id;
    }

    // EDICIÓN — solo mientras el traslado está en estado Solicitado
    public function editar(int $id, array $datos, array $usuario): void
    {
        $actual = $this->peticiones->obtener($id);
        if ($actual === null) {
            throw new ReglaNegocioException('El traslado no existe.', 404);
        }
        if ($actual['estado'] !== 'Solicitado') {
            throw new ReglaNegocioException('Solo se pueden editar traslados en estado Solicitado.', 409);
        }

        $datos = $this->validar($datos, $id);

        $this->pdo->beginTransaction();
        try {
            $this->peticiones->actualizar($id, $this->datosComunes($datos));
            $itemViejo = $this->peticiones->desvincularElemento($id);
            if ($itemViejo !== null) {
                $this->items->eliminar($itemViejo);
            }
            $this->vincularElemento($id, $datos);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->registrar('Modifica', $id, $usuario);
    }

    // CAMBIO DE ESTADO — respeta las transiciones permitidas
    public function cambiarEstado(int $id, string $nuevoEstado, array $usuario): void
    {
        if (!in_array($nuevoEstado, ReglasTraslado::ESTADOS, true)) {
            throw new ReglaNegocioException('El estado indicado no es válido.');
        }

        $actual = $this->peticiones->obtener($id);
        if ($actual === null) {
            throw new ReglaNegocioException('El traslado no existe.', 404);
        }
        if (!ReglasTraslado::transicionValida($actual['estado'], $nuevoEstado)) {
            throw new ReglaNegocioException(
                'No se puede pasar de ' . str_replace('_', ' ', $actual['estado'])
                . ' a ' . str_replace('_', ' ', $nuevoEstado) . '.',
                409
            );
        }

        $this->peticiones->cambiarEstado($id, $nuevoEstado);
        $this->registrar('Estado: ' . $nuevoEstado, $id, $usuario);
    }

    public function listar(array $filtros): array
    {
        return $this->peticiones->listar($filtros);
    }

    public function obtener(int $id): ?array
    {
        return $this->peticiones->obtener($id);
    }

    // Vehículos con su disponibilidad. Si se indica la hora de salida, cada uno trae 'libre'
    // (operativo y sin otro traslado cerca) y 'compatible' (cumple el tipo que exige el traslado).
    public function vehiculosParaTraslado(?string $tipoRequerido, ?string $salida, ?int $excluirPeticion): array
    {
        $salidaSql = $salida !== null ? ReglasTraslado::normalizarFecha($salida) : null;
        $lista     = $this->vehiculos->listar();

        foreach ($lista as &$v) {
            $v['compatible'] = $tipoRequerido === null || $v['tipo'] === $tipoRequerido;
            $v['libre']      = $v['estado_fisico'] === 'Operativo'
                && ($salidaSql === null
                    || $this->vehiculos->estaLibre($v['matricula'], $salidaSql, ReglasTraslado::VENTANA_MINUTOS, $excluirPeticion));
        }
        unset($v);

        return $lista;
    }

    // ---------- Internos ----------

    // Valida los datos y las reglas del vehículo. Devuelve los datos con la fecha ya normalizada.
    private function validar(array $datos, ?int $excluirPeticion): array
    {
        $errores = ReglasTraslado::validar($datos);
        if ($errores) {
            throw new ReglaNegocioException($errores);
        }

        $datos['hora_salida'] = ReglasTraslado::normalizarFecha($datos['hora_salida']);

        $vehiculo = $this->vehiculos->obtener(trim($datos['matricula']));
        if ($vehiculo === null) {
            throw new ReglaNegocioException('El vehículo indicado no existe.');
        }
        if ($vehiculo['estado_fisico'] !== 'Operativo') {
            throw new ReglaNegocioException('El vehículo está en mantenimiento y no se puede asignar.');
        }

        $requerido = ReglasTraslado::tipoVehiculoRequerido($datos);
        if ($requerido !== null && $vehiculo['tipo'] !== $requerido) {
            throw new ReglaNegocioException('Este traslado solo puede hacerse en ambulancia.');
        }

        if (!$this->vehiculos->estaLibre($vehiculo['matricula'], $datos['hora_salida'], ReglasTraslado::VENTANA_MINUTOS, $excluirPeticion)) {
            throw new ReglaNegocioException('El vehículo ya tiene un traslado asignado cerca de ese horario.', 409);
        }

        return $datos;
    }

    private function datosComunes(array $d): array
    {
        $obs = trim((string) ($d['observaciones'] ?? ''));
        return [
            'matricula'     => trim($d['matricula']),
            'chofer'        => trim($d['chofer']),
            'enfermero'     => trim($d['enfermero']),
            'razon'         => trim($d['razon']),
            'destino'       => trim($d['destino']),
            'hora_salida'   => $d['hora_salida'],
            'observaciones' => $obs === '' ? null : $obs,
        ];
    }

    // Una petición traslada un paciente O un item (nunca los dos): crea el vínculo que corresponda
    private function vincularElemento(int $idPeticion, array $d): void
    {
        if ($d['tipo_elemento'] === 'Paciente') {
            $idPaciente = $this->pacientes->obtenerOCrear(trim($d['documento']));
            $this->peticiones->vincularPaciente($idPeticion, $idPaciente, [
                'requiere_oxigeno'     => ReglasTraslado::aBool($d['requiere_oxigeno'] ?? false),
                'requiere_aislamiento' => ReglasTraslado::aBool($d['requiere_aislamiento'] ?? false),
                'requiere_camilla'     => ReglasTraslado::aBool($d['requiere_camilla'] ?? false),
                'autonomo'             => !array_key_exists('autonomo', $d) || ReglasTraslado::aBool($d['autonomo']),
            ]);
            return;
        }

        $caract = trim((string) ($d['item_caracteristicas'] ?? ''));
        $idItem = $this->items->crear(trim($d['item_nombre']), $caract === '' ? null : $caract, $d['tipo_elemento']);
        $this->peticiones->vincularItem($idPeticion, $idItem);
    }

    // Todas las operaciones que modifican traslados pasan por acá y quedan en logs
    private function registrar(string $accion, int $idPeticion, array $usuario): void
    {
        Log::registrar($usuario['ci'], $accion, Log::TRASLADOS, $idPeticion);
    }
}
