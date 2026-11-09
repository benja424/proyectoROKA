<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo de respuestas anónimas a encuestas (envíos) y sus resultados
class Respuesta
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // Guarda un envío completo. $respuestas: [id_pregunta => ['escala' => int|null, 'texto' => string|null]]
    public function guardarEnvio(int $idEncuesta, array $respuestas): int
    {
        $this->conexion->beginTransaction();
        try {
            $this->conexion->prepare("INSERT INTO envio (id_encuesta) VALUES (:id)")->execute([':id' => $idEncuesta]);
            $idEnvio = (int) $this->conexion->lastInsertId();

            $stmt = $this->conexion->prepare(
                "INSERT INTO respuesta (id_envio, id_pregunta, valor_escala, valor_texto)
                 VALUES (:envio, :pregunta, :escala, :texto)"
            );
            foreach ($respuestas as $idPregunta => $r) {
                $stmt->execute([
                    ':envio'    => $idEnvio,
                    ':pregunta' => $idPregunta,
                    ':escala'   => $r['escala'],
                    ':texto'    => $r['texto'],
                ]);
            }
            $this->conexion->commit();
            return $idEnvio;
        } catch (Throwable $e) {
            $this->conexion->rollBack();
            throw $e;
        }
    }

    public function resultadosEscala(int $idEncuesta): array
    {
        $stmt = $this->conexion->prepare("SELECT * FROM v_resultados_escala WHERE id_encuesta = :id ORDER BY orden");
        $stmt->execute([':id' => $idEncuesta]);
        return $stmt->fetchAll();
    }

    // Respuestas de texto, de la más nueva a la más vieja
    public function respuestasTexto(int $idEncuesta): array
    {
        $stmt = $this->conexion->prepare(
            "SELECT p.id_pregunta, r.valor_texto AS texto, v.fecha
             FROM respuesta r
             JOIN pregunta p ON p.id_pregunta = r.id_pregunta
             JOIN envio    v ON v.id_envio    = r.id_envio
             WHERE p.id_encuesta = :id AND p.tipo = 'Texto' AND r.valor_texto IS NOT NULL
             ORDER BY v.fecha DESC"
        );
        $stmt->execute([':id' => $idEncuesta]);
        return $stmt->fetchAll();
    }
}
