<?php
session_start();
include("config/db.php");

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 'admin') {
    header("Location: index.php");
    exit;
}

$mensaje_admin = "";
$estados_permitidos = ['pagado', 'enviado', 'entregado'];

if (isset($_POST['actualizar_pedido'])) {
    $id_pedido = intval($_POST['id_pedido']);
    $estado = in_array($_POST['estado'], $estados_permitidos) ? $_POST['estado'] : 'pendiente';

    $sql = "UPDATE pedidos SET estado='$estado' WHERE id_pedido=$id_pedido";

    if ($conn->query($sql)) {
        $mensaje_admin = "Pedido actualizado correctamente";
    } else {
        $mensaje_admin = "Error al actualizar el pedido";
    }
}

$sql_pedidos = "SELECT p.*, u.nombre, u.email
                FROM pedidos p
                JOIN usuarios u ON p.id_usuario = u.id_usuario
                ORDER BY FIELD(p.estado, 'pagado', 'enviado', 'entregado'), p.fecha_pedido DESC";
$pedidos = $conn->query($sql_pedidos);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar pedidos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<main class="admin-container">
    <?php if ($mensaje_admin !== ""): ?>
        <div class="admin-message"><?php echo $mensaje_admin; ?></div>
    <?php endif; ?>

    <section class="admin-hero">
        <div>
            <h2>Administrar pedidos</h2>
        </div>
        <a class="btn btn-secundario" href="admin_panel.php">Volver al panel</a>
    </section>

    <section class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table admin-orders-table">
                <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Productos</th>
                    <th>Total</th>
                    <th>Descuento</th>
                    <th>Estado</th>
                    <th>Factura</th>
                    <th>Acciones</th>
                </tr>

                <?php if ($pedidos->num_rows > 0): ?>
                    <?php while ($pedido = $pedidos->fetch_assoc()): ?>
                        <?php
                        $id_pedido = intval($pedido['id_pedido']);
                        $form_id = "pedido-" . $id_pedido;
                        $sql_detalle = "SELECT dp.*, pr.nombre, pr.talla
                                        FROM detalle_pedido dp
                                        JOIN productos pr ON dp.id_producto = pr.id_producto
                                        WHERE dp.id_pedido = $id_pedido";
                        $detalles = $conn->query($sql_detalle);
                        ?>
                        <tr>
                            <td>
                                <strong>#<?php echo $id_pedido; ?></strong>
                                <span class="admin-muted"><?php echo $pedido['fecha_pedido']; ?></span>
                                <form method="POST" id="<?php echo $form_id; ?>">
                                    <input type="hidden" name="id_pedido" value="<?php echo $id_pedido; ?>">
                                </form>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($pedido['nombre']); ?></strong>
                                <span class="admin-muted"><?php echo htmlspecialchars($pedido['email']); ?></span>
                            </td>
                            <td class="admin-order-products">
                                <?php while ($detalle = $detalles->fetch_assoc()): ?>
                                    <span>
                                        <?php echo htmlspecialchars($detalle['nombre']); ?>
                                        <?php if ($detalle['talla']): ?>
                                            · <?php echo strtoupper($detalle['talla']); ?>
                                        <?php endif; ?>
                                        x <?php echo intval($detalle['cantidad']); ?>
                                    </span>
                                <?php endwhile; ?>
                            </td>
                            <td><strong><?php echo number_format($pedido['total'], 2); ?> €</strong></td>
                            <td>
                                <?php if (!empty($pedido['descuento_codigo'])): ?>
                                    <strong><?php echo htmlspecialchars($pedido['descuento_codigo']); ?></strong>
                                    <span class="admin-muted"><?php echo intval($pedido['descuento_porcentaje']); ?>%</span>
                                <?php else: ?>
                                    <span class="admin-muted">Sin descuento</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select name="estado" form="<?php echo $form_id; ?>">
                                    <?php foreach ($estados_permitidos as $estado): ?>
                                        <option value="<?php echo $estado; ?>" <?php echo $pedido['estado'] === $estado ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($estado); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <a class="btn btn-secundario" href="factura.php?id=<?php echo $id_pedido; ?>">PDF</a>
                            </td>
                            <td>
                                <button class="btn editar" type="submit" name="actualizar_pedido" form="<?php echo $form_id; ?>">
                                    Guardar
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="admin-empty">No hay pedidos todavía.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
    </section>
</main>

</body>
</html>
