<?php
include("config/db.php");

$id_categoria = $_GET['id'];

$sql_cat = "SELECT * FROM categorias WHERE id_categoria = $id_categoria";
$categ = $conn->query($sql_cat)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo $cat['nombre']; ?></title>
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
        <h2><?php echo $categ['nombre']; ?></h2>
        <div class="productos">
            <?php
            
            $sql = "SELECT * FROM productos 
                    WHERE id_categoria = $id_categoria ";
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
                </div>
                ";
            }
            ?>
        </div>
        <a class='ver-producto' href='index.php'>
        Volver al inicio
                    </a>
    </main>

    </div>





