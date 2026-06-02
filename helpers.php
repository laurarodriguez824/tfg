<?php
function stockDisponible($conn, $id_producto, $stock_fallback = 0)
{
    $id_producto = intval($id_producto);
    $stock_fallback = intval($stock_fallback);
    $tabla = $conn->query("SHOW TABLES LIKE 'inventario'");

    if (!$tabla || $tabla->num_rows == 0) {
        return $stock_fallback;
    }

    $sql = "SELECT
                COUNT(*) AS movimientos,
                COALESCE(SUM(CASE WHEN movimiento = 'entrada' THEN cantidad ELSE 0 END), 0) -
                COALESCE(SUM(CASE WHEN movimiento = 'salida' THEN cantidad ELSE 0 END), 0) AS stock_actual
            FROM inventario
            WHERE id_producto = $id_producto";
    $result = $conn->query($sql);

    if ($result && $row = $result->fetch_assoc()) {
        if (intval($row['movimientos']) === 0) {
            return max(0, $stock_fallback);
        }

        return max(0, intval($row['stock_actual']));
    }

    return $stock_fallback;
}

function registrarSalidaInventario($conn, $id_producto, $cantidad)
{
    $id_producto = intval($id_producto);
    $cantidad = intval($cantidad);
    $tabla = $conn->query("SHOW TABLES LIKE 'inventario'");

    if ($tabla && $tabla->num_rows > 0 && $cantidad > 0) {
        $conn->query("INSERT INTO inventario (id_producto, movimiento, cantidad, fecha_movimiento)
                      VALUES ($id_producto, 'salida', $cantidad, NOW())");
    }

    $conn->query("UPDATE productos SET stock = GREATEST(stock - $cantidad, 0) WHERE id_producto = $id_producto");
}

function registrarEntradaInventario($conn, $id_producto, $cantidad)
{
    $id_producto = intval($id_producto);
    $cantidad = intval($cantidad);
    $tabla = $conn->query("SHOW TABLES LIKE 'inventario'");

    if ($tabla && $tabla->num_rows > 0 && $cantidad > 0) {
        $conn->query("INSERT INTO inventario (id_producto, movimiento, cantidad, fecha_movimiento)
                      VALUES ($id_producto, 'entrada', $cantidad, NOW())");
    }
}

function ajustarStockProducto($conn, $id_producto, $stock_objetivo)
{
    $id_producto = intval($id_producto);
    $stock_objetivo = max(0, intval($stock_objetivo));
    $producto = $conn->query("SELECT stock FROM productos WHERE id_producto = $id_producto")->fetch_assoc();
    $stock_fallback = $producto ? intval($producto['stock']) : 0;
    $stock_actual = stockDisponible($conn, $id_producto, $stock_fallback);
    $diferencia = $stock_objetivo - $stock_actual;
    $tabla = $conn->query("SHOW TABLES LIKE 'inventario'");

    if ($tabla && $tabla->num_rows > 0 && $diferencia !== 0) {
        $movimiento = $diferencia > 0 ? 'entrada' : 'salida';
        $cantidad = abs($diferencia);
        $conn->query("INSERT INTO inventario (id_producto, movimiento, cantidad, fecha_movimiento)
                      VALUES ($id_producto, '$movimiento', $cantidad, NOW())");
    }

    $conn->query("UPDATE productos SET stock = $stock_objetivo WHERE id_producto = $id_producto");
}

function asegurarColumnaDireccion($conn)
{
    $columna = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'direccion'");

    if ($columna && $columna->num_rows == 0) {
        $conn->query("ALTER TABLE pedidos ADD direccion VARCHAR(255) NULL AFTER estado");
    }
}

function asegurarColumnasPedidoDescuento($conn)
{
    asegurarColumnaDireccion($conn);

    $columnas = [
        'subtotal' => "ALTER TABLE pedidos ADD subtotal DECIMAL(10,2) NULL AFTER total",
        'descuento_codigo' => "ALTER TABLE pedidos ADD descuento_codigo VARCHAR(40) NULL AFTER direccion",
        'descuento_porcentaje' => "ALTER TABLE pedidos ADD descuento_porcentaje INT NULL AFTER descuento_codigo"
    ];

    foreach ($columnas as $columna => $sql) {
        $existe = $conn->query("SHOW COLUMNS FROM pedidos LIKE '$columna'");

        if ($existe && $existe->num_rows == 0) {
            $conn->query($sql);
        }
    }
}

function asegurarColumnasUsuarioContacto($conn)
{
    $columnas = [
        'telefono' => "ALTER TABLE usuarios ADD telefono VARCHAR(30) NULL AFTER email",
        'direccion' => "ALTER TABLE usuarios ADD direccion VARCHAR(255) NULL AFTER telefono"
    ];

    foreach ($columnas as $columna => $sql) {
        $existe = $conn->query("SHOW COLUMNS FROM usuarios LIKE '$columna'");

        if ($existe && $existe->num_rows == 0) {
            $conn->query($sql);
        }
    }
}

function asegurarTablaFavoritos($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS favoritos (
        id_favorito INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_producto INT NOT NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY favorito_unico (id_usuario, id_producto)
    )");
}

function asegurarTablasDescuentos($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS descuentos (
        id_descuento INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(40) NOT NULL UNIQUE,
        porcentaje INT NOT NULL,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS usuario_descuentos (
        id_usuario_descuento INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_descuento INT NOT NULL,
        fecha_obtenido DATE NOT NULL,
        usado TINYINT(1) NOT NULL DEFAULT 0,
        origen VARCHAR(20) NOT NULL DEFAULT 'admin'
    )");

    $columna_origen = $conn->query("SHOW COLUMNS FROM usuario_descuentos LIKE 'origen'");
    if ($columna_origen && $columna_origen->num_rows == 0) {
        $conn->query("ALTER TABLE usuario_descuentos ADD origen VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER usado");
    }
}

function asegurarTablaPuntos($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS usuario_puntos (
        id_movimiento_puntos INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        id_pedido INT NULL,
        puntos INT NOT NULL,
        tipo VARCHAR(20) NOT NULL,
        descripcion VARCHAR(255) NULL,
        fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}

function puntosUsuario($conn, $id_usuario)
{
    asegurarTablaPuntos($conn);
    $id_usuario = intval($id_usuario);
    $result = $conn->query("SELECT COALESCE(SUM(puntos), 0) AS puntos
                            FROM usuario_puntos
                            WHERE id_usuario = $id_usuario");

    if ($result && $row = $result->fetch_assoc()) {
        return max(0, intval($row['puntos']));
    }

    return 0;
}

function registrarPuntosPedido($conn, $id_usuario, $id_pedido, $total)
{
    asegurarTablaPuntos($conn);
    $id_usuario = intval($id_usuario);
    $id_pedido = intval($id_pedido);
    $puntos = max(0, intval(floor(floatval($total) * 100)));
    $existe = $conn->query("SELECT id_movimiento_puntos FROM usuario_puntos
                            WHERE id_usuario = $id_usuario
                            AND id_pedido = $id_pedido
                            AND tipo = 'pedido'
                            LIMIT 1");

    if ($puntos > 0 && (!$existe || $existe->num_rows == 0)) {
        $conn->query("INSERT INTO usuario_puntos (id_usuario, id_pedido, puntos, tipo, descripcion, fecha_movimiento)
                      VALUES ($id_usuario, $id_pedido, $puntos, 'pedido', 'Puntos por compra', NOW())");
    }
}

function canjearPuntosDescuento($conn, $id_usuario, $porcentaje)
{
    asegurarTablasDescuentos($conn);
    asegurarTablaPuntos($conn);

    $id_usuario = intval($id_usuario);
    $porcentaje = intval($porcentaje);
    $costes = [
        5 => 5000,
        10 => 10000,
        15 => 15000,
        20 => 20000
    ];

    if (!isset($costes[$porcentaje])) {
        return ['ok' => false, 'mensaje' => 'Selecciona un descuento válido.'];
    }

    $coste = $costes[$porcentaje];

    if (puntosUsuario($conn, $id_usuario) < $coste) {
        return ['ok' => false, 'mensaje' => 'No tienes puntos suficientes para ese descuento.'];
    }

    $descuento = crearCodigoDescuento($conn, $porcentaje, "PUNTOS");
    $id_descuento = intval($descuento['id_descuento']);
    $conn->query("INSERT INTO usuario_descuentos (id_usuario, id_descuento, fecha_obtenido, usado, origen)
                  VALUES ($id_usuario, $id_descuento, CURDATE(), 0, 'puntos')");
    $conn->query("INSERT INTO usuario_puntos (id_usuario, id_pedido, puntos, tipo, descripcion, fecha_movimiento)
                  VALUES ($id_usuario, NULL, -$coste, 'canje', 'Canje por descuento del $porcentaje%', NOW())");

    return [
        'ok' => true,
        'mensaje' => "Has canjeado $coste puntos por un descuento del $porcentaje%. Código: " . $descuento['codigo']
    ];
}

function crearCodigoDescuento($conn, $porcentaje, $prefijo = "STYLE")
{
    $porcentaje = max(0, min(100, intval($porcentaje)));

    do {
        $codigo = $prefijo . "-" . $porcentaje . "-" . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $codigo_sql = $conn->real_escape_string($codigo);
        $existe = $conn->query("SELECT id_descuento FROM descuentos WHERE codigo='$codigo_sql' LIMIT 1");
    } while ($existe && $existe->num_rows > 0);

    $conn->query("INSERT INTO descuentos (codigo, porcentaje, activo, fecha_creacion)
                  VALUES ('$codigo_sql', $porcentaje, 1, NOW())");

    return [
        'id_descuento' => $conn->insert_id,
        'codigo' => $codigo,
        'porcentaje' => $porcentaje
    ];
}

function esCategoriaZapatos($categoria)
{
    return stripos($categoria, 'zapato') !== false || stripos($categoria, 'calzado') !== false;
}

function opcionesTallaPorCategoria($categoria)
{
    if (esCategoriaZapatos($categoria)) {
        return ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44'];
    }

    return ['xs', 's', 'm', 'l', 'xl'];
}

function ordenarTallasSql($categoria)
{
    $opciones = opcionesTallaPorCategoria($categoria);
    $orden = "'" . implode("','", $opciones) . "'";
    return "FIELD(talla, $orden), talla";
}
?>
