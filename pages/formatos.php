<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit();
}

$usuario_id = (int)($_SESSION['usuario_id'] ?? 0);
if ($usuario_id <= 0) {
    header("Location: ../views/login.php");
    exit();
}

$responsable_entrega = $usuario_id;
$errorMessage = '';
$successMessage = '';
?>
<link rel="stylesheet" href="../assets/css/formatos.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<?php
// ============================================================
// INICIALIZACIÓN Y VALIDACIONES
// ============================================================

// ============================================================
// FUNCIONES AUXILIARES
// ============================================================

/**
 * Guarda una firma en base64 en el directorio de firmas
 * @param string $base64 Datos de firma en base64
 * @return string|null Nombre del archivo guardado o null si falla
 */
function guardarFirma($base64) {
    if (empty($base64)) {
        return null;
    }

    // Aceptar cualquier data URL de imagen con base64 (png/jpg/etc.)
    if (!preg_match('/^data:image\/[a-zA-Z]+;base64,/', $base64)) {
        return null;
    }

    try {
        $directorio = __DIR__ . '/../firmas/';
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de firmas");
        }

        // Extraer la parte base64 después de la coma
        $parts = explode(',', $base64, 2);
        if (count($parts) !== 2) {
            throw new Exception("Formato de data URL inválido");
        }

        $b64 = str_replace(' ', '+', $parts[1]);
        $data = base64_decode($b64, true);
        if ($data === false) {
            throw new Exception("Error al decodificar firma");
        }

        $filename = 'firma_' . uniqid() . '.png';
        $path = $directorio . $filename;

        if (file_put_contents($path, $data) === false) {
            throw new Exception("Error al guardar archivo de firma");
        }

        return $filename;
    } catch (Exception $e) {
        error_log("Error guardando firma: " . $e->getMessage());
        return null;
    }
}

/**
 * Guarda un archivo PDF en el directorio de uploads
 * @param array $file Información del archivo desde $_FILES
 * @return string|null Nombre del archivo guardado o null si falla
 */
function guardarPDF($file) {
    if (empty($file['name'])) {
        return null;
    }

    try {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir archivo (código: {$file['error']})");
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("El archivo PDF excede 5 MB");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if ($mime !== 'application/pdf') {
            throw new Exception("El archivo no es un PDF válido");
        }

        $directorio = __DIR__ . '/../uploads/';
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de uploads");
        }

        $pdf_filename = 'pdf_' . uniqid() . '.pdf';
        $ruta = $directorio . $pdf_filename;

        if (!move_uploaded_file($file['tmp_name'], $ruta)) {
            throw new Exception("No se pudo mover el archivo PDF");
        }

        return $pdf_filename;
    } catch (Exception $e) {
        error_log("Error guardando PDF: " . $e->getMessage());
        return null;
    }
}

function existeTabla($conn, $tabla) {
    try {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $tabla);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $existe = $resultado && $resultado->num_rows > 0;
        $stmt->close();
        return $existe;
    } catch (Throwable $e) {
        error_log('Error validando tabla ' . $tabla . ': ' . $e->getMessage());
        return false;
    }
}

function columnaExiste($conn, $tabla, $columna) {
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

/**
 * Obtiene los elementos permitidos de una plantilla genérica.
 * Si la tabla de personalizados no existe, se usan solo los elementos base.
 */
function obtenerElementosPermitidos($conn, $usuario_id) {
    $elementosEstandar = [
        'Elemento plantilla A',
        'Elemento plantilla B',
        'Elemento plantilla C',
        'Elemento plantilla D',
        'Elemento plantilla E'
    ];

    if (!existeTabla($conn, 'elementos_permitidos')) {
        return $elementosEstandar;
    }

    $elementosPersonalizados = [];
    try {
        $stmt = $conn->prepare("SELECT nombre_elemento FROM elementos_permitidos ORDER BY nombre_elemento ASC");
        if (!$stmt) {
            return $elementosEstandar;
        }
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($row = $resultado->fetch_assoc()) {
            $elementosPersonalizados[] = $row['nombre_elemento'];
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('Error consultando elementos_permitidos: ' . $e->getMessage());
        return $elementosEstandar;
    }

    return array_values(array_unique(array_merge($elementosEstandar, $elementosPersonalizados)));
}

// ============================================================
// PROCESAMIENTO DE FORMULARIO
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar datos requeridos
    $empleado_id = filter_var($_POST['empleado_id'] ?? null, FILTER_VALIDATE_INT);
    $fecha_entrega = $_POST['fecha_entrega'] ?? '';
    $elementos = array_filter($_POST['elementos'] ?? []);
    $observaciones = trim($_POST['observaciones'] ?? '');
    $sst_id = filter_var($_POST['sst_id'] ?? null, FILTER_VALIDATE_INT);
    $sst_id = ($sst_id === false) ? 0 : (int)$sst_id;

    // Validaciones básicas
    if (!$empleado_id) {
        $errorMessage = "Debe seleccionar un empleado válido.";
    } elseif (empty($fecha_entrega)) {
        $errorMessage = "La fecha de entrega es obligatoria.";
    } elseif (empty($elementos) && empty($observaciones) && empty($_FILES['archivo_pdf']['name'])) {
        $errorMessage = "Debe seleccionar al menos un ítem de plantilla, observaciones o subir un PDF.";
    } else {
        // Validar firmas
        $firma_empleado = $_POST['firma_empleado'] ?? '';
        $firma_responsable = $_POST['firma_responsable'] ?? '';
        $firma_sst = $_POST['firma_sst'] ?? '';
        $soporta_firma_responsable = columnaExiste($conn, 'entregas', 'firma_responsable');
        $soporta_firma_sst = columnaExiste($conn, 'entregas', 'firma_sst');
        $soporta_usuario_id = columnaExiste($conn, 'entregas', 'usuario_id');

        if (empty($firma_empleado)) {
            $errorMessage = "La firma del empleado es obligatoria.";
        } else {
            // Guardar firmas
            $archivo_firma_empleado = guardarFirma($firma_empleado);
            $archivo_firma_responsable = $soporta_firma_responsable && !empty($firma_responsable)
                ? guardarFirma($firma_responsable)
                : null;
            $archivo_firma_sst = $soporta_firma_sst && !empty($firma_sst)
                ? guardarFirma($firma_sst)
                : null;

            if (!$archivo_firma_empleado) {
                $errorMessage = "Error al guardar la firma del empleado. Por favor, intente de nuevo.";
            } else {
                // Guardar entrega en base de datos
                $conn->begin_transaction();

                try {
                    $pdf_filename = null;
                    $numero_dotacion = trim((string)($_POST['numero_dotacion'] ?? ''));

                    $campos = [
                        'empleado_id',
                        'fecha_entrega',
                        'numero_dotacion',
                        'responsable_entrega',
                        'sst_id',
                        'firma_empleado',
                        'usuario_id'
                    ];
                    $valores = ['?', '?', '?', '?', '?', '?', '?'];
                    $tipos = 'issiisi';

                    if ($soporta_firma_responsable) {
                        $campos[] = 'firma_responsable';
                        $valores[] = '?';
                        $tipos .= 's';
                    }

                    if ($soporta_firma_sst) {
                        $campos[] = 'firma_sst';
                        $valores[] = '?';
                        $tipos .= 's';
                    }

                    $campos[] = 'pdf_file';
                    $valores[] = 'NULL';

                    $stmt = $conn->prepare(
                        "INSERT INTO entregas (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $valores) . ")"
                    );

                    if (!$stmt) {
                        throw new Exception("Error en preparación de consulta: " . $conn->error);
                    }

                    $parametros = [
                        &$empleado_id,
                        &$fecha_entrega,
                        &$numero_dotacion,
                        &$responsable_entrega,
                        &$sst_id,
                        &$archivo_firma_empleado,
                        &$usuario_id
                    ];

                    if ($soporta_firma_responsable) {
                        $parametros[] = &$archivo_firma_responsable;
                    }

                    if ($soporta_firma_sst) {
                        $parametros[] = &$archivo_firma_sst;
                    }

                    $stmt->bind_param($tipos, ...$parametros);

                    if (!$stmt->execute()) {
                        throw new Exception("Error al insertar entrega: " . $stmt->error);
                    }

                    $entrega_id = $stmt->insert_id;
                    $stmt->close();

                    // Guardar PDF si se proporciona
                    if (!empty($_FILES['archivo_pdf']['name'])) {
                        $pdf_filename = guardarPDF($_FILES['archivo_pdf']);
                        if ($pdf_filename) {
                            $stmt_pdf = $conn->prepare("UPDATE entregas SET pdf_file = ? WHERE id = ?");
                            if (!$stmt_pdf->execute([$pdf_filename, $entrega_id])) {
                                error_log("Error al actualizar PDF: " . $stmt_pdf->error);
                            }
                            $stmt_pdf->close();
                        }
                    }

                    // Insertar detalles de entrega
                    $stmt_detalle = $conn->prepare(
                        "INSERT INTO entregas_detalle (entrega_id, elemento, observacion) VALUES (?, ?, ?)"
                    );

                    if (!$stmt_detalle) {
                        throw new Exception("Error en preparación de detalles: " . $conn->error);
                    }

                    $elementosPermitidos = obtenerElementosPermitidos($conn, $usuario_id);

                    // Insertar elementos seleccionados
                    if (!empty($elementos)) {
                        foreach ($elementos as $elemento_data) {
                            // Parsear elemento y cantidad (formato: "elemento|cantidad")
                            $partes = explode('|', $elemento_data);
                            $elemento = trim($partes[0]);
                            $cantidad = isset($partes[1]) ? intval($partes[1]) : 1;

                            // Verificar que el elemento base esté permitido
                            if (!in_array($elemento, $elementosPermitidos)) {
                                continue;
                            }

                            // Construir texto final que se guarda en la columna 'elemento'
                            $elemento_guardar = $elemento . ' Cantidad ' . $cantidad;

                            if (!$stmt_detalle->bind_param("iss", $entrega_id, $elemento_guardar, $observaciones)) {
                                throw new Exception("Error al asociar parámetros: " . $stmt_detalle->error);
                            }

                            if (!$stmt_detalle->execute()) {
                                throw new Exception("Error al insertar detalle: " . $stmt_detalle->error);
                            }
                        }
                    }

                    // Insertar "otros" si se proporciona (sin cantidad adicional)
                    $otros_texto = trim($_POST['otros_texto'] ?? '');
                    if (!empty($otros_texto)) {
                        if (!$stmt_detalle->bind_param("iss", $entrega_id, $otros_texto, $observaciones)) {
                            throw new Exception("Error al asociar parámetros (otros): " . $stmt_detalle->error);
                        }

                        if (!$stmt_detalle->execute()) {
                            throw new Exception("Error al insertar otro: " . $stmt_detalle->error);
                        }
                    }

                    // Insertar observaciones como fila separada si no hay elementos ni "otros"
                    if (empty($elementos) && empty($otros_texto) && !empty($observaciones)) {
                        $elemento_vacio = 'Observaciones';
                        if (!$stmt_detalle->bind_param("iss", $entrega_id, $elemento_vacio, $observaciones)) {
                            throw new Exception("Error al asociar parámetros (observaciones): " . $stmt_detalle->error);
                        }

                        if (!$stmt_detalle->execute()) {
                            throw new Exception("Error al insertar observaciones: " . $stmt_detalle->error);
                        }
                    }

                    $stmt_detalle->close();

                    $conn->commit();
                    $successMessage = "Entrega registrada correctamente ✅";

                    // Limpiar formulario
                    $_POST = [];

                } catch (Exception $e) {
                    $conn->rollback();
                    $errorMessage = "Error al guardar la entrega: " . $e->getMessage();
                    error_log("Error en guardado de entrega: " . $e->getMessage());
                }
            }
        }
    }
}

// Obtener datos para el formulario
$stmt_user = $conn->prepare("SELECT nombre FROM usuarios WHERE id = ?");
$stmt_user->bind_param("i", $usuario_id);
$stmt_user->execute();
$stmt_user->bind_result($nombre_responsable);
$stmt_user->fetch();
$stmt_user->close();

$empleados = $conn->query("SELECT id, nombre FROM empleados ORDER BY nombre ASC");
$elementosPermitidos = obtenerElementosPermitidos($conn, $usuario_id);

// Preparar valores para re-popular el formulario en caso de error
$empleado_nombre_post = '';
if (!empty($_POST['empleado_id'])) {
    $tmp_id = intval($_POST['empleado_id']);
    $stmt_e = $conn->prepare("SELECT nombre FROM empleados WHERE id = ?");
    if ($stmt_e) {
        $stmt_e->bind_param('i', $tmp_id);
        $stmt_e->execute();
        $stmt_e->bind_result($tmp_nombre);
        if ($stmt_e->fetch()) {
            $empleado_nombre_post = $tmp_nombre;
        }
        $stmt_e->close();
    }
}

$elementos_seleccionados_map = (isset($_POST['elementos']) && is_array($_POST['elementos'])) ? [] : [];
if (!empty($_POST['elementos']) && is_array($_POST['elementos'])) {
    foreach ($_POST['elementos'] as $ed) {
        $parts = explode('|', $ed);
        $ename = trim($parts[0]);
        $qty = isset($parts[1]) ? intval($parts[1]) : 1;
        if ($ename !== '') $elementos_seleccionados_map[$ename] = $qty;
    }
}

$elementos_seleccionados_json = json_encode($elementos_seleccionados_map, JSON_UNESCAPED_UNICODE);

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid formulario-entrega">
    <!-- Mensajes de estado -->
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <h2 class="titulo-formulario">Registro de Entregas</h2>

    <form method="POST" enctype="multipart/form-data" id="formularioEntrega">
        <!-- Empleado y Fecha -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <label class="form-label">Empleado que recibe *</label>
                <div style="position: relative;">
                          <input type="text" class="form-control" id="empleadoInput" 
                              placeholder="Buscar y seleccionar empleado..." 
                              autocomplete="off" required
                              value="<?= htmlspecialchars($empleado_nombre_post ?? '') ?>">
                          <input type="hidden" name="empleado_id" id="empleadoIdHidden" required value="<?= intval($_POST['empleado_id'] ?? 0) ?>">
                    <div id="empleadoDropdown" class="dropdown-list"></div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label">Fecha de entrega *</label>
                <input type="date" name="fecha_entrega" class="form-control" required value="<?= htmlspecialchars($_POST['fecha_entrega'] ?? '') ?>">
            </div>
        </div>

        <!-- Ítems de plantilla -->
        <div class="mb-4">
            <label class="form-label">Ítems de plantilla *</label>
            <p class="form-text small">Seleccione uno o más ítems de plantilla o complete observaciones. Al menos una de estas opciones es obligatoria.</p>

            <div class="row g-2 mb-3">
                <div class="col-12 col-md-6 col-lg-8">
                    <input type="text" id="buscarElemento" class="form-control" 
                           placeholder="Buscar ítem para filtrar la lista..." autocomplete="off">
                    <div id="mensajeElemento" class="mensaje-elemento mt-2"></div>
                </div>
                <div class="col-12 col-md-6 col-lg-4">
                    <button type="button" id="btnAgregarElemento" class="btn btn-success w-100">
                        <i class="bi bi-plus-circle"></i> Agregar nuevo
                    </button>
                </div>
            </div>

            <div id="contenedorElementos" class="contenedor-elementos" style="border: 1px solid #dee2e6; border-radius: 0.375rem; max-height: 400px; overflow-y: auto;">
                <!-- Los elementos se cargarán aquí dinámicamente -->
            </div>
            <small class="form-text text-muted d-block mt-2">
                ✓ Seleccione los ítems de plantilla y especifique la cantidad de cada uno.
            </small>

            <!-- Campo hidden para pasar datos al servidor -->
            <input type="hidden" id="elementosSeleccionados" name="elementos[]">
        </div>

        <!-- Información Adicional -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <label class="form-label">Número de dotación (opcional)</label>
                  <input type="text" name="numero_dotacion" class="form-control" 
                      placeholder="Ej: DOT-2024-001" value="<?= htmlspecialchars($_POST['numero_dotacion'] ?? '') ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Otros elemento (opcional)</label>
                  <input type="text" name="otros_texto" class="form-control" 
                      placeholder="Otro elemento no listado" value="<?= htmlspecialchars($_POST['otros_texto'] ?? '') ?>">
            </div>
        </div>

        <!-- Observaciones -->
        <div class="mb-4">
            <label class="form-label">Observaciones (opcional)</label>
            <textarea name="observaciones" class="form-control" rows="3" 
                      placeholder="Notas adicionales sobre la entrega..."><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
        </div>

        <!-- PDF -->
        <div class="mb-4">
            <label class="form-label">Archivo PDF (opcional, máx. 5 MB)</label>
            <input type="file" name="archivo_pdf" class="form-control" accept="application/pdf">
            <small class="form-text text-muted d-block mt-2">
                Formatos soportados: PDF
            </small>
        </div>

        <!-- Firmas -->
        <div class="row g-3 mb-4">
            <!-- Firma Empleado -->
            <div class="col-12 col-md-4">
                <label class="form-label fw-bold">Firma del Empleado *</label>
                <canvas id="firmaEmpleado" class="border border-2 d-block w-100 bg-light" 
                        style="height: 180px; touch-action: none; cursor: crosshair;"></canvas>
                <input type="hidden" name="firma_empleado" id="firma_empleado">
                <button type="button" class="btn btn-sm btn-secondary mt-2 w-100" 
                        onclick="limpiarCanvas('firmaEmpleado')">
                    Limpiar firma
                </button>
            </div>

            <!-- Firma Responsable -->
            <div class="col-12 col-md-4">
                <label class="form-label fw-bold">Firma del Responsable *</label>
                <div class="alert alert-info p-2 small">
                    <strong><?= htmlspecialchars($nombre_responsable) ?></strong>
                </div>
                <canvas id="firmaResponsable" class="border border-2 d-block w-100 bg-light" 
                        style="height: 180px; touch-action: none; cursor: crosshair;"></canvas>
                <input type="hidden" name="firma_responsable" id="firma_responsable">
                <button type="button" class="btn btn-sm btn-secondary mt-2 w-100" 
                        onclick="limpiarCanvas('firmaResponsable')">
                    Limpiar firma
                </button>
            </div>

            <!-- Firma SST -->
            <div class="col-12 col-md-4">
                <label class="form-label fw-bold">Representante SST</label>
                <select name="sst_id" id="sstSelect" class="form-select mb-3">
                    <option value="0" <?= (!isset($_POST['sst_id']) || intval($_POST['sst_id']) === 0) ? 'selected' : '' ?>>Representante genérico</option>
                </select>
                <label class="form-label small">Firma SST</label>
                <canvas id="firmaSst" class="border border-2 d-block w-100 bg-light" 
                        style="height: 180px; touch-action: none; cursor: crosshair;"></canvas>
                <input type="hidden" name="firma_sst" id="firma_sst">
                <button type="button" class="btn btn-sm btn-secondary mt-2 w-100" 
                        onclick="limpiarCanvas('firmaSst')">
                    Limpiar firma
                </button>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="d-grid gap-2 gap-md-0 d-md-flex justify-content-md-between mb-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle"></i> Guardar entrega
            </button>
            <button type="reset" class="btn btn-secondary btn-lg">
                <i class="bi bi-arrow-counterclockwise"></i> Limpiar formulario
            </button>
        </div>
    </form>
</div>

<!-- Scripts -->
<script>
// ============================================================
// UTILIDADES GENERALES
// ============================================================

/**
 * Muestra alerta visual
 */
function mostrarAlerta(mensaje, tipo = 'info') {
    const alertClass = `alert alert-${tipo} alert-dismissible fade show`;
    const alerta = `
        <div class="${alertClass}" role="alert">
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#formularioEntrega').before(alerta);
}

/**
 * Limpia canvas de firma
 */
function limpiarCanvas(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

/**
 * Configura canvas para firma
 */
function configurarCanvasFirma(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    // Redimensionar canvas al tamaño real
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;

    const ctx = canvas.getContext('2d');
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = 2;

    let dibujando = false;
    let ultimaX = 0;
    let ultimaY = 0;

    // Funciones auxiliares
    const getPos = (e) => {
        const rect = canvas.getBoundingClientRect();
        if (e.touches) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        }
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    };

    const iniciarDibujo = (e) => {
        dibujando = true;
        const pos = getPos(e);
        ultimaX = pos.x;
        ultimaY = pos.y;
        ctx.beginPath();
        ctx.moveTo(ultimaX, ultimaY);
    };

    const dibujar = (e) => {
        if (!dibujando) return;
        e.preventDefault();
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    };

    const finalizarDibujo = () => {
        dibujando = false;
        ctx.closePath();
    };

    // Eventos ratón
    canvas.addEventListener('mousedown', iniciarDibujo);
    canvas.addEventListener('mousemove', dibujar);
    canvas.addEventListener('mouseup', finalizarDibujo);
    canvas.addEventListener('mouseout', finalizarDibujo);

    // Eventos táctiles
    canvas.addEventListener('touchstart', iniciarDibujo, { passive: false });
    canvas.addEventListener('touchmove', dibujar, { passive: false });
    canvas.addEventListener('touchend', finalizarDibujo);
}

/**
 * Guarda firmas antes de enviar formulario
 */
function guardarFirmas() {
    ['firmaEmpleado', 'firmaResponsable', 'firmaSst'].forEach(canvasId => {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return; // Skip if canvas doesn't exist
        
        let inputId;
        if (canvasId === 'firmaEmpleado') inputId = 'firma_empleado';
        else if (canvasId === 'firmaResponsable') inputId = 'firma_responsable';
        else if (canvasId === 'firmaSst') inputId = 'firma_sst';
        
        const dataURL = canvas.toDataURL('image/png');
        document.getElementById(inputId).value = dataURL;
    });
    return true;
}

/**
 * Valida el formulario antes de enviar
 */
function validarFormulario() {
    const empleadoId = document.getElementById('empleadoIdHidden').value;
    const fecha = document.querySelector('input[name="fecha_entrega"]').value;
    const observaciones = document.querySelector('textarea[name="observaciones"]').value.trim();
    const archivoPdf = document.querySelector('input[name="archivo_pdf"]').files.length > 0;

    if (!empleadoId) {
        mostrarAlerta('Debe seleccionar un empleado válido', 'warning');
        return false;
    }

    if (!fecha) {
        mostrarAlerta('La fecha de entrega es obligatoria', 'warning');
        return false;
    }

    const elementosSeleccionados = document.querySelectorAll('.elemento-item input[type="checkbox"]:checked').length;
    if (elementosSeleccionados === 0 && observaciones === '' && !archivoPdf) {
        mostrarAlerta('Debe seleccionar al menos un elemento entregado, observaciones o subir un PDF', 'warning');
        return false;
    }

    return true;
}

// ============================================================
// INICIALIZACIÓN
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // Configurar canvas de firmas
    configurarCanvasFirma('firmaEmpleado');
    configurarCanvasFirma('firmaResponsable');
    configurarCanvasFirma('firmaSst');

    // Envío del formulario
    document.getElementById('formularioEntrega').addEventListener('submit', function(e) {
        // Guardar firmas primero para asegurarnos que los campos hidden estén llenos
        try {
            guardarFirmas();
        } catch (err) {
            console.error('Error guardando firmas en cliente:', err);
        }

        if (!validarFormulario()) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<!-- Script de búsqueda de empleados -->
<script>
$(document).ready(function() {
    const $inputEmpleado = $('#empleadoInput');
    const $hiddenEmpleado = $('#empleadoIdHidden');
    const $dropdown = $('#empleadoDropdown');

    // Cargar empleados desde PHP
    const empleados = <?= json_encode(
        array_map(fn($emp) => ['id' => $emp['id'], 'nombre' => $emp['nombre']], 
                  iterator_to_array($empleados)), 
        JSON_UNESCAPED_UNICODE
    ) ?>;

    const removerTildes = (str) => {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    };

    const mostrarEmpleados = (filtro = '') => {
        $dropdown.empty();

        let resultados = empleados;
        if (filtro) {
            const filtroLimpio = removerTildes(filtro);
            resultados = empleados.filter(emp => 
                removerTildes(emp.nombre).includes(filtroLimpio)
            );
        }

        if (resultados.length === 0) {
            $dropdown.html('<div class="p-2 text-muted">No hay resultados</div>');
            $hiddenEmpleado.val('');
            return;
        }

        resultados.forEach(emp => {
            const item = $('<div></div>')
                .addClass('dropdown-item p-2')
                .text(emp.nombre)
                .on('click', function() {
                    $inputEmpleado.val(emp.nombre);
                    $hiddenEmpleado.val(emp.id);
                    $dropdown.hide();
                });
            $dropdown.append(item);
        });

        $dropdown.show();
    };

    $inputEmpleado.on('focus click input', function() {
        mostrarEmpleados($(this).val());
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#empleadoInput, #empleadoDropdown').length) {
            $dropdown.hide();
        }
    });
});
</script>

<!-- Script de búsqueda y selección de elementos -->
<script>
$(document).ready(function() {
    const $inputBuscar = $('#buscarElemento');
    const $btnAgregar = $('#btnAgregarElemento');
    const $contenedorElementos = $('#contenedorElementos');

    // Elementos disponibles (desde PHP + BD)
    window.elementosPermitidos = <?= json_encode($elementosPermitidos, JSON_UNESCAPED_UNICODE) ?>;
    // Restaurar selección previa desde POST si existe
    window.elementosSeleccionados = <?= $elementos_seleccionados_json ?? '{}' ?>;

    // Cargar elementos personalizados de BD al inicio
    $.ajax({
        url: '../includes/elementos_handler.php',
        method: 'POST',
        data: { accion: 'obtener' },
        dataType: 'json',
        success: function(data) {
            if (data.elementos) {
                // Agregar elementos no duplicados
                data.elementos.forEach(elemento => {
                    if (!window.elementosPermitidos.includes(elemento)) {
                        window.elementosPermitidos.push(elemento);
                    }
                });
            }
            // Renderizar elementos una sola vez
            renderizarElementos();
        },
        error: function(err) {
            console.error('Error cargando elementos de BD:', err);
            // Igualmente renderizar con elementos estándar
            renderizarElementos();
        }
    });

    /**
     * Renderiza lista de elementos con checkboxes y campos de cantidad
     */
    function renderizarElementos() {
        $contenedorElementos.empty();
        const elementosOrdenados = window.elementosPermitidos.sort();

        elementosOrdenados.forEach((elemento, index) => {
            const id = `elemento_${index}`;
            const html = `
                <div class="elemento-item row align-items-center p-2 border-bottom">
                    <div class="col-12 col-md-7">
                        <div class="form-check">
                            <input class="form-check-input checkbox-elemento" type="checkbox" 
                                   id="${id}" data-elemento="${htmlEscape(elemento)}" value="${htmlEscape(elemento)}">
                            <label class="form-check-label" for="${id}">
                                ${htmlEscape(elemento)}
                            </label>
                        </div>
                    </div>
                    <div class="col-12 col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Cantidad</span>
                            <input type="number" class="form-control cantidad-elemento" 
                                   min="1" value="1" data-elemento="${htmlEscape(elemento)}" 
                                   disabled>
                        </div>
                    </div>
                </div>
            `;
            $contenedorElementos.append(html);
        });

        // Si hay elementos seleccionados desde POST, marcar checkboxes y cantidades
        try {
            Object.entries(window.elementosSeleccionados || {}).forEach(([elem, qty]) => {
                const escaped = htmlEscape(elem);
                const $checkbox = $(`.checkbox-elemento[data-elemento="${escaped}"]`);
                const $cantidadInput = $(`input.cantidad-elemento[data-elemento="${escaped}"]`);
                if ($checkbox.length) {
                    $checkbox.prop('checked', true);
                    $cantidadInput.prop('disabled', false);
                    $cantidadInput.val(parseInt(qty) || 1);
                }
            });
            // Actualizar los inputs hidden según la selección restaurada
            actualizarCampoOculto();
        } catch (e) {
            console.error('Error restaurando selección de elementos:', e);
        }

        // Event listeners para checkboxes
        $(document).on('change', '.checkbox-elemento', function() {
            const $checkbox = $(this);
            const elemento = $checkbox.data('elemento');
            const $cantidadInput = $(`input.cantidad-elemento[data-elemento="${htmlEscape(elemento)}"]`);

            if ($checkbox.is(':checked')) {
                $cantidadInput.prop('disabled', false);
                window.elementosSeleccionados[elemento] = parseInt($cantidadInput.val()) || 1;
            } else {
                $cantidadInput.prop('disabled', true);
                delete window.elementosSeleccionados[elemento];
            }
            actualizarCampoOculto();
        });

        // Event listeners para campos de cantidad
        $(document).on('change', '.cantidad-elemento:not(:disabled)', function() {
            const $input = $(this);
            const elemento = $input.data('elemento');
            const cantidad = Math.max(1, parseInt($input.val()) || 1);
            $input.val(cantidad);
            window.elementosSeleccionados[elemento] = cantidad;
            actualizarCampoOculto();
        });
    }

    /**
     * Actualiza campo oculto con datos de elementos seleccionados
     */
    function actualizarCampoOculto() {
        // Crear inputs hidden individuales para cada elemento
        $('#formularioEntrega').find('input[name="elementos[]"]').not('#elementosSeleccionados').remove();
        Object.entries(window.elementosSeleccionados).forEach(([elemento, cantidad]) => {
            const $input = $('<input>').attr({
                type: 'hidden',
                name: 'elementos[]',
                value: `${elemento}|${cantidad}`
            });
            $('#formularioEntrega').append($input);
        });
    }

    /**
     * Escapa caracteres HTML
     */
    function htmlEscape(str) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return str.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Filtra elementos mientras se escribe
     */
    $inputBuscar.on('input', function() {
        const termino = $(this).val().toLowerCase();
        $('.elemento-item').each(function() {
            const texto = $(this).find('.form-check-label').text().toLowerCase();
            $(this).toggle(texto.includes(termino));
        });
    });

    /**
     * Agregar nuevo elemento
     */
    $btnAgregar.on('click', function() {
        const nuevoElemento = $inputBuscar.val().trim();

        if (!nuevoElemento) {
            mostrarMensajeElemento('Por favor, ingrese un nombre para el nuevo elemento', 'warning');
            return;
        }

        // Capitalizar
        const elementoFormato = nuevoElemento.charAt(0).toUpperCase() + nuevoElemento.slice(1).toLowerCase();

        // Verificar duplicado
        if (window.elementosPermitidos.includes(elementoFormato)) {
            mostrarMensajeElemento(`El elemento "${elementoFormato}" ya existe`, 'info');
            return;
        }

        // Guardar en BD
        $.ajax({
            url: '../includes/elementos_handler.php',
            method: 'POST',
            data: {
                accion: 'guardar',
                nombre: elementoFormato
            },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    mostrarMensajeElemento(data.error, 'danger');
                    return;
                }

                // Agregar a lista local
                window.elementosPermitidos.push(elementoFormato);

                // Re-renderizar elementos
                renderizarElementos();

                // Limpiar input
                $inputBuscar.val('');

                mostrarMensajeElemento(`Elemento "${elementoFormato}" agregado correctamente ✓`, 'success');
            },
            error: function() {
                mostrarMensajeElemento('Error al guardar el elemento', 'danger');
            }
        });
    });

    /**
     * Muestra mensaje temporal
     */
    function mostrarMensajeElemento(mensaje, tipo = 'info') {
        const $msg = $('#mensajeElemento');
        $msg.attr('class', `mensaje-elemento alert alert-${tipo}`);
        $msg.text(mensaje);
        $msg.show();

        setTimeout(() => {
            $msg.fadeOut();
        }, 4000);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
