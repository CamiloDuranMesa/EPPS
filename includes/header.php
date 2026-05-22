<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Control EPP</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/index.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/historial.css?v=1.4">
    <style>
        .logo-container {
            position: fixed;
            top: 45px;
            right: 45px;
            z-index: 1000;
        }
        .logo-container img {
            max-height: 100px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
<div class="logo-container">
    <?php
    $basePath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
    if (strpos($basePath, '/pages') !== false) {
        $imgPath = "../img/agro-logo.png";
    } else {
        $imgPath = "img/agro-logo.png";
    }
    ?>
    <img src="<?php echo $imgPath; ?>" alt="Logo Empresa">
</div>
