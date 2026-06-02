<?php
session_start();
include("config/db.php");
include("helpers.php");

// Verificar login
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar carrito
if (empty($_SESSION['carrito'])) {
    header("Location: carrito.php");
    exit();
}

// Calcular total y resumen
$total = 0;
$productos_carrito = [];
$error_pago = "";
$descuento_aplicado = null;
$subtotal_carrito = 0;
$ahorro_descuento = 0;
$codigo_descuento_preview = isset($_POST['codigo_descuento']) ? trim($_POST['codigo_descuento']) : '';

asegurarTablasDescuentos($conn);
$id_usuario_checkout = intval($_SESSION['usuario']['id_usuario']);
$codigos_disponibles = $conn->query("SELECT d.codigo, d.porcentaje
                                     FROM usuario_descuentos ud
                                     JOIN descuentos d ON ud.id_descuento = d.id_descuento
                                     WHERE ud.id_usuario = $id_usuario_checkout
                                     AND ud.usado = 0
                                     AND d.activo = 1
                                     ORDER BY d.porcentaje DESC, ud.fecha_obtenido DESC");

foreach ($_SESSION['carrito'] as $id => $cantidad) {
    $id = intval($id);
    $cantidad = intval($cantidad);

    $sql = "SELECT * FROM productos WHERE id_producto = $id";
    $producto = $conn->query($sql)->fetch_assoc();

    if ($producto) {
        $stock_disponible = stockDisponible($conn, $id, $producto['stock']);

        if ($cantidad > $stock_disponible) {
            $error_pago = "No hay stock suficiente de " . $producto['nombre'] . ". Disponible: " . $stock_disponible . ".";
        }

        $subtotal = $producto['precio'] * $cantidad;
        $total += $subtotal;
        $subtotal_carrito += $subtotal;

        $productos_carrito[] = [
            'id_producto' => $id,
            'nombre' => $producto['nombre'],
            'precio' => $producto['precio'],
            'imagen_url' => $producto['imagen_url'],
            'cantidad' => $cantidad,
            'stock_disponible' => $stock_disponible,
            'subtotal' => $subtotal,
            'talla' => isset($_SESSION['carrito_tallas'][$id]) ? strtoupper($_SESSION['carrito_tallas'][$id]) : ''
        ];
    }
}

if ($codigo_descuento_preview !== '') {
    $codigo_sql_preview = $conn->real_escape_string($codigo_descuento_preview);
    $id_usuario_descuento_preview = intval($_SESSION['usuario']['id_usuario']);
    $sql_descuento_preview = "SELECT d.*, ud.id_usuario_descuento
                              FROM descuentos d
                              LEFT JOIN usuario_descuentos ud
                              ON d.id_descuento = ud.id_descuento
                              AND ud.id_usuario = $id_usuario_descuento_preview
                              WHERE d.codigo = '$codigo_sql_preview'
                              AND d.activo = 1
                              AND (ud.id_usuario_descuento IS NULL OR ud.usado = 0)
                              LIMIT 1";
    $descuento_preview_result = $conn->query($sql_descuento_preview);

    if ($descuento_preview_result && $descuento_preview = $descuento_preview_result->fetch_assoc()) {
        $descuento_aplicado = $descuento_preview;
        $ahorro_descuento = $subtotal_carrito * intval($descuento_preview['porcentaje']) / 100;
        $total = $subtotal_carrito - $ahorro_descuento;
    }
}

// Simular pago
if ($_POST) {
    $titular = trim($_POST['titular']);
    $tarjeta = preg_replace('/\D/', '', $_POST['tarjeta']);
    $fecha = trim($_POST['fecha']);
    $cvv = preg_replace('/\D/', '', $_POST['cvv']);
    $direccion = trim($_POST['direccion']);
    $codigo_descuento = isset($_POST['codigo_descuento']) ? trim($_POST['codigo_descuento']) : '';

    if (isset($_POST['aplicar_descuento'])) {
        $error_pago = $descuento_aplicado ? "" : ($codigo_descuento === "" ? "Introduce un código de descuento." : "El código de descuento no es válido.");
    } elseif ($error_pago === "" && $titular === "") {
        $error_pago = "Introduce el nombre del titular.";
    } elseif ($error_pago === "" && $direccion === "") {
        $error_pago = "Introduce la dirección de envío.";
    } elseif ($error_pago === "" && !preg_match('/^\d{13,19}$/', $tarjeta)) {
        $error_pago = "El número de tarjeta debe tener entre 13 y 19 dígitos.";
    } elseif ($error_pago === "" && !preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $fecha)) {
        $error_pago = "La fecha debe tener formato MM/AA.";
    } elseif ($error_pago === "" && !preg_match('/^\d{3,4}$/', $cvv)) {
        $error_pago = "El CVV debe tener 3 o 4 dígitos.";
    } elseif ($error_pago === "") {
        list($mes_caducidad, $anio_caducidad) = explode('/', $fecha);
        $anio_caducidad = 2000 + intval($anio_caducidad);
        $ultimo_dia_caducidad = strtotime($anio_caducidad . '-' . $mes_caducidad . '-01 last day of this month 23:59:59');

        if ($ultimo_dia_caducidad < time()) {
            $error_pago = "La tarjeta está caducada.";
        } else {
            if ($codigo_descuento !== '') {
                $codigo_sql = $conn->real_escape_string($codigo_descuento);
                $id_usuario_descuento = intval($_SESSION['usuario']['id_usuario']);
                $sql_descuento = "SELECT d.*, ud.id_usuario_descuento
                                  FROM descuentos d
                                  LEFT JOIN usuario_descuentos ud
                                  ON d.id_descuento = ud.id_descuento
                                  AND ud.id_usuario = $id_usuario_descuento
                                  WHERE d.codigo = '$codigo_sql'
                                  AND d.activo = 1
                                  AND (ud.id_usuario_descuento IS NULL OR ud.usado = 0)
                                  LIMIT 1";
                $descuento_result = $conn->query($sql_descuento);

                if ($descuento_result && $descuento = $descuento_result->fetch_assoc()) {
                    $descuento_aplicado = $descuento;
                    $ahorro_descuento = $subtotal_carrito * intval($descuento['porcentaje']) / 100;
                    $total = $subtotal_carrito - $ahorro_descuento;
                } else {
                    $error_pago = "El código de descuento no es válido.";
                }
            }

            if ($error_pago === "") {
                $_SESSION['pedido_pendiente'] = [
                    'productos' => $productos_carrito,
                    'subtotal' => $subtotal_carrito,
                    'total' => $total,
                    'direccion' => $direccion,
                    'descuento' => $descuento_aplicado,
                    'ahorro_descuento' => $ahorro_descuento
                ];

                header("Location: compra_exitosa.php");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Style boutique</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<div class="checkout-page">
    <main class="checkout-shell">
        <section class="checkout-card">
            <div class="checkout-heading">
                <span>Pago seguro</span>
                <h2>Finalizar compra</h2>
            </div>

            <?php if ($error_pago !== ""): ?>
                <div class="checkout-error">
                    <?php echo htmlspecialchars($error_pago); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="checkout-form">
                <label>
                    Nombre del titular
                    <input type="text" name="titular" placeholder="Laura García" required>
                </label>

                <label>
                    Dirección de envío
                    <input type="text" name="direccion" placeholder="Calle, número, ciudad y CP" required>
                </label>

                <label>
                    Código de descuento
                    <select name="codigo_descuento">
                        <option value="">Sin descuento</option>
                        <?php if ($codigos_disponibles && $codigos_disponibles->num_rows > 0): ?>
                            <?php while ($codigo = $codigos_disponibles->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($codigo['codigo']); ?>"
                                    <?php echo $codigo_descuento_preview === $codigo['codigo'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($codigo['codigo']); ?> · <?php echo intval($codigo['porcentaje']); ?>%
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </label>
                <button class="ver-producto btn-secundario checkout-apply" type="submit" name="aplicar_descuento">Aplicar código</button>

                <label>
                    Número de tarjeta
                    <input type="text"
                           name="tarjeta"
                           maxlength="23"
                           placeholder="1234 1234 1234 1234"
                           inputmode="numeric"
                           pattern="[0-9 ]{13,23}"
                           required>
                </label>

                <div class="checkout-fields">
                    <label>
                        Fecha
                        <input type="text"
                               name="fecha"
                               placeholder="MM/AA"
                               maxlength="5"
                               pattern="(0[1-9]|1[0-2])/[0-9]{2}"
                               required>
                    </label>

                    <label>
                        CVV
                        <input type="text"
                               name="cvv"
                               maxlength="4"
                               inputmode="numeric"
                               pattern="[0-9]{3,4}"
                               placeholder="123"
                               required>
                    </label>
                </div>

                <button class="ver-producto checkout-pay" type="submit">
                    Pagar <?php echo number_format($total, 2); ?> €
                </button>
            </form>
        </section>

        <aside class="checkout-summary">
            <h3>Resumen del pedido</h3>

            <div class="checkout-items">
                <?php foreach ($productos_carrito as $item): ?>
                    <div class="checkout-item">
                        <img src="<?php echo htmlspecialchars($item['imagen_url']); ?>" alt="<?php echo htmlspecialchars($item['nombre']); ?>">
                        <div>
                            <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                            <span>
                                <?php echo intval($item['cantidad']); ?> ud.
                                <?php if ($item['talla'] !== ''): ?>
                                    · Talla <?php echo htmlspecialchars($item['talla']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <b><?php echo number_format($item['subtotal'], 2); ?> €</b>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="checkout-total">
                <span>Subtotal</span>
                <strong><?php echo number_format($subtotal_carrito, 2); ?> €</strong>
            </div>

            <?php if ($descuento_aplicado): ?>
                <div class="checkout-total checkout-discount">
                    <span>
                        Código <?php echo htmlspecialchars($descuento_aplicado['codigo']); ?>
                        (<?php echo intval($descuento_aplicado['porcentaje']); ?>%)
                    </span>
                    <strong>-<?php echo number_format($ahorro_descuento, 2); ?> €</strong>
                </div>
                <div class="checkout-discount-note">
                    <?php echo number_format($subtotal_carrito, 2); ?> € - <?php echo number_format($ahorro_descuento, 2); ?> € de descuento = <?php echo number_format($total, 2); ?> €
                </div>
            <?php endif; ?>

            <div class="checkout-total">
                <span>Total</span>
                <strong><?php echo number_format($total, 2); ?> €</strong>
            </div>
        </aside>
    </main>
</div>

</body>
</html>
