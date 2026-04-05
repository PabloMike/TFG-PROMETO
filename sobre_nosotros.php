<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sobre nosotros - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <script>
    function toggleMenu(){
      var d=document.getElementById('dropdown');
      if(d){ d.classList.contains('visible')?d.classList.remove('visible'):d.classList.add('visible'); }
    }
    document.addEventListener('click',function(e){
      var menu=document.querySelector('.user-menu');
      var d=document.getElementById('dropdown');
      if(d&&menu&&!menu.contains(e.target)) d.classList.remove('visible');
    });
    document.addEventListener('DOMContentLoaded',function(){
      var els=document.querySelectorAll('.animar-scroll');
      function mostrar(){
        els.forEach(function(el,i){
          var r=el.getBoundingClientRect();
          if(r.top<window.innerHeight-80) setTimeout(function(){ el.classList.add('visible'); },i*130);
        });
      }
      window.addEventListener('scroll',mostrar); mostrar();
    });
  </script>
</head>
<body>

<?php include 'header.php'; ?>

<!-- HERO -->
<div class="sobre-hero">
  <p>Transporte privado de confianza en Málaga</p>
  <h1>Sobre Prometeo VTC</h1>
</div>

<!-- CONTENIDO -->
<div class="sobre-bg">

  <div class="sobre-contenido animar-scroll">
    <h2>¿Quiénes somos?</h2>
    <p>
      Prometeo VTC es una empresa de transporte de viajeros con licencia VTC (Vehículo de Transporte con Conductor)
      con base en Málaga. Nacimos con el objetivo de ofrecer una alternativa de movilidad privada, cómoda y segura
      para particulares, empresas y visitantes de la Costa del Sol.
    </p>
    <p>
      Nuestro equipo está formado por conductores profesionales con experiencia, comprometidos con la puntualidad
      y la discreción. Cada trayecto se adapta a las necesidades del cliente, desde traslados al aeropuerto
      hasta servicios de empresa o eventos especiales.
    </p>
  </div>

  <div class="sobre-contenido animar-scroll">
    <h2>Nuestra misión</h2>
    <p>
      Proporcionar un servicio de transporte privado que supere las expectativas de nuestros clientes en
      comodidad, seguridad y atención personalizada. Creemos que moverse por Málaga debe ser una experiencia
      agradable, no un trámite.
    </p>
  </div>

  <!-- VALORES -->
  <div class="sobre-valores">
    <div class="valor-card animar-scroll">
      <div class="icono">🛡️</div>
      <h3>Seguridad</h3>
      <p>Vehículos en perfecto estado y conductores certificados para garantizar cada trayecto.</p>
    </div>
    <div class="valor-card animar-scroll">
      <div class="icono">⏱️</div>
      <h3>Puntualidad</h3>
      <p>Nos tomamos en serio tu tiempo. Siempre en el lugar acordado cuando nos necesitas.</p>
    </div>
    <div class="valor-card animar-scroll">
      <div class="icono">✨</div>
      <h3>Confort</h3>
      <p>Flota de vehículos premium para que cada viaje sea una experiencia a tu altura.</p>
    </div>
    <div class="valor-card animar-scroll">
      <div class="icono">🤝</div>
      <h3>Cercanía</h3>
      <p>Trato personalizado y atención al cliente en todo momento, antes y después del viaje.</p>
    </div>
  </div>

  <div class="sobre-cta animar-scroll">
    <a href="reserva.php" class="btn primary" style="padding:16px 40px;font-size:16px;">Reservar ahora</a>
  </div>

</div>

<footer class="footer">
  <p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p>
</footer>
</body>
</html>
