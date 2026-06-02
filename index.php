<?php
include("config/db.php");
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Style boutique</title>
    <link rel="stylesheet" href="estilos.css">
</head>
<body>

<?php include("header.php"); ?>

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
        <!-- Carrusel de novedades -->

<div class="carrusel">

<div class="slides">

    <div class="slide active">
        <img src="img/novedad1.png" alt="Nueva colección">
        <div class="slide-text">
            <h2>Nueva colección verano</h2>
            <p>Descubre las últimas tendencias</p>
        </div>
    </div>

    <div class="slide">
        <img src="img/novedad2.png" alt="Ofertas">
        <div class="slide-text">
            <h2>Ofertas exclusivas</h2>
            <p>Hasta un 40% de descuento</p>
        </div>
    </div>

    <div class="slide">
        <img src="img/novedad3.png" alt="Moda urbana">
        <div class="slide-text">
            <h2>Moda urbana</h2>
            <p>Estilo moderno para cada día</p>
        </div>
    </div>

</div>

<button class="prev">&#10094;</button>
<button class="next">&#10095;</button>

</div>
        <h2>Productos destacados</h2>
        <div class="search-container">
            <form method="GET" action="">
                <input 
                    type="text" 
                    name="buscar" 
                    placeholder="Buscar prendas..."
                    value="<?php echo isset($_GET['buscar']) ? $_GET['buscar'] : ''; ?>"
                >

                <button type="submit">Buscar</button>
            </form>
        </div>
        <div class="productos">
            <?php
            if (isset($_GET['buscar']) && !empty($_GET['buscar'])) {

                $buscar = $conn->real_escape_string($_GET['buscar']);
            
                $sql = "SELECT MIN(id_producto) AS id_producto,
                               nombre,
                               MIN(precio) AS precio,
                               MAX(imagen_url) AS imagen_url
                        FROM productos
                        WHERE nombre LIKE '%$buscar%'
                        GROUP BY nombre, id_categoria
                        LIMIT 20";
            
            } else {
            
                $sql = "SELECT MIN(id_producto) AS id_producto,
                               nombre,
                               MIN(precio) AS precio,
                               MAX(imagen_url) AS imagen_url
                        FROM productos
                        GROUP BY nombre, id_categoria
                        LIMIT 20";
            }
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {

                while ($prod = $result->fetch_assoc()) {
    
                    echo "
                    <div class='producto'>
                        <h3>{$prod['nombre']}</h3>
    
                        <img src='{$prod['imagen_url']}' 
                             alt='{$prod['nombre']}' 
                             class='producto-img'>
    
                        <p>{$prod['precio']} €</p>
    
                        <a class='ver-producto' 
                           href='producto.php?id={$prod['id_producto']}'>
                            Ver producto
                        </a>
                    </div>";
                }
    
            } else {
    
                echo "<p>No se encontraron productos.</p>";
            }
            ?>
        </div>
    </main>

</div>
<footer class="footer">

    <div class="footer-container">

        <!-- Formulario de contacto -->
        <div class="footer-section">
            <h3>Contacto</h3>

            <form action="enviar_contacto.php" method="POST" class="contact-form">

                <input 
                    type="text" 
                    name="nombre" 
                    placeholder="Tu nombre"
                    required
                >

                <input 
                    type="email" 
                    name="email" 
                    placeholder="Tu email"
                    required
                >

                <textarea 
                    name="mensaje" 
                    placeholder="Escribe tu mensaje..."
                    required
                ></textarea>

                <button type="submit">Enviar</button>

            </form>
        </div>

        <!-- Preguntas frecuentes -->
        <div class="footer-section">
            <h3>Preguntas frecuentes</h3>

            <div class="faq">

                <div class="faq-item">
                    <h4>¿Cómo puedo comprar?</h4>
                    <p>
                        Añade productos al carrito y finaliza la compra desde la sección carrito.
                    </p>
                </div>

                <div class="faq-item">
                    <h4>¿Necesito una cuenta?</h4>
                    <p>
                        Sí, debes registrarte para poder realizar pedidos.
                    </p>
                </div>

                <div class="faq-item">
                    <h4>¿Cómo busco productos?</h4>
                    <p>
                        Usa la barra de búsqueda para encontrar prendas rápidamente.
                    </p>
                </div>

                <div class="faq-item">
                    <h4>¿Puedo cerrar sesión?</h4>
                    <p>
                        Sí, desde el botón "Cerrar sesión" del menú superior.
                    </p>
                </div>

            </div>
        </div>

    </div>

    <div class="footer-bottom">
        <p>© <?php echo date("Y"); ?> Style boutique - Todos los derechos reservados</p>
    </div>

</footer>

<script>

const slides = document.querySelectorAll('.slide');

const nextBtn = document.querySelector('.next');
const prevBtn = document.querySelector('.prev');

let current = 0;

/* MOSTRAR SLIDE */
function showSlide(index){

    slides.forEach(slide => {
        slide.classList.remove('active');
    });

    slides[index].classList.add('active');
}

/* SIGUIENTE */
nextBtn.addEventListener('click', () => {

    current++;

    if(current >= slides.length){
        current = 0;
    }

    showSlide(current);
});

/* ANTERIOR */
prevBtn.addEventListener('click', () => {

    current--;

    if(current < 0){
        current = slides.length - 1;
    }

    showSlide(current);
});

/* AUTOMÁTICO */
setInterval(() => {

    current++;

    if(current >= slides.length){
        current = 0;
    }

    showSlide(current);

}, 4000);

</script>

</body>
</html>
