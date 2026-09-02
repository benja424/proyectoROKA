<?php
require_once __DIR__ . '/../config/Database.php';

// Clase que representa el modelo Usuario
// Contiene toda la lógica de acceso a datos relacionada a usuarios
class Usuario
{
    private PDO $conexion;

    public function __construct()
    {
        // Obtiene la conexión desde el Singleton
        $this->conexion = Database::getInstancia()->getConexion();
    }

    // LOGIN
    // Recibe usuario y contraseña, devuelve los datos del usuario o null si no existe
    public function login(string $user, string $pass): ?array
    {
        $sql = "SELECT ci, nombre, apellido, user_name, rol 
                FROM usuario 
                WHERE user_name = :user AND pass = :pass";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([
            ':user' => $user,
            ':pass' => $pass
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        // fetch() devuelve false si no encuentra nada — lo convertimos a null
        return $resultado ?: null;
    }

    // Obtener usuario por user_name
    // Útil para verificar si un usuario existe sin necesitar la contraseña
    public function obtenerPorUserName(string $user): ?array
    {
        $sql = "SELECT ci, nombre, apellido, user_name, rol 
                FROM usuario 
                WHERE user_name = :user";

        $stmt = $this->conexion->prepare($sql);
        $stmt->execute([':user' => $user]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        return $resultado ?: null;
    }
                // Obtener todos los usuarios (excepto root)
    public function obtenerTodos(): array
    {
        $sql  = "SELECT ci, nombre, apellido, user_name, rol 
                 FROM usuario 
                 WHERE rol != 'root'
                 ORDER BY apellido ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Crear nuevo usuario
    public function crear(array $datos): bool
    {
        $sql = "INSERT INTO usuario (ci, nombre, apellido, user_name, pass, rol)
                VALUES (:ci, :nombre, :apellido, :user_name, :pass, :rol)";

        $stmt = $this->conexion->prepare($sql);
        
        try {
            return $stmt->execute([
                ':ci'        => $datos['ci'],
                ':nombre'    => $datos['nombre'],
                ':apellido'  => $datos['apellido'],
                ':user_name' => $datos['user_name'],
                ':pass'      => $datos['pass'],
                ':rol'       => $datos['rol']
            ]);
        } catch (PDOException $e) {
            // CI o user_name duplicado
            if ($e->getCode() === '23000') {
                throw new Exception('La CI o el nombre de usuario ya existe.');
            }
            throw $e;
        }
    }

    // Eliminar usuario por CI
    public function eliminar(string $ci): bool
    {
        $sql  = "DELETE FROM usuario WHERE ci = :ci";
        $stmt = $this->conexion->prepare($sql);
        return $stmt->execute([':ci' => $ci]);
    }
}