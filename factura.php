<?php
session_start();
include("config/db.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = intval($_SESSION['usuario']['id_usuario']);
$es_admin = isset($_SESSION['usuario']['rol']) && $_SESSION['usuario']['rol'] === 'admin';
$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;
$where_usuario = $es_admin ? "" : "AND p.id_usuario = $id_usuario";

$sql_pedido = "SELECT p.*, u.nombre, u.email
               FROM pedidos p
               JOIN usuarios u ON p.id_usuario = u.id_usuario
               WHERE p.id_pedido = $id_pedido
               $where_usuario";
$pedido = $conn->query($sql_pedido)->fetch_assoc();

if (!$pedido) {
    echo "Factura no disponible";
    exit();
}

$sql_detalle = "SELECT dp.*, pr.nombre, pr.talla, pr.imagen_url
                FROM detalle_pedido dp
                JOIN productos pr ON dp.id_producto = pr.id_producto
                WHERE dp.id_pedido = $id_pedido";
$detalles = $conn->query($sql_detalle);
$items = [];

while ($detalle = $detalles->fetch_assoc()) {
    $items[] = $detalle;
}

function pdfText($texto)
{
    $texto = iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
}

function pdfLine($texto, $x, $y, $size = 11, $color = '0 0 0')
{
    return "BT\n$color rg\n/F1 $size Tf\n$x $y Td\n(" . pdfText($texto) . ") Tj\nET\n";
}

function pdfRect($x, $y, $w, $h, $color)
{
    return "$color rg\n$x $y $w $h re f\n";
}

function localJpegPath($imagen)
{
    if (!$imagen || preg_match('/^https?:\/\//i', $imagen)) {
        return null;
    }

    $ruta = __DIR__ . "/" . ltrim($imagen, "/");
    $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

    if (!file_exists($ruta) || !in_array($extension, ['jpg', 'jpeg'])) {
        return null;
    }

    return $ruta;
}

$contenido = "";
$contenido .= pdfRect(0, 760, 595, 82, "0.13 0.12 0.11");
$contenido .= pdfRect(0, 735, 595, 25, "0.61 0.25 0.29");
$contenido .= pdfLine("Style Boutique", 46, 800, 26, "1 0.98 0.96");
$contenido .= pdfLine("Factura de compra", 46, 775, 13, "1 0.90 0.82");
$contenido .= pdfLine("Pedido #" . $pedido['id_pedido'], 415, 800, 16, "1 0.98 0.96");
$contenido .= pdfLine($pedido['fecha_pedido'], 415, 777, 10, "1 0.90 0.82");

$direccion_envio = isset($pedido['direccion']) && trim($pedido['direccion']) !== '' ? $pedido['direccion'] : 'No indicada';
if (strlen($direccion_envio) > 62) {
    $direccion_envio = substr($direccion_envio, 0, 59) . '...';
}

$contenido .= pdfRect(42, 635, 245, 78, "0.98 0.96 0.93");
$contenido .= pdfRect(308, 635, 245, 78, "0.98 0.96 0.93");
$contenido .= pdfLine("Cliente", 58, 693, 10, "0.44 0.15 0.19");
$contenido .= pdfLine($pedido['nombre'], 58, 675, 13, "0.13 0.12 0.11");
$contenido .= pdfLine($pedido['email'], 58, 661, 9, "0.46 0.43 0.40");
$contenido .= pdfLine("Direccion de envio", 58, 647, 9, "0.44 0.15 0.19");
$contenido .= pdfLine($direccion_envio, 58, 633, 8, "0.46 0.43 0.40");
$contenido .= pdfLine("Estado", 324, 693, 10, "0.44 0.15 0.19");
$contenido .= pdfLine(ucfirst($pedido['estado']), 324, 675, 13, "0.13 0.12 0.11");
$contenido .= pdfLine("Subtotal: " . number_format($pedido['subtotal'] ?? $pedido['total'], 2) . " EUR", 324, 661, 10, "0.46 0.43 0.40");

if (!empty($pedido['descuento_codigo'])) {
    $contenido .= pdfLine("Descuento: " . $pedido['descuento_codigo'] . " (" . intval($pedido['descuento_porcentaje']) . "%)", 324, 647, 9, "0.44 0.15 0.19");
}

$contenido .= pdfLine("Total: " . number_format($pedido['total'], 2) . " EUR", 324, 633, 13, "0.09 0.44 0.42");

$contenido .= pdfLine("Detalle del pedido", 42, 595, 16, "0.44 0.15 0.19");
$contenido .= pdfRect(42, 570, 511, 22, "0.13 0.12 0.11");
$contenido .= pdfLine("Producto", 104, 577, 9, "1 1 1");
$contenido .= pdfLine("Cant.", 370, 577, 9, "1 1 1");
$contenido .= pdfLine("Precio", 425, 577, 9, "1 1 1");
$contenido .= pdfLine("Subtotal", 490, 577, 9, "1 1 1");

$imagenes = [];
$y = 515;
$imagen_index = 1;

foreach ($items as $item) {
    $subtotal = $item['cantidad'] * $item['precio_unitario'];
    $talla = $item['talla'] ? "Talla " . strtoupper($item['talla']) : "";
    $contenido .= pdfRect(42, $y - 8, 511, 56, "1 0.99 0.98");

    $jpeg = localJpegPath($item['imagen_url']);

    if ($jpeg) {
        $info = getimagesize($jpeg);

        if ($info) {
            $nombre_imagen = "Im" . $imagen_index++;
            $channels = isset($info['channels']) ? intval($info['channels']) : 3;
            $imagenes[$nombre_imagen] = [
                'path' => $jpeg,
                'width' => $info[0],
                'height' => $info[1],
                'colorspace' => $channels === 1 ? '/DeviceGray' : '/DeviceRGB'
            ];
            $contenido .= "q\n42 0 0 50 50 " . ($y - 2) . " cm\n/$nombre_imagen Do\nQ\n";
        }
    } else {
        $contenido .= pdfRect(50, $y - 2, 42, 50, "0.93 0.89 0.85");
        $contenido .= pdfLine("IMG", 60, $y + 20, 9, "0.46 0.43 0.40");
    }

    $contenido .= pdfLine($item['nombre'], 104, $y + 31, 11, "0.13 0.12 0.11");
    $contenido .= pdfLine($talla, 104, $y + 15, 9, "0.46 0.43 0.40");
    $contenido .= pdfLine($item['cantidad'], 374, $y + 22, 10, "0.13 0.12 0.11");
    $contenido .= pdfLine(number_format($item['precio_unitario'], 2), 425, $y + 22, 10, "0.13 0.12 0.11");
    $contenido .= pdfLine(number_format($subtotal, 2), 492, $y + 22, 10, "0.13 0.12 0.11");

    $y -= 64;
}

$contenido .= pdfRect(350, 86, 203, 58, "0.09 0.44 0.42");
$contenido .= pdfLine("Total pagado", 370, 122, 10, "1 0.98 0.96");
$contenido .= pdfLine(number_format($pedido['total'], 2) . " EUR", 370, 100, 20, "1 1 1");
$contenido .= pdfLine("Gracias por comprar en Style Boutique.", 42, 55, 10, "0.46 0.43 0.40");

$objetos = [];
$objetos[] = "<< /Type /Catalog /Pages 2 0 R >>";
$objetos[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
$objetos[] = null;
$objetos[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
$objetos[] = "<< /Length " . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream";

$image_object_numbers = [];

foreach ($imagenes as $nombre => $imagen) {
    $image_object_numbers[$nombre] = count($objetos) + 1;
    $data = file_get_contents($imagen['path']);
    $objetos[] = "<< /Type /XObject /Subtype /Image /Width {$imagen['width']} /Height {$imagen['height']} /ColorSpace {$imagen['colorspace']} /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
}

$xobjects = "";

foreach ($image_object_numbers as $nombre => $numero) {
    $xobjects .= "/$nombre $numero 0 R ";
}

$objetos[2] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> /XObject << $xobjects >> >> /Contents 5 0 R >>";

$pdf = "%PDF-1.4\n";
$offsets = [0];

foreach ($objetos as $indice => $objeto) {
    $offsets[] = strlen($pdf);
    $pdf .= ($indice + 1) . " 0 obj\n" . $objeto . "\nendobj\n";
}

$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objetos) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";

for ($i = 1; $i <= count($objetos); $i++) {
    $pdf .= str_pad($offsets[$i], 10, "0", STR_PAD_LEFT) . " 00000 n \n";
}

$pdf .= "trailer\n<< /Size " . (count($objetos) + 1) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n" . $xref . "\n%%EOF";

header("Content-Type: application/pdf");
header("Content-Disposition: attachment; filename=factura_pedido_" . $pedido['id_pedido'] . ".pdf");
header("Content-Length: " . strlen($pdf));
echo $pdf;
exit();
