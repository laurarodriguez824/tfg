<?php
session_start();
include("config/db.php");

// Seguridad: solo clientes logueados
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['usuario']['id'];

$sql = "SELECT * 
        FROM pedidos 
        WHERE id_usuario = $id_usuario 
        ORDER BY fecha DESC";

$pedidos = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis pedidos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<div class="container">

    <main class="content">

        <h2>Historial de compras</h2>

        <?php if ($pedidos->num_rows == 0): ?>
            <p>No tienes pedidos todavía.</p>
        <?php else: ?>

            <table border="1" cellpadding="10">
                <tr>
                    <th>ID Pedido</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>

                <?php while ($p = $pedidos->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $p['id_pedido']; ?></td>
                        <td><?php echo $p['fecha']; ?></td>
                        <td><?php echo $p['total']; ?> €</td>
                        <td><?php echo $p['estado']; ?></td>
                        <td>
                            <a class="ver-producto"
                               href="detalle_mi_pedido.php?id=<?php echo $p['id_pedido']; ?>">
                                Ver detalles
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>

            </table>

        <?php endif; ?>

    </main>

</div>

</body>
</html>