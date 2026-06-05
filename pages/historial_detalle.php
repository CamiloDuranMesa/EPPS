<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit();
}

$entrega_id = isset($_POST['entrega_id']) ? (int)$_POST['entrega_id'] : (isset($_GET['entrega_id']) ? (int)$_GET['entrega_id'] : 0);
if ($entrega_id <= 0) {
    header("Location: listado_empleados.php");
    exit();
}

$success = isset($_GET['success']) ? true : false;
$errorMessage = null;

function columnaExisteDetalle($conn, $tabla, $columna) {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM `{$tabla}` LIKE ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $columna);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $existe = $resultado && $resultado->num_rows > 0;
        $stmt->close();
        return $existe;
    } catch (Throwable $e) {
        error_log('Error validando columna ' . $tabla . '.' . $columna . ': ' . $e->getMessage());
        return false;
    }
}

$soporta_firma_empleado = columnaExisteDetalle($conn, 'entregas', 'firma_empleado');
$soporta_firma_responsable = columnaExisteDetalle($conn, 'entregas', 'firma_responsable');
$soporta_firma_sst = columnaExisteDetalle($conn, 'entregas', 'firma_sst');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo'])) {
    $map = [
        'empleado' => 'firma_empleado',
        'responsable' => 'firma_responsable',
        'sst' => 'firma_sst'
    ];

    $tipo = $_POST['tipo'];
    $columna = $map[$tipo] ?? null;
    $columnaSoportada = match ($tipo) {
        'empleado' => $soporta_firma_empleado,
        'responsable' => $soporta_firma_responsable,
        'sst' => $soporta_firma_sst,
        default => false,
    };

    if (!$columna || !$columnaSoportada) {
        $errorMessage = "La firma seleccionada no está disponible en este esquema.";
    } else {
        if ($tipo === 'sst') {
            $targetDir = __DIR__ . "/../firmas/firma_sst/";
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    $errorMessage = "No se pudo crear el directorio de firma_sst.";
                }
            }
        } else {
            $targetDir = __DIR__ . "/../firmas/";
            if (!is_dir($targetDir)) {
                if (!mkdir($targetDir, 0755, true)) {
                    $errorMessage = "No se pudo crear el directorio de firmas.";
                }
            }
        }

        $firmaDibujada = $_POST['firma_dibujada'] ?? '';
        if ($firmaDibujada && strpos($firmaDibujada, 'data:image/png;base64,') === 0) {
            $base64 = substr($firmaDibujada, strlen('data:image/png;base64,'));
            $data = base64_decode($base64);
            if ($data === false) {
                $errorMessage = "Error al decodificar la firma dibujada.";
            } else {
                $fileName = uniqid("firma_") . ".png";
                $targetFile = $targetDir . $fileName;
                if (file_put_contents($targetFile, $data) === false) {
                    $errorMessage = "No se pudo guardar la firma dibujada.";
                } else {
                    $oldFile = null;
                    $stmtOld = $conn->prepare("SELECT $columna FROM entregas WHERE id = ?");
                    $stmtOld->bind_param("i", $entrega_id);
                    $stmtOld->execute();
                    $resOld = $stmtOld->get_result();
                    if ($rowOld = $resOld->fetch_assoc()) {
                        $oldFile = $rowOld[$columna];
                    }
                    $stmtOld->close();

                    $query = "UPDATE entregas SET $columna = ? WHERE id = ?";
                    $dbFile = ($tipo === 'sst') ? "firma_sst/" . $fileName : $fileName;
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("si", $dbFile, $entrega_id);

                    if ($stmt->execute()) {
                        if (!empty($oldFile) && $oldFile !== $dbFile) {
                            $oldPath = __DIR__ . "/../firmas/" . $oldFile;
                            if (file_exists($oldPath)) {
                                @unlink($oldPath);
                            }
                        }
                        $stmt->close();
                        header("Location: historial_detalle.php?entrega_id={$entrega_id}&success=1");
                        exit();
                    } else {
                        $errorMessage = "Error al actualizar la base de datos.";
                        if (file_exists($targetFile)) {
                            @unlink($targetFile);
                        }
                        $stmt->close();
                    }
                }
            }
        }
        elseif (isset($_FILES['firma']) && $_FILES['firma']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['firma'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessage = "Error al subir el archivo (code {$file['error']}).";
            } else {
                $maxSize = 2 * 1024 * 1024; 
                if ($file['size'] > $maxSize) {
                    $errorMessage = "El archivo excede el tamaño máximo de 2MB.";
                } else {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/pjpeg' => 'jpg',
                        'image/png' => 'png'
                    ];
                    if (!isset($allowed[$mime])) {
                        $errorMessage = "Tipo de archivo no permitido. Solo JPG y PNG.";
                    } else {
                        $ext = $allowed[$mime];
                        $fileName = uniqid("firma_") . "." . $ext;
                        $targetFile = $targetDir . $fileName;
                        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                            $oldFile = null;
                            $stmtOld = $conn->prepare("SELECT $columna FROM entregas WHERE id = ?");
                            $stmtOld->bind_param("i", $entrega_id);
                            $stmtOld->execute();
                            $resOld = $stmtOld->get_result();
                            if ($rowOld = $resOld->fetch_assoc()) {
                                $oldFile = $rowOld[$columna];
                            }
                            $stmtOld->close();
                            $query = "UPDATE entregas SET $columna = ? WHERE id = ?";
                            $dbFile = ($tipo === 'sst') ? "firma_sst/" . $fileName : $fileName;
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("si", $dbFile, $entrega_id);

                            if ($stmt->execute()) {
                                if (!empty($oldFile) && $oldFile !== $dbFile) {
                                    $oldPath = __DIR__ . "/../firmas/" . $oldFile;
                                    if (file_exists($oldPath)) {
                                        @unlink($oldPath);
                                    }
                                }

                                $stmt->close();
                                header("Location: historial_detalle.php?entrega_id={$entrega_id}&success=1");
                                exit();
                            } else {
                                $errorMessage = "Error al actualizar la base de datos.";
                                if (file_exists($targetFile)) {
                                    @unlink($targetFile);
                                }
                                $stmt->close();
                            }
                        } else {
                            $errorMessage = "No se pudo mover el archivo subido.";
                        }
                    }
                }
            }
        }
        else {
            $errorMessage = "No se recibió ningún archivo ni firma dibujada.";
        }
    }
}

$camposCabecera = [
    'e.id',
    'e.fecha_entrega',
    'e.numero_dotacion',
    'emp.id AS empleado_id',
    'emp.nombre AS empleado_nombre',
    'emp.cedula',
    'emp.cargo',
    'emp.area',
    'u_resp.nombre AS responsable_nombre',
    'COALESCE(e.sst_nombre, u_sst.nombre) AS sst_nombre',
    'e.pdf_file'
];

if ($soporta_firma_empleado) {
    $camposCabecera[] = 'e.firma_empleado';
}
if ($soporta_firma_responsable) {
    $camposCabecera[] = 'e.firma_responsable';
}
if ($soporta_firma_sst) {
    $camposCabecera[] = 'e.firma_sst';
}

$queryCab = "
    SELECT " . implode(', ', $camposCabecera) . "
    FROM entregas e
    INNER JOIN empleados emp ON emp.id = e.empleado_id
    LEFT JOIN usuarios u_resp ON u_resp.id = e.responsable_entrega
    LEFT JOIN empleados u_sst  ON u_sst.id  = e.sst_id
    WHERE e.id = ?
";

$stmtCab = $conn->prepare($queryCab);
$stmtCab->bind_param("i", $entrega_id);
$stmtCab->execute();
$resCab = $stmtCab->get_result();
$cab = $resCab->fetch_assoc();
$stmtCab->close();

if (!$cab) {
    header("Location: listado_empleados.php");
    exit();
}

$queryDet = "
    SELECT elemento, observacion
    FROM entregas_detalle
    WHERE entrega_id = ?
    ORDER BY id ASC
";
$stmtDet = $conn->prepare($queryDet);
$stmtDet->bind_param("i", $entrega_id);
$stmtDet->execute();
$resDet = $stmtDet->get_result();

$detRows = [];
$observacion = null;
while ($r = $resDet->fetch_assoc()) {
    $detRows[] = $r;
    if ($observacion === null && !empty($r['observacion'])) {
        $observacion = $r['observacion'];
    }
}
$stmtDet->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_items') {
    $entrega_id = (int)$_POST['entrega_id'];

    // Eliminar detalles anteriores
    $conn->query("DELETE FROM entregas_detalle WHERE entrega_id = $entrega_id");

    // Insertar nuevos elementos y/o observaciones
    if (!empty($_POST['elementos'])) {
        // Si hay elementos seleccionados, parsear formato "elemento|cantidad"
        $stmt = $conn->prepare("INSERT INTO entregas_detalle (entrega_id, elemento, observacion) VALUES (?, ?, ?)");
        $obs = !empty($_POST['observaciones']) ? $_POST['observaciones'] : $observacion;
        foreach ($_POST['elementos'] as $elem_data) {
            if (!empty(trim($elem_data))) {
                // Parsear formato: "elemento|cantidad"
                $partes = explode('|', $elem_data);
                $elemento = trim($partes[0]);
                $cantidad = isset($partes[1]) ? intval($partes[1]) : 1;
                $elemento_guardar = $elemento . ' Cantidad ' . $cantidad;
                $stmt->bind_param("iss", $entrega_id, $elemento_guardar, $obs);
                $stmt->execute();
            }
        }
        $stmt->close();
    } elseif (!empty($_POST['observaciones'])) {
        // Si no hay elementos pero hay observación
        $stmt = $conn->prepare("INSERT INTO entregas_detalle (entrega_id, elemento, observacion) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $entrega_id, 'Observaciones', $_POST['observaciones']);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: historial_detalle.php?entrega_id=$entrega_id&success=1");
    exit();
}

// Manejar eliminación de entrega
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_entrega') {
    $entrega_id = (int)$_POST['entrega_id'];
    $empleado_id_redirect = (int)$_POST['empleado_id'];
    
    // Usar transacción para asegurar que ambas eliminaciones se completen
    $conn->begin_transaction();
    
    try {
        // Primero eliminar los detalles de la entrega
        $stmtDetalle = $conn->prepare("DELETE FROM entregas_detalle WHERE entrega_id = ?");
        $stmtDetalle->bind_param("i", $entrega_id);
        $stmtDetalle->execute();
        $stmtDetalle->close();
        
        // Luego eliminar la entrega principal
        $stmtEntrega = $conn->prepare("DELETE FROM entregas WHERE id = ?");
        $stmtEntrega->bind_param("i", $entrega_id);
        $stmtEntrega->execute();
        $stmtEntrega->close();
        
        // Si todo salió bien, confirmar la transacción
        $conn->commit();
        
        // Redirigir al historial del empleado con mensaje de éxito
        header("Location: historial.php?empleado_id=$empleado_id_redirect&deleted=1");
        exit();
    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $conn->rollback();
        $errorMessage = "Error al eliminar la entrega: " . $e->getMessage();
    }
}


include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid">
    <h2 class="mt-4">Detalle de entrega #<?= (int)$cab['id'] ?></h2>

    <?php if ($success): ?>
        <div class="alert alert-success">Cambios guardados correctamente.</div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <h5>Empleado</h5>
            <p>
                <strong>Nombre:</strong> <?= htmlspecialchars($cab['empleado_nombre'] ?? '') ?><br>
                <strong>Cédula:</strong> <?= htmlspecialchars($cab['cedula'] ?? '') ?><br>
                <strong>Cargo:</strong> <?= htmlspecialchars($cab['cargo'] ?? '') ?><br>
                <strong>Área:</strong> <?= htmlspecialchars($cab['area'] ?? '') ?><br>
                <strong>Fecha:</strong> <?= htmlspecialchars($cab['fecha_entrega'] ?? '') ?><br>
                <strong>Número de dotación:</strong> <?= htmlspecialchars($cab['numero_dotacion'] ?? 'N/A') ?><br>
                <strong>Responsable entrega:</strong> <?= htmlspecialchars($cab['responsable_nombre'] ?? '—') ?><br>
                <strong>Representante SST:</strong> <?= htmlspecialchars($cab['sst_nombre'] ?? '—') ?>
            </p>
        </div>
    </div>

    <h5>Ítems entregados</h5>
    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>Elemento</th>
                <th style="width:120px">Cantidad</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($detRows)): ?>
            <tr><td class="text-center" colspan="2">Sin detalles</td></tr>
        <?php else: ?>
            <?php $mostrarFilas = false; ?>
            <?php foreach ($detRows as $d): ?>
                <?php
                    $elem_text = trim((string)($d['elemento'] ?? ''));
                    if ($elem_text === 'Observaciones') {
                        continue;
                    }
                    $mostrarFilas = true;
                    $cantidad = '';
                    $nombre_elem = $elem_text;
                    if (preg_match('/\b[Cc]antidad\s*:?[\s]*([0-9]+)\s*$/', $elem_text, $m)) {
                        $cantidad = $m[1];
                        $nombre_elem = trim(preg_replace('/\b[Cc]antidad\s*:?[\s]*([0-9]+)\s*$/', '', $elem_text));
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($nombre_elem) ?></td>
                    <td class="text-center"><?= $cantidad !== '' ? (int)$cantidad : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$mostrarFilas): ?>
                <tr><td class="text-center" colspan="2">Sin detalles</td></tr>
            <?php endif; ?>
        <?php endif; ?>

        </tbody>
    </table>

    <div class="mb-4">
        <button class="btn btn-sm btn-warning" type="button" onclick="toggleForm('formEditarItems')"> Editar items / Observaciones</button>
        <button class="btn btn-sm btn-danger ms-2" type="button" data-bs-toggle="modal" data-bs-target="#modalEliminarEntrega">
            <i class="bi bi-trash"></i> Eliminar entrega
        </button>
    </div>

    <form action="" method="POST" id="formEditarItems" class="mt-3 d-none">
        <input type="hidden" name="entrega_id" value="<?= (int)$entrega_id ?>">
        <input type="hidden" name="accion" value="editar_items">

        <label>Ítems de plantilla</label>
        <p class="form-text small">Seleccione ítems y especifique cantidad para cada uno.</p>
        <input type="text" id="filtroElementoEdit" class="form-control mb-2" placeholder="Buscar un ítem...">
        <div id="contenedorElementosEdit" class="border rounded p-3" style="max-height: 400px; overflow-y: auto; background-color: #f8f9fa;">
            <!-- Los elementos se cargarán aquí -->
        </div>
            <?php
            $opciones_backup = [
                'Elemento plantilla A',
                'Elemento plantilla B',
                'Elemento plantilla C',
                'Elemento plantilla D',
                'Elemento plantilla E'
            ];

            $elementos_bd = [];
            $resAllItems = $conn->query("SHOW TABLES LIKE 'elementos_permitidos'");
            if ($resAllItems && $resAllItems->num_rows > 0) {
                $resAllItems = $conn->query("SELECT nombre_elemento FROM elementos_permitidos ORDER BY nombre_elemento ASC");
                if ($resAllItems) {
                    while ($row = $resAllItems->fetch_assoc()) {
                        $elementos_bd[] = $row['nombre_elemento'];
                    }
                }
            }

            $opciones = array_values(array_unique(array_merge($elementos_bd, $opciones_backup)));
            sort($opciones);

            // Crear mapa de elementos guardados con sus cantidades
            $elementosGuardadosMap = [];
            foreach ($detRows ?? [] as $dd) {
                $txt = $dd['elemento'] ?? '';
                $base = preg_replace('/\b[Cc]antidad\s*:?[\s]*([0-9]+)\s*$/', '', $txt);
                $base = trim($base);
                $cant = 1;
                if (preg_match('/\b[Cc]antidad\s*:?[\s]*([0-9]+)\s*$/', $txt, $m)) {
                    $cant = intval($m[1]);
                }
                if ($base !== '') $elementosGuardadosMap[$base] = $cant;
            }
            ?>
            <script>
                window.elementosPermitidosEdit = <?= json_encode($opciones, JSON_UNESCAPED_UNICODE) ?>;
                window.elementosGuardadosMapEdit = <?= json_encode($elementosGuardadosMap, JSON_UNESCAPED_UNICODE) ?>;
            </script>

        <br>
        <label>Observaciones</label>
        <textarea name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($observacion ?? '') ?></textarea>

        <button type="submit" class="btn btn-success mt-2 mb-4">Guardar cambios</button>
        <button type="button" class="btn btn-secondary mt-2 mb-4" onclick="toggleForm('formEditarItems')">Cancelar</button>
    </form>

    <?php if (!empty($observacion)): ?>
        <div class="card border-info mb-4">
            <div class="card-header text-black">Observaciones</div>
            <div class="card-body"><p class="mb-0"><?= nl2br(htmlspecialchars($observacion)) ?></p></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($cab['pdf_file'])): ?>
        <div class="my-3">
            <a href="../includes/ver_pdf.php?id=<?= (int)$cab['id'] ?>" class="btn btn-success" target="_blank">Ver / Descargar PDF</a>
        </div>
    <?php else: ?>
        <div class="my-3 text-muted">No se ha subido ningún PDF para esta entrega</div>
    <?php endif; ?>

    <a href="historial.php?empleado_id=<?= urlencode($cab['empleado_id'] ?? 0) ?>" class="btn btn-secondary">Volver</a>

    <!-- Modal de confirmación para eliminar entrega -->
    <div class="modal fade" id="modalEliminarEntrega" tabindex="-1" aria-labelledby="modalEliminarEntregaLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEliminarEntregaLabel">
                        <i class="bi bi-exclamation-triangle"></i> Confirmar eliminación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2"><strong>¿Está seguro que desea eliminar esta entrega?</strong></p>
                    <p class="mb-2">Se eliminará:</p>
                    <ul>
                        <li>Entrega #<?= (int)$cab['id'] ?></li>
                        <li>Empleado: <?= htmlspecialchars($cab['empleado_nombre'] ?? '') ?></li>
                        <li>Fecha: <?= htmlspecialchars($cab['fecha_entrega'] ?? '') ?></li>
                        <li>Todos los elementos asociados</li>
                    </ul>
                    <p class="text-danger mb-0"><strong>Esta acción no se puede deshacer.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form action="" method="POST" style="display: inline;">
                        <input type="hidden" name="entrega_id" value="<?= (int)$entrega_id ?>">
                        <input type="hidden" name="empleado_id" value="<?= (int)$cab['empleado_id'] ?>">
                        <input type="hidden" name="accion" value="eliminar_entrega">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Sí, eliminar entrega
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row my-4">
        <?php
        $firmas = [];
        if ($soporta_firma_empleado) {
            $firmas[] = ['key' => 'firma_empleado', 'label' => 'Firma Empleado', 'tipo' => 'empleado', 'formId' => 'formEmpleado'];
        }
        if ($soporta_firma_responsable) {
            $firmas[] = ['key' => 'firma_responsable', 'label' => 'Firma Responsable', 'tipo' => 'responsable', 'formId' => 'formResponsable'];
        }
        if ($soporta_firma_sst) {
            $firmas[] = ['key' => 'firma_sst', 'label' => 'Firma SST', 'tipo' => 'sst', 'formId' => 'formSST'];
        }

        $renderFirmaSrc = function ($valor) {
            if (empty($valor)) {
                return null;
            }

            $ruta = __DIR__ . '/../firmas/' . ltrim($valor, '/');
            if (!file_exists($ruta)) {
                return null;
            }

            return '../firmas/' . htmlspecialchars($valor);
        };

        foreach ($firmas as $f): ?>
            <div class="col-md-4 text-center">
                <p><strong><?= $f['label'] ?></strong></p>
                <?php $srcFirma = $renderFirmaSrc($cab[$f['key']] ?? ''); ?>
                <?php if ($srcFirma): ?>
                    <img src="<?= $srcFirma ?>"
                        alt="<?= htmlspecialchars($f['label']) ?>"
                        style="max-width:200px;height:auto;border:1px solid #ccc;border-radius:8px;padding:4px;">
                <?php else: ?>
                    <div class="text-muted">Sin firma</div>
                <?php endif; ?>

                <?php if ($f['tipo'] !== 'sst'): ?>
                    <button class="btn btn-sm btn-warning mt-2" type="button" onclick="toggleForm('<?= $f['formId'] ?>')">Editar</button>
                    <form action="" method="POST" enctype="multipart/form-data" id="<?= $f['formId'] ?>"
                        class="mt-2 d-none"
                        onsubmit="return prepararFirmaEdit('canvas_<?= $f['formId'] ?>', 'input_<?= $f['formId'] ?>')">
                        <input type="hidden" name="entrega_id" value="<?= (int)$entrega_id ?>">
                        <input type="hidden" name="tipo" value="<?= $f['tipo'] ?>">

                        <div class="mb-2">
                            <label class="form-label">Dibujar firma</label>
                            <canvas id="canvas_<?= $f['formId'] ?>" width="320" height="100"
                                    style="border:1px solid #bbb; border-radius:8px;"></canvas>
                            <input type="hidden" name="firma_dibujada" id="input_<?= $f['formId'] ?>">
                            <button type="button" class="btn btn-sm btn-secondary mt-1"
                                    onclick="limpiarCanvasEdit('canvas_<?= $f['formId'] ?>')">Limpiar</button>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">O subir archivo</label>
                            <input type="file" name="firma" accept="image/*" class="form-control">
                        </div>
                        <button type="submit" name="update_firma" class="btn btn-sm btn-primary">Guardar</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>

function agregarFila() {
    let table = document.getElementById('itemsTable');
    let row = document.createElement('tr');
    row.innerHTML = `
    <td><input type="text" name="elemento[]" class="form-control"></td>
    <td><input type="text" name="observaciones[]" class="form-control"></td>
    <td><button type="button" class="btn btn-danger btn-sm" onclick="eliminarFila(this)">X</button></td>
    `;
    table.appendChild(row);
}

function eliminarFila(btn) {
    btn.closest('tr').remove();
}

function toggleForm(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.toggle('d-none');
}

function prepararFirmaEdit(canvasId, inputId) {
    var canvas = document.getElementById(canvasId);
    var input = document.getElementById(inputId);
    if (canvas && input) {
        var dataURL = canvas.toDataURL("image/png");
        var blank = document.createElement('canvas');
        blank.width = canvas.width;
        blank.height = canvas.height;
        if (canvas.toDataURL() !== blank.toDataURL()) {
            input.value = dataURL;
        } else {
            input.value = '';
        }
    }
    return true;
}
function limpiarCanvasEdit(id) {
    var canvas = document.getElementById(id);
    if (canvas) {
        var ctx = canvas.getContext("2d");
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Renderizar elementos con cantidad en el formulario de edición
        // Helper object - Debe estar ANTES de usarlo
        window.element = {
            escapeHtml: function(text) {
                const map = {
                    '&': '&amp;', '<': '&lt;', '>': '&gt;',
                    '"': '&quot;', "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, m => map[m]);
            }
        };

    if (typeof window.elementosPermitidosEdit !== 'undefined') {
        const $inputBuscar = document.getElementById('filtroElementoEdit');
        const $contenedorElementos = document.getElementById('contenedorElementosEdit');
        const elementosOrdenados = window.elementosPermitidosEdit.sort();
        
        window.elementosSeleccionadosEdit = {};

        elementosOrdenados.forEach((elemento, index) => {
            const id = `elemento_edit_${index}`;
            const html = `
                <div class="elemento-item row align-items-center p-2 border-bottom">
                    <div class="col-12 col-md-7">
                        <div class="form-check">
                            <input class="form-check-input checkbox-elemento-edit" type="checkbox" 
                                   id="${id}" data-elemento="${element.escapeHtml(elemento)}" value="${element.escapeHtml(elemento)}">
                            <label class="form-check-label" for="${id}">
                                ${element.escapeHtml(elemento)}
                            </label>
                        </div>
                    </div>
                    <div class="col-12 col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Cantidad</span>
                            <input type="number" class="form-control cantidad-elemento-edit" 
                                   min="1" value="1" data-elemento="${element.escapeHtml(elemento)}" 
                                   disabled>
                        </div>
                    </div>
                </div>
            `;
            $contenedorElementos.innerHTML += html;
        });

        // Preseleccionar elementos guardados
        if (typeof window.elementosGuardadosMapEdit === 'object') {
            Object.entries(window.elementosGuardadosMapEdit).forEach(([elem, qty]) => {
                const $checkbox = document.querySelector(`.checkbox-elemento-edit[data-elemento="${elem}"]`);
                const $cantidadInput = document.querySelector(`.cantidad-elemento-edit[data-elemento="${elem}"]`);
                if ($checkbox) {
                    $checkbox.checked = true;
                    $cantidadInput.disabled = false;
                    $cantidadInput.value = qty;
                    window.elementosSeleccionadosEdit[elem] = qty;
                }
            });
        }

        // Event listeners
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('checkbox-elemento-edit')) {
                const $checkbox = e.target;
                const elemento = $checkbox.dataset.elemento;
                const $cantidadInput = document.querySelector(`.cantidad-elemento-edit[data-elemento="${elemento}"]`);
                
                if ($checkbox.checked) {
                    $cantidadInput.disabled = false;
                    window.elementosSeleccionadosEdit[elemento] = parseInt($cantidadInput.value) || 1;
                } else {
                    $cantidadInput.disabled = true;
                    delete window.elementosSeleccionadosEdit[elemento];
                }
                actualizarCampoEditarOculto();
            }
            
            if (e.target.classList.contains('cantidad-elemento-edit') && !e.target.disabled) {
                const $input = e.target;
                const elemento = $input.dataset.elemento;
                const cantidad = Math.max(1, parseInt($input.value) || 1);
                $input.value = cantidad;
                window.elementosSeleccionadosEdit[elemento] = cantidad;
                actualizarCampoEditarOculto();
            }
        });

        if ($inputBuscar) {
            $inputBuscar.addEventListener('input', function() {
                const termino = this.value.toLowerCase();
                document.querySelectorAll('.elemento-item').forEach(item => {
                    const label = item.querySelector('.form-check-label');
                    if (label && label.textContent.toLowerCase().includes(termino)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        function actualizarCampoEditarOculto() {
            // Remover inputs hidden anteriores
            document.querySelectorAll('input[name="elementos[]"]').forEach(input => {
                if (input.id !== 'elementosSeleccionadosEdit') input.remove();
            });
            // Crear nuevos inputs hidden
            Object.entries(window.elementosSeleccionadosEdit).forEach(([elemento, cantidad]) => {
                const $input = document.createElement('input');
                $input.type = 'hidden';
                $input.name = 'elementos[]';
                $input.value = `${elemento}|${cantidad}`;
                document.getElementById('formEditarItems').appendChild($input);
            });
        }
        // Asegurar que existan los inputs hidden iniciales para POST
        actualizarCampoEditarOculto();
    }


    ['formEmpleado','formResponsable'].forEach(function(fid){
        var canvas = document.getElementById('canvas_' + fid);
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        let dibujando = false;

        function getPosicionTouch(evt) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: evt.touches[0].clientX - rect.left,
                y: evt.touches[0].clientY - rect.top
            };
        }

        function getMousePos(evt) {
            const rect = canvas.getBoundingClientRect();
            return {
                x: evt.clientX - rect.left,
                y: evt.clientY - rect.top
            };
        }

        canvas.addEventListener('mousedown', function (e) {
            dibujando = true;
            const pos = getMousePos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        });

        canvas.addEventListener('mousemove', function (e) {
            if (!dibujando) return;
            const pos = getMousePos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        });

        canvas.addEventListener('mouseup', function () {
            dibujando = false;
        });

        canvas.addEventListener('mouseleave', function () {
            dibujando = false;
        });

        canvas.addEventListener('touchstart', function (e) {
            e.preventDefault();
            dibujando = true;
            const pos = getPosicionTouch(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
        }, { passive: false});

        canvas.addEventListener('touchmove', function (e) {
            e.preventDefault();
            if (!dibujando) return;
            const pos = getPosicionTouch(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
        }, { passive: false});

        canvas.addEventListener('touchend', function () {
            dibujando = false;
        });
    });
});

document.getElementById('filtroElemento').addEventListener('input', function() {
    var filter = this.value.toLowerCase(); // Obtener el texto ingresado en el filtro
    var select = document.querySelector('select[name="elementos[]"]'); // Seleccionar el select
    var options = select.options; // Obtener todas las opciones dentro del select

    // Iterar sobre todas las opciones y ocultar las que no coinciden con el filtro
    for (var i = 0; i < options.length; i++) {
        var option = options[i];
        if (option.text.toLowerCase().indexOf(filter) > -1) {
            option.style.display = ''; // Mostrar opción si coincide con el filtro
        } else {
            option.style.display = 'none'; // Ocultar opción si no coincide con el filtro
        }
    }
});

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
