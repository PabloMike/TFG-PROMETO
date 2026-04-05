<?php
session_start();
if (isset($_SESSION['id_usuario'])) { header("Location: inicio.php"); exit; }

$errores = '';
$aviso   = '';
if (isset($_GET['mensaje']) && $_GET['mensaje'] === 'debes_login') {
    $aviso = 'Para realizar una reserva primero debes iniciar sesión.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errores = 'Debes introducir email y contraseña.';
    } else {
        $conexion = new mysqli("localhost","root","","vtc");
        if ($conexion->connect_error) die("Error: " . $conexion->connect_error);

        $stmt = $conexion->prepare("SELECT id,nombre,email,password FROM usuarios WHERE email=?");
        $stmt->bind_param("s",$email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();

        if ($usuario && password_verify($password, $usuario['password'])) {
            $_SESSION['id_usuario'] = $usuario['id'];
            $_SESSION['nombre']     = $usuario['nombre'];
            $_SESSION['email']      = $usuario['email'];
            header("Location: inicio.php"); exit;
        } else {
            $errores = 'Credenciales incorrectas.';
        }
        $stmt->close(); $conexion->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <script>
    function toggleMenu(){
      var d=document.getElementById('dropdown');
      if(d){ d.classList.contains('visible')?d.classList.remove('visible'):d.classList.add('visible'); }
    }
  </script>
</head>
<body>

<?php include 'header.php'; ?>

<div class="pagina-interior">
  <div class="caja-formulario">
    <h1>Iniciar sesión</h1>
    <p class="subtitulo-form">Accede a tu cuenta de Prometeo VTC</p>

    <?php if ($aviso !== ''): ?>
      <div class="alerta alerta-aviso"><?php echo htmlspecialchars($aviso); ?></div>
    <?php endif; ?>
    <?php if ($errores !== ''): ?>
      <div class="alerta alerta-error"><?php echo htmlspecialchars($errores); ?></div>
    <?php endif; ?>

    <form action="login.php" method="post">
      <div>
        <label for="email">Correo electrónico</label>
        <input type="email" name="email" id="email" required autocomplete="email" placeholder="tucorreo@email.com">
      </div>
      <div>
        <label for="password">Contraseña</label>
        <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn primary">Entrar</button>
    </form>

    <p class="enlace-pie">¿No tienes cuenta? <a href="registro.php">Registrarse</a></p>
  </div>
</div>

<footer class="footer">
  <p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p>
</footer>
</body>
</html>
