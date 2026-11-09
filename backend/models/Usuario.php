<?php
require_once __DIR__ . '/../config/Database.php';

// Modelo Usuario — acceso a datos de los usuarios del sistema
class Usuario
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // LOGIN
    // Busca el usuario por nombre y compara la contraseña con el hash guardado (password_verify).
    // Devuelve los datos del usuario (sin la contraseña) o null si no coincide.
    public function login(string $user, string $pass): ?array
    {
        $stmt = $this->conexion->prepare(
            "SELECT ci, nombre, apellido, user_name, rol, estado, pass FROM usuario WHERE user_name = :user"
        );
        $stmt->execute([':user' => $user]);
        $usuario = $stmt->fetch();

        // Mismo resultado si no existe el usuario o si la contraseña no coincide
        if (!$usuario || !password_verify($pass, $usuario['pass'])) {
            return null;
        }

        // Si PHP cambió su algoritmo por defecto, se actualiza el hash guardado
        if (password_needs_rehash($usuario['pass'], PASSWORD_DEFAULT)) {
            $this->conexion->prepare("UPDATE usuario SET pass = :pass WHERE ci = :ci")
                ->execute([':pass' => password_hash($pass, PASSWORD_DEFAULT), ':ci' => $usuario['ci']]);
        }

        unset($usuario['pass']); // la contraseña nunca sale del modelo
        return $usuario;
    }

    // ¿El usuario sigue activo? Se consulta en cada llamada protegida,
    // así un usuario inhabilitado pierde el acceso aunque ya tuviera la sesión abierta.
    public function estaActivo(string $ci): bool
    {
        $stmt = $this->conexion->prepare("SELECT estado FROM usuario WHERE ci = :ci");
        $stmt->execute([':ci' => $ci]);
        return $stmt->fetchColumn() === 'Activo';
    }

    // Todos los usuarios menos root
    public function obtenerTodos(): array
    {
        return $this->conexion->query(
            "SELECT ci, nombre, apellido, user_name, rol, estado
             FROM usuario WHERE rol != 'root' ORDER BY estado, apellido"
        )->fetchAll();
    }

    // Inserta un usuario. La contraseña se guarda como hash, nunca en texto plano.
    // Si la CI o el nombre de usuario ya existen, PDO lanza una excepción con código 23000.
    public function crear(array $d): void
    {
        $this->conexion->prepare(
            "INSERT INTO usuario (ci, nombre, apellido, user_name, pass, rol)
             VALUES (:ci, :nombre, :apellido, :user_name, :pass, :rol)"
        )->execute([
            ':ci'        => $d['ci'],
            ':nombre'    => $d['nombre'],
            ':apellido'  => $d['apellido'],
            ':user_name' => $d['user_name'],
            ':pass'      => password_hash($d['pass'], PASSWORD_DEFAULT),
            ':rol'       => $d['rol'],
        ]);
    }

    // Cambia el estado (Activo / Inactivo). Los usuarios no se borran para no perder
    // su historial de traslados y logs. Devuelve false si la CI no existe o es el root.
    public function cambiarEstado(string $ci, string $estado): bool
    {
        $stmt = $this->conexion->prepare("UPDATE usuario SET estado = :estado WHERE ci = :ci AND rol != 'root'");
        $stmt->execute([':estado' => $estado, ':ci' => $ci]);
        return $stmt->rowCount() > 0;
    }
}
