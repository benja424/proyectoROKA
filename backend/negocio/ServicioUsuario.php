<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/ReglaNegocioException.php';

// Capa de negocio de usuarios: validaciones y registro en logs
class ServicioUsuario
{
    // Roles que se pueden asignar desde la aplicación (el root no se crea desde acá)
    public const ROLES_ASIGNABLES = ['Gestion_General', 'Division_Enfermeria', 'Division_Transito'];
    public const ESTADOS = ['Activo', 'Inactivo'];

    private Usuario $usuarios;

    public function __construct()
    {
        $this->usuarios = new Usuario();
    }

    public function listar(): array
    {
        return $this->usuarios->obtenerTodos();
    }

    public function crear(array $datos, array $admin): void
    {
        $d = self::validar($datos);
        try {
            $this->usuarios->crear($d);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new ReglaNegocioException('La CI o el nombre de usuario ya existe.', 409);
            }
            throw $e;
        }
        Log::registrar($admin['ci'], 'Crea', Log::USUARIOS, $d['ci']);
    }

    public function cambiarEstado(string $ci, string $estado, array $admin): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new ReglaNegocioException('El estado no es válido.');
        }
        if ($ci === $admin['ci']) {
            throw new ReglaNegocioException('No podés cambiar tu propio estado.', 403);
        }
        if (!$this->usuarios->cambiarEstado($ci, $estado)) {
            throw new ReglaNegocioException('El usuario no existe o no se puede modificar.', 404);
        }
        Log::registrar($admin['ci'], $estado === 'Activo' ? 'Activa' : 'Inhabilita', Log::USUARIOS, $ci);
    }

    // Valida los datos de un usuario nuevo. Devuelve los datos limpios o lanza la lista de errores.
    public static function validar(array $datos): array
    {
        $txt = fn($k) => trim((string) ($datos[$k] ?? ''));
        $d = [
            'ci'        => $txt('ci'),
            'nombre'    => $txt('nombre'),
            'apellido'  => $txt('apellido'),
            'user_name' => $txt('user_name'),
            'pass'      => (string) ($datos['pass'] ?? ''),
            'rol'       => $txt('rol'),
        ];

        $errores = [];
        $nombreValido = '/^[\p{L}][\p{L} \'\-]{1,29}$/u';   // letras, espacios, apóstrofo y guion

        if (!preg_match('/^\d{7,8}$/', $d['ci']))          $errores[] = 'La CI debe tener 7 u 8 dígitos, sin puntos ni guion.';
        if (!preg_match($nombreValido, $d['nombre']))      $errores[] = 'El nombre debe tener entre 2 y 30 letras.';
        if (!preg_match($nombreValido, $d['apellido']))    $errores[] = 'El apellido debe tener entre 2 y 30 letras.';
        if (!preg_match('/^[a-zA-Z0-9._]{3,30}$/', $d['user_name']))
            $errores[] = 'El usuario debe tener entre 3 y 30 caracteres: letras, números, punto o guion bajo.';
        if (strlen($d['pass']) < 8 || strlen($d['pass']) > 72)
            $errores[] = 'La contraseña debe tener entre 8 y 72 caracteres.';
        if (!in_array($d['rol'], self::ROLES_ASIGNABLES, true)) $errores[] = 'Elegí un rol válido.';

        if ($errores) {
            throw new ReglaNegocioException($errores);
        }
        return $d;
    }
}
