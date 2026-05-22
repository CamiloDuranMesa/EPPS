<?php
/**
 * Nombre: database.php
 * Descripción: Configuración dinámica de la base de datos mediante variables de entorno.
 */

$host     = getenv('DB_HOST') ?: 'localhost';
$user     = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$database = getenv('DB_NAME') ?: 'prueba_epp';
$port     = getenv('DB_PORT') ?: 3306;
$ssl_mode = getenv('DB_SSL_MODE') ?: 'DISABLE';

$conn = mysqli_init();

if (!$conn) {
    die("Error crítico: No se pudo inicializar mysqli_init()");
}

if ($ssl_mode === 'REQUIRED') {
    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    $flags = MYSQLI_CLIENT_SSL;
} else {
    $flags = 0;
}

if (!$conn->real_connect($host, $user, $password, $database, (int)$port, null, $flags)) {
    die("Error de conexión a la base de datos (" . mysqli_connect_errno() . "): " . mysqli_connect_error());
}

$conn->set_charset(getenv('DB_CHARSET') ?: "utf8mb4");
