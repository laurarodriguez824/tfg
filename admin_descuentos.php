<?php
session_start();
include("config/db.php");
include("helpers.php");

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 'admin') {
    header("Location: index.php");
    exit;
}

asegurarTablasDescuentos($conn);
$mensaje_admin = "";

if (isset($_POST['agregar_descuento'])) {
    $codigo = strtoupper(trim($_POST['codigo']));
    $porcentaje = max(0, min(100, intval($_POST['porcentaje'])));

    if ($codigo === "") {
        $mensaje_admin = "Introduce un código de descuento.";
    } else {
        $codigo_sql = $conn->real_escape_string($codigo);
        $sql = "INSERT INTO descuentos (codigo, porcentaje, activo, fecha_creacion)
                VALUES ('$codigo_sql', $porcentaje, 1, NOW())";
        $mensaje_admin = $conn->query($sql)
            ? "Código de descuento creado correctamente"
            : "No se ha podido crear el código. Revisa que no exista ya.";
    }
}

if (isset($_POST['guardar_descuento'])) {
    $id_descuento = intval($_POST['guardar_descuento']);
    $codigo = strtoupper(trim($_POST['descuentos'][$id_descuento]['codigo']));
    $porcentaje = max(0, min(100, intval($_POST['descuentos'][$id_descuento]['porcentaje'])));
    $activo = isset($_POST['descuentos'][$id_descuento]['activo']) ? 1 : 0;

    if ($codigo === "") {
        $mensaje_admin = "El código no puede quedar vacío.";
    } else {
        $codigo_sql = $conn->real_escape_string($codigo);
        $sql = "UPDATE descuentos
                SET codigo='$codigo_sql', porcentaje=$porcentaje, activo=$activo
                WHERE id_descuento=$id_descuento";
        $mensaje_admin = $conn->query($sql)
            ? "Código actualizado correctamente"
            : "No se ha podido actualizar el código.";
    }
}

if (isset($_POST['eliminar_descuento'])) {
    $id_descuento = intval($_POST['eliminar_descuento']);
    $conn->query("DELETE FROM usuario_descuentos WHERE id_descuento=$id_descuento");
    $mensaje_admin = $conn->query("DELETE FROM descuentos WHERE id_descuento=$id_descuento")
        ? "Código eliminado correctamente"
        : "No se ha podido eliminar el código.";
}

$descuentos = $conn->query("SELECT * FROM descuentos ORDER BY fecha_creacion DESC, id_descuento DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar descuentos</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<main class="admin-container">
    <?php if ($mensaje_admin !== ""): ?>
        <div class="admin-message"><?php echo htmlspecialchars($mensaje_admin); ?></div>
    <?php endif; ?>

    <section class="admin-hero">
        <div>
            <h2>Administrar descuentos</h2>
        </div>
        <a class="btn btn-secundario" href="admin_panel.php">Volver al panel</a>
    </section>

    <section class="admin-card">
        <div class="admin-section-title">
            <h3>Crear código</h3>
        </div>

        <form method="post" class="admin-form">
            <label>
                Código
                <input type="text" name="codigo" placeholder="STYLE-10" required>
            </label>
            <label>
                Descuento (%)
                <input type="number" name="porcentaje" min="0" max="100" value="10" required>
            </label>
            <button class="btn agregar" type="submit" name="agregar_descuento">Crear código</button>
        </form>
    </section>

    <section class="admin-card">
        <div class="admin-section-title">
            <h3>Códigos disponibles</h3>
        </div>

        <form method="post">
            <div class="admin-table-wrap">
                <table class="admin-table admin-discounts-table">
                    <tr>
                        <th>ID</th>
                        <th>Código</th>
                        <th>Descuento</th>
                        <th>Activo</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                    <?php if ($descuentos && $descuentos->num_rows > 0): ?>
                        <?php while ($descuento = $descuentos->fetch_assoc()): ?>
                            <?php $id_descuento = intval($descuento['id_descuento']); ?>
                            <tr>
                                <td class="admin-id">#<?php echo $id_descuento; ?></td>
                                <td>
                                    <input type="text"
                                           name="descuentos[<?php echo $id_descuento; ?>][codigo]"
                                           value="<?php echo htmlspecialchars($descuento['codigo']); ?>">
                                </td>
                                <td>
                                    <input type="number"
                                           name="descuentos[<?php echo $id_descuento; ?>][porcentaje]"
                                           min="0"
                                           max="100"
                                           value="<?php echo intval($descuento['porcentaje']); ?>">
                                </td>
                                <td>
                                    <label class="admin-check">
                                        <input type="checkbox"
                                               name="descuentos[<?php echo $id_descuento; ?>][activo]"
                                               <?php echo intval($descuento['activo']) === 1 ? 'checked' : ''; ?>>
                                        Activo
                                    </label>
                                </td>
                                <td><?php echo htmlspecialchars($descuento['fecha_creacion']); ?></td>
                                <td class="admin-actions">
                                    <button class="btn editar" type="submit" name="guardar_descuento" value="<?php echo $id_descuento; ?>">Guardar</button>
                                    <button class="btn eliminar" type="submit" name="eliminar_descuento" value="<?php echo $id_descuento; ?>" onclick="return confirm('¿Eliminar este código?')">Eliminar</button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="admin-empty">No hay códigos de descuento todavía.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </form>
    </section>
</main>

</body>
</html>
