<?php
include("config/db.php");

if ($_POST) {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $sql = "INSERT INTO usuarios (nombre, email, contraseña, rol, fecha_registro)
            VALUES ('$nombre', '$email', '$password', 'cliente', NOW())";

    if ($conn->query($sql)) {
        header("Location: login.php");
    } else {
        echo "Error al registrar usuario";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<header class="header">
    
    <h1 class="title">Style boutique</h1>
    <div class="auth-buttons">
        <?php
        if (isset($_SESSION['usuario'])) {
            echo "<a class='ver-producto' href='usuario.php' >Bienvenido, {$_SESSION['usuario']['nombre']}</a>";
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
    <h1>Registro</h1>

        <form method="post">
        <input type="text" name="nombre" placeholder="Nombre" required><br>
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Contraseña" required><br>
        <button class='ver-producto' type="submit">Registrarse</button>
        </form>

        <a class='ver-producto' href="login.php">¿Ya tienes cuenta?</a>
    </main>

</div>




</body>
</html>
