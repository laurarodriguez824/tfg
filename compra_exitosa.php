<?php
session_start();
include("config/db.php");
include("helpers.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = intval($_SESSION['usuario']['id_usuario']);
$pedido_creado = null;
$mensaje_error = "";

if (isset($_SESSION['pedido_pendiente']) && !empty($_SESSION['pedido_pendiente']['productos'])) {
    $pedido = $_SESSION['pedido_pendiente'];
    $total = floatval($pedido['total']);
    $subtotal = isset($pedido['subtotal']) ? floatval($pedido['subtotal']) : $total;
    $direccion = $conn->real_escape_string($pedido['direccion']);
    $descuento_codigo = isset($pedido['descuento']['codigo']) ? $conn->real_escape_string($pedido['descuento']['codigo']) : "";
    $descuento_porcentaje = isset($pedido['descuento']['porcentaje']) ? intval($pedido['descuento']['porcentaje']) : 0;

    $conn->begin_transaction();

    try {
        asegurarColumnasPedidoDescuento($conn);
        $sql_pedido = "INSERT INTO pedidos (id_usuario, fecha_pedido, total, estado)
                       VALUES ($id_usuario, NOW(), '$total', 'pagado')";

        $sql_pedido = "INSERT INTO pedidos
                       (id_usuario, fecha_pedido, subtotal, total, estado, direccion, descuento_codigo, descuento_porcentaje)
                       VALUES
                       ($id_usuario, NOW(), '$subtotal', '$total', 'pagado', '$direccion', '$descuento_codigo', '$descuento_porcentaje')";

        $conn->query($sql_pedido);
        $pedido_creado = $conn->insert_id;

        foreach ($pedido['productos'] as $producto) {
            $id_producto = intval($producto['id_producto']);
            $cantidad = intval($producto['cantidad']);
            $precio = floatval($producto['precio']);
            $producto_actual = $conn->query("SELECT stock FROM productos WHERE id_producto = $id_producto FOR UPDATE")->fetch_assoc();
            $stock_disponible = $producto_actual ? stockDisponible($conn, $id_producto, $producto_actual['stock']) : 0;

            if ($stock_disponible < $cantidad) {
                throw new Exception("Stock insuficiente");
            }

            $sql_detalle = "INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
                            VALUES ($pedido_creado, $id_producto, $cantidad, '$precio')";
            $conn->query($sql_detalle);
            registrarSalidaInventario($conn, $id_producto, $cantidad);
        }

        if (!empty($pedido['descuento']['id_descuento'])) {
            $id_descuento = intval($pedido['descuento']['id_descuento']);
            $conn->query("UPDATE descuentos SET activo=0 WHERE id_descuento=$id_descuento");
            $conn->query("DELETE FROM usuario_descuentos WHERE id_usuario=$id_usuario AND id_descuento=$id_descuento");
        }

        if (!empty($pedido['descuento']['id_usuario_descuento'])) {
            $id_usuario_descuento = intval($pedido['descuento']['id_usuario_descuento']);
            $conn->query("UPDATE usuario_descuentos SET usado=1 WHERE id_usuario_descuento=$id_usuario_descuento");
        }

        registrarPuntosPedido($conn, $id_usuario, $pedido_creado, $total);

        $conn->commit();

        $_SESSION['ultimo_pedido'] = $pedido_creado;
        unset($_SESSION['pedido_pendiente']);
        unset($_SESSION['carrito']);
        unset($_SESSION['carrito_tallas']);
    } catch (Exception $e) {
        $conn->rollback();
        $mensaje_error = "No se ha podido registrar el pedido. Inténtalo de nuevo.";
    }
} elseif (isset($_SESSION['ultimo_pedido'])) {
    $pedido_creado = intval($_SESSION['ultimo_pedido']);
}

$pedido_info = null;

if ($pedido_creado) {
    $sql = "SELECT * FROM pedidos
            WHERE id_pedido = $pedido_creado
            AND id_usuario = $id_usuario";
    $pedido_info = $conn->query($sql)->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compra realizada</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<main class="success-page">
    <section class="success-card">
        <?php if ($mensaje_error !== ""): ?>
            <span class="success-icon error">!</span>
            <h1>Ha ocurrido un problema</h1>
            <p><?php echo $mensaje_error; ?></p>
            <a class="ver-producto" href="pago.php">Volver al pago</a>
        <?php else: ?>
            <span class="success-icon">✓</span>
            <h1>Compra realizada</h1>
            <p>Tu pedido se ha registrado correctamente y ya aparece en tus pedidos realizados.</p>

            <?php if ($pedido_info): ?>
                <div class="success-summary">
                    <div>
                        <span>Pedido</span>
                        <strong>#<?php echo $pedido_info['id_pedido']; ?></strong>
                    </div>
                    <div>
                        <span>Estado</span>
                        <strong><?php echo htmlspecialchars($pedido_info['estado']); ?></strong>
                    </div>
                    <div>
                        <span>Total</span>
                        <strong><?php echo number_format($pedido_info['total'], 2); ?> €</strong>
                    </div>
                    <?php if (!empty($pedido_info['descuento_codigo'])): ?>
                        <div>
                            <span>Descuento</span>
                            <strong><?php echo htmlspecialchars($pedido_info['descuento_codigo']); ?> · <?php echo intval($pedido_info['descuento_porcentaje']); ?>%</strong>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="success-track">
                <div class="track-step active">
                    <span></span>
                    <p>Pedido recibido</p>
                </div>
                <div class="track-step active">
                    <span></span>
                    <p>Pagado</p>
                </div>
                <div class="track-step">
                    <span></span>
                    <p>Enviado</p>
                </div>
                <div class="track-step">
                    <span></span>
                    <p>Entregado</p>
                </div>
            </div>

            <div class="success-actions">
                <?php if ($pedido_info): ?>
                    <a class="ver-producto" href="factura.php?id=<?php echo $pedido_info['id_pedido']; ?>">Descargar factura</a>
                <?php endif; ?>
                <a class="ver-producto" href="usuario.php">Ver mis pedidos</a>
                <a class="ver-producto btn-secundario" href="index.php">Seguir comprando</a>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
