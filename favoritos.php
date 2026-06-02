<?php
session_start();
include("config/db.php");
include("helpers.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

asegurarTablaFavoritos($conn);
$id_usuario = intval($_SESSION['usuario']['id_usuario']);

if (isset($_GET['eliminar'])) {
    $id_producto = intval($_GET['eliminar']);
    $conn->query("DELETE FROM favoritos WHERE id_usuario=$id_usuario AND id_producto=$id_producto");
    header("Location: favoritos.php");
    exit();
}

$sql = "SELECT p.*
        FROM favoritos f
        JOIN productos p ON f.id_producto = p.id_producto
        WHERE f.id_usuario = $id_usuario
        ORDER BY f.fecha_creacion DESC";
$favoritos = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis favoritos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>
<?php include("header.php"); ?>

<div class="container favoritos-container">
    <main class="content">
        <h2>Mis favoritos</h2>

        <div class="productos">
            <?php if ($favoritos->num_rows > 0): ?>
                <?php while ($prod = $favoritos->fetch_assoc()): ?>
                    <div class="producto">
                        <h3><?php echo htmlspecialchars($prod['nombre']); ?></h3>
                        <img src="<?php echo htmlspecialchars($prod['imagen_url']); ?>" alt="<?php echo htmlspecialchars($prod['nombre']); ?>" class="producto-img">
                        <p><?php echo $prod['precio']; ?> €</p>
                        <a class="ver-producto" href="producto.php?id=<?php echo $prod['id_producto']; ?>">Ver producto</a>
                        <a class="ver-producto btn-secundario" href="favoritos.php?eliminar=<?php echo $prod['id_producto']; ?>">Quitar</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>Todavía no tienes favoritos.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>
