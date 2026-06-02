<?php
session_start();
include("config/db.php");
include("helpers.php");

// PROTEGER SOLO ADMIN
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] != 'admin') {
    header("Location: index.php");
    exit;
}

$categoria_filtro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$categorias_result = $conn->query("SELECT * FROM categorias ORDER BY nombre");
$categorias = [];

while ($cat = $categorias_result->fetch_assoc()) {
    $categorias[] = $cat;
}

$mensaje_admin = "";

function guardarImagenSubida($campo)
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($_FILES[$campo]['size'] > 5 * 1024 * 1024) {
        return false;
    }

    $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($extension, $extensiones_permitidas)) {
        return false;
    }

    $info_imagen = getimagesize($_FILES[$campo]['tmp_name']);

    if (!$info_imagen) {
        return false;
    }

    $mime_permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!in_array($info_imagen['mime'], $mime_permitidos)) {
        return false;
    }

    $carpeta_destino = __DIR__ . "/img/uploads";

    if (!is_dir($carpeta_destino)) {
        mkdir($carpeta_destino, 0755, true);
    }

    $nombre_archivo = uniqid("producto_", true) . "." . $extension;
    $ruta_destino = $carpeta_destino . "/" . $nombre_archivo;

    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $ruta_destino)) {
        return false;
    }

    return "img/uploads/" . $nombre_archivo;
}

function guardarImagenSubidaFila($campo, $id)
{
    if (!isset($_FILES[$campo]['name'][$id]) || $_FILES[$campo]['error'][$id] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$campo]['error'][$id] !== UPLOAD_ERR_OK || $_FILES[$campo]['size'][$id] > 5 * 1024 * 1024) {
        return false;
    }

    $extension = strtolower(pathinfo($_FILES[$campo]['name'][$id], PATHINFO_EXTENSION));
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($extension, $extensiones_permitidas)) {
        return false;
    }

    $info_imagen = getimagesize($_FILES[$campo]['tmp_name'][$id]);
    $mime_permitidos = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!$info_imagen || !in_array($info_imagen['mime'], $mime_permitidos)) {
        return false;
    }

    $carpeta_destino = __DIR__ . "/img/uploads";

    if (!is_dir($carpeta_destino)) {
        mkdir($carpeta_destino, 0755, true);
    }

    $nombre_archivo = uniqid("producto_", true) . "." . $extension;
    $ruta_destino = $carpeta_destino . "/" . $nombre_archivo;

    if (!move_uploaded_file($_FILES[$campo]['tmp_name'][$id], $ruta_destino)) {
        return false;
    }

    return "img/uploads/" . $nombre_archivo;
}

function actualizarProductoAdmin($conn, $id, $datos)
{
    global $categorias;

    $nombre = $conn->real_escape_string($datos['nombre']);
    $descripcion = $conn->real_escape_string($datos['descripcion']);
    $precio = floatval($datos['precio']);
    $imagen = trim($datos['imagen_url']);
    $nueva_imagen = guardarImagenSubidaFila('imagen_archivo', $id);
    $categoria = intval($datos['categoria']);
    $stock = isset($datos['stock']) ? max(0, intval($datos['stock'])) : 0;
    $nombre_categoria = '';

    foreach ($categorias as $cat) {
        if (intval($cat['id_categoria']) === $categoria) {
            $nombre_categoria = $cat['nombre'];
            break;
        }
    }

    $tallas_permitidas = opcionesTallaPorCategoria($nombre_categoria);
    $talla = isset($datos['talla']) && in_array($datos['talla'], $tallas_permitidas) ? $datos['talla'] : '';

    if ($nueva_imagen === false) {
        return "Selecciona una imagen válida para actualizar el producto #$id";
    }

    if ($nueva_imagen !== null) {
        $imagen = $nueva_imagen;
    }

    if ($imagen === '') {
        return "La imagen no puede quedar vacía en el producto #$id";
    }

    if (!preg_match('/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $imagen)) {
        return "La URL del producto #$id debe apuntar a una imagen jpg, png, webp o gif";
    }

    $imagen = $conn->real_escape_string($imagen);

    $sql = "UPDATE productos SET
            nombre='$nombre',
            descripcion='$descripcion',
            precio='$precio',
            imagen_url='$imagen',
            id_categoria='$categoria',
            talla='$talla'
            WHERE id_producto='$id'";

    if (!$conn->query($sql)) {
        return "Error al actualizar el producto #$id";
    }

    ajustarStockProducto($conn, $id, $stock);
    return "";
}

/* =========================
   ELIMINAR PRODUCTO
========================= */
if (isset($_GET['eliminar'])) {

    $id = intval($_GET['eliminar']);

    $sql = "DELETE FROM productos WHERE id_producto=$id";

    if ($conn->query($sql)) {
        $mensaje_admin = "Producto eliminado correctamente";
    } else {
        $mensaje_admin = "Error al eliminar";
    }
}

if (isset($_POST['eliminar_seleccionados']) && !empty($_POST['seleccionados'])) {
    $ids = array_map('intval', $_POST['seleccionados']);
    $ids = array_filter($ids, function ($id) {
        return $id > 0;
    });

    if (!empty($ids)) {
        $ids_sql = implode(',', $ids);
        $conn->query("DELETE FROM productos WHERE id_producto IN ($ids_sql)");
        $mensaje_admin = count($ids) . " producto(s) eliminado(s) correctamente";
    }
}

/* =========================
   AÑADIR PRODUCTO
========================= */
if (isset($_POST['agregar'])) {

    $nombre = $conn->real_escape_string($_POST['nombre']);
    $precio = $_POST['precio'];
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $imagen = guardarImagenSubida('imagen_archivo');
    $imagen_url = isset($_POST['imagen_url']) ? trim($_POST['imagen_url']) : '';
    $categoria = $_POST['categoria'];
    $stock = isset($_POST['stock']) ? intval($_POST['stock']) : 0;
    $tallas = isset($_POST['tallas']) ? $_POST['tallas'] : [];
    $tallas_permitidas = array_merge(opcionesTallaPorCategoria('ropa'), opcionesTallaPorCategoria('zapatos'));

    if ($imagen === false) {
        $mensaje_admin = "Selecciona una imagen válida para añadir el producto";
    } else {
        if ($imagen === null) {
            $imagen = $imagen_url;
        }

        if ($imagen === '') {
            $mensaje_admin = "Añade una URL de imagen o selecciona un archivo";
        }
    }

    if ($mensaje_admin === "") {
        $imagen = $conn->real_escape_string($imagen);
    
        if (!preg_match('/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $imagen)) {
            $mensaje_admin = "La URL debe apuntar a una imagen jpg, png, webp o gif";
        }
    }

    if ($mensaje_admin === "") {
        $categoria_nombre = '';

        foreach ($categorias as $cat) {
            if (intval($cat['id_categoria']) === intval($categoria)) {
                $categoria_nombre = $cat['nombre'];
                break;
            }
        }

        $tallas = array_values(array_intersect(opcionesTallaPorCategoria($categoria_nombre), $tallas));

        if (empty($tallas)) {
            $mensaje_admin = "Selecciona al menos una talla disponible";
        }
    }

    if ($mensaje_admin === "") {
        $precio = floatval($precio);
        $categoria = intval($categoria);
        $stock = max(0, $stock);
        $productos_creados = 0;

        foreach ($tallas as $talla) {
            $talla = $conn->real_escape_string($talla);

            $sql = "INSERT INTO productos
                    (nombre, descripcion, precio, stock, talla, imagen_url, id_categoria, fecha_creacion)
                    VALUES
                    ('$nombre', '$descripcion', '$precio', '$stock', '$talla', '$imagen', '$categoria', NOW())";

            if ($conn->query($sql)) {
                registrarEntradaInventario($conn, $conn->insert_id, $stock);
                $productos_creados++;
            }
        }

        $mensaje_admin = $productos_creados > 0
            ? "Producto añadido correctamente en $productos_creados talla(s)"
            : "Error al añadir producto";
    }
}

/* =========================
   EDITAR PRODUCTO
========================= */
if (isset($_POST['editar']) && isset($_POST['productos'])) {

    $id = intval($_POST['editar']);

    if (isset($_POST['productos'][$id])) {
        $mensaje_admin = actualizarProductoAdmin($conn, $id, $_POST['productos'][$id]);

        if ($mensaje_admin === "") {
            $mensaje_admin = "Producto actualizado correctamente";
        }
    }
}

if (isset($_POST['guardar_seleccionados']) && isset($_POST['productos'])) {
    $seleccionados = isset($_POST['seleccionados']) ? array_map('intval', $_POST['seleccionados']) : [];
    $actualizados = 0;

    foreach ($seleccionados as $id) {
        if (isset($_POST['productos'][$id])) {
            $error = actualizarProductoAdmin($conn, $id, $_POST['productos'][$id]);

            if ($error !== "") {
                $mensaje_admin = $error;
                break;
            }

            $actualizados++;
        }
    }

    if ($mensaje_admin === "") {
        $mensaje_admin = $actualizados > 0
            ? "$actualizados producto(s) guardado(s) correctamente"
            : "Selecciona al menos un producto";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar productos</title>
    <link rel="stylesheet" href="estilos.css">

</head>
<body>

<?php include("header.php"); ?>

<div class="admin-container">
    <?php if ($mensaje_admin !== ""): ?>
        <div class="admin-message">
            <?php echo $mensaje_admin; ?>
        </div>
    <?php endif; ?>

    <section class="admin-hero">
        <div>
            <h2>Administrar productos</h2>
        </div>
        <span class="admin-badge">Panel privado</span>
    </section>

    <section class="admin-card">
        <div class="admin-section-title">
            <div>
                <h3>Añadir producto</h3>
            </div>
        </div>

        <form method="POST" class="admin-form" enctype="multipart/form-data">

            <label>
                Nombre
                <input type="text"
                       name="nombre"
                       placeholder="Vestido midi"
                       required>
            </label>

            <label>
                Precio
                <input type="number"
                       step="0.01"
                       name="precio"
                       placeholder="49.99"
                       required>
            </label>

            <label>
                Categoría
                <select name="categoria" required>
                    <option value="">Selecciona categoría</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id_categoria']; ?>">
                            <?php echo htmlspecialchars($cat['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="admin-form-wide">
                Descripción
                <textarea name="descripcion"
                          rows="3"
                          placeholder="Describe el producto"></textarea>
            </label>

            <fieldset class="admin-size-field admin-form-wide">
                <legend>Tallas disponibles</legend>
                <label>
                    <input type="checkbox" name="tallas[]" value="xs">
                    XS
                </label>
                <label>
                    <input type="checkbox" name="tallas[]" value="s">
                    S
                </label>
                <label>
                    <input type="checkbox" name="tallas[]" value="m">
                    M
                </label>
                <label>
                    <input type="checkbox" name="tallas[]" value="l">
                    L
                </label>
                <label>
                    <input type="checkbox" name="tallas[]" value="xl">
                    XL
                </label>
                <?php foreach (opcionesTallaPorCategoria('zapatos') as $numero_zapato): ?>
                    <label>
                        <input type="checkbox" name="tallas[]" value="<?php echo $numero_zapato; ?>">
                        <?php echo $numero_zapato; ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <label>
                URL imagen
                <input type="text"
                       name="imagen_url"
                       placeholder="img/producto.jpg">
            </label>

            <label>
                Stock
                <input type="number"
                       name="stock"
                       min="0"
                       value="0"
                       required>
            </label>

            <label>
                Subir imagen
                <input type="file"
                       name="imagen_archivo"
                       accept="image/*"
                       data-file-label="Seleccionar imagen">
            </label>

            <button class="btn agregar"
                    type="submit"
                    name="agregar">

                Añadir producto

            </button>

        </form>
    </section>

    <section class="admin-card">
        <div class="admin-section-title">
            <div>
                <h3>Productos</h3>
            </div>
            <div class="admin-bulk-actions">
                <button class="btn editar" type="submit" name="guardar_seleccionados" form="admin-products-form">
                    Guardar seleccionados
                </button>
                <button class="btn eliminar" type="submit" name="eliminar_seleccionados" form="admin-products-form" data-bulk-delete>
                    Eliminar seleccionados
                </button>
            </div>
        </div>

        <form method="GET" class="admin-filter">
            <label>
                Ver productos de
                <select name="categoria">
                    <option value="0">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id_categoria']; ?>"
                            <?php echo $categoria_filtro == $cat['id_categoria'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['nombre']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button class="btn" type="submit">Filtrar</button>
            <a class="btn btn-secundario" href="admin_productos.php">Ver todos</a>
        </form>

        <form method="POST" id="admin-products-form" class="admin-products-form" enctype="multipart/form-data">
        <div class="admin-table-wrap">
            <table class="admin-table admin-products-table">

                <tr>
                    <th>
                        <input type="checkbox" id="seleccionar-todos" aria-label="Seleccionar todos">
                    </th>
                    <th>ID</th>
                    <th>Imagen y URL</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Precio</th>
                    <th>Talla</th>
                    <th>Categoría</th>
                    <th>Stock</th>
                    <th>Nueva imagen</th>
                    <th>Acciones</th>
                </tr>

                <?php

                if ($categoria_filtro > 0) {
                    $sql = "SELECT * FROM productos WHERE id_categoria = $categoria_filtro";
                } else {
                    $sql = "SELECT * FROM productos";
                }
                $result = $conn->query($sql);

                if ($result->num_rows > 0):
                while($prod = $result->fetch_assoc()){
                    $id_producto_fila = intval($prod['id_producto']);
                    $stock_fila = stockDisponible($conn, $id_producto_fila, $prod['stock']);

                ?>

                <tr>
                    <td>
                        <input type="checkbox"
                               class="producto-selector"
                               name="seleccionados[]"
                               value="<?php echo $id_producto_fila; ?>"
                               aria-label="Seleccionar producto <?php echo $id_producto_fila; ?>">
                    </td>

                    <td class="admin-id">

                        #<?php echo $prod['id_producto']; ?>

                    </td>

                    <td class="admin-image-cell">

                        <img src="<?php echo htmlspecialchars($prod['imagen_url']); ?>" alt="<?php echo htmlspecialchars($prod['nombre']); ?>">
                        <input type="text"
                               class="admin-image-url"
                               name="productos[<?php echo $id_producto_fila; ?>][imagen_url]"
                               value="<?php echo htmlspecialchars($prod['imagen_url']); ?>"
                               aria-label="URL de la imagen">

                    </td>

                    <td class="admin-name-cell">

                        <input type="text"
                               name="productos[<?php echo $id_producto_fila; ?>][nombre]"
                               value="<?php echo htmlspecialchars($prod['nombre']); ?>">

                    </td>

                    <td class="admin-description-cell">

                        <textarea name="productos[<?php echo $id_producto_fila; ?>][descripcion]"
                                  rows="3"><?php echo htmlspecialchars($prod['descripcion'] ?? ''); ?></textarea>

                    </td>

                    <td class="admin-price-cell">

                        <input type="number"
                               step="0.01"
                               name="productos[<?php echo $id_producto_fila; ?>][precio]"
                               value="<?php echo $prod['precio']; ?>">

                    </td>

                    <td class="admin-size-cell">

                        <select name="productos[<?php echo $id_producto_fila; ?>][talla]">
                            <?php
                            $categoria_fila_nombre = '';
                            foreach ($categorias as $cat) {
                                if (intval($cat['id_categoria']) === intval($prod['id_categoria'])) {
                                    $categoria_fila_nombre = $cat['nombre'];
                                    break;
                                }
                            }
                            ?>
                            <?php foreach (opcionesTallaPorCategoria($categoria_fila_nombre) as $talla_opcion): ?>
                                <option value="<?php echo $talla_opcion; ?>"
                                    <?php echo ($prod['talla'] ?? '') == $talla_opcion ? 'selected' : ''; ?>>
                                    <?php echo esCategoriaZapatos($categoria_fila_nombre) ? $talla_opcion : strtoupper($talla_opcion); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </td>

                    <td>

                        <select name="productos[<?php echo $id_producto_fila; ?>][categoria]">
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id_categoria']; ?>"
                                    <?php echo $prod['id_categoria'] == $cat['id_categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </td>

                    <td class="admin-stock-cell">

                        <input type="number"
                               name="productos[<?php echo $id_producto_fila; ?>][stock]"
                               min="0"
                               value="<?php echo $stock_fila; ?>">

                    </td>

                    <td>

                        <input type="file"
                               name="imagen_archivo[<?php echo $id_producto_fila; ?>]"
                               accept="image/*">

                    </td>

                    <td class="admin-actions">

                        <button class="btn editar"
                                type="submit"
                                name="editar"
                                value="<?php echo $id_producto_fila; ?>">

                            Guardar

                        </button>

                        <a class="btn eliminar"
                           href="admin_productos.php?eliminar=<?php echo $prod['id_producto']; ?><?php echo $categoria_filtro > 0 ? '&categoria=' . $categoria_filtro : ''; ?>"
                           data-delete-product="<?php echo htmlspecialchars($prod['nombre']); ?>">

                            Eliminar

                        </a>

                    </td>

                </tr>

                <?php
                }
                else:
                ?>

                <tr>
                    <td colspan="11" class="admin-empty">
                        No hay productos en esta categoría.
                    </td>
                </tr>

                <?php endif; ?>

            </table>
        </div>
        </form>
    </section>

</div>

<div class="modal-backdrop" id="delete-modal" hidden>
    <div class="delete-modal" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
        <h3 id="delete-modal-title">Eliminar producto</h3>
        <p>¿Seguro que quieres eliminar este producto?</p>
        <strong id="delete-product-name"></strong>
        <div class="delete-modal-actions">
            <button class="btn btn-secundario" type="button" id="cancel-delete">Cancelar</button>
            <a class="btn eliminar" href="#" id="confirm-delete">Eliminar</a>
        </div>
    </div>
</div>

<script>
const deleteModal = document.getElementById('delete-modal');
const deleteName = document.getElementById('delete-product-name');
const confirmDelete = document.getElementById('confirm-delete');
const cancelDelete = document.getElementById('cancel-delete');
const seleccionarTodos = document.getElementById('seleccionar-todos');
const selectoresProducto = document.querySelectorAll('.producto-selector');
const bulkDeleteButton = document.querySelector('[data-bulk-delete]');

document.querySelectorAll('[data-delete-product]').forEach((link) => {
    link.addEventListener('click', (event) => {
        event.preventDefault();
        deleteName.textContent = link.dataset.deleteProduct;
        confirmDelete.href = link.href;
        deleteModal.hidden = false;
    });
});

if (cancelDelete) {
    cancelDelete.addEventListener('click', () => {
        deleteModal.hidden = true;
    });
}

if (deleteModal) {
    deleteModal.addEventListener('click', (event) => {
        if (event.target === deleteModal) {
            deleteModal.hidden = true;
        }
    });
}

if (seleccionarTodos) {
    seleccionarTodos.addEventListener('change', () => {
        selectoresProducto.forEach((checkbox) => {
            checkbox.checked = seleccionarTodos.checked;
        });
    });
}

if (bulkDeleteButton) {
    bulkDeleteButton.addEventListener('click', (event) => {
        const seleccionados = document.querySelectorAll('.producto-selector:checked');

        if (seleccionados.length === 0 || !confirm('¿Seguro que quieres eliminar los productos seleccionados?')) {
            event.preventDefault();
        }
    });
}

selectoresProducto.forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
        if (!checkbox.checked && seleccionarTodos) {
            seleccionarTodos.checked = false;
        }
    });
});
</script>

</body>
</html>
