<link rel="stylesheet" href="../assets/css/ingreso.css">

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "config/database.php";

require_once "vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $area = trim($_POST['area'] ?? '');

    if (!empty($nombre) && !empty($documento)) {
        $check = $conn->prepare("SELECT id FROM empleados WHERE cedula = ?");
        $check->bind_param("s", $documento);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensaje = "Ya existe un empleado registrado con esa cédula.";
        } else {
            $stmt = $conn->prepare("INSERT INTO empleados (nombre, cedula, cargo, area) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $documento, $cargo, $area);

            if ($stmt->execute()) {
                $mensaje = "Empleado registrado correctamente.";
            } else {
                $mensaje = "Error al registrar empleado. Inténtalo de nuevo.";
            }
            $stmt->close();
        }
        $check->close();
    } else {
        $mensaje = "Nombre y documento son obligatorios.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_excel'])) {
    $fileTmpPath = $_FILES['archivo_excel']['tmp_name'];
    $fileName = $_FILES['archivo_excel']['name'];
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

    if(in_array($fileExtension, ['xls', 'xlsx'])) {
        try {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            $guardados = 0;
            $omitidos = 0;
            $duplicados = 0;
            $vacios = 0;

            foreach ($rows as $index => $row) {
                if ($index == 0) continue;

                $nombre = !empty(trim((string)($row[0] ?? ''))) ? trim((string)$row[0]) : "";
                $cedula = !empty(trim((string)($row[1] ?? ''))) ? trim((string)$row[1]) : "";
                $cargo  = !empty(trim((string)($row[2] ?? ''))) ? trim((string)$row[2]) : "";
                $area   = !empty(trim((string)($row[3] ?? ''))) ? trim((string)$row[3]) : "";

                if(empty($nombre) || empty($cedula)) {
                    $vacios++;
                    $omitidos++;
                    continue;
                }

                $check = $conn->prepare("SELECT id FROM empleados WHERE cedula = ?");
                $check->bind_param("s", $cedula);
                $check->execute();
                $check->store_result();

                if ($check->num_rows == 0) {
                    $stmt = $conn->prepare("INSERT INTO empleados (nombre, cedula, cargo, area) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $nombre, $cedula, $cargo, $area);
                    if ($stmt->execute()) {
                        $guardados++;
                    } else {
                        $omitidos++;
                    }
                    $stmt->close();
                } else {
                    $duplicados++;
                    $omitidos++;
                }
                $check->close();
            }
            $mensaje = "Archivo procesado correctamente.<br>Empleados guardados: $guardados<br>Omitidos: $omitidos (Duplicados: $duplicados, Vacíos: $vacios)";
        } catch (Exception $e) {
            $mensaje = "Error al leer el archivo: " . $e->getMessage();
        }
    } else {
        $mensaje = "Formato de archivo no válido. Solo se permiten .xls o .xlsx";
    }
}

?>



<div class="container-fluid">
    <div class="row">
        <div class="col-10 p-4">
            <h2>Ingreso de empleados</h2>
            
            <?php if ($mensaje): ?>
                <div class="alert <?= strpos($mensaje, 'Error') !== false || strpos($mensaje, 'existe') !== false ? 'alert-danger' : 'alert-success' ?>">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Cédula</label>
                        <input type="text" name="documento" class="form-control" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Cargo</label>
                        <input type="text" name="cargo" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Área</label>
                        <input type="text" name="area" class="form-control">
                    </div>
                </div>
                <button class="btn btn-primary mt-2" type="submit">Guardar</button>
            </form>

                <h4>Subir empleados desde Excel</h4>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" name="archivo_excel" class="form-control" accept=".xls, .xlsx" required>
                    </div>
                    <button class="btn btn-success" type="submit">Cargar Excel</button>
                </form> 
        </div>
    </div>
</div>
