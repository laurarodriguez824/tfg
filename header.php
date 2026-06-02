<header class="header">
    <div class="header-actions header-actions-left">
        <?php if (isset($_SESSION['usuario'])): ?>
            <a class="ver-producto" href="usuario.php">
                Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario']['nombre']); ?>
            </a>
            <a class="ver-producto" href="carrito.php">Carrito</a>
            <a class="ver-producto" href="favoritos.php">♥</a>
        <?php endif; ?>
    </div>

    <h1 class="title">
        <a href="index.php">Style Boutique</a>
    </h1>

    <div class="header-actions header-actions-right">
        <?php if (isset($_SESSION['usuario'])): ?>
            <?php if ($_SESSION['usuario']['rol'] == 'admin'): ?>
                <a class="ver-producto" href="admin_panel.php">
                    Panel administrador
                </a>
            <?php endif; ?>
            <a class="ver-producto" href="logout.php">Cerrar sesión</a>
        <?php else: ?>
            <a class="ver-producto" href="login.php">Login</a>
            <a class="ver-producto" href="registro.php">Registro</a>
        <?php endif; ?>
    </div>
</header>
