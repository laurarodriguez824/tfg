<?php
session_start();

$nombre = "";
$email = "";
$mensaje = "";
$mensaje_envio = "No se ha recibido ningun mensaje.";
$correo_enviado = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $mensaje = trim($_POST['mensaje']);
    $destino = "laura.rodriguez33@educa.madrid.org";

    if ($nombre === "" || $email === "" || $mensaje === "") {
        $mensaje_envio = "Completa todos los campos del formulario.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje_envio = "Introduce un correo valido.";
    } else {
        $asunto = "Nuevo mensaje de contacto - Style Boutique";
        $cuerpo = "Nombre: $nombre\n";
        $cuerpo .= "Email: $email\n\n";
        $cuerpo .= "Mensaje:\n$mensaje\n";
        $headers = "From: Style Boutique <no-reply@styleboutique.local>\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $correo_enviado = mail($destino, $asunto, $cuerpo, $headers);
        $mensaje_envio = $correo_enviado
            ? "Hemos recibido tu mensaje correctamente."
            : "Tu servidor no ha podido enviar el correo. Configura SMTP en MAMP o usa PHPMailer.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensaje enviado</title>

    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

<div class="contacto-exito">

    <div class="contacto-card">

        <h2><?php echo $correo_enviado ? "Mensaje enviado" : "Mensaje recibido"; ?></h2>

        <p>
            Gracias <strong><?php echo htmlspecialchars($nombre); ?></strong>,
            <?php echo htmlspecialchars($mensaje_envio); ?>
        </p>

        <a href="index.php" class="btn-volver">
            Volver a la tienda
        </a>

    </div>

</div>

</body>
</html>
