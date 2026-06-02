<?php
include("config/db.php");
include("helpers.php");
session_start();

$id_producto = intval($_GET['id']);
$sql = "SELECT * FROM productos WHERE id_producto = $id_producto";
$prod = $conn->query($sql)->fetch_assoc();

if (!$prod) {
    header("Location: index.php");
    exit();
}

$nombre_producto = $conn->real_escape_string($prod['nombre']);
$id_categoria_producto = intval($prod['id_categoria']);
$categoria_producto = $conn->query("SELECT nombre FROM categorias WHERE id_categoria = $id_categoria_producto")->fetch_assoc();
$nombre_categoria_producto = $categoria_producto ? $categoria_producto['nombre'] : '';
$tallas_disponibles = opcionesTallaPorCategoria($nombre_categoria_producto);
$orden_tallas = ordenarTallasSql($nombre_categoria_producto);
$sql_variantes = "SELECT * FROM productos
                  WHERE nombre = '$nombre_producto'
                  AND id_categoria = $id_categoria_producto
                  ORDER BY $orden_tallas";
$variantes_result = $conn->query($sql_variantes);
$variantes = [];

while ($variante = $variantes_result->fetch_assoc()) {
    $clave_talla = strtolower($variante['talla']);
    $variante['stock_disponible'] = stockDisponible($conn, $variante['id_producto'], $variante['stock']);
    $variantes[$clave_talla] = $variante;
}

$mensaje_producto = "";
$hay_tallas_disponibles = false;
$es_favorito = false;
$sql_recomendados = "SELECT MIN(id_producto) AS id_producto,
                            nombre,
                            MIN(precio) AS precio,
                            MAX(imagen_url) AS imagen_url
                     FROM productos
                     WHERE id_categoria = $id_categoria_producto
                     AND nombre <> '$nombre_producto'
                     GROUP BY nombre, id_categoria
                     LIMIT 4";
$recomendados = $conn->query($sql_recomendados);

if (isset($_SESSION['usuario'])) {
    asegurarTablaFavoritos($conn);
    $id_usuario_favorito = intval($_SESSION['usuario']['id_usuario']);
    $fav = $conn->query("SELECT id_favorito FROM favoritos WHERE id_usuario=$id_usuario_favorito AND id_producto=$id_producto");
    $es_favorito = $fav && $fav->num_rows > 0;
}

foreach ($tallas_disponibles as $talla_revision) {
    if (isset($variantes[$talla_revision]) && intval($variantes[$talla_revision]['stock_disponible']) > 0) {
        $hay_tallas_disponibles = true;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['favorito'])) {
    if (!isset($_SESSION['usuario'])) {
        header("Location: login.php");
        exit();
    }

    asegurarTablaFavoritos($conn);
    $id_usuario_favorito = intval($_SESSION['usuario']['id_usuario']);

    if ($es_favorito) {
        $conn->query("DELETE FROM favoritos WHERE id_usuario=$id_usuario_favorito AND id_producto=$id_producto");
    } else {
        $conn->query("INSERT IGNORE INTO favoritos (id_usuario, id_producto) VALUES ($id_usuario_favorito, $id_producto)");
    }

    header("Location: producto.php?id=$id_producto");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comprar'])) {
    if (!isset($_SESSION['usuario'])) {
        header("Location: login.php");
        exit();
    }

    $cantidad = max(1, intval($_POST['cantidad']));
    $talla = isset($_POST['talla']) ? $_POST['talla'] : '';

    $cantidad_en_carrito = 0;

    if (isset($variantes[$talla])) {
        $id_variante = intval($variantes[$talla]['id_producto']);
        $cantidad_en_carrito = isset($_SESSION['carrito'][$id_variante]) ? intval($_SESSION['carrito'][$id_variante]) : 0;
    }

    if (!isset($variantes[$talla]) || intval($variantes[$talla]['stock_disponible']) < ($cantidad_en_carrito + $cantidad)) {
        $mensaje_producto = "Esa talla no está disponible.";
    } else {
        $id = intval($variantes[$talla]['id_producto']);

        if (!isset($_SESSION['carrito'][$id])) {
            $_SESSION['carrito'][$id] = 0;
        }

        $_SESSION['carrito'][$id] += $cantidad;
        $_SESSION['carrito_tallas'][$id] = $talla;

        header("Location: carrito.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($prod['nombre']); ?></title>
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime('estilos.css'); ?>">
</head>
<body>
<?php include("header.php"); ?>

<div class="container producto-page">

    <aside class="sidebar">
        <h2>Categorías</h2>
        <ul>
            <?php
            $sql = "SELECT * FROM categorias";
            $result = $conn->query($sql);

            while ($cat = $result->fetch_assoc()) {
                echo "<li>
                        <a href='categoria.php?id={$cat['id_categoria']}'>
                            {$cat['nombre']}
                        </a>
                      </li>";
            }
            ?>
        </ul>
    </aside>

    <main class="content">
        <div class="producto-detalle">
            <div class="producto-detalle-imagen">
                <img src='<?php echo htmlspecialchars($prod['imagen_url']); ?>' alt='<?php echo htmlspecialchars($prod['nombre']); ?>' class='producto-img'>
            </div>

            <div class="producto-detalle-info">
                <span class="producto-kicker"><?php echo htmlspecialchars($nombre_categoria_producto); ?></span>
                <h1><?php echo htmlspecialchars($prod['nombre']); ?></h1>
                <p><?php echo htmlspecialchars($prod['descripcion']); ?></p>
                <p class="producto-detalle-precio"><?php echo $prod['precio']; ?> €</p>

                <?php if ($mensaje_producto !== ""): ?>
                    <div class="checkout-error"><?php echo htmlspecialchars($mensaje_producto); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION['usuario'])): ?>

                <form method="post" class="producto-compra">
                    <label>
                        Talla
                        <select name="talla" required>
                            <?php $primera_disponible = true; ?>
                            <?php foreach ($tallas_disponibles as $talla): ?>
                                <?php
                                $disponible = isset($variantes[$talla]) && intval($variantes[$talla]['stock_disponible']) > 0;
                                $texto_talla = strtoupper($talla) . ($disponible ? "" : " - agotada");
                                $selected = $disponible && $primera_disponible ? "selected" : "";

                                if ($disponible) {
                                    $primera_disponible = false;
                                }
                                ?>
                                <option value="<?php echo $talla; ?>" <?php echo $selected; ?> <?php echo $disponible ? "" : "disabled class='talla-agotada'"; ?>>
                                    <?php echo $texto_talla; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        Cantidad
                        <input type="number" name="cantidad" value="1" min="1">
                    </label>

                    <button class='ver-producto' type="submit" name="comprar" <?php echo $hay_tallas_disponibles ? "" : "disabled"; ?>>
                        <?php echo $hay_tallas_disponibles ? "Añadir al carrito" : "Agotado"; ?>
                    </button>
                </form>

                <form method="post" class="favorite-form">
                    <button class="ver-producto favorito-btn" type="submit" name="favorito">
                        <?php echo $es_favorito ? "♥ Guardado en favoritos" : "♡ Guardar favorito"; ?>
                    </button>
                </form>

                <?php else: ?>

                <p>Debes iniciar sesión para añadir productos al carrito.</p>
                <a class="ver-producto" href="login.php">Iniciar sesión</a>

                <?php endif; ?>

                <a class='ver-producto' href='index.php'> Volver al inicio </a>
            </div>
        </div>

        <?php if ($recomendados && $recomendados->num_rows > 0): ?>
            <section class="recomendados">
                <div class="recomendados-heading">
                    <h2>Productos recomendados</h2>
                </div>
                <div class="recomendados-grid">
                    <?php while ($rec = $recomendados->fetch_assoc()): ?>
                        <article class="recomendado-card">
                            <img src="<?php echo htmlspecialchars($rec['imagen_url']); ?>" alt="<?php echo htmlspecialchars($rec['nombre']); ?>" class="producto-img">
                            <div>
                                <h3><?php echo htmlspecialchars($rec['nombre']); ?></h3>
                                <p><?php echo $rec['precio']; ?> €</p>
                                <a class="ver-producto" href="producto.php?id=<?php echo $rec['id_producto']; ?>">Ver producto</a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>

</div>

</body>
</html>
