<link rel="stylesheet" href="/../assets/css/historial.css">

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_SESSION['filtroTexto'] = $_GET['buscador'] ?? $_SESSION['filtroTexto'] ?? '';
        $_SESSION['filtroCargo'] = $_GET['filtroCargo'] ?? $_SESSION['filtroCargo'] ?? '';
        $_SESSION['filtroArea'] = $_GET['filtroArea'] ?? $_SESSION['filtroArea'] ?? '';
        $_SESSION['filtroFecha'] = $_GET['filtroFecha'] ?? $_SESSION['filtroFecha'] ?? '';
        $_SESSION['filtroMes'] = $_GET['filtroMes'] ?? $_SESSION['filtroMes'] ?? 0;
        $_SESSION['filtroAnio'] = $_GET['filtroAnio'] ?? $_SESSION['filtroAnio'] ?? 0;
    }
}

require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit();
}



$por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$filtroTexto = isset($_GET['buscador']) ? trim($_GET['buscador']) : '';
$filtroCargo = isset($_GET['filtroCargo']) ? trim($_GET['filtroCargo']) : '';
$filtroArea = isset($_GET['filtroArea']) ? trim($_GET['filtroArea']) : '';
$filtroFecha = isset($_GET['filtroFecha']) ? trim($_GET['filtroFecha']) : '';
$filtroMes = isset($_GET['filtroMes']) ? (int)$_GET['filtroMes'] : 0;
$filtroAnio = isset($_GET['filtroAnio']) ? (int)$_GET['filtroAnio'] : 0;



$where = [];
$params = [];
$types = '';

if ($filtroTexto !== '') {
    $where[] = '(empleados.nombre LIKE ? OR empleados.cedula LIKE ?)';
    $params[] = "%$filtroTexto%";
    $params[] = "%$filtroTexto%";
    $types .= 'ss';
}
if ($filtroCargo !== '') {
    $where[] = 'empleados.cargo = ?';
    $params[] = $filtroCargo;
    $types .= 's';
}
if ($filtroArea !== '') {
    $where[] = 'empleados.area = ?';
    $params[] = $filtroArea;
    $types .= 's';
}
if ($filtroFecha !== ''){
    $where[] = 'DATE(entregas.fecha_entrega) = ?';
    $params[] = $filtroFecha;
    $types .= 's';
}
if ($filtroMes > 0 && $filtroAnio > 0) {
    // Filtrar por mes y año
    $where[] = 'MONTH(entregas.fecha_entrega) = ? AND YEAR(entregas.fecha_entrega) = ?';
    $params[] = $filtroMes;
    $params[] = $filtroAnio;
    $types .= 'ii';
} elseif ($filtroMes > 0) {
    // Si solo el mes está presente, filtramos por mes
    $where[] = 'MONTH(entregas.fecha_entrega) = ?';
    $params[] = $filtroMes;
    $types .= 'i';
} elseif ($filtroAnio > 0) {
    // Si solo el año está presente, filtramos por año
    $where[] = 'YEAR(entregas.fecha_entrega) = ?';
    $params[] = $filtroAnio;
    $types .= 'i';
}


$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sqlCount = "SELECT COUNT(DISTINCT empleados.id) AS total
FROM empleados
LEFT JOIN entregas ON entregas.empleado_id = empleados.id
$whereSQL";

$stmtCount = $conn->prepare($sqlCount);
if ($params) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$resCount = $stmtCount->get_result();
$total_filas = $resCount->fetch_assoc()['total'];
$stmtCount->close();
$total_paginas = ceil($total_filas / $por_pagina);

$offset = ($pagina_actual - 1) * $por_pagina;

$sql = "SELECT DISTINCT empleados.id, empleados.nombre, empleados.cedula, empleados.cargo, empleados.area
FROM empleados
LEFT JOIN entregas ON entregas.empleado_id = empleados.id
$whereSQL
ORDER BY empleados.nombre ASC
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if ($params) {
    $types2 = $types . 'ii';
    $bindParams = $params;
    $bindParams[] = $por_pagina;
    $bindParams[] = $offset;
    $stmt->bind_param($types2, ...$bindParams);
} else {
    $stmt->bind_param('ii', $por_pagina, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Consultar entregas incompletas (sin entregas_detalle O sin pdf_file)
$sqlIncompletas = "
    SELECT e.id, e.empleado_id, emp.nombre AS empleado_nombre, 
           COALESCE(COUNT(ed.id), 0) AS tiene_detalle,
           IF(e.pdf_file IS NULL OR e.pdf_file = '', 0, 1) AS tiene_pdf
    FROM entregas e
    INNER JOIN empleados emp ON emp.id = e.empleado_id
    LEFT JOIN entregas_detalle ed ON ed.entrega_id = e.id
    GROUP BY e.id, e.empleado_id, emp.nombre, e.pdf_file
    HAVING (tiene_detalle = 0 AND tiene_pdf = 0)
";

$resultIncompletas = $conn->query($sqlIncompletas);
$entregasIncompletas = [];
while ($incomp = $resultIncompletas->fetch_assoc()) {
    $entregasIncompletas[] = $incomp;
}

$pagina_url = "index.php?page=historial";

include __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid">
    <h2 class="mt-4">Empleados</h2>

    <?php if (!empty($entregasIncompletas)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>⚠️ Advertencia: Entregas incompletas detectadas</strong><br>
            <small>Se encontraron <strong><?= count($entregasIncompletas) ?></strong> entrega(s) sin detalles ni PDF:</small>
            <ul class="mb-0 mt-2" style="font-size: 0.9rem;">
                <?php foreach ($entregasIncompletas as $inc): ?>
                    <li>Entrega #<?= htmlspecialchars($inc['id']) ?> - <?= htmlspecialchars($inc['empleado_nombre']) ?></li>
                <?php endforeach; ?>
            </ul>
            <small class="mt-2" style="display: block;">Verifique que cada entrega tenga al menos detalles o PDF.</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-3">
        <form id="formFiltros" method="get" class="row g-2">
            <input type="hidden" name="page" value="historial">
            <input type="hidden" name="pagina" value="1" id="paginaInput">
            <div class="col-md-4 mb-2">
                <input type="text" name="buscador" id="buscador" class="form-control" placeholder="Buscar por nombre o cédula..." value="<?= htmlspecialchars($filtroTexto) ?>">
            </div>
            <div class="col-md-4 mb-2">
                <select name="filtroCargo" id="filtroCargo" class="form-control">
                    <option value="">Filtrar por cargo</option>
                    <?php
                    $cargos = $conn->query("SELECT DISTINCT cargo FROM empleados WHERE cargo IS NOT NULL AND cargo <> ''");
                    while ($c = $cargos->fetch_assoc()) {
                        $selected = ($filtroCargo === $c['cargo']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($c['cargo']) . "' $selected>" . htmlspecialchars($c['cargo']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <select name="filtroArea" id="filtroArea" class="form-control">
                    <option value="">Filtrar por área</option>
                    <?php
                    $areas = $conn->query("SELECT DISTINCT area FROM empleados WHERE area IS NOT NULL AND area <> ''");
                    while ($a = $areas->fetch_assoc()) {
                        $selected = ($filtroArea === $a['area']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($a['area']) . "' $selected>" . htmlspecialchars($a['area']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4 mb-2">
                <input type="date" name="filtroFecha" id="filtroFecha" class="form-control" value="<?= htmlspecialchars($filtroFecha)?>">
            </div>
            <div class="col-md-2 mb-2">
                <select name="filtroMes" id="filtroMes" class="form-control">
                    <option value="0">Filtrar por mes</option>
                    <?php 
                    $meses = [
                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                    ];
                    foreach ($meses as $num => $nombre) {
                        $sel = ($filtroMes == $num) ? 'selected' : '';
                        echo "<option value='$num' $sel>$nombre</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="col-md-2 mb-2">
                <select name="filtroAnio" id="filtroAnio" class="form-control">
                    <option value="0">Filtrar por año</option>
                    <?php 
                    $anioActual = date('Y');
                    for ($a = $anioActual; $a >= $anioActual - 5; $a--) {
                        $sel = ($filtroAnio == $a) ? 'selected' : '';
                        echo "<option value='$a' $sel>$a</option>";
                    }
                    ?>
                </select>
            </div>

        </form>
    </div>

    <table class="table table-striped table-hover" id="tablaEmpleados">
        <thead class="table-dark">
            <tr>
                <th>Nombre</th>
                <th>Cédula</th>
                <th>Cargo</th>
                <th>Área</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): 
            // Verificar si este empleado tiene entregas incompletas
            $tieneIncompletas = array_filter($entregasIncompletas, function($inc) use ($row) {
                return $inc['empleado_id'] == $row['id'];
            });
            $badgeClass = !empty($tieneIncompletas) ? 'badge bg-warning text-dark' : '';
            $badgeText = !empty($tieneIncompletas) ? '⚠️' : '';
        ?>
            <tr <?= !empty($tieneIncompletas) ? 'style="background-color: #fff3cd;"' : '' ?>>
                <td><?= htmlspecialchars($row['nombre']) ?> <span class="<?= $badgeClass ?>"><?= $badgeText ?></span></td>
                <td><?= htmlspecialchars($row['cedula']) ?></td>
                <td><?= htmlspecialchars($row['cargo']) ?></td>
                <td><?= htmlspecialchars($row['area']) ?></td>
                <td>
                    <a href="pages/historial.php?empleado_id=<?= urlencode($row['id']) ?>&buscador=<?= urlencode($filtroTexto) ?>&filtroCargo=<?= urlencode($filtroCargo) ?>&filtroArea=<?= urlencode($filtroArea) ?>&filtroFecha=<?= urlencode($filtroFecha) ?>&filtroMes=<?= urlencode($filtroMes) ?>&filtroAnio=<?= urlencode($filtroAnio) ?>" class="btn-ver-historial">
                        Ver historial
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <nav id="paginador">
        <ul class="pagination justify-content-center">
            <?php
            $filtros_url = '';
            if ($filtroTexto !== '') $filtros_url .= '&buscador=' . urlencode($filtroTexto);
            if ($filtroCargo !== '') $filtros_url .= '&filtroCargo=' . urlencode($filtroCargo);
            if ($filtroArea !== '') $filtros_url .= '&filtroArea=' . urlencode($filtroArea);
            if ($filtroFecha !== '') $filtros_url .= '&filtroFecha=' . urlencode($filtroFecha);
            if ($filtroMes > 0) $filtros_url .= '&filtroMes=' . urlencode($filtroMes);
            if ($filtroAnio > 0) $filtros_url .= '&filtroAnio=' . urlencode($filtroAnio);


            echo '<li class="page-item ' . (($pagina_actual <= 1) ? 'disabled' : '') . '"><a class="page-link" href="' . $pagina_url . '&pagina=' . ($pagina_actual - 1) . '&buscador=' . urlencode($filtroTexto) . '&filtroCargo=' . urlencode($filtroCargo) . '&filtroArea=' . urlencode($filtroArea) . '&filtroFecha=' . urlencode($filtroFecha) . '&filtroMes=' . urlencode($filtroMes) . '&filtroAnio=' . urlencode($filtroAnio) . '">Anterior</a></li>';


            $rango = 2;
            $inicio = max(1, $pagina_actual - $rango);
            $fin = min($total_paginas, $pagina_actual + $rango);

            if ($inicio > 1) {
                echo '<li class="page-item"><a class="page-link" href="'.$pagina_url.'&pagina=1' . $filtros_url . '">1</a></li>';
                if ($inicio > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            for ($i = $inicio; $i <= $fin; $i++) {
                $active = ($i == $pagina_actual) ? 'active' : '';
                echo "<li class='page-item $active'><a class='page-link' href='$pagina_url&pagina=$i$filtros_url'>$i</a></li>";
            }

            if ($fin < $total_paginas) {
                if ($fin < $total_paginas - 1) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="'.$pagina_url.'&pagina=' . $total_paginas . $filtros_url . '">' . $total_paginas . '</a></li>';
            }

            echo '<li class="page-item ' . (($pagina_actual >= $total_paginas) ? 'disabled' : '') . '"><a class="page-link" href="' . $pagina_url . '&pagina=' . ($pagina_actual + 1) . $filtros_url . '">Siguiente</a></li>';
            ?>
        </ul>
    </nav>
</div>

<script>
const form = document.getElementById("formFiltros");
const paginaInput = document.getElementById("paginaInput");

function enviarFiltro() {
    paginaInput.value = 1;
    form.submit();
}

let debounceTimer;
document.getElementById("buscador").addEventListener("input", function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        enviarFiltro();
    }, 700); 
});

document.getElementById("filtroCargo").addEventListener("change", function() {
    enviarFiltro();
});
document.getElementById("filtroArea").addEventListener("change", function() {
    enviarFiltro();
});
document.getElementById("filtroFecha").addEventListener("change", function(){
    enviarFiltro();
});
document.getElementById("filtroMes").addEventListener("change", function() {
    enviarFiltro();
});
document.getElementById("filtroAnio").addEventListener("change", function() {
    enviarFiltro();
});

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
