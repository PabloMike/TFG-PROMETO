<?php // header.php - incluir con session_start() ya activo ?>
<header class="header">
  <a href="inicio.php" class="logo">
    <img src="img/logo.png" alt="Prometeo VTC">
  </a>

  <nav class="nav">
    <a href="reserva.php" class="nav-reservar">Reservar</a>
    <?php if (isset($_SESSION['id_usuario'])): ?>
      <a href="lista_reservas.php">Mis reservas</a>
    <?php endif; ?>
    <a href="sobre_nosotros.php">Sobre nosotros</a>
    <a href="contacto.php">Contacto</a>
  </nav>

  <div class="auth-buttons">
    <?php if (isset($_SESSION['id_usuario'])): ?>
      <div class="user-menu">
        <button class="btn primary" onclick="toggleMenu()">
          <?php echo htmlspecialchars($_SESSION['nombre']); ?>
        </button>
        <div class="dropdown" id="dropdown">
          <a href="lista_reservas.php">📋 Mis reservas</a>
          <a href="perfil.php">⚙️ Ajustes de cuenta</a>
          <hr class="dropdown-sep">
          <a href="#" class="dropdown-danger" onclick="mostrarPopupCerrar(event)">🚪 Cerrar sesión</a>
        </div>
      </div>
    <?php else: ?>
      <a href="login.php" class="btn secondary">Iniciar sesión</a>
      <a href="registro.php" class="btn primary">Registrarse</a>
    <?php endif; ?>
  </div>
</header>

<!-- POP-UP CERRAR SESIÓN -->
<div class="popup-overlay" id="popup-cerrar">
  <div class="popup-caja">
    <h2>¿Cerrar sesión?</h2>
    <p>¿Estás seguro de que quieres cerrar tu sesión en Prometeo VTC?</p>
    <div class="popup-botones">
      <a href="cerrar_sesion.php" class="btn danger">Sí, cerrar sesión</a>
      <button class="btn secondary" onclick="cerrarPopup()">Cancelar</button>
    </div>
  </div>
</div>

<script>
function toggleMenu() {
  var d = document.getElementById('dropdown');
  if (!d) return;
  if (d.classList.contains('visible')) {
    d.style.opacity='0'; d.style.transform='translateY(-8px)';
    setTimeout(()=>d.classList.remove('visible'),250);
  } else {
    d.classList.add('visible');
    setTimeout(()=>{ d.style.opacity='1'; d.style.transform='translateY(0)'; },10);
  }
}
document.addEventListener('click',function(e){
  var menu=document.querySelector('.user-menu');
  var d=document.getElementById('dropdown');
  if(d&&menu&&!menu.contains(e.target)){
    d.style.opacity='0'; d.style.transform='translateY(-8px)';
    setTimeout(()=>d.classList.remove('visible'),250);
  }
});
function mostrarPopupCerrar(e){
  e.preventDefault();
  document.getElementById('popup-cerrar').classList.add('activo');
}
function cerrarPopup(){
  document.getElementById('popup-cerrar').classList.remove('activo');
}
document.getElementById('popup-cerrar').addEventListener('click',function(e){
  if(e.target===this) cerrarPopup();
});
</script>
