<link rel="stylesheet" href="assets/css/sidebar.css">

<div class="sidebar">
    <h4 class="mb-4">🛡️Panel EPP </h4>

    <div class="profile">
        <div class="avatar">
            <?php 
                $nombre = $_SESSION['username'] ?? 'Usuario';
                $inicial = strtoupper(substr($nombre, 0, 1));
                echo $inicial;
            ?>
        </div>
        <div class="profile-info">
            <h3><?php echo htmlspecialchars($nombre); ?></h3>
            <p>Conectado</p>
        </div>
    </div>

    <ul class="nav flex-column">
        <li class="nav-item mb-2">
            <a href="index.php?page=ingreso" class="nav-link">👥 Ingreso de empleados</a>
        </li>
        <li class="nav-item mb-2">
            <a href="index.php?page=formatos" class="nav-link">📄 Formato de entrega</a>
        </li>
        <li class="nav-item mb-2">
            <a href="index.php?page=historial" class="nav-link">📂 Historial</a>
        </li>
        <li class="nav-item mb-2">
            <a href="index.php?page=graficas" class="nav-link">📊 Gráficas</a>
        </li>
        <li class="nav-item mt-4">
            <a href="../views/logout.php" class="nav-link text-danger">🚪 Cerrar sesión</a>
        </li>
    </ul>
</div>
