<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const els = document.querySelectorAll(".animar-scroll");
      function mostrar() {
        els.forEach((el,i) => {
          if (el.getBoundingClientRect().top < window.innerHeight - 80)
            setTimeout(() => el.classList.add("visible"), i * 140);
        });
      }
      window.addEventListener("scroll", mostrar);
      mostrar();
    });
  </script>
</head>
<body>

<?php include 'header.php'; ?>

<main>
  <section class="hero">
    <div class="hero-content">
      <div class="hero-top">
        <h1 class="hero-titulo">PROMETEO VTC</h1>
        <div class="hero-separador"></div>
        <p class="hero-subtitulo" style="margin-top:10px;">Servicio privado de Transporte VTC en Málaga</p>
      </div>
      <div class="hero-bottom">
        <p class="hero-descripcion">
          Reserva tu servicio de forma sencilla y recibe una experiencia
          de transporte al nivel de tus exigencias y adaptada a tus necesidades.
        </p>
        <div class="hero-buttons">
          <a href="reserva.php" class="btn primary">Reservar ahora</a>
          <?php if (isset($_SESSION['id_usuario'])): ?>
            <a href="lista_reservas.php" class="btn secondary">Mis reservas</a>
          <?php else: ?>
            <a href="login.php" class="btn secondary">Iniciar sesión</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="features animar-scroll">
    <h2>¿Qué ofrece Prometeo VTC?</h2>
    <div class="feature-grid">
      <div class="card animar-scroll">
        <h3>Reserva rápida</h3>
        <p>Crea tu reserva en pocos pasos indicando origen, destino, fecha y hora.</p>
      </div>
      <div class="card animar-scroll">
        <h3>Precio estimado</h3>
        <p>Consulta el coste del trayecto en función de la distancia antes de confirmar.</p>
      </div>
      <div class="card animar-scroll">
        <h3>Gestión sencilla</h3>
        <p>Accede a tus reservas, revisa tus datos y consulta tu historial fácilmente.</p>
      </div>
    </div>
  </section>

  <section class="steps animar-scroll">
    <h2>¿Cómo funciona?</h2>
    <div class="steps-grid">
      <div class="step animar-scroll"><span>1</span><h3>Regístrate</h3><p>Crea una cuenta para acceder a las funciones de la plataforma.</p></div>
      <div class="step animar-scroll"><span>2</span><h3>Indica tu trayecto</h3><p>Introduce los datos del servicio: origen, destino, fecha y pasajeros.</p></div>
      <div class="step animar-scroll"><span>3</span><h3>Confirma tu reserva</h3><p>Guarda la reserva y consulta su estado desde tu perfil.</p></div>
    </div>
  </section>
</main>

<footer class="footer">
  <p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p>
</footer>
</body>
</html>
