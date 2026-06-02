<?php
session_start();
include("config/db.php");
include("helpers.php");

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 'admin') {
    header("Location: index.php");
    exit;
}

asegurarTablasDescuentos($conn);
asegurarTablaPuntos($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel administrador</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<main class="admin-container">
    <section class="admin-hero">
        <div>
            <h2>Panel administrador</h2>
        </div>
        <span class="admin-badge">Gestión</span>
    </section>

    <section class="admin-panel-grid">
        <a class="admin-panel-card" href="admin_productos.php">
            <span>Productos</span>
            <strong>Administrar productos</strong>
            <p>Crear variantes por talla, editar stock, imágenes, descripción y precios.</p>
        </a>

        <a class="admin-panel-card" href="admin_pedidos.php">
            <span>Pedidos</span>
            <strong>Administrar pedidos</strong>
            <p>Ver pedidos de clientes, revisar sus productos y cambiar el estado.</p>
        </a>

        <a class="admin-panel-card" href="admin_descuentos.php">
            <span>Descuentos</span>
            <strong>Administrar descuentos</strong>
            <p>Crear códigos, elegir el porcentaje y activar o desactivar promociones.</p>
        </a>
    </section>
</main>

</body>
</html>
