<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Encuesta.php';
require_once __DIR__ . '/../models/Respuesta.php';
require_once __DIR__ . '/ServicioCategoria.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/ReglaNegocioException.php';

// Capa de negocio de encuestas: validaciones, reglas de edición y respuestas anónimas
class ServicioEncuesta
{
    public const ESTADOS   = ['Publica', 'Privada'];
    public const TIPOS     = ['Escala', 'Texto'];
    public const MAX_PREGUNTAS = 30;

    private PDO $pdo;
    private Encuesta $encuestas;
    private Respuesta $respuestas;
    private ServicioCategoria $categorias;

    public function __construct()
    {
        $this->pdo        = Database::getInstancia()->getConexion();
        $this->encuestas  = new Encuesta();
        $this->respuestas = new Respuesta();
        $this->categorias = new ServicioCategoria();
    }

    public function listar(bool $soloPublicas): array
    {
        return $this->encuestas->listar($soloPublicas);
    }

    // Encuesta con sus preguntas. Si $soloPublica, una privada se trata como inexistente.
    public function obtenerCompleta(int $id, bool $soloPublica): ?array
    {
        $e = $this->encuestas->obtener($id);
        if ($e === null || ($soloPublica && $e['estado'] !== 'Publica')) {
            return null;
        }
        $e['lista_preguntas'] = $this->encuestas->preguntas($id);
        return $e;
    }

    public function crear(array $datos, array $usuario): int
    {
        [$e, $preguntas] = $this->validar($datos);

        $this->pdo->beginTransaction();
        try {
            $e['id_categoria'] = $this->categorias->resolver($e['id_categoria'], $e['nueva_categoria'], $usuario);
            $id = $this->encuestas->crear($e + ['usuario_ci' => $usuario['ci']]);
            $this->encuestas->guardarPreguntas($id, $preguntas);
            $this->pdo->commit();
        } catch (Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }

        Log::registrar($usuario['ci'], 'Crea', Log::ENCUESTAS, $id);
        return $id;
    }

    public function editar(int $id, array $datos, array $usuario): void
    {
        $actual = $this->existente($id);
        if ((int) $actual['respuestas'] > 0) {
            throw new ReglaNegocioException('La encuesta ya tiene respuestas: solo se puede cambiar su estado.', 409);
        }
        [$e, $preguntas] = $this->validar($datos);

        $this->pdo->beginTransaction();
        try {
            $e['id_categoria'] = $this->categorias->resolver($e['id_categoria'], $e['nueva_categoria'], $usuario);
            $this->encuestas->actualizar($id, $e);
            $this->encuestas->guardarPreguntas($id, $preguntas);
            $this->pdo->commit();
        } catch (Throwable $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }

        Log::registrar($usuario['ci'], 'Modifica', Log::ENCUESTAS, $id);
    }

    public function cambiarEstado(int $id, string $estado, array $usuario): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new ReglaNegocioException('El estado indicado no es válido.');
        }
        $this->existente($id);
        $this->encuestas->cambiarEstado($id, $estado);
        Log::registrar($usuario['ci'], $estado === 'Publica' ? 'Publica' : 'Oculta', Log::ENCUESTAS, $id);
    }

    public function eliminar(int $id, array $usuario): void
    {
        $actual = $this->existente($id);
        if ((int) $actual['respuestas'] > 0) {
            throw new ReglaNegocioException('La encuesta ya tiene respuestas y no se puede eliminar. Podés ocultarla.', 409);
        }
        $this->encuestas->eliminar($id);
        Log::registrar($usuario['ci'], 'Elimina', Log::ENCUESTAS, $id);
    }

    public function resultados(int $id): array
    {
        $e = $this->obtenerCompleta($id, false);
        if ($e === null) {
            throw new ReglaNegocioException('La encuesta no existe.', 404);
        }
        return [
            'encuesta' => $e,
            'escala'   => $this->respuestas->resultadosEscala($id),
            'textos'   => $this->respuestas->respuestasTexto($id),
        ];
    }

    // RESPUESTA ANÓNIMA desde el portal. $datos: {"respuestas": {"id_pregunta": valor, ...}}
    public function responder(int $id, array $datos): void
    {
        $e = $this->obtenerCompleta($id, true);
        if ($e === null) {
            throw new ReglaNegocioException('La encuesta no está disponible.', 404);
        }

        $recibidas = is_array($datos['respuestas'] ?? null) ? $datos['respuestas'] : [];
        $guardar = [];
        $faltan  = 0;

        // Solo se aceptan respuestas a preguntas de ESTA encuesta (las demás se ignoran)
        foreach ($e['lista_preguntas'] as $p) {
            $valor = $recibidas[$p['id_pregunta']] ?? null;

            if ($p['tipo'] === 'Escala') {
                $n = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
                if ($n === false) { $faltan++; continue; }
                $guardar[$p['id_pregunta']] = ['escala' => $n, 'texto' => null];
            } else {
                $texto = trim((string) ($valor ?? ''));
                if ($texto === '') continue;                       // texto opcional
                if (mb_strlen($texto) > 500) {
                    throw new ReglaNegocioException('Las respuestas de texto admiten hasta 500 caracteres.');
                }
                $guardar[$p['id_pregunta']] = ['escala' => null, 'texto' => $texto];
            }
        }

        if ($faltan > 0) {
            throw new ReglaNegocioException('Respondé todas las preguntas de puntaje (1 a 5).');
        }
        if (!$guardar) {
            throw new ReglaNegocioException('La encuesta está vacía.');
        }

        $this->respuestas->guardarEnvio($id, $guardar);
        // No se registra en logs: las respuestas son anónimas
    }

    // ---------- Internos ----------

    private function existente(int $id): array
    {
        $e = $this->encuestas->obtener($id);
        if ($e === null) {
            throw new ReglaNegocioException('La encuesta no existe.', 404);
        }
        return $e;
    }

    private function validar(array $d): array
    {
        $errores = [];
        $txt = fn($k) => trim((string) ($d[$k] ?? ''));

        $e = [
            'titulo'          => $txt('titulo'),
            'descripcion'     => $txt('descripcion') === '' ? null : $txt('descripcion'),
            'estado'          => $d['estado'] ?? 'Privada',
            'id_categoria'    => (int) ($d['id_categoria'] ?? 0),
            'nueva_categoria' => $txt('nueva_categoria'),
        ];

        if ($e['titulo'] === '')                      $errores[] = 'Indicá el título de la encuesta.';
        if (mb_strlen($e['titulo']) > 120)            $errores[] = 'El título admite hasta 120 caracteres.';
        if (mb_strlen($e['descripcion'] ?? '') > 255) $errores[] = 'La descripción admite hasta 255 caracteres.';
        if (!in_array($e['estado'], self::ESTADOS, true)) $errores[] = 'El estado no es válido.';

        $errorCategoria = $this->categorias->validar($e['id_categoria'], $e['nueva_categoria']);
        if ($errorCategoria) $errores[] = $errorCategoria;

        $preguntas = [];
        foreach ((is_array($d['preguntas'] ?? null) ? $d['preguntas'] : []) as $p) {
            $enunciado = trim((string) ($p['enunciado'] ?? ''));
            if ($enunciado === '') continue;                       // se ignoran las vacías
            if (mb_strlen($enunciado) > 200) { $errores[] = 'Cada pregunta admite hasta 200 caracteres.'; break; }
            if (!in_array($p['tipo'] ?? '', self::TIPOS, true)) { $errores[] = 'Tipo de pregunta no válido.'; break; }
            $preguntas[] = ['enunciado' => $enunciado, 'tipo' => $p['tipo']];
        }
        if (!$preguntas) $errores[] = 'Agregá al menos una pregunta.';
        if (count($preguntas) > self::MAX_PREGUNTAS) $errores[] = 'Máximo ' . self::MAX_PREGUNTAS . ' preguntas.';

        if ($errores) {
            throw new ReglaNegocioException($errores);
        }
        return [$e, $preguntas];
    }
}
