<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo de logs: registro de quién hizo qué y cuándo.
// Es el ÚNICO punto por donde se escribe en la tabla logs.
class Log
{
    // Nombres de módulo usados en todo el sistema (así no hay variantes escritas distinto)
    public const SESION     = 'Sesion';
    public const USUARIOS   = 'Usuarios';
    public const TRASLADOS  = 'Traslados';
    public const DOCUMENTOS = 'Documentos';
    public const CATEGORIAS = 'Categorias';
    public const ENCUESTAS  = 'Encuestas';

    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // Registra una acción. Si falla el log, la operación del usuario NO se cancela:
    // el error queda en el log de PHP/Apache para revisarlo.
    public static function registrar(string $usuarioCi, string $accion, string $modulo, string|int|null $idRegistro = null): void
    {
        try {
            $stmt = Database::getInstancia()->getConexion()->prepare(
                "INSERT INTO logs (usuario_ci, accion, modulo, id_registro)
                 VALUES (:ci, :accion, :modulo, :registro)"
            );
            $stmt->execute([
                ':ci'       => $usuarioCi,
                ':accion'   => mb_substr($accion, 0, 40),
                ':modulo'   => $modulo,
                ':registro' => $idRegistro === null ? null : (string) $idRegistro,
            ]);
        } catch (Throwable $e) {
            error_log('[logs] No se pudo registrar: ' . $e->getMessage());
        }
    }

    // Listado para la pantalla de logs (solo root). Filtros opcionales: usuario_ci, modulo, accion, desde, hasta.
    public function listar(array $filtros = [], int $limite = 200): array
    {
        $sql    = "SELECT * FROM v_logs WHERE 1 = 1";
        $params = [];

        if (!empty($filtros['usuario_ci'])) { $sql .= " AND usuario_ci = :ci";  $params[':ci'] = $filtros['usuario_ci']; }
        if (!empty($filtros['modulo']))     { $sql .= " AND modulo = :modulo";  $params[':modulo'] = $filtros['modulo']; }
        if (!empty($filtros['accion']))     { $sql .= " AND accion = :accion";  $params[':accion'] = $filtros['accion']; }
        if (!empty($filtros['desde']))      { $sql .= " AND fecha >= :desde";   $params[':desde'] = $filtros['desde'] . ' 00:00:00'; }
        if (!empty($filtros['hasta']))      { $sql .= " AND fecha <= :hasta";   $params[':hasta'] = $filtros['hasta'] . ' 23:59:59'; }

        $sql .= " ORDER BY fecha DESC, id_log DESC LIMIT " . max(1, min($limite, 1000));

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Valores que existen en los logs, para llenar los filtros de la pantalla
    public function opcionesFiltro(): array
    {
        $c = $this->conexion;
        return [
            'modulos'  => $c->query("SELECT DISTINCT modulo FROM logs ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN),
            'acciones' => $c->query("SELECT DISTINCT modulo, accion FROM logs ORDER BY modulo, accion")->fetchAll(),
            'usuarios' => $c->query("SELECT DISTINCT usuario_ci, usuario FROM v_logs ORDER BY usuario")->fetchAll(),
        ];
    }
}
