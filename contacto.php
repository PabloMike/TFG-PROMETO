<?php
session_start();
$enviado=false; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nombre=trim($_POST['nombre']??''); $email=trim($_POST['email']??'');
    $asunto=trim($_POST['asunto']??''); $mensaje=trim($_POST['mensaje']??'');
    if($nombre===''||$email===''||$asunto===''||$mensaje==='') $error='Todos los campos son obligatorios.';
    else $enviado=true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contacto - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <script>
    document.addEventListener('DOMContentLoaded',function(){
      var els=document.querySelectorAll('.animar-scroll');
      function mostrar(){ els.forEach(function(el,i){ if(el.getBoundingClientRect().top<window.innerHeight-80) setTimeout(function(){ el.classList.add('visible'); },i*130); }); }
      window.addEventListener('scroll',mostrar); mostrar();
    });
  </script>
</head>
<body>
<?php include 'header.php'; ?>
<div class="contacto-top">
  <p>¿Tienes alguna pregunta?</p>
  <h1>Contacto</h1>
</div>
<div class="contacto-bg">
  <div class="contacto-grid">
    <div class="contacto-info">
      <h2 class="animar-scroll">Información</h2>
      <div class="info-item animar-scroll"><div class="icono">📍</div><div class="texto"><strong>Ubicación</strong><span>Málaga, Andalucía, España</span></div></div>
      <div class="info-item animar-scroll"><div class="icono">📞</div><div class="texto"><strong>Teléfono</strong><span>+34 952 241 780</span></div></div>
      <div class="info-item animar-scroll"><div class="icono">✉️</div><div class="texto"><strong>Email</strong><span>infovtc@prometeo-vtc.es</span></div></div>
      <div class="info-item animar-scroll"><div class="icono">🕐</div><div class="texto"><strong>Horario de atención</strong><span>Lunes a domingo, 24 horas</span></div></div>
    </div>
    <div class="contacto-form-wrap animar-scroll">
      <?php if($enviado): ?>
        <div class="caja-formulario" style="text-align:center;">
          <div style="font-size:3.5rem;margin-bottom:18px;">✅</div>
          <h1 style="font-size:1.8rem;">Mensaje enviado</h1>
          <p style="color:#555;margin:14px 0 28px;font-size:15px;">Gracias por contactarnos. Te responderemos en el menor tiempo posible.</p>
          <a href="inicio.php" class="btn primary">Volver al inicio</a>
        </div>
      <?php else: ?>
        <div class="caja-formulario">
          <h1>Envíanos un mensaje</h1>
          <p class="subtitulo-form">Responderemos a la mayor brevedad posible</p>
          <?php if($error!==''): ?><div class="alerta alerta-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
          <form method="post">
            <div><label>Nombre</label><input type="text" name="nombre" required placeholder="Tu nombre" value="<?php echo isset($_SESSION['nombre'])?htmlspecialchars($_SESSION['nombre']):''; ?>"></div>
            <div><label>Correo electrónico</label><input type="email" name="email" required placeholder="tucorreo@email.com" value="<?php echo isset($_SESSION['email'])?htmlspecialchars($_SESSION['email']):''; ?>"></div>
            <div><label>Asunto</label><input type="text" name="asunto" required placeholder="¿En qué podemos ayudarte?"></div>
            <div><label>Mensaje</label><textarea name="mensaje" required placeholder="Escribe aquí tu consulta..." style="min-height:130px;"></textarea></div>
            <button type="submit" class="btn primary">Enviar mensaje</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<footer class="footer"><p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p></footer>
</body>
</html>
