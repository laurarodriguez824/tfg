<?php
session_start();
include("config/db.php");

if ($_POST) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM usuarios WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['contraseña'])) {
            $_SESSION['usuario'] = $user;
            header("Location: index.php");
        } else {
            echo "Contraseña incorrecta";
        }
    } else {
        echo "Usuario no encontrado";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
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
        <h1>Login</h1>

    <form method="post">
        <input type="email" name="email" placeholder="Email" required><br>
        <input type="password" name="password" placeholder="Contraseña" required><br>
        <button type="submit" class='ver-producto' >Entrar</button>
    </form>


    <a class='ver-producto' href='registro.php'> Crear cuenta </a>
    </main>

</div>


</body>
</html>
