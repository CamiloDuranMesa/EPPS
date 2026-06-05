<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$filtrosUrl = http_build_query([
    'page' => 'historial',
    'buscador' => $_GET['buscador'] ?? '',
    'filtroCargo' => $_GET['filtroCargo'] ?? '',
    'filtroArea' => $_GET['filtroArea'] ?? '',
    'filtroFecha' => $_GET['filtroFecha'] ?? '',
    'filtroMes' => $_GET['filtroMes'] ?? 0,
    'filtroAnio' => $_GET['filtroAnio'] ?? 0,
]);

require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit();
}

$empleado_id = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;
if ($empleado_id <= 0) {
    header("Location: listado_empleados.php");
    exit();
}

$stmtEmp = $conn->prepare("SELECT nombre, cedula, cargo, area FROM empleados WHERE id = ?");
$stmtEmp->bind_param("i", $empleado_id);
$stmtEmp->execute();
$stmtEmp->bind_result($empNombre, $empCedula, $empCargo, $empArea);
if (!$stmtEmp->fetch()) {
    $stmtEmp->close();
    header("Location: listado_empleados.php");
    exit();
}
$stmtEmp->close();


$orden = $_GET['orden'] ?? 'desc'; 
$fechaFiltro = $_GET['fecha'] ?? null;

$porPagina = 10;
$pagina = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($pagina - 1) * $porPagina;

$sqlCount = "SELECT COUNT(*) FROM entregas WHERE empleado_id = ?";
$params = [$empleado_id];
$types = "i";

if ($fechaFiltro) {
    $sqlCount .= " AND DATE(fecha_entrega) = ?";
    $params[] = $fechaFiltro;
    $types .= "s";
}
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$stmtCount->bind_result($totalEntregas);
$stmtCount->fetch();
$stmtCount->close();

$totalPaginas = max(1, (int)ceil($totalEntregas / $porPagina));


$query = "
    SELECT e.id, e.fecha_entrega,
            u_resp.nombre AS responsable_nombre,
            COALESCE(e.sst_nombre, u_sst.nombre)  AS sst_nombre,
           (SELECT COUNT(*) FROM entregas_detalle d WHERE d.entrega_id = e.id) AS items
    FROM entregas e
    LEFT JOIN usuarios u_resp ON u_resp.id = e.responsable_entrega
    LEFT JOIN empleados u_sst  ON u_sst.id  = e.sst_id
    WHERE e.empleado_id = ?
";
$params = [$empleado_id];
$types = "i";

if ($fechaFiltro) {
    $query .= " AND DATE(e.fecha_entrega) = ?";
    $params[] = $fechaFiltro;
    $types .= "s";
}

$query .= " ORDER BY e.fecha_entrega " . ($orden === 'asc' ? "ASC" : "DESC") . ", e.id DESC
            LIMIT ? OFFSET ?";
$params[] = $porPagina;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <a href="/index.php?<?= $filtrosUrl ?>" class="boton-volver">Volver al inicio</a>

    <h2 class="mt-4">Historial de entregas</h2>

    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> La entrega se ha eliminado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <strong>Empleado:</strong> <?= htmlspecialchars($empNombre) ?><br>
            <strong>Cédula:</strong> <?= htmlspecialchars($empCedula) ?><br>
            <strong>Cargo:</strong> <?= htmlspecialchars($empCargo) ?><br>
            <strong>Área:</strong> <?= htmlspecialchars($empArea) ?>
        </div>
    </div>

    <div class="mb-3">
        <a href="../includes/informe_pdf.php?empleado_id=<?= $empleado_id ?>" class="btn btn-danger" target="_blank">
            <i class="bi bi-file-earmark-pdf"></i> Informe PDF
        </a>
    </div>


    <form method="get" class="row mb-3">
        <input type="hidden" name="empleado_id" value="<?= $empleado_id ?>">
        
        <div class="col-md-3">
            <label class="form-label">Ordenar por fecha:</label>
            <select name="orden" class="form-select">
                <option value="desc" <?= $orden === 'desc' ? 'selected' : '' ?>>Más reciente primero</option>
                <option value="asc" <?= $orden === 'asc' ? 'selected' : '' ?>>Más antiguo primero</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Filtrar por fecha:</label>
            <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fechaFiltro ?? '') ?>">
        </div>

        <div class="col-md-3 align-self-end">
            <button type="submit" class="btn btn-primary">Aplicar</button>
            <a href="?empleado_id=<?= $empleado_id ?>" class="btn btn-secondary">Limpiar</a>
        </div>
    </form>

    <?php if ($totalEntregas == 0): ?>
        <div class="alert alert-info">No hay entregas registradas para este empleado.</div>
    <?php else: ?>
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Responsable entrega</th>
                    <th>Representante SST</th>
                    <th>Ítems</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= htmlspecialchars($row['fecha_entrega']) ?></td>
                    <td><?= htmlspecialchars($row['responsable_nombre'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($row['sst_nombre'] ?? '—') ?></td>
                    <td><?= (int)$row['items'] ?></td>
                    <td>
                        <a href="historial_detalle.php?entrega_id=<?= urlencode($row['id']) ?>"
                           class="btn btn-info btn-sm">Ver detalle</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>


        <?php
        $queryBase = "empleado_id=" . urlencode($empleado_id) . "&orden=" . urlencode($orden);
        if ($fechaFiltro) {
            $queryBase .= "&fecha=" . urlencode($fechaFiltro);
        }
        ?>
        <nav aria-label="Paginación">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= $queryBase ?>&page=<?= $pagina - 1 ?>">Anterior</a>
                </li>

                <?php
                $rango = 2; 
                $inicio = max(1, $pagina - $rango);
                $fin = min($totalPaginas, $pagina + $rango);

                if ($inicio > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?'.$queryBase.'&page=1">1</a></li>';
                    if ($inicio > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($p = $inicio; $p <= $fin; $p++) {
                    $active = ($p == $pagina) ? 'active' : '';
                    echo '<li class="page-item '.$active.'"><a class="page-link" href="?'.$queryBase.'&page='.$p.'">'.$p.'</a></li>';
                }

                if ($fin < $totalPaginas) {
                    if ($fin < $totalPaginas - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?'.$queryBase.'&page='.$totalPaginas.'">'.$totalPaginas.'</a></li>';
                }
                ?>

                <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= $queryBase ?>&page=<?= $pagina + 1 ?>">Siguiente</a>
                </li>
            </ul>
        </nav>


    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
