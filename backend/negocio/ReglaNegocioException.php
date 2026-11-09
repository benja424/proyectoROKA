<?php
// Excepción para errores de validación o de reglas del negocio.
// El endpoint la convierte en una respuesta JSON con el código HTTP indicado.
class ReglaNegocioException extends Exception
{
    private array $errores;
    private int $http;

    // $errores: un mensaje o una lista de mensajes — $http: código de respuesta (422 por defecto)
    public function __construct(string|array $errores, int $http = 422)
    {
        $this->errores = (array) $errores;
        $this->http    = $http;
        parent::__construct(implode(' ', $this->errores));
    }

    public function getErrores(): array
    {
        return $this->errores;
    }

    public function getHttp(): int
    {
        return $this->http;
    }
}
