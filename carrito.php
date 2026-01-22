<?php
session_start();
include("config/db.php");
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
    <h1>Carrito de compra</h1>

        <?php
        $total = 0;

        if (!empty($_SESSION['carrito'])) {
            foreach ($_SESSION['carrito'] as $id_producto => $cantidad) {
                $sql = "SELECT * FROM productos WHERE id_producto = $id_producto";
                $prod = $conn->query($sql)->fetch_assoc();

                $subtotal = $prod['precio'] * $cantidad;
                $total += $subtotal;

                echo "
                <div>
                    <h3>{$prod['nombre']}</h3>
                    <p>Precio: {$prod['precio']} €</p>
                    <p>Cantidad: $cantidad</p>
                    <p>Subtotal: $subtotal €</p>
                </div>
                ";
            }

            echo "<h2>Total: $total €</h2>";
            echo "<button class='ver-producto'>Finalizar compra </button>";
        } else {
            echo "<p>El carrito está vacío</p>";
        }
        ?>

        <a class='ver-producto' href="index.php">Seguir comprando</a>
    </main>

</div>






</body>
</html>
