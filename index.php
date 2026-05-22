<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: views/login.php");
    exit();
}

$validPages = ['ingreso', 'historial', 'formatos', 'graficas'];
$page = $_GET['page'] ?? 'home';

if (!in_array($page, $validPages)) {
    $page = 'home';
}

include __DIR__ . '/includes/header.php';
?>
  
<div class="d-flex vh-100">

        <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-4 overflow-auto">
        <?php
        if ($page === 'home') {
    echo "
    <div class='home-wrapper'>
        <div class='welcome-card'>
            <h1 class='fade-in'>👷‍♂️ Bienvenido al Sistema de Gestión de EPP</h1>
            <p class='subtitle fade-in-delay'>Hola, " . htmlspecialchars($_SESSION['nombre']) . ".</p>
            <p class='description fade-in-delay2'>
                Administra de manera eficiente la <strong>entrega, control y seguimiento</strong> de los 
                Elementos de Protección Personal en tu organización.
            </p>

            <div class='intro fade-in-delay3'>
                <img src='/img/epp_icon.png' alt='EPP' class='intro-icon'>
                <p class='intro-text'>
                    Este sistema está diseñado para <strong>garantizar la seguridad de los trabajadores</strong>, 
                    facilitando la gestión, control y entrega de los Elementos de Protección Personal en tu organización.
                </p>
            </div>

            <p class='cta fade-in-delay4'>Selecciona una opción del menú lateral para comenzar.</p>
        </div>
    </div>";
        } else {
            if ($page === 'historial') { 
                if (isset($_GET['id'])) {
                    include __DIR__ . '/pages/historial.php'; 
                } else {
                    include __DIR__ . '/pages/listado_empleados.php'; 
                }
            } elseif ($page === 'ingreso') {
                include __DIR__ . '/pages/ingreso.php';
            } elseif ($page === 'formatos') {
                include __DIR__ . '/pages/formatos.php';
            } elseif ($page === 'graficas') {
                include __DIR__ . '/pages/graficas.php';
            }
        }
        ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

