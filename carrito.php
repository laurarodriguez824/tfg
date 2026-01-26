<?php
session_start();
include("config/db.php");

// Procesar actualización de cantidades
if (isset($_POST['actualizar'])) {
    foreach ($_POST['cantidades'] as $id_producto => $cantidad) {
        if ($cantidad > 0) {
            $_SESSION['carrito'][$id_producto] = $cantidad;
        } else {
            unset($_SESSION['carrito'][$id_producto]);
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
    }
    header("Location: carrito.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito</title>
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
                $total += $subtotal;

                echo "
                <div class='producto-carrito'>
                    <h3>{$prod['nombre']}</h3>
                    <p>Precio: {$prod['precio']} €</p>
                    <p>
                        Cantidad: 
                        <input type='number' name='cantidades[$id_producto]' value='$cantidad' min='0'>
                        <a href='carrito.php?eliminar=$id_producto' class='ver-producto'>Eliminar</a>
                    </p>
                    <p>Subtotal: $subtotal €</p>
                </div>
                ";
            }

            echo "<h2>Total: $total €</h2>";
            echo "<button type='submit' name='actualizar' class='ver-producto'>Actualizar carrito</button>";
            echo "<button class='ver-producto'>Finalizar compra</button>";
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
