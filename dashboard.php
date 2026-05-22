<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: views/login.php");
    exit();
}
include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-2 p-0">
            <?php include __DIR__ . '/includes/sidebar.php'; ?>
        </div>
        <div class="col-10 p-4">
            <h1>Bienvenido, <?= $_SESSION['nombre'] ?></h1>
            <p>Selecciona una opción del menú para continuar.</p>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
