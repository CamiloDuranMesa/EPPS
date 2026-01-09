<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../views/login.php");
    exit();
}

// Tipo de gráfica seleccionada (por defecto: por área)
$tipoGrafica = isset($_GET['tipo']) ? $_GET['tipo'] : 'area';

// Obtener datos según el tipo de gráfica seleccionado
$datos = [];
$labels = [];
$titulo = "";

switch ($tipoGrafica) {
    case 'area':
        $titulo = "Entregas por Área";
        $query = "SELECT e.area, COUNT(ed.id) as total_elementos 
                 FROM empleados e 
                 INNER JOIN entregas en ON e.id = en.empleado_id 
                 INNER JOIN entregas_detalle ed ON en.id = ed.entrega_id 
                 WHERE e.area IS NOT NULL AND e.area <> '' 
                 GROUP BY e.area 
                 ORDER BY total_elementos DESC";
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['area'];
            $datos[] = (int)$row['total_elementos'];
        }
        break;
        
    case 'empleado':
        $titulo = "Entregas por Empleado";
        $query = "SELECT e.nombre, COUNT(ed.id) as total_elementos 
                 FROM empleados e 
                 INNER JOIN entregas en ON e.id = en.empleado_id 
                 INNER JOIN entregas_detalle ed ON en.id = ed.entrega_id 
                 GROUP BY e.id 
                 ORDER BY total_elementos DESC 
                 LIMIT 10"; // Limitamos a los 10 principales
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['nombre'];
            $datos[] = (int)$row['total_elementos'];
        }
        break;
        
    case 'elemento':
        $titulo = "Elementos más Entregados";
        $query = "SELECT elemento, COUNT(*) as total 
                 FROM entregas_detalle 
                 GROUP BY elemento 
                 ORDER BY total DESC 
                 LIMIT 10"; // Limitamos a los 10 principales
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['elemento'];
            $datos[] = (int)$row['total'];
        }
        break;
        
    case 'tiempo':
        $titulo = "Entregas por Mes";
        $query = "SELECT DATE_FORMAT(fecha_entrega, '%Y-%m') as mes, COUNT(*) as total 
                 FROM entregas 
                 GROUP BY mes 
                 ORDER BY mes ASC 
                 LIMIT 12"; // Últimos 12 meses
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['mes'];
            $datos[] = (int)$row['total'];
        }
        break;
}

// Convertir datos a formato JSON para usar en JavaScript
$datosJSON = json_encode($datos);
$labelsJSON = json_encode($labels);

include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mt-4">Gráficas de Entregas</h2>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Seleccione el tipo de gráfica</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row">
                        <input type="hidden" name="page" value="graficas">
                        
                        <div class="col-md-4 mb-3">
                            <select name="tipo" class="form-select" onchange="this.form.submit()">
                                <option value="area" <?= $tipoGrafica === 'area' ? 'selected' : '' ?>>Por Área</option>
                                <option value="empleado" <?= $tipoGrafica === 'empleado' ? 'selected' : '' ?>>Por Empleado</option>
                                <option value="elemento" <?= $tipoGrafica === 'elemento' ? 'selected' : '' ?>>Por Elemento</option>
                                <option value="tiempo" <?= $tipoGrafica === 'tiempo' ? 'selected' : '' ?>>Por Tiempo</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5><?= htmlspecialchars($titulo) ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="grafica" style="width: 100%; height: 400px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incluir Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Configuración de la gráfica
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('grafica').getContext('2d');
    
    // Datos de la gráfica
    const datos = <?= $datosJSON ?>;
    const etiquetas = <?= $labelsJSON ?>;
    
    // Determinar el tipo de gráfica según la selección
    let tipoChart = 'bar';
    let opciones = {};
    
    // Personalizar según el tipo de gráfica
    switch('<?= $tipoGrafica ?>') {
        case 'tiempo':
            tipoChart = 'line';
            opciones = {
                tension: 0.3,
                fill: false
            };
            break;
        case 'area':
            tipoChart = 'pie';
            break;
        case 'elemento':
        case 'empleado':
            tipoChart = 'bar';
            break;
    }
    
    // Crear la gráfica
    const myChart = new Chart(ctx, {
        type: tipoChart,
        data: {
            labels: etiquetas,
            datasets: [{
                label: '<?= htmlspecialchars($titulo) ?>',
                data: datos,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)',
                    'rgba(83, 102, 255, 0.7)',
                    'rgba(40, 159, 64, 0.7)',
                    'rgba(210, 199, 199, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)',
                    'rgba(83, 102, 255, 1)',
                    'rgba(40, 159, 64, 1)',
                    'rgba(210, 199, 199, 1)'
                ],
                borderWidth: 1,
                ...opciones
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: tipoChart === 'pie' ? 'right' : 'top',
                },
                title: {
                    display: true,
                    text: '<?= htmlspecialchars($titulo) ?>'
                }
            },
            scales: tipoChart !== 'pie' ? {
                y: {
                    beginAtZero: true
                }
            } : {}
        }
    });
});
</script>