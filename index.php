<?php
include("config/db.php");
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Style boutique</title>
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
        <h2>Productos destacados</h2>
        <div class="productos">
            <?php
            $sql = "SELECT * FROM productos LIMIT 20";
            $result = $conn->query($sql);

            while ($prod = $result->fetch_assoc()) {
                echo "
                <div class='producto'>
                    <h3>{$prod['nombre']}</h3>
                    <img src='{$prod['imagen_url']}' alt='{$prod['nombre']}' class='producto-img'>
                    <p>{$prod['precio']} €</p>
                    <a class='ver-producto' href='producto.php?id={$prod['id_producto']}'>
                        Ver producto
                    </a>
                </div>";
            }
            ?>
        </div>
    </main>

</div>




</body>
</html>
