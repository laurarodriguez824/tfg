<?php
include("config/db.php");
session_start();

$id_categoria = $_GET['id'];

$sql_cat = "SELECT * FROM categorias WHERE id_categoria = $id_categoria";
$categ = $conn->query($sql_cat)->fetch_assoc();


$filtro_nombre = isset($_GET['nombre']) ? $_GET['nombre'] : '';
$filtro_precio_min = isset($_GET['precio_min']) ? floatval($_GET['precio_min']) : 0;
$filtro_precio_max = isset($_GET['precio_max']) ? floatval($_GET['precio_max']) : 0;

$where = ["id_categoria = $id_categoria"];
if ($filtro_nombre !== '') {
    $where[] = "nombre LIKE '%$filtro_nombre%'";
}
if ($filtro_precio_min > 0) {
    $where[] = "precio >= $filtro_precio_min";
}
if ($filtro_precio_max > 0) {
    $where[] = "precio <= $filtro_precio_max";
}

$sql = "SELECT * FROM productos WHERE " . implode(" AND ", $where);
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $categ['nombre']; ?></title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<header class="header">
    <h1 class="title">Style boutique</h1>
    <div class="auth-buttons">
        <?php
        if (isset($_SESSION['usuario'])) {
            echo "<a class='ver-producto' href='usuario.php'>Bienvenido, {$_SESSION['usuario']['nombre']}</a>";
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
            $sql = "SELECT * FROM categorias";
            $result_cats = $conn->query($sql);
            while ($cat = $result_cats->fetch_assoc()) {
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
        <h2><?php echo $categ['nombre']; ?></h2>

        <!-- FORMULARIO DE FILTROS DENTRO DEL MAIN -->
        <form method="get" action="categoria.php" class="filtros">
            <input type="hidden" name="id" value="<?php echo $id_categoria; ?>">

            <label>Precio min:</label>
            <input type="number" step="5" name="precio_min" value="<?php echo $filtro_precio_min; ?>">

            <label>Precio max:</label>
            <input type="number" step="5" name="precio_max" value="<?php echo $filtro_precio_max; ?>">

            <button class="ver-producto" type="submit">Filtrar</button>
        </form>

        <div class="productos">
            <?php
            if ($result->num_rows > 0) {
                while ($prod = $result->fetch_assoc()) {
                    echo "
                    <div class='producto'>
                        <h3>{$prod['nombre']}</h3>
                        <img src='{$prod['imagen_url']}' alt='{$prod['nombre']}' class='producto-img'>
                        <p>{$prod['precio']} €</p>
                        <a class='ver-producto' href='producto.php?id={$prod['id_producto']}'>
                            Ver producto
                        </a>
                    </div>
                    ";
                }
            } else {
                echo "<p>No se encontraron productos para estos filtros.</p>";
            }
            ?>
        </div>

        <a class='ver-producto' href='index.php'>Volver al inicio</a>
    </main>

</div>
</body>
</html>
