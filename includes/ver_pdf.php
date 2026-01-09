<?php
if (session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado");
}

$entrega_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($entrega_id <= 0) {
    die("ID invalido");
}

$stmt = $conn->prepare("SELECT pdf_file FROM entregas WHERE id = ?");
$stmt->bind_param("i", $entrega_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if(!$row || empty($row['pdf_file'])) {
    die("Archivo no encontrado");
}

$pdf_path = __DIR__ . "/../uploads/" . $row['pdf_file'];
if(!file_exists($pdf_path)) {
    die("El archivo ya no existe en el servidor");
}

header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=\"" . basename($row['pdf_file']) . "\"");
readfile($pdf_path);
exit();