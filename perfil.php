<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header("Location: login.php"); exit; }

$id_usuario = $_SESSION['id_usuario'];
$conexion   = new mysqli("localhost","root","","vtc");

// Cargar datos usuario
$st = $conexion->prepare("SELECT nombre,email,telefono FROM usuarios WHERE id=?");
$st->bind_param("i",$id_usuario); $st->execute();
$usuario = $st->get_result()->fetch_assoc(); $st->close();

// Cargar direcciones favoritas
$dirs = [];
$st = $conexion->prepare("SELECT id,alias,direccion_completa,ciudad,codigo_postal FROM direcciones WHERE id_usuario=? ORDER BY id ASC");
$st->bind_param("i",$id_usuario); $st->execute();
$res_dirs = $st->get_result();
while ($d = $res_dirs->fetch_assoc()) $dirs[] = $d;
$st->close();

$ok = ''; $error = '';

// ===== ACCIONES POST =====
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $accion = $_POST['accion'] ?? '';

    // --- Datos personales ---
    if ($accion==='datos') {
        $nombre   = trim($_POST['nombre']   ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        if ($nombre==='') { $error='El nombre no puede estar vacío.'; }
        else {
            $st=$conexion->prepare("UPDATE usuarios SET nombre=?,telefono=? WHERE id=?");
            $st->bind_param("ssi",$nombre,$telefono,$id_usuario);
            if($st->execute()){ $_SESSION['nombre']=$nombre; $ok='Datos actualizados correctamente.'; $usuario['nombre']=$nombre; $usuario['telefono']=$telefono; }
            else { $error='Error al guardar: '.$st->error; }
            $st->close();
        }
    }

    // --- Cambiar contraseña ---
    if ($accion==='password') {
        $actual = $_POST['password_actual'] ?? '';
        $nueva  = $_POST['password_nueva']  ?? '';
        $repite = $_POST['password_repite'] ?? '';
        $st=$conexion->prepare("SELECT password FROM usuarios WHERE id=?");
        $st->bind_param("i",$id_usuario); $st->execute();
        $row=$st->get_result()->fetch_assoc(); $st->close();
        if (!password_verify($actual,$row['password']))      { $error='La contraseña actual no es correcta.'; }
        elseif (strlen($nueva)<6)                            { $error='La nueva contraseña debe tener al menos 6 caracteres.'; }
        elseif ($nueva!==$repite)                            { $error='Las contraseñas nuevas no coinciden.'; }
        else {
            $hash=password_hash($nueva,PASSWORD_DEFAULT);
            $st=$conexion->prepare("UPDATE usuarios SET password=? WHERE id=?");
            $st->bind_param("si",$hash,$id_usuario);
            if($st->execute()) $ok='Contraseña actualizada correctamente.';
            else $error='Error al actualizar.';
            $st->close();
        }
    }

    // --- Añadir dirección favorita ---
    if ($accion==='add_dir') {
        $alias    = trim($_POST['alias']    ?? '');
        $dir_comp = trim($_POST['dir_comp'] ?? '');
        $ciudad   = trim($_POST['ciudad']   ?? '');
        $cp       = trim($_POST['cp']       ?? '');
        if ($alias===''||$dir_comp==='') { $error='El alias y la dirección son obligatorios.'; }
        else {
            $st=$conexion->prepare("INSERT INTO direcciones (id_usuario,alias,direccion_completa,ciudad,codigo_postal) VALUES (?,?,?,?,?)");
            $st->bind_param("issss",$id_usuario,$alias,$dir_comp,$ciudad,$cp);
            if($st->execute()){ $ok='Dirección añadida correctamente.'; header("Location: perfil.php?ok=dir"); exit; }
            else { $error='Error al guardar dirección.'; }
            $st->close();
        }
    }

    // --- Eliminar dirección ---
    if ($accion==='del_dir') {
        $id_dir = intval($_POST['id_dir'] ?? 0);
        $st=$conexion->prepare("DELETE FROM direcciones WHERE id=? AND id_usuario=?");
        $st->bind_param("ii",$id_dir,$id_usuario);
        $st->execute(); $st->close();
        $ok='Dirección eliminada.'; header("Location: perfil.php?ok=del"); exit;
    }
}

if (isset($_GET['ok'])) {
    $msgs = ['dir'=>'Dirección añadida correctamente.','del'=>'Dirección eliminada.'];
    $ok = $msgs[$_GET['ok']] ?? '';
    // Recargar dirs
    $dirs=[];
    $st=$conexion->prepare("SELECT id,alias,direccion_completa,ciudad,codigo_postal FROM direcciones WHERE id_usuario=? ORDER BY id ASC");
    $st->bind_param("i",$id_usuario); $st->execute();
    $res2=$st->get_result();
    while($d=$res2->fetch_assoc()) $dirs[]=$d;
    $st->close();
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ajustes de cuenta - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .tabs { display:flex; gap:4px; margin-bottom:28px; border-bottom:2px solid rgba(0,0,0,0.08); }
    .tab-btn { background:none; border:none; padding:12px 20px; font-family:var(--font-body); font-size:14px; font-weight:600; color:#888; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; transition:color 0.2s,border-color 0.2s; }
    .tab-btn.activo { color:var(--color-acento); border-bottom-color:var(--color-acento); }
    .tab-panel { display:none; } .tab-panel.activo { display:block; }

    .dir-card { background:rgba(255,255,255,0.7); border:1px solid rgba(0,0,0,0.09); border-radius:10px; padding:16px 18px; display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; transition:box-shadow 0.2s; }
    .dir-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.1); }
    .dir-card .dir-alias { font-weight:700; font-size:14px; color:#111; margin-bottom:3px; }
    .dir-card .dir-info  { font-size:13px; color:#555; }
    .dir-card .dir-del   { background:none; border:none; color:#e74c3c; cursor:pointer; font-size:18px; padding:4px 8px; border-radius:6px; transition:background 0.2s; }
    .dir-card .dir-del:hover { background:#fde8e8; }

    .campo-perfil { margin-bottom:16px; }
    .campo-perfil label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#666; margin-bottom:5px; }
    .campo-perfil input { width:100%; padding:12px 14px; border-radius:8px; border:1.5px solid rgba(0,0,0,0.12); background:rgba(255,255,255,0.85); font-size:14px; font-family:var(--font-body); color:#111; transition:border-color 0.2s,box-shadow 0.2s; }
    .campo-perfil input:focus { border-color:var(--color-acento); box-shadow:0 0 0 3px rgba(201,168,76,0.18); outline:none; }
    .campo-perfil input:disabled { background:#f0f0f0; color:#aaa; }
  </style>
  <script>
    function activarTab(id) {
      document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('activo'));
      document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('activo'));
      document.getElementById('tab-'+id).classList.add('activo');
      document.getElementById('panel-'+id).classList.add('activo');
      localStorage.setItem('perfil-tab',id);
    }
    document.addEventListener('DOMContentLoaded',function(){
      var tab=localStorage.getItem('perfil-tab')||'datos';
      activarTab(tab);
    });
  </script>
</head>
<body>
<?php include 'header.php'; ?>

<div class="pagina-interior" style="align-items:flex-start;padding-top:50px;padding-bottom:60px;">
  <div style="width:100%;max-width:820px;">

    <h1 style="font-family:var(--font-display);font-size:2.2rem;color:#111;margin-bottom:30px;text-align:center;">⚙️ Ajustes de cuenta</h1>

    <?php if($ok!==''): ?>
      <div class="alerta alerta-ok" style="margin-bottom:20px;"><?php echo htmlspecialchars($ok); ?></div>
    <?php endif; ?>
    <?php if($error!==''): ?>
      <div class="alerta alerta-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div style="background:rgba(255,255,255,0.52);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.75);border-radius:16px;padding:36px 40px;box-shadow:0 16px 50px rgba(0,0,0,0.12);">

      <!-- TABS -->
      <div class="tabs">
        <button class="tab-btn" id="tab-datos"      onclick="activarTab('datos')">👤 Datos personales</button>
        <button class="tab-btn" id="tab-password"   onclick="activarTab('password')">🔒 Contraseña</button>
        <button class="tab-btn" id="tab-direcciones" onclick="activarTab('direcciones')">📌 Direcciones favoritas</button>
      </div>

      <!-- PANEL: DATOS -->
      <div class="tab-panel" id="panel-datos">
        <form method="post">
          <input type="hidden" name="accion" value="datos">
          <div class="campo-perfil">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required value="<?php echo htmlspecialchars($usuario['nombre']); ?>">
          </div>
          <div class="campo-perfil">
            <label>Correo electrónico</label>
            <input type="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled>
            <p style="font-size:11px;color:#aaa;margin-top:4px;">El email no se puede modificar.</p>
          </div>
          <div class="campo-perfil" style="margin-bottom:22px;">
            <label>Teléfono</label>
            <input type="tel" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>" placeholder="+34 600 000 000">
          </div>
          <button type="submit" class="btn primary" style="width:100%;">Guardar datos</button>
        </form>
      </div>

      <!-- PANEL: CONTRASEÑA -->
      <div class="tab-panel" id="panel-password">
        <form method="post">
          <input type="hidden" name="accion" value="password">
          <div class="campo-perfil">
            <label>Contraseña actual</label>
            <input type="password" name="password_actual" required placeholder="••••••••">
          </div>
          <div class="campo-perfil">
            <label>Nueva contraseña</label>
            <input type="password" name="password_nueva" required placeholder="Mínimo 6 caracteres">
          </div>
          <div class="campo-perfil" style="margin-bottom:22px;">
            <label>Repetir nueva contraseña</label>
            <input type="password" name="password_repite" required placeholder="••••••••">
          </div>
          <button type="submit" class="btn primary" style="width:100%;">Cambiar contraseña</button>
        </form>
      </div>

      <!-- PANEL: DIRECCIONES -->
      <div class="tab-panel" id="panel-direcciones">
        <p style="font-size:14px;color:#666;margin-bottom:20px;">
          Guarda tus direcciones habituales para usarlas rápidamente al hacer reservas.
        </p>

        <?php if (!empty($dirs)): ?>
          <?php foreach($dirs as $d): ?>
            <div class="dir-card">
              <div>
                <div class="dir-alias">📍 <?php echo htmlspecialchars($d['alias']); ?></div>
                <div class="dir-info">
                  <?php echo htmlspecialchars($d['direccion_completa']); ?>
                  <?php if($d['ciudad']) echo ' · '.htmlspecialchars($d['ciudad']); ?>
                  <?php if($d['codigo_postal']) echo ' '.htmlspecialchars($d['codigo_postal']); ?>
                </div>
              </div>
              <form method="post" style="margin:0;" onsubmit="return confirm('¿Eliminar esta dirección?');">
                <input type="hidden" name="accion" value="del_dir">
                <input type="hidden" name="id_dir" value="<?php echo $d['id']; ?>">
                <button type="submit" class="dir-del" title="Eliminar">🗑️</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="color:#888;font-size:14px;text-align:center;padding:20px 0;">No tienes direcciones guardadas todavía.</p>
        <?php endif; ?>

        <hr style="border:none;border-top:1px solid rgba(0,0,0,0.08);margin:24px 0;">
        <h3 style="font-size:15px;font-weight:700;color:#111;margin-bottom:16px;">➕ Añadir dirección</h3>
        <form method="post">
          <input type="hidden" name="accion" value="add_dir">
          <div style="display:flex;gap:14px;flex-wrap:wrap;">
            <div class="campo-perfil" style="flex:1;min-width:180px;">
              <label>Alias <span style="color:#e74c3c;">*</span></label>
              <input type="text" name="alias" required placeholder="Ej: Casa, Trabajo, Aeropuerto">
            </div>
            <div class="campo-perfil" style="flex:2;min-width:220px;">
              <label>Dirección completa <span style="color:#e74c3c;">*</span></label>
              <input type="text" name="dir_comp" required placeholder="Ej: Calle Larios 5, Málaga">
            </div>
          </div>
          <div style="display:flex;gap:14px;flex-wrap:wrap;">
            <div class="campo-perfil" style="flex:2;min-width:180px;">
              <label>Ciudad</label>
              <input type="text" name="ciudad" placeholder="Ej: Málaga">
            </div>
            <div class="campo-perfil" style="flex:1;min-width:120px;">
              <label>Código postal</label>
              <input type="text" name="cp" placeholder="29001">
            </div>
          </div>
          <button type="submit" class="btn secondary" style="width:100%;margin-top:4px;">Guardar dirección</button>
        </form>
      </div>

    </div>

    <div style="text-align:center;margin-top:24px;display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
      <a href="reserva.php" class="btn primary">➜ Nueva reserva</a>
      <a href="lista_reservas.php" class="btn secondary">📋 Mis reservas</a>
    </div>

  </div>
</div>

<footer class="footer"><p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p></footer>
</body>
</html>
