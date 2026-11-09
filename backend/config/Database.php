<?php
// Credenciales de conexión a la base de datos
// La aplicación NO usa root: usa sigsm_app, que solo tiene los permisos necesarios
// (ver backend/database/usuarios_bd.sql). Al instalar en el servidor, cambiar la contraseña
// en ese script y acá.
define('SERVERNAME', 'localhost');
define('USERNAME', 'sigsm_app');
define('PASSWORD', 'App.Sigsm#2026');
define('DBNAME', 'sigsm');

// Hora de Uruguay en PHP y en MySQL (XAMPP trae por defecto Europe/Berlin).
// Sin esto "ahora" sería distinto en PHP y en la base, y fallaría la regla de horarios.
define('ZONA_HORARIA', 'America/Montevideo');
define('ZONA_HORARIA_MYSQL', '-03:00');
date_default_timezone_set(ZONA_HORARIA);

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
        $this->conexion = new PDO($dsn, USERNAME, PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errores como excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // filas como arrays asociativos
        ]);
        $this->conexion->exec("SET time_zone = '" . ZONA_HORARIA_MYSQL . "'");
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
