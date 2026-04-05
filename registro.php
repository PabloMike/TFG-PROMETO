<?php
session_start();
if (isset($_SESSION['id_usuario'])) { header("Location: inicio.php"); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password']      ?? '';

    if ($nombre===''||$email===''||$telefono===''||$password==='') {
        $error = "Todos los campos son obligatorios.";
    } else {
        $pass_hash = password_hash($password, PASSWORD_DEFAULT);
        $conexion  = new mysqli("localhost","root","","vtc");
        if ($conexion->connect_error) die("Error: " . $conexion->connect_error);

        $stmt = $conexion->prepare("INSERT INTO usuarios (nombre,email,telefono,password) VALUES (?,?,?,?)");
        if (!$stmt) die("Error: " . $conexion->error);
        $stmt->bind_param("ssss",$nombre,$email,$telefono,$pass_hash);

        if ($stmt->execute()) {
            $_SESSION['id_usuario'] = $conexion->insert_id;
            $_SESSION['nombre']     = $nombre;
            $_SESSION['email']      = $email;
            header("Location: inicio.php"); exit;
        } else {
            $error = "Error al registrar. Es posible que el email ya esté en uso.";
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
  <title>Crear cuenta - Prometeo VTC</title>
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
    <h1>Crear cuenta</h1>
    <p class="subtitulo-form">Únete a Prometeo VTC y gestiona tus reservas</p>

    <?php if ($error !== ''): ?>
      <div class="alerta alerta-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post">
      <div>
        <label>Nombre completo</label>
        <input type="text" name="nombre" required autocomplete="name" placeholder="Tu nombre">
      </div>
      <div>
        <label>Correo electrónico</label>
        <input type="email" name="email" required autocomplete="email" placeholder="tucorreo@email.com">
      </div>
      <div>
        <label>Teléfono</label>
        <input type="tel" name="telefono" required autocomplete="tel" placeholder="+34 600 000 000">
      </div>
      <div>
        <label>Contraseña</label>
        <input type="password" name="password" required autocomplete="new-password" placeholder="••••••••">
      </div>
      <button type="submit" class="btn primary">Registrarse</button>
    </form>

    <p class="enlace-pie">¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>
  </div>
</div>

<footer class="footer">
  <p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p>
</footer>
</body>
</html>
