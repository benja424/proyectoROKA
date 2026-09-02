<?php
// Credenciales de conexión a la base de datos
define('SERVERNAME', 'localhost');
define('USERNAME', 'root');
define('PASSWORD', '');
define('DBNAME', 'sigsm');

// Clase de conexión — patrón Singleton
// Solo existe una instancia de la conexión en toda la aplicación
class Database
{
    private static ?Database $instancia = null; // Única instancia de la clase
    private PDO $conexion;                       // Objeto de conexión PDO

    // Constructor privado: nadie puede hacer "new Database()" desde afuera
    private function __construct()
    {
        $dsn = "mysql:host=" . SERVERNAME . ";dbname=" . DBNAME . ";charset=utf8mb4";

        try {
            $this->conexion = new PDO($dsn, USERNAME, PASSWORD);
            // PDO lanza excepciones en vez de errores silenciosos
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), $e->getCode());
        }
    }

    // Devuelve la única instancia — la crea si no existe todavía
    public static function getInstancia(): Database
    {
        if (self::$instancia === null) {
            self::$instancia = new Database();
        }
        return self::$instancia;
    }

    // Devuelve el objeto PDO para hacer consultas
    public function getConexion(): PDO
    {
        return $this->conexion;
    }
}