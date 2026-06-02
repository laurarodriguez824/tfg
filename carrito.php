<?php
session_start();
include("config/db.php");
include("helpers.php");

// Procesar actualización de cantidades
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cantidades'])) {
    foreach ($_POST['cantidades'] as $id_producto => $cantidad) {
        $id_producto = intval($id_producto);
        $cantidad = intval($cantidad);
        $id_destino = $id_producto;

        if (isset($_POST['tallas'][$id_producto])) {
            $talla_nueva = $conn->real_escape_string($_POST['tallas'][$id_producto]);
            $producto_actual = $conn->query("SELECT nombre, id_categoria FROM productos WHERE id_producto = $id_producto")->fetch_assoc();

            if ($producto_actual) {
                $nombre_actual = $conn->real_escape_string($producto_actual['nombre']);
                $categoria_actual = intval($producto_actual['id_categoria']);
                $variante = $conn->query("SELECT id_producto, stock FROM productos
                                          WHERE nombre = '$nombre_actual'
                                          AND id_categoria = $categoria_actual
                                          AND talla = '$talla_nueva'
                                          LIMIT 1")->fetch_assoc();

                if ($variante) {
                    $id_destino = intval($variante['id_producto']);
                    unset($_SESSION['carrito'][$id_producto]);
                    unset($_SESSION['carrito_tallas'][$id_producto]);
                    $_SESSION['carrito_tallas'][$id_destino] = $talla_nueva;
                }
            }
        }

        if ($cantidad > 0) {
            $producto_stock = $conn->query("SELECT stock FROM productos WHERE id_producto = $id_destino")->fetch_assoc();
            $stock_disponible = $producto_stock ? stockDisponible($conn, $id_destino, $producto_stock['stock']) : 0;
            $cantidad_ajustada = min($cantidad, $stock_disponible);

            if ($cantidad_ajustada > 0) {
                $_SESSION['carrito'][$id_destino] = $cantidad_ajustada;
            } else {
                unset($_SESSION['carrito'][$id_destino]);
                unset($_SESSION['carrito_tallas'][$id_destino]);
            }
        } else {
            unset($_SESSION['carrito'][$id_producto]);
            unset($_SESSION['carrito_tallas'][$id_producto]);
        }
    }
    header("Location: carrito.php"); // recargar para evitar reenvío de formulario
    exit();
}

// Procesar eliminación de producto
if (isset($_GET['eliminar'])) {
    $id_producto = $_GET['eliminar'];
    if (isset($_SESSION['carrito'][$id_producto])) {
        unset($_SESSION['carrito'][$id_producto]);
        unset($_SESSION['carrito_tallas'][$id_producto]);
    }
    header("Location: carrito.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<div class="container">

    <aside class="sidebar">
        <h2>Categorías</h2>
        <ul>
            <?php
            $sql = "SELECT * FROM categorias";
            $result = $conn->query($sql);
            while ($cat = $result->fetch_assoc()) {
                echo "<li><a href='categoria.php?id={$cat['id_categoria']}'>{$cat['nombre']}</a></li>";
            }
            ?>
        </ul>
    </aside>
    
    <main class="content">
        <h1>Carrito de compra</h1>

        <?php
        $total = 0;

        if (!empty($_SESSION['carrito'])) {
            echo "<form method='post' action='carrito.php'>";
            
            foreach ($_SESSION['carrito'] as $id_producto => $cantidad) {
                $sql = "SELECT * FROM productos WHERE id_producto = $id_producto";
                $prod = $conn->query($sql)->fetch_assoc();

                $subtotal = $prod['precio'] * $cantidad;
                $stock_disponible = stockDisponible($conn, $id_producto, $prod['stock']);
                $total += $subtotal;
                $categoria = $conn->query("SELECT nombre FROM categorias WHERE id_categoria = " . intval($prod['id_categoria']))->fetch_assoc();
                $nombre_categoria = $categoria ? $categoria['nombre'] : '';
                $orden_tallas = ordenarTallasSql($nombre_categoria);
                $nombre_prod_sql = $conn->real_escape_string($prod['nombre']);
                $variantes = $conn->query("SELECT id_producto, talla, stock FROM productos
                                           WHERE nombre = '$nombre_prod_sql'
                                           AND id_categoria = " . intval($prod['id_categoria']) . "
                                           ORDER BY $orden_tallas");
                $talla_actual = isset($_SESSION['carrito_tallas'][$id_producto]) ? strtolower($_SESSION['carrito_tallas'][$id_producto]) : strtolower($prod['talla']);
                $opciones_talla = "";

                while ($variante = $variantes->fetch_assoc()) {
                    $talla_variante = strtolower($variante['talla']);
                    $stock_variante = stockDisponible($conn, $variante['id_producto'], $variante['stock']);
                    $selected = $talla_variante === $talla_actual ? "selected" : "";
                    $disabled = $stock_variante <= 0 ? "disabled" : "";
                    $texto_talla = esCategoriaZapatos($nombre_categoria) ? $variante['talla'] : strtoupper($variante['talla']);
                    $opciones_talla .= "<option value='{$variante['talla']}' $selected $disabled>$texto_talla</option>";
                }

                echo "
                <div class='producto-carrito'>
                    <img src='{$prod['imagen_url']}' alt='{$prod['nombre']}' class='carrito-img'>
                    <div class='carrito-info'>
                        <h3>{$prod['nombre']}</h3>
                        <p>Precio: {$prod['precio']} €</p>
                        <label class='carrito-cantidad'>
                            Talla:
                            <select name='tallas[$id_producto]' onchange='this.form.submit()'>
                                $opciones_talla
                            </select>
                        </label>
                        <label class='carrito-cantidad'>
                            Cantidad:
                            <input type='number' name='cantidades[$id_producto]' value='$cantidad' min='0' max='$stock_disponible' onchange='this.form.submit()' oninput='this.form.submit()'>
                        </label>
                        <p>Subtotal: $subtotal €</p>
                    </div>
                    <a href='carrito.php?eliminar=$id_producto' class='ver-producto carrito-eliminar'>Eliminar</a>
                </div>
                ";
            }

            echo "<h2>Total: $total €</h2>";
            if (!empty($_SESSION['carrito'])): ?>
                <a class="ver-producto" href="pago.php">
                    Finalizar compra
                </a>
            <?php endif; 
            echo "</form>";

        } else {
            echo "<p>El carrito está vacío</p>";
        }
        ?>

        <a class='ver-producto' href="index.php">Seguir comprando</a>
    </main>

</div>

</body>
</html>
