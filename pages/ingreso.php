<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../config/database.php";

require_once __DIR__ . "/../vendor/autoload.php";
use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit();
}

$mensaje = "";
$tipo_mensaje = "info"; // "success", "danger", "info"

// ============================================================
// MANEJO DE EDICIÓN DE EMPLEADO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar') {
    $empleado_id = filter_var($_POST['empleado_id'] ?? null, FILTER_VALIDATE_INT);
    $nombre = trim($_POST['nombre'] ?? '');
    $documento = trim($_POST['documento'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $area = trim($_POST['area'] ?? '');

    if (!$empleado_id || empty($nombre) || empty($documento)) {
        $mensaje = "Datos incompletos o inválidos.";
        $tipo_mensaje = "danger";
    } else {
        // Verificar que no exista otro empleado con la misma cédula
        $check = $conn->prepare("SELECT id FROM empleados WHERE cedula = ? AND id != ?");
        $check->bind_param("si", $documento, $empleado_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $mensaje = "Ya existe otro empleado con esa cédula.";
            $tipo_mensaje = "danger";
        } else {
            $stmt = $conn->prepare("UPDATE empleados SET nombre = ?, cedula = ?, cargo = ?, area = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $nombre, $documento, $cargo, $area, $empleado_id);

            if ($stmt->execute()) {
                $mensaje = "Empleado actualizado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al actualizar empleado: " . $stmt->error;
                $tipo_mensaje = "danger";
            }
            $stmt->close();
        }
        $check->close();
    }
}

// ============================================================
// MANEJO DE ELIMINACIÓN DE EMPLEADO
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar') {
    $empleado_id = filter_var($_POST['empleado_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$empleado_id) {
        $mensaje = "Empleado inválido.";
        $tipo_mensaje = "danger";
    } else {
        // Verificar que el empleado no tenga entregas registradas
        $check_entregas = $conn->prepare("SELECT COUNT(*) as total FROM entregas WHERE empleado_id = ?");
        $check_entregas->bind_param("i", $empleado_id);
        $check_entregas->execute();
        $check_entregas->bind_result($total_entregas);
        $check_entregas->fetch();
        $check_entregas->close();

        if ($total_entregas > 0) {
            $mensaje = "No se puede eliminar empleado con entregas registradas. Actualmente tiene $total_entregas entrega(s).";
            $tipo_mensaje = "danger";
        } else {
            $stmt = $conn->prepare("DELETE FROM empleados WHERE id = ?");
            $stmt->bind_param("i", $empleado_id);

            if ($stmt->execute()) {
                $mensaje = "Empleado eliminado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al eliminar empleado: " . $stmt->error;
                $tipo_mensaje = "danger";
            }
            $stmt->close();
        }
    }
}

// ============================================================
// MANEJO DE NUEVO EMPLEADO
// ============================================================
?>
<link rel="stylesheet" href="../assets/css/ingreso.css">

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre']) && !isset($_POST['accion'])) {
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
            $tipo_mensaje = "danger";
        } else {
            $stmt = $conn->prepare("INSERT INTO empleados (nombre, cedula, cargo, area) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nombre, $documento, $cargo, $area);

            if ($stmt->execute()) {
                $mensaje = "Empleado registrado correctamente.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al registrar empleado. Inténtalo de nuevo.";
                $tipo_mensaje = "danger";
            }
            $stmt->close();
        }
        $check->close();
    } else {
        $mensaje = "Nombre y documento son obligatorios.";
        $tipo_mensaje = "danger";
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
            $tipo_mensaje = "success";
        } catch (Exception $e) {
            $mensaje = "Error al leer el archivo: " . $e->getMessage();
            $tipo_mensaje = "danger";
        }
    } else {
        $mensaje = "Formato de archivo no válido. Solo se permiten .xls o .xlsx";
        $tipo_mensaje = "danger";
    }
}

?>



<div class="container-fluid">
    <div class="row">
        <div class="col-10 p-4">
            <h2>Ingreso de empleados</h2>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                    <?= nl2br(htmlspecialchars($mensaje)) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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

                <!-- TABLA DE EMPLEADOS -->
                <h4 class="mt-5 mb-3">Empleados Registrados</h4>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Cédula</th>
                                <th>Cargo</th>
                                <th>Área</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = $conn->query("SELECT id, nombre, cedula, cargo, area FROM empleados ORDER BY nombre");
                            
                            if ($result && $result->num_rows > 0) {
                                while ($empleado = $result->fetch_assoc()) {
                                    $id = htmlspecialchars($empleado['id']);
                                    $nombre = htmlspecialchars($empleado['nombre']);
                                    $cedula = htmlspecialchars($empleado['cedula']);
                                    $cargo = htmlspecialchars($empleado['cargo'] ?? '');
                                    $area = htmlspecialchars($empleado['area'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?= $nombre ?></td>
                                        <td><?= $cedula ?></td>
                                        <td><?= $cargo ?></td>
                                        <td><?= $area ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editarEmpleadoModal"
                                                    onclick="cargarEmpleadoParaEditar(<?= $id ?>, '<?= $nombre ?>', '<?= $cedula ?>', '<?= $cargo ?>', '<?= $area ?>')">
                                                <i class="bi bi-pencil"></i> Editar
                                            </button>
                                            <form method="POST" style="display:inline;" 
                                                  onsubmit="return confirm('¿Estás seguro de eliminar este empleado?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="empleado_id" value="<?= $id ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-trash"></i> Eliminar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                echo '<tr><td colspan="5" class="text-center text-muted">No hay empleados registrados</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
        </div>
    </div>
</div>

<!-- MODAL PARA EDITAR EMPLEADO -->
<div class="modal fade" id="editarEmpleadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Empleado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="empleado_id" id="empleado_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" id="nombre_edit" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cédula</label>
                        <input type="text" name="documento" id="documento_edit" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Cargo</label>
                        <input type="text" name="cargo" id="cargo_edit" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Área</label>
                        <input type="text" name="area" id="area_edit" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function cargarEmpleadoParaEditar(id, nombre, cedula, cargo, area) {
    document.getElementById('empleado_id').value = id;
    document.getElementById('nombre_edit').value = nombre;
    document.getElementById('documento_edit').value = cedula;
    document.getElementById('cargo_edit').value = cargo;
    document.getElementById('area_edit').value = area;
}
