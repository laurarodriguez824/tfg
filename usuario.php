<?php
include("config/db.php");
session_start();

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario']['id_usuario'];

// Obtener pedidos del usuario
$sql_pedidos = "SELECT * FROM pedidos WHERE id_usuario = $id_usuario ORDER BY fecha_pedido DESC";
$result_pedidos = $conn->query($sql_pedidos);

// Obtener categorías para la barra lateral
$sql_cats = "SELECT * FROM categorias";
$result_cats = $conn->query($sql_cats);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis pedidos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<header class="header">
    <h1 class="title">Style boutique</h1>
    <div class="auth-buttons">
        <?php
        if (isset($_SESSION['usuario'])) {
            echo "<a class='ver-producto' href='usuario.php' >Bienvenido, {$_SESSION['usuario']['nombre']}</a>";
            echo "<a class='ver-producto' href='carrito.php'>Carrito</a>";
            echo "<a class='ver-producto' href='logout.php'>Cerrar sesión</a>";
        } else {
            echo "<a class='ver-producto' href='login.php'>Login</a>";
            echo "<a class='ver-producto' href='registro.php'>Registro</a>";
        }
        ?>
    </div>
</header>

<div class="container">

    <aside class="sidebar">
        <h2>Categorías</h2>
        <ul>
            <?php
            while ($cat = $result_cats->fetch_assoc()) {
                echo "<li><a href='categoria.php?id={$cat['id_categoria']}'>{$cat['nombre']}</a></li>";
            }
            ?>
        </ul>
    </aside>

    <main class="content">
        <h2>Mis pedidos</h2>

        <?php
        if ($result_pedidos->num_rows > 0) {
            while ($pedido = $result_pedidos->fetch_assoc()) {
                $id_pedido = $pedido['id_pedido'];
                echo "<div class='pedido'>";
                echo "<h3>Pedido #{$id_pedido} - Fecha: {$pedido['fecha_pedido']}</h3>";
                echo "<p class='estado'>Estado: {$pedido['estado']}</p>";

                // Detalle de productos
                $sql_detalle = "SELECT dp.*, p.nombre 
                                FROM detalle_pedido dp 
                                JOIN productos p ON dp.id_producto = p.id_producto
                                WHERE dp.id_pedido = $id_pedido";
                $result_detalle = $conn->query($sql_detalle);

                echo "<div class='detalle-productos'>";
                while ($det = $result_detalle->fetch_assoc()) {
                    $subtotal = $det['cantidad'] * $det['precio_unitario'];
                    echo "<div class='detalle-producto'>
                            <span>{$det['nombre']} x {$det['cantidad']}</span>
                            <span>{$det['precio_unitario']} € c/u</span>
                            <span>Subtotal: {$subtotal} €</span>
                          </div>";
                }
                echo "</div>";

                echo "<div class='pedido-total'>Total: {$pedido['total']} €</div>";
                echo "</div>";
            }
        } else {
            echo "<p>No has realizado pedidos todavía.</p>";
        }
        ?>

        <a class='ver-producto' href='index.php'>Volver al inicio</a>
    </main>

</div>

</body>
</html>
