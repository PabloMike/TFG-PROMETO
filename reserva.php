<?php
session_start();

$errores_login    = '';
$mensaje_reserva  = '';
$error_reserva    = '';
$precio           = 0;
$id_nueva_reserva = 0;

$v_origen      = '';
$v_destino     = '';
$v_fecha       = '';
$v_hora        = '';
$v_pasajeros   = '';
$v_km          = '';
$v_comentarios = '';
$v_silla       = 'no';
$v_silla_info  = '';
$v_mascota     = 'no';

// Cargar direcciones favoritas
$dirs_favoritas = [];
if (isset($_SESSION['id_usuario'])) {
    $con = new mysqli("localhost","root","","vtc");
    if (!$con->connect_error) {
        $st = $con->prepare("SELECT alias,direccion_completa FROM direcciones WHERE id_usuario=? ORDER BY id ASC");
        if ($st) {
            $st->bind_param("i",$_SESSION['id_usuario']);
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) $dirs_favoritas[] = $r;
            $st->close();
        }
        $con->close();
    }
}

// ===== LOGIN =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['formulario_login'])) {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    if ($email===''||$password==='') {
        $errores_login = 'Debes introducir email y contraseña.';
    } else {
        $c  = new mysqli("localhost","root","","vtc");
        $st = $c->prepare("SELECT id,nombre,email,password FROM usuarios WHERE email=?");
        $st->bind_param("s",$email); $st->execute();
        $u  = $st->get_result()->fetch_assoc();
        if ($u && password_verify($password,$u['password'])) {
            $_SESSION['id_usuario'] = $u['id'];
            $_SESSION['nombre']     = $u['nombre'];
            $_SESSION['email']      = $u['email'];
            header("Location: reserva.php"); exit;
        } else { $errores_login = 'Credenciales incorrectas.'; }
        $st->close(); $c->close();
    }
}

// ===== CREAR RESERVA =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['formulario_reserva'])) {
    $v_origen      = trim($_POST['origen']          ?? '');
    $v_destino     = trim($_POST['destino']         ?? '');
    $v_fecha       = $_POST['fecha_reserva']        ?? '';
    $v_hora        = $_POST['hora_reserva']         ?? '';
    $v_pasajeros   = $_POST['num_pasajeros']        ?? '';
    $v_km          = $_POST['distancia_km']         ?? '';
    $v_comentarios = trim($_POST['comentarios']     ?? '');
    $v_silla       = $_POST['silla_bebe']           ?? 'no';
    $v_silla_info  = trim($_POST['silla_info']      ?? '');
    $v_mascota     = $_POST['mascota']              ?? 'no';

    if (!isset($_SESSION['id_usuario'])) {
        $error_reserva = 'Debes iniciar sesión para realizar una reserva.';
    } elseif ($v_origen===''||$v_destino===''||$v_fecha===''||$v_hora===''||
              $v_pasajeros===''||intval($v_pasajeros)<=0||
              $v_km===''||floatval($v_km)<=0) {
        $error_reserva = 'Por favor rellena todos los campos obligatorios.';
    } else {
        $id_usuario    = $_SESSION['id_usuario'];
        $num_pasajeros = intval($v_pasajeros);
        $distancia_km  = floatval($v_km);
        $precio_silla  = 0;
        if ($v_silla !== 'no' && $v_silla !== 'otros' && is_numeric($v_silla))
            $precio_silla = intval($v_silla) * 10.00;
        $precio_mascota = ($v_mascota === 'si') ? 10.00 : 0;
        $precio = round(10.00 + ($distancia_km * 1.20) + $precio_silla + $precio_mascota, 2);
        $estado = 'pendiente';

        $c   = new mysqli("localhost","root","","vtc");
        $sql = "INSERT INTO reservas
                (id_usuario,origen,destino,fecha_reserva,hora_reserva,
                 num_pasajeros,comentarios,distancia_km,precio,estado,
                 silla_bebe,silla_bebe_info,mascota)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $st = $c->prepare($sql);
        if ($st) {
            $st->bind_param(
                "issssisddssss",
                $id_usuario,$v_origen,$v_destino,$v_fecha,$v_hora,
                $num_pasajeros,$v_comentarios,$distancia_km,$precio,$estado,
                $v_silla,$v_silla_info,$v_mascota
            );
            if ($st->execute()) {
                $id_nueva_reserva = $c->insert_id;
                $mensaje_reserva  = 'Reserva creada correctamente.';
            } else {
                $error_reserva = 'Error al guardar: '.$st->error;
            }
            $st->close();
        } else {
            $error_reserva = 'Error BD: '.$c->error;
        }
        $c->close();
    }
}

// ===== PAGO =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['formulario_pago'])) {
    $id_reserva  = intval($_POST['id_reserva']  ?? 0);
    $metodo_pago = trim($_POST['metodo_pago']   ?? '');
    $importe     = floatval($_POST['importe']   ?? 0);
    if ($id_reserva>0 && $metodo_pago!=='' && $importe>0) {
        $c  = new mysqli("localhost","root","","vtc");
        $st = $c->prepare("INSERT INTO pagos (id_reserva,importe,metodo_pago,estado,fecha_pago) VALUES (?,?,?,'pendiente',NOW())");
        if ($st) { $st->bind_param("ids",$id_reserva,$importe,$metodo_pago); $st->execute(); $st->close(); }
        $c->close();
    }
    header("Location: lista_reservas.php"); exit;
}

$err_origen  = ($error_reserva!=='' && $v_origen==='');
$err_destino = ($error_reserva!=='' && $v_destino==='');
$err_fecha   = ($error_reserva!=='' && $v_fecha==='');
$err_hora    = ($error_reserva!=='' && $v_hora==='');
$err_pas     = ($error_reserva!=='' && ($v_pasajeros===''||intval($v_pasajeros)<=0));
$err_km      = ($error_reserva!=='' && ($v_km===''||floatval($v_km)<=0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reservar - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .pago-metodos { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:4px; }
    .pago-opcion  { flex:1; min-width:110px; }
    .pago-opcion input[type=radio] { display:none; }
    .pago-opcion label {
      display:flex; flex-direction:column; align-items:center; justify-content:center;
      gap:6px; border:1.5px solid rgba(0,0,0,0.12); border-radius:10px;
      padding:14px 8px; text-align:center; cursor:pointer; font-size:13px;
      font-weight:700; color:#555; transition:all 0.2s; background:rgba(255,255,255,0.85);
      height:80px; white-space:nowrap;
    }
    .pago-opcion label .pago-icon { font-size:1.5rem; }
    .pago-opcion input[type=radio]:checked + label { border-color:var(--color-acento); background:rgba(201,168,76,0.12); color:#111; }
    .pago-opcion label:hover { border-color:var(--color-acento); }
    .precio-wrap { position:relative; }
    .precio-tooltip {
      display:none; position:absolute; bottom:calc(100% + 10px); left:50%; transform:translateX(-50%);
      background:#1a1a1a; border:1px solid var(--color-acento); border-radius:10px;
      padding:14px 18px; min-width:240px; z-index:100; box-shadow:0 8px 24px rgba(0,0,0,0.3);
    }
    .precio-wrap:hover .precio-tooltip { display:block; }
    .precio-tooltip::after { content:''; position:absolute; top:100%; left:50%; transform:translateX(-50%); border:7px solid transparent; border-top-color:#1a1a1a; }
    .tooltip-fila { display:flex; justify-content:space-between; font-size:13px; color:#ccc; padding:4px 0; border-bottom:1px solid rgba(255,255,255,0.07); }
    .tooltip-fila:last-child { border-bottom:none; color:var(--color-acento); font-weight:700; font-size:14px; }
    .tooltip-fila span:first-child { color:#aaa; }
    .input-error { border-color:#e74c3c !important; background:#fff8f8 !important; }
    .msg-error-campo { color:#e74c3c; font-size:12px; margin-top:4px; }
  </style>
  <script>
  var precioBase=10, precioPorKm=1.20;

  function recalcularPrecio() {
    var km      = parseFloat(document.getElementById('distancia_km')?.value)||0;
    var silla   = document.getElementById('silla_bebe')?.value||'no';
    var mascota = document.getElementById('mascota')?.value||'no';
    var nSillas = (silla!=='no'&&silla!=='otros'&&!isNaN(parseInt(silla))) ? parseInt(silla) : 0;
    var pSilla  = nSillas * 10;
    var pMascota= mascota==='si' ? 10 : 0;
    var total   = km>0 ? precioBase + (km*precioPorKm) + pSilla + pMascota : 0;
    var el=document.getElementById('precio-valor');
    if(el) el.textContent = total>0 ? total.toFixed(2)+' €' : '—';
    var ttBase=document.getElementById('tt-base');     if(ttBase)   ttBase.textContent   = precioBase.toFixed(2)+' €';
    var ttKm=document.getElementById('tt-km');         if(ttKm)     ttKm.textContent     = km>0?(km*precioPorKm).toFixed(2)+' €':'—';
    var ttSillas=document.getElementById('tt-sillas'); if(ttSillas) ttSillas.textContent = pSilla>0?pSilla.toFixed(2)+' €':'—';
    var ttMasc=document.getElementById('tt-mascota');  if(ttMasc)   ttMasc.textContent   = pMascota>0?'10.00 €':'—';
    var ttTot=document.getElementById('tt-total');     if(ttTot)    ttTot.textContent    = total>0?total.toFixed(2)+' €':'—';
  }

  function usarDireccion(dir, campo) {
    var inp = document.getElementById(campo);
    if(inp) inp.value = dir;
  }

  document.addEventListener('DOMContentLoaded', function(){
    ['distancia_km','silla_bebe','mascota'].forEach(function(id){
      var el=document.getElementById(id);
      if(el){ el.addEventListener('change',recalcularPrecio); el.addEventListener('input',recalcularPrecio); }
    });
    recalcularPrecio();

    var f=document.getElementById('fecha_reserva');
    if(f) f.setAttribute('min',new Date().toISOString().split('T')[0]);

    var selSilla=document.getElementById('silla_bebe'), bloqSilla=document.getElementById('bloque-silla-info');
    if(selSilla&&bloqSilla) selSilla.addEventListener('change',function(){ bloqSilla.style.display=this.value!=='no'?'block':'none'; });

    var selMasc=document.getElementById('mascota'), bloqMasc=document.getElementById('bloque-mascota-aviso');
    if(selMasc&&bloqMasc) selMasc.addEventListener('change',function(){ bloqMasc.style.display=this.value==='si'?'block':'none'; });

    var mo=document.getElementById('modal-mascotas');
    if(mo) mo.addEventListener('click',function(e){ if(e.target===this) cerrarModal(); });
  });

  function abrirModal(){ document.getElementById('modal-mascotas').classList.add('activo'); }
  function cerrarModal(){ document.getElementById('modal-mascotas').classList.remove('activo'); }
  function cerrarDirMenus(){ document.querySelectorAll('[id^="dir-menu-"]').forEach(function(m){ m.style.display='none'; }); }
  function toggleDirMenu(idx){
    var m=document.getElementById('dir-menu-'+idx);
    cerrarDirMenus();
    if(m.style.display!=='block') m.style.display='block';
  }
  document.addEventListener('click',function(e){ if(!e.target.closest('#dirs-container')) cerrarDirMenus(); });
  </script>
</head>
<body>

<?php include 'header.php'; ?>

<div class="modal-overlay" id="modal-mascotas">
  <div class="modal-caja">
    <h2>🐾 Política de viajes con animales</h2>
    <p>En Prometeo VTC aceptamos animales de compañía en nuestros vehículos, siempre bajo las siguientes condiciones:</p>
    <p>• El cliente es responsable de proporcionar un <strong>transportín adecuado</strong>. No se admiten animales sueltos, salvo perros guía y animales de asistencia debidamente acreditados.</p>
    <p>• Los animales deben permanecer en el transportín durante todo el trayecto.</p>
    <p>• Prometeo VTC se reserva el derecho a rechazar el servicio si el animal supone un riesgo para la seguridad.</p>
    <div class="aviso-legal">⚠️ <strong>Aviso de responsabilidad:</strong> Cualquier daño causado por el animal será de <strong>exclusiva responsabilidad del propietario</strong>.</div>
    <div style="text-align:center;margin-top:20px;"><button class="btn primary" onclick="cerrarModal()">Entendido</button></div>
  </div>
</div>

<div class="pagina-interior">

<?php if (!isset($_SESSION['id_usuario'])): ?>
  <div class="caja-formulario">
    <h1>Inicia sesión</h1>
    <p class="subtitulo-form">Necesitas cuenta para realizar una reserva</p>
    <?php if($errores_login!==''): ?><div class="alerta alerta-error"><?php echo htmlspecialchars($errores_login); ?></div><?php endif; ?>
    <form action="reserva.php" method="post">
      <input type="hidden" name="formulario_login" value="1">
      <div><label>Correo electrónico</label><input type="email" name="email" required placeholder="tucorreo@email.com"></div>
      <div><label>Contraseña</label><input type="password" name="password" required placeholder="••••••••"></div>
      <button type="submit" class="btn primary">Iniciar sesión</button>
    </form>
    <p class="enlace-pie">¿No tienes cuenta? <a href="registro.php">Registrarse</a></p>
  </div>

<?php elseif ($mensaje_reserva!==''): ?>
  <div class="caja-formulario wide">
    <h1>💳 Método de pago</h1>
    <p class="subtitulo-form">Tu reserva está creada. Elige cómo quieres pagar.</p>
    <div class="alerta alerta-ok"><?php echo htmlspecialchars($mensaje_reserva); ?></div>
    <div class="resumen-reserva" style="margin-bottom:24px;">
      <p><strong>Origen</strong>    <span><?php echo htmlspecialchars($v_origen); ?></span></p>
      <p><strong>Destino</strong>   <span><?php echo htmlspecialchars($v_destino); ?></span></p>
      <p><strong>Fecha</strong>     <span><?php echo htmlspecialchars($v_fecha); ?></span></p>
      <p><strong>Hora</strong>      <span><?php echo htmlspecialchars($v_hora); ?></span></p>
      <p><strong>Pasajeros</strong> <span><?php echo htmlspecialchars($v_pasajeros); ?></span></p>
      <p><strong>Distancia</strong> <span><?php echo htmlspecialchars($v_km); ?> km</span></p>
      <?php if($v_silla!=='no'): ?><p><strong>Silla bebé</strong><span><?php echo htmlspecialchars($v_silla); ?></span></p><?php endif; ?>
      <?php if($v_mascota==='si'): ?><p><strong>Mascota</strong><span>Sí (+10,00 €)</span></p><?php endif; ?>
      <p><strong>Precio total</strong><span class="precio-final"><?php echo number_format($precio,2); ?> €</span></p>
    </div>
    <form action="reserva.php" method="post">
      <input type="hidden" name="formulario_pago" value="1">
      <input type="hidden" name="id_reserva" value="<?php echo $id_nueva_reserva; ?>">
      <input type="hidden" name="importe" value="<?php echo $precio; ?>">
      <p style="font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:12px;">Selecciona método de pago</p>
      <div class="pago-metodos">
        <div class="pago-opcion"><input type="radio" name="metodo_pago" id="p-efectivo" value="efectivo" required><label for="p-efectivo"><span class="pago-icon">💵</span>EFECTIVO</label></div>
        <div class="pago-opcion"><input type="radio" name="metodo_pago" id="p-tarjeta" value="tarjeta"><label for="p-tarjeta"><span class="pago-icon">💳</span>TARJETA</label></div>
        <div class="pago-opcion"><input type="radio" name="metodo_pago" id="p-bizum" value="bizum"><label for="p-bizum"><span class="pago-icon">📱</span>BIZUM</label></div>
        <div class="pago-opcion"><input type="radio" name="metodo_pago" id="p-transferencia" value="transferencia"><label for="p-transferencia"><span class="pago-icon">🏦</span>TRANSFER.</label></div>
      </div>
      <p style="font-size:12px;color:#aaa;margin-top:6px;margin-bottom:20px;">El pago se realizará al finalizar el servicio salvo transferencia bancaria.</p>
      <button type="submit" class="btn primary">Confirmar reserva y pago</button>
    </form>
    <form action="reserva.php" method="get" style="margin-top:10px;">
      <button type="submit" class="btn secondary" style="width:100%;text-align:center;">← Editar reserva</button>
    </form>
  </div>

<?php else: ?>
  <div class="caja-formulario wide">
    <h1>Nueva reserva</h1>
    <p class="subtitulo-form">Completa los datos para solicitar tu trayecto</p>

    <?php if($error_reserva!==''): ?><div class="alerta alerta-error">⚠️ <?php echo htmlspecialchars($error_reserva); ?></div><?php endif; ?>

    <?php if(!empty($dirs_favoritas)): ?>
    <div style="margin-bottom:16px;">
      <p style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:8px;">📌 Direcciones guardadas</p>
      <div style="display:flex;flex-direction:column;gap:6px;" id="dirs-container">
        <?php foreach($dirs_favoritas as $i=>$d): ?>
          <div style="position:relative;">
            <button type="button" class="dir-favorita-btn" onclick="toggleDirMenu(<?php echo $i; ?>)"
              style="width:100%;text-align:left;display:flex;justify-content:space-between;align-items:center;gap:10px;">
              <span style="font-weight:700;">📍 <?php echo htmlspecialchars($d['alias']); ?></span>
              <span style="font-size:11px;color:#aaa;font-weight:400;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars(mb_strimwidth($d['direccion_completa'],0,45,'…')); ?></span>
              <span>▾</span>
            </button>
            <div id="dir-menu-<?php echo $i; ?>" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:50;background:white;border:1px solid rgba(201,168,76,0.4);border-radius:0 0 8px 8px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,0.12);">
              <button type="button" onclick="usarDireccion('<?php echo addslashes($d['direccion_completa']); ?>','origen');cerrarDirMenus();"
                style="display:block;width:100%;text-align:left;padding:12px 16px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:#333;"
                onmouseover="this.style.background='rgba(201,168,76,0.08)'" onmouseout="this.style.background='none'">➜ Usar como origen</button>
              <button type="button" onclick="usarDireccion('<?php echo addslashes($d['direccion_completa']); ?>','destino');cerrarDirMenus();"
                style="display:block;width:100%;text-align:left;padding:12px 16px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:#333;border-top:1px solid rgba(0,0,0,0.06);"
                onmouseover="this.style.background='rgba(201,168,76,0.08)'" onmouseout="this.style.background='none'">➜ Usar como destino</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <form action="reserva.php" method="post" id="form-reserva">
      <input type="hidden" name="formulario_reserva" value="1">

      <div>
        <label>Origen</label>
        <input type="text" name="origen" id="origen" required placeholder="Ej: Aeropuerto de Málaga"
          value="<?php echo htmlspecialchars($v_origen); ?>" class="<?php echo $err_origen?'input-error':''; ?>">
        <?php if($err_origen): ?><p class="msg-error-campo">⚠️ El origen es obligatorio.</p><?php endif; ?>
      </div>

      <div>
        <label>Destino</label>
        <input type="text" name="destino" id="destino" required placeholder="Ej: Hotel Málaga Centro"
          value="<?php echo htmlspecialchars($v_destino); ?>" class="<?php echo $err_destino?'input-error':''; ?>">
        <?php if($err_destino): ?><p class="msg-error-campo">⚠️ El destino es obligatorio.</p><?php endif; ?>
      </div>

      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;min-width:140px;">
          <label>Fecha</label>
          <input type="date" name="fecha_reserva" id="fecha_reserva" required
            value="<?php echo htmlspecialchars($v_fecha); ?>" class="<?php echo $err_fecha?'input-error':''; ?>">
          <?php if($err_fecha): ?><p class="msg-error-campo">⚠️ Selecciona una fecha.</p><?php endif; ?>
        </div>
        <div style="flex:1;min-width:120px;">
          <label>Hora</label>
          <input type="time" name="hora_reserva" required
            value="<?php echo htmlspecialchars($v_hora); ?>" class="<?php echo $err_hora?'input-error':''; ?>">
          <?php if($err_hora): ?><p class="msg-error-campo">⚠️ Selecciona una hora.</p><?php endif; ?>
        </div>
      </div>

      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;min-width:130px;">
          <label>Pasajeros</label>
          <input type="number" name="num_pasajeros" min="1" max="8" placeholder="1" required
            value="<?php echo htmlspecialchars($v_pasajeros); ?>" class="<?php echo $err_pas?'input-error':''; ?>">
          <?php if($err_pas): ?><p class="msg-error-campo">⚠️ Indica el nº de pasajeros.</p><?php endif; ?>
        </div>
        <div style="flex:1;min-width:130px;">
          <label>Distancia (km)</label>
          <input type="number" name="distancia_km" id="distancia_km" step="0.1" min="0.1" placeholder="Ej: 15.5" required
            value="<?php echo htmlspecialchars($v_km); ?>" class="<?php echo $err_km?'input-error':''; ?>">
          <?php if($err_km): ?><p class="msg-error-campo">⚠️ Indica la distancia en km.</p><?php endif; ?>
        </div>
      </div>

      <div class="precio-wrap">
        <div class="precio-tooltip">
          <div class="tooltip-fila"><span>Tarifa base</span><span id="tt-base">10.00 €</span></div>
          <div class="tooltip-fila"><span>Distancia (× 1,20 €/km)</span><span id="tt-km">—</span></div>
          <div class="tooltip-fila"><span>Silla(s) bebé (× 10 €)</span><span id="tt-sillas">—</span></div>
          <div class="tooltip-fila"><span>Mascota (+10 €)</span><span id="tt-mascota">—</span></div>
          <div class="tooltip-fila"><span>Total estimado</span><span id="tt-total">—</span></div>
        </div>
        <div class="precio-estimado" style="cursor:default;">
          <span class="precio-label">💡 Precio estimado <span style="font-size:10px;opacity:0.6;">— pasa el ratón para ver el desglose</span></span>
          <span class="precio-valor" id="precio-valor">—</span>
        </div>
      </div>

      <div class="extra-bloque">
        <p class="extra-titulo">👶 ¿Necesitas silla de bebé?</p>
        <select name="silla_bebe" id="silla_bebe">
          <option value="no"   <?php echo $v_silla==='no'   ?'selected':''; ?>>No</option>
          <option value="1"    <?php echo $v_silla==='1'    ?'selected':''; ?>>1 silla</option>
          <option value="2"    <?php echo $v_silla==='2'    ?'selected':''; ?>>2 sillas</option>
          <option value="3"    <?php echo $v_silla==='3'    ?'selected':''; ?>>3 sillas</option>
          <option value="4"    <?php echo $v_silla==='4'    ?'selected':''; ?>>4 sillas</option>
          <option value="otros"<?php echo $v_silla==='otros'?'selected':''; ?>>Otros</option>
        </select>
        <div id="bloque-silla-info" style="display:<?php echo $v_silla!=='no'?'block':'none'; ?>;margin-top:10px;">
          <input type="text" name="silla_info" value="<?php echo htmlspecialchars($v_silla_info); ?>"
            placeholder="Indica la edad de los menores que necesitan silla (ej: 2 años, 8 meses)">
        </div>
      </div>

      <div class="extra-bloque">
        <p class="extra-titulo">🐾 ¿Viajáis con mascotas? <span style="font-size:11px;color:#aaa;font-weight:400;">(+10 €)</span></p>
        <select name="mascota" id="mascota">
          <option value="no" <?php echo $v_mascota==='no'?'selected':''; ?>>No</option>
          <option value="si" <?php echo $v_mascota==='si'?'selected':''; ?>>Sí</option>
        </select>
        <div id="bloque-mascota-aviso" style="display:<?php echo $v_mascota==='si'?'block':'none'; ?>;margin-top:10px;">
          <div class="aviso-mascota">
            🐕 Recuerda que <strong>debes traer tu propio transportín</strong>. No se admiten animales sueltos salvo perros guía y animales de asistencia debidamente acreditados.<br><br>
            <a href="#" onclick="abrirModal();return false;">📋 Ver política completa de viajes con animales</a>
          </div>
        </div>
      </div>

      <div>
        <label>Comentarios <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#888;">(opcional)</span></label>
        <textarea name="comentarios" placeholder="Observaciones, equipaje, necesidades especiales..."><?php echo htmlspecialchars($v_comentarios); ?></textarea>
      </div>

      <button type="submit" class="btn primary">Continuar →</button>
    </form>
  </div>

<?php endif; ?>
</div>

<footer class="footer"><p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p></footer>
</body>
</html>
