<?php
require_once __DIR__ . '/../config/database.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

function existeTablaElementos($conn) {
    try {
        $stmt = $conn->prepare("SHOW TABLES LIKE 'elementos_permitidos'");
        if (!$stmt) {
            return false;
        }
        $stmt->execute();
        $resultado = $stmt->get_result();
        $existe = $resultado && $resultado->num_rows > 0;
        $stmt->close();
        return $existe;
    } catch (Throwable $e) {
        error_log('Error validando elementos_permitidos: ' . $e->getMessage());
        return false;
    }
}

$usuario_id = $_SESSION['usuario_id'];
$accion = $_POST['accion'] ?? '';

if ($accion === 'guardar') {
    if (!existeTablaElementos($conn)) {
        echo json_encode(['error' => 'La tabla de elementos permitidos no está configurada en esta plantilla.']);
        exit;
    }
    $nombre = trim($_POST['nombre'] ?? '');
    if ($nombre === '') {
        echo json_encode(['error' => 'Nombre vacío']);
        exit;
    }

    // Verificar si ya existe
    $stmt = $conn->prepare("SELECT id FROM elementos_permitidos WHERE usuario_id = ? AND nombre_elemento = ?");
    $stmt->bind_param("is", $usuario_id, $nombre);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(['mensaje' => 'Elemento ya existe']);
    } else {
        $stmt_insert = $conn->prepare("INSERT INTO elementos_permitidos (usuario_id, nombre_elemento) VALUES (?, ?)");
        $stmt_insert->bind_param("is", $usuario_id, $nombre);
        $stmt_insert->execute();
        echo json_encode(['mensaje' => 'Elemento agregado', 'nombre' => $nombre]);
    }

    $stmt->close();
    exit;
}

if ($accion === 'obtener') {
    if (!existeTablaElementos($conn)) {
        echo json_encode(['elementos' => []]);
        exit;
    }

    $stmt = $conn->prepare("SELECT nombre_elemento FROM elementos_permitidos WHERE usuario_id = ?");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $elementos = [];

    while ($row = $resultado->fetch_assoc()) {
        $elementos[] = $row['nombre_elemento'];
    }

    echo json_encode(['elementos' => $elementos]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no válida']);
