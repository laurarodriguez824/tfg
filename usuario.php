<?php
include("config/db.php");
include("helpers.php");
session_start();

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = intval($_SESSION['usuario']['id_usuario']);
asegurarTablasDescuentos($conn);
asegurarTablaPuntos($conn);
asegurarColumnasUsuarioContacto($conn);
$mensaje_descuento = "";
$descuento_ganado = null;
$mensaje_puntos = "";
$mensaje_perfil = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_perfil'])) {
    $nombre = $conn->real_escape_string(trim($_POST['nombre']));
    $email = $conn->real_escape_string(trim($_POST['email']));
    $telefono = $conn->real_escape_string(trim($_POST['telefono']));
    $direccion = $conn->real_escape_string(trim($_POST['direccion']));

    if ($nombre === "" || $email === "") {
        $mensaje_perfil = "Nombre y email son obligatorios.";
    } else {
        $sql_perfil = "UPDATE usuarios
                       SET nombre='$nombre', email='$email', telefono='$telefono', direccion='$direccion'
                       WHERE id_usuario=$id_usuario";

        if ($conn->query($sql_perfil)) {
            $_SESSION['usuario']['nombre'] = $nombre;
            $_SESSION['usuario']['email'] = $email;
            $_SESSION['usuario']['telefono'] = $telefono;
            $_SESSION['usuario']['direccion'] = $direccion;
            $mensaje_perfil = "Datos personales actualizados.";
        } else {
            $mensaje_perfil = "No se han podido actualizar tus datos.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['canjear_puntos'])) {
    $resultado_canje = canjearPuntosDescuento($conn, $id_usuario, $_POST['porcentaje_descuento']);
    $mensaje_puntos = $resultado_canje['mensaje'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['girar_ruleta'])) {
    $giro_hoy = $conn->query("SELECT ud.*, d.codigo, d.porcentaje
                              FROM usuario_descuentos ud
                              JOIN descuentos d ON ud.id_descuento = d.id_descuento
                              WHERE ud.id_usuario = $id_usuario
                              AND ud.fecha_obtenido = CURDATE()
                              AND ud.origen = 'ruleta'
                              LIMIT 1");

    if ($giro_hoy && $giro_hoy->num_rows > 0) {
        $descuento_ganado = $giro_hoy->fetch_assoc();
        $mensaje_descuento = "Ya has girado hoy. Tu código de hoy es " . $descuento_ganado['codigo'] . ".";
    } else {
        $opciones = [0, 5, 10, 15, 20];
        $porcentaje = $opciones[array_rand($opciones)];
        $nuevo_descuento = crearCodigoDescuento($conn, $porcentaje, "RULETA");
        $id_descuento = intval($nuevo_descuento['id_descuento']);

        $conn->query("INSERT INTO usuario_descuentos (id_usuario, id_descuento, fecha_obtenido, usado, origen)
                      VALUES ($id_usuario, $id_descuento, CURDATE(), 0, 'ruleta')");

        $descuento_ganado = [
            'codigo' => $nuevo_descuento['codigo'],
            'porcentaje' => $nuevo_descuento['porcentaje']
        ];
        $mensaje_descuento = $porcentaje > 0
            ? "Has ganado un descuento del $porcentaje%. Usa el código " . $nuevo_descuento['codigo'] . " al pagar."
            : "Hoy no ha tocado descuento, pero mañana puedes volver a girar.";
    }
}

$descuentos_usuario = $conn->query("SELECT d.codigo, d.porcentaje, ud.fecha_obtenido, ud.usado
                                    FROM usuario_descuentos ud
                                    JOIN descuentos d ON ud.id_descuento = d.id_descuento
                                    WHERE ud.id_usuario = $id_usuario
                                    AND ud.usado = 0
                                    AND d.activo = 1
                                    ORDER BY ud.fecha_obtenido DESC, ud.id_usuario_descuento DESC");
$puntos_disponibles = puntosUsuario($conn, $id_usuario);
$usuario_actual = $conn->query("SELECT * FROM usuarios WHERE id_usuario=$id_usuario")->fetch_assoc();

// Obtener pedidos del usuario
$sql_pedidos = "SELECT * FROM pedidos WHERE id_usuario = $id_usuario ORDER BY fecha_pedido DESC";
$result_pedidos = $conn->query($sql_pedidos);

// Obtener categorías para la barra lateral
$sql_cats = "SELECT * FROM categorias";
$result_cats = $conn->query($sql_cats);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis pedidos</title>
    <link rel="stylesheet" href="estilos.css?v=<?php echo filemtime('estilos.css'); ?>">
</head>
<body>

<?php include("header.php"); ?>

<div class="container">

    <aside class="sidebar">
        <h2>Categorías</h2>
        <ul>
            <?php
            while ($cat = $result_cats->fetch_assoc()) {
                echo "<li><a href='categoria.php?id={$cat['id_categoria']}'>{$cat['nombre']}</a></li>";
            }
            ?>
        </ul>
    </aside>

    <main class="content">
        <nav class="account-tabs">
            <a href="#datos"><span>Perfil</span></a>
            <a href="#descuentos"><span>Descuentos</span></a>
            <a href="#puntos"><span>Puntos</span></a>
            <a href="#pedidos"><span>Pedidos</span></a>
        </nav>

        <div class="account-grid">
        <section class="descuentos-panel account-card" id="datos">
            <div class="descuentos-header">
                <div>
                    <span>Mi cuenta</span>
                    <h2>Datos personales</h2>
                </div>
            </div>

            <?php if ($mensaje_perfil !== ""): ?>
                <div class="admin-message"><?php echo htmlspecialchars($mensaje_perfil); ?></div>
            <?php endif; ?>

            <form method="post" class="perfil-form">
                <label>
                    Nombre
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario_actual['nombre'] ?? ''); ?>" required>
                </label>
                <label>
                    Email
                    <input type="email" name="email" value="<?php echo htmlspecialchars($usuario_actual['email'] ?? ''); ?>" required>
                </label>
                <label>
                    Teléfono
                    <input type="text" name="telefono" value="<?php echo htmlspecialchars($usuario_actual['telefono'] ?? ''); ?>">
                </label>
                <label>
                    Dirección
                    <input type="text" name="direccion" value="<?php echo htmlspecialchars($usuario_actual['direccion'] ?? ''); ?>">
                </label>
                <button class="ver-producto" type="submit" name="guardar_perfil">Guardar datos</button>
            </form>
        </section>

        <section class="descuentos-panel account-card" id="descuentos">
            <div class="descuentos-header">
                <div>
                    <span>Mis descuentos</span>
                    <h2>Ruleta diaria</h2>
                </div>
                <form method="post">
                    <button class="ver-producto" type="submit" name="girar_ruleta">Girar ruleta</button>
                </form>
            </div>

            <div class="ruleta-wrap">
                <div class="ruleta"
                     data-porcentaje="<?php echo $descuento_ganado ? intval($descuento_ganado['porcentaje']) : ''; ?>">
                    <span>0%</span>
                    <span>5%</span>
                    <span>10%</span>
                    <span>15%</span>
                    <span>20%</span>
                </div>
                <div class="ruleta-puntero"></div>
            </div>

            <?php if ($mensaje_descuento !== ""): ?>
                <div class="admin-message descuento-resultado" hidden><?php echo htmlspecialchars($mensaje_descuento); ?></div>
            <?php endif; ?>

            <div class="descuento-codigos">
                <?php if ($descuentos_usuario && $descuentos_usuario->num_rows > 0): ?>
                    <?php while ($descuento = $descuentos_usuario->fetch_assoc()): ?>
                        <div class="descuento-codigo">
                            <strong><?php echo htmlspecialchars($descuento['codigo']); ?></strong>
                            <span><?php echo intval($descuento['porcentaje']); ?>% descuento</span>
                            <em><?php echo intval($descuento['usado']) === 1 ? 'Usado' : 'Disponible'; ?></em>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No tienes descuentos todavía.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="descuentos-panel account-card" id="puntos">
            <div class="descuentos-header">
                <div>
                    <span>Mis puntos</span>
                    <h2><?php echo number_format($puntos_disponibles, 0, ',', '.'); ?> puntos</h2>
                </div>
            </div>

            <?php if ($mensaje_puntos !== ""): ?>
                <div class="admin-message"><?php echo htmlspecialchars($mensaje_puntos); ?></div>
            <?php endif; ?>

            <form method="post" class="puntos-form">
                <label>
                    Canjear por descuento
                    <select name="porcentaje_descuento">
                        <option value="5">5% - 5.000 puntos</option>
                        <option value="10">10% - 10.000 puntos</option>
                        <option value="15">15% - 15.000 puntos</option>
                        <option value="20">20% - 20.000 puntos</option>
                    </select>
                </label>
                <button class="ver-producto" type="submit" name="canjear_puntos">Canjear puntos</button>
            </form>
        </section>
        </div>

        <section class="account-orders" id="pedidos">
        <h2>Mis pedidos</h2>

        <?php
        if ($result_pedidos->num_rows > 0) {
            while ($pedido = $result_pedidos->fetch_assoc()) {
                $id_pedido = $pedido['id_pedido'];
                echo "<div class='pedido'>";
                echo "<h3>Pedido #{$id_pedido} - Fecha: {$pedido['fecha_pedido']}</h3>";
                echo "<p class='estado'>Estado: {$pedido['estado']}</p>";

                $estado_actual = strtolower($pedido['estado']);
                $pagado_activo = in_array($estado_actual, ['pagado', 'enviado', 'entregado']);
                $enviado_activo = in_array($estado_actual, ['enviado', 'entregado']);
                $entregado_activo = $estado_actual === 'entregado';

                echo "<div class='pedido-seguimiento'>
                        <div class='track-step active'>
                            <span></span>
                            <p>Pedido recibido</p>
                        </div>
                        <div class='track-step " . ($pagado_activo ? "active" : "") . "'>
                            <span></span>
                            <p>Pagado</p>
                        </div>
                        <div class='track-step " . ($enviado_activo ? "active" : "") . "'>
                            <span></span>
                            <p>Enviado</p>
                        </div>
                        <div class='track-step " . ($entregado_activo ? "active" : "") . "'>
                            <span></span>
                            <p>Entregado</p>
                        </div>
                      </div>";

                // Detalle de productos
                $sql_detalle = "SELECT dp.*, p.nombre, p.talla
                                FROM detalle_pedido dp 
                                JOIN productos p ON dp.id_producto = p.id_producto
                                WHERE dp.id_pedido = $id_pedido";
                $result_detalle = $conn->query($sql_detalle);

                echo "<div class='detalle-productos'>";
                while ($det = $result_detalle->fetch_assoc()) {
                    $subtotal = $det['cantidad'] * $det['precio_unitario'];
                    echo "<div class='detalle-producto'>
                            <span>{$det['nombre']}" . (!empty($det['talla']) ? " · " . strtoupper($det['talla']) : "") . " x {$det['cantidad']}</span>
                            <span>{$det['precio_unitario']} € c/u</span>
                            <span>Subtotal: {$subtotal} €</span>
                          </div>";
                }
                echo "</div>";

                if (!empty($pedido['descuento_codigo'])) {
                    echo "<div class='pedido-descuento'>Descuento aplicado: {$pedido['descuento_codigo']} ({$pedido['descuento_porcentaje']}%)</div>";
                }

                echo "<div class='pedido-total'>Total: {$pedido['total']} €</div>";
                echo "<a class='ver-producto' href='factura.php?id={$id_pedido}'>Descargar factura</a>";
                echo "</div>";
            }
        } else {
            echo "<p>No has realizado pedidos todavía.</p>";
        }
        ?>
        </section>

        <a class='ver-producto' href='index.php'>Volver al inicio</a>
    </main>

</div>

<script>
const ruleta = document.querySelector('.ruleta[data-porcentaje]');
const resultadoDescuento = document.querySelector('.descuento-resultado');

if (ruleta && ruleta.dataset.porcentaje !== '') {
    const centros = {
        '0': 36,
        '5': 108,
        '10': 180,
        '15': 252,
        '20': 324
    };
    const centro = centros[ruleta.dataset.porcentaje] || 36;
    const giroFinal = 1440 + 270 - centro;

    requestAnimationFrame(() => {
        ruleta.style.transform = `rotate(${giroFinal}deg)`;
    });

    ruleta.addEventListener('transitionend', () => {
        if (resultadoDescuento) {
            resultadoDescuento.hidden = false;
        }
    }, { once: true });
}
</script>

</body>
</html>
