<?php

/**
 * CONFIGURACIÓN DE PARÁMETROS DE CONEXIÓN (MySQL)
 * Defino las credenciales y el juego de caracteres para la base de datos.
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'kaloai_db');
define('DB_USER', 'root');
define('DB_PASS', '');          
define('DB_CHARSET', 'utf8mb4'); 

/**
 * CONFIGURACIÓN DE LA API DE GOOGLE GEMINI
 * Listado de claves para la rotación de peticiones y URL del modelo utilizado.
 */
define('GEMINI_API_KEYS', [
    'xxx',
    'xxx'
]);
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent');

/**
 * Establezco la conexión a la base de datos utilizando PDO.
 * Configuro el manejo de errores mediante excepciones y desactivo la emulación 
 * de sentencias preparadas para mejorar la seguridad contra inyecciones SQL.
 */
function connectDB() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        // Configuro el modo de error para que lance excepciones y poder capturarlas
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Establezco que el retorno por defecto sean arrays asociativos
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Refuerzo la seguridad utilizando sentencias preparadas reales del motor MySQL
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (\PDOException $e) {
        // Registro el fallo en el log del sistema y detengo la ejecución con un mensaje controlado
        error_log("Error de conexión a la BD: " . $e->getMessage());
        die("Error fatal: No se pudo conectar a la base de datos. Consulta los logs del servidor.");
    }
}