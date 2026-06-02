<?php
session_start();
include("config/db.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario']['id'];
$id_pedido = intval($_GET['id']);

// Verificar que el pedido pertenece al usuario
$sql = "SELECT * FROM pedidos 
        WHERE id_pedido = $id_pedido 
        AND id_usuario = $id_usuario";

$pedido = $conn->query($sql);

if ($pedido->num_rows == 0) {
    echo "Acceso no permitido";
    exit();
}

// Obtener productos del pedido
$sql = "SELECT pp.*, pr.nombre
        FROM pedido_productos pp
        JOIN productos pr ON pp.id_producto = pr.id_producto
        WHERE pp.id_pedido = $id_pedido";

$productos = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle pedido</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<div class="container">

    <main class="content">

        <table border="1" cellpadding="10">
            <tr>
                <th>Producto</th>
                <th>Cantidad</th>
                <th>Precio</th>
                <th>Subtotal</th>
            </tr>

            <?php 
            $total = 0;

            while ($p = $productos->fetch_assoc()):
                $subtotal = $p['cantidad'] * $p['precio'];
                $total += $subtotal;
            ?>
                <tr>
                    <td><?php echo $p['nombre']; ?></td>
                    <td><?php echo $p['cantidad']; ?></td>
                    <td><?php echo $p['precio']; ?> €</td>
                    <td><?php echo $subtotal; ?> €</td>
                </tr>
            <?php endwhile; ?>

        </table>

        <h3>Total: <?php echo $total; ?> €</h3>

    </main>

</div>

</body>
</html>