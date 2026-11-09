<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/ReglaNegocioException.php';

// Control de acceso: sesión, usuario activo y rol. Lo usan todos los endpoints.
class Autorizacion
{
    // Roles por módulo (los mismos que ven cada card en el portal)
    public const ROLES_TRASLADOS  = ['root', 'Gestion_General', 'Division_Transito'];
    public const ROLES_DOCUMENTOS = ['root', 'Gestion_General', 'Division_Enfermeria'];
    public const ROLES_ROOT       = ['root'];

    // Inicia la sesión solo cuando hace falta
    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Usuario logueado y activo, o null. No corta la ejecución (sirve para endpoints públicos).
    public static function usuarioActual(): ?array
    {
        self::iniciarSesion();
        if (!isset($_SESSION['usuario'])) {
            return null;
        }
        // Si lo inhabilitaron con la sesión abierta, se la cerramos
        if (!(new Usuario())->estaActivo($_SESSION['usuario']['ci'])) {
            self::cerrarSesion();
            return null;
        }
        return $_SESSION['usuario'];
    }

    public static function cerrarSesion(): void
    {
        self::iniciarSesion();
        $_SESSION = [];
        session_destroy();
    }

    // Exige sesión activa y uno de los roles. Si no, responde 401/403 y corta.
    public static function exigirRol(array $rolesPermitidos): array
    {
        $usuario = self::usuarioActual();
        if ($usuario === null) {
            throw new ReglaNegocioException('Sesión no iniciada', 401);
        }
        if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
            throw new ReglaNegocioException('No tenés permisos para esta operación', 403);
        }
        return $usuario;
    }

    // ¿El usuario logueado tiene uno de estos roles? (sin cortar la ejecución)
    public static function tieneRol(array $roles): bool
    {
        $usuario = self::usuarioActual();
        return $usuario !== null && in_array($usuario['rol'], $roles, true);
    }

    // Lee el cuerpo JSON de la petición; si no es un objeto JSON válido responde 400
    public static function leerJson(): array
    {
        $datos = json_decode(file_get_contents('php://input'), true);
        if (!is_array($datos)) {
            throw new ReglaNegocioException('El cuerpo de la petición no es un JSON válido', 400);
        }
        return $datos;
    }

    // Responde 405 si el método no está entre los permitidos
    public static function exigirMetodo(string ...$metodos): string
    {
        $metodo = $_SERVER['REQUEST_METHOD'];
        if (!in_array($metodo, $metodos, true)) {
            throw new ReglaNegocioException('Método no permitido', 405);
        }
        return $metodo;
    }
}
