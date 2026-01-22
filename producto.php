<?php
include("config/db.php");
session_start();
$id_producto = $_GET['id'];

$sql = "SELECT * FROM productos WHERE id_producto = $id_producto";
$prod = $conn->query($sql)->fetch_assoc();
if ($_POST) {
    $id = $prod['id_producto'];
    $cantidad = $_POST['cantidad'];

    if (!isset($_SESSION['carrito'][$id])) {
        $_SESSION['carrito'][$id] = 0;
    }
    $_SESSION['carrito'][$id] += $cantidad;

    header("Location: carrito.php");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $prod['nombre']; ?></title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<header class="header">
    
    <h1 class="title">Style boutique</h1>
    <div class="auth-buttons">
        <?php
        if (isset($_SESSION['usuario'])) {
            echo "<span>Bienvenido, {$_SESSION['usuario']['nombre']}</span>";
            echo "<a class='btn' href='carrito.php'>Carrito</a>";
            echo "<a class='btn' href='logout.php'>Cerrar sesión</a>";
        } else {
            echo "<a class='btn' href='login.php'>Login</a>";
            echo "<a class='btn' href='registro.php'>Registro</a>";
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
    <h1><?php echo $prod['nombre']; ?></h1>
        <p><?php echo $prod['descripcion']; ?></p>
        <p><strong>Precio:</strong> <?php echo $prod['precio']; ?> €</p>
        <p><strong>Talla:</strong> <?php echo $prod['talla']; ?></p>
        <img src='<?php echo $prod['imagen_url']; ?>' alt='<?php echo $prod['nombre']; ?>' class='producto-img'>

        <form method="post">
            <label>Cantidad:</label>
            <input type="number" name="cantidad" value="1" min="1">
            <button class='ver-producto' type="submit">Añadir al carrito</button>
        </form>

        <a class='ver-producto' href='index.php'> Volver al inicio </a>
    </main>

</div>



</body>
</html>
