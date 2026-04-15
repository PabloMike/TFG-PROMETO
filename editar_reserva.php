<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header("Location: login.php"); exit; }

define('MAPS_API_KEY', 'AIzaSyAHJ4u8u0Dt-2Cb_8rYpZIQS2sCFo_N8Xc');

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: lista_reservas.php"); exit; }

$conexion = new mysqli("localhost","root","","vtc");
$stmt = $conexion->prepare("SELECT * FROM reservas WHERE id=? AND id_usuario=? AND estado='pendiente'");
$stmt->bind_param("ii",$id,$_SESSION['id_usuario']);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reserva) { header("Location: lista_reservas.php"); exit; }

$error = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $origen        = trim($_POST['origen']        ?? '');
    $destino       = trim($_POST['destino']       ?? '');
    $fecha_reserva = $_POST['fecha_reserva']      ?? '';
    $hora_reserva  = $_POST['hora_reserva']       ?? '';
    $num_pasajeros = intval($_POST['num_pasajeros'] ?? 0);
    $distancia_km  = floatval($_POST['distancia_km'] ?? 0);
    $comentarios   = trim($_POST['comentarios']   ?? '');
    $origen_place_id  = trim($_POST['origen_place_id']  ?? '');
    $destino_place_id = trim($_POST['destino_place_id'] ?? '');

    if ($origen===''||$destino===''||$fecha_reserva===''||$hora_reserva===''||$num_pasajeros<=0||$distancia_km<=0) {
        $error='Faltan datos obligatorios.';
    } elseif ($origen_place_id===''||$destino_place_id==='') {
        $error='Debes seleccionar las direcciones del autocompletado de Google Maps.';
    } else {
        $precio = 10.00 + ($distancia_km * 1.20);
        $st2=$conexion->prepare("UPDATE reservas SET origen=?,destino=?,fecha_reserva=?,hora_reserva=?,num_pasajeros=?,comentarios=?,distancia_km=?,precio=? WHERE id=? AND id_usuario=?");
        $st2->bind_param("ssssissdii",$origen,$destino,$fecha_reserva,$hora_reserva,$num_pasajeros,$comentarios,$distancia_km,$precio,$id,$_SESSION['id_usuario']);
        if($st2->execute()){ header("Location: lista_reservas.php"); exit; }
        else { $error='Error al guardar: '.$st2->error; }
        $st2->close();
    }
}
$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar reserva - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .validacion-msg { font-size:12px; font-weight:700; margin-top:5px; min-height:18px; }
    .validacion-msg.ok    { color:#28a745; }
    .validacion-msg.error { color:#e74c3c; }
    .distancia-aviso { font-size:12px; color:var(--color-acento); font-weight:600; margin-top:4px; min-height:18px; }
    .distancia-auto-nota { font-size:11px; color:#999; margin-top:4px; font-style:italic; }
    input[readonly] { background:rgba(220,220,220,0.5)!important; cursor:not-allowed; }
    .pac-container { border-radius:8px!important; border:1px solid rgba(201,168,76,0.35)!important; box-shadow:0 6px 20px rgba(0,0,0,0.12)!important; font-family:var(--font-body)!important; z-index:9999!important; }
    .pac-item { padding:10px 14px!important; font-size:13px!important; }
    .pac-matched { color:var(--color-acento)!important; font-weight:700!important; }
  </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="pagina-interior">
  <div class="caja-formulario wide">
    <h1>Editar reserva</h1>
    <p class="subtitulo-form">Modifica los datos de tu reserva pendiente</p>
    <?php if($error!==''): ?><div class="alerta alerta-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="alerta alerta-info" style="font-size:13px;">
      📍 Escribe la dirección y <strong>selecciona una opción del desplegable</strong> de Google Maps para validarla.
    </div>

    <form method="post" id="form-editar">
      <input type="hidden" name="origen_place_id"  id="origen_place_id"  value="<?php echo htmlspecialchars($reserva['origen'] !== '' ? 'ok' : ''); ?>">
      <input type="hidden" name="destino_place_id" id="destino_place_id" value="<?php echo htmlspecialchars($reserva['destino'] !== '' ? 'ok' : ''); ?>">
      <input type="hidden" name="origen"  id="origen-hidden"  value="<?php echo htmlspecialchars($reserva['origen']); ?>">
      <input type="hidden" name="destino" id="destino-hidden" value="<?php echo htmlspecialchars($reserva['destino']); ?>">

      <div>
        <label>Origen</label>
        <input type="text" id="pac-origen" autocomplete="off" required
          placeholder="Escribe y selecciona del desplegable..."
          value="<?php echo htmlspecialchars($reserva['origen']); ?>"
          style="width:100%;padding:12px 14px;border-radius:8px;border:1.5px solid rgba(0,0,0,0.12);background:rgba(255,255,255,0.85);font-size:14px;font-family:var(--font-body);color:#111;box-sizing:border-box;">
        <div class="validacion-msg ok" id="val-origen">✔ Dirección actual cargada</div>
      </div>

      <div>
        <label>Destino</label>
        <input type="text" id="pac-destino" autocomplete="off" required
          placeholder="Escribe y selecciona del desplegable..."
          value="<?php echo htmlspecialchars($reserva['destino']); ?>"
          style="width:100%;padding:12px 14px;border-radius:8px;border:1.5px solid rgba(0,0,0,0.12);background:rgba(255,255,255,0.85);font-size:14px;font-family:var(--font-body);color:#111;box-sizing:border-box;">
        <div class="validacion-msg ok" id="val-destino">✔ Dirección actual cargada</div>
      </div>

      <div class="distancia-aviso" id="distancia-aviso"></div>

      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;min-width:140px;"><label>Fecha</label><input type="date" name="fecha_reserva" id="fecha_reserva" required value="<?php echo htmlspecialchars($reserva['fecha_reserva']); ?>"></div>
        <div style="flex:1;min-width:120px;"><label>Hora</label><input type="time" name="hora_reserva" required value="<?php echo htmlspecialchars($reserva['hora_reserva']); ?>"></div>
      </div>

      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;min-width:130px;"><label>Pasajeros</label><input type="number" name="num_pasajeros" min="1" max="8" required value="<?php echo htmlspecialchars($reserva['num_pasajeros']); ?>"></div>
        <div style="flex:1;min-width:130px;">
          <label>Distancia (km)</label>
          <input type="number" name="distancia_km" id="distancia_km" step="0.1" min="0.1" required readonly
            value="<?php echo htmlspecialchars($reserva['distancia_km']); ?>">
          <p class="distancia-auto-nota">📍 Se recalcula al cambiar origen o destino</p>
        </div>
      </div>

      <div class="precio-estimado">
        <span class="precio-label">Precio estimado</span>
        <span class="precio-valor" id="precio-valor">—</span>
      </div>

      <div><label>Comentarios</label><textarea name="comentarios"><?php echo htmlspecialchars($reserva['comentarios'] ?? ''); ?></textarea></div>

      <button type="submit" class="btn primary">Guardar cambios</button>
      <a href="lista_reservas.php" class="btn secondary" style="display:block;text-align:center;margin-top:8px;">Cancelar</a>
    </form>
  </div>
</div>
<footer class="footer"><p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p></footer>

<script>
var MAPS_KEY    = '<?php echo MAPS_API_KEY; ?>';
var precioBase  = 10;
var precioPorKm = 1.20;
var origenValidado  = true; // Ya tiene valor de la BD
var destinoValidado = true; // Ya tiene valor de la BD
var origenAddr  = '<?php echo addslashes($reserva["origen"]); ?>';
var destinoAddr = '<?php echo addslashes($reserva["destino"]); ?>';

function actualizarPrecio() {
  var km = parseFloat(document.getElementById('distancia_km').value) || 0;
  var el = document.getElementById('precio-valor');
  if(el) el.textContent = km > 0 ? (precioBase + km * precioPorKm).toFixed(2) + ' €' : '—';
}

function calcularDistancia() {
  if (!origenValidado || !destinoValidado || !origenAddr || !destinoAddr) return;
  var av = document.getElementById('distancia-aviso');
  if(av) av.textContent = '⏳ Calculando distancia...';
  fetch('https://routes.googleapis.com/directions/v2:computeRoutes', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Goog-Api-Key': MAPS_KEY, 'X-Goog-FieldMask': 'routes.distanceMeters' },
    body: JSON.stringify({ origin: { address: origenAddr }, destination: { address: destinoAddr }, travelMode: 'DRIVE', routingPreference: 'TRAFFIC_AWARE' })
  })
  .then(r => r.json())
  .then(data => {
    if (data.routes && data.routes.length > 0) {
      var km = Math.ceil(data.routes[0].distanceMeters / 100) / 10;
      document.getElementById('distancia_km').value = km.toFixed(1);
      if(av) av.textContent = '✅ ' + km.toFixed(1) + ' km por carretera';
      actualizarPrecio();
    } else {
      if(av) av.textContent = '⚠️ No se pudo calcular. Introduce la distancia manualmente.';
      document.getElementById('distancia_km').removeAttribute('readonly');
    }
  })
  .catch(() => {
    if(av) av.textContent = '⚠️ Error de conexión.';
    document.getElementById('distancia_km').removeAttribute('readonly');
  });
}

function setVal(campo, cls, txt) {
  var el = document.getElementById('val-' + campo);
  if(!el) return;
  el.className = 'validacion-msg' + (cls ? ' '+cls : '');
  el.textContent = txt || '';
}

function validarFormulario(e) {
  if (!origenValidado)  { e.preventDefault(); setVal('origen',  'error', '✖ Selecciona el origen del desplegable');  return false; }
  if (!destinoValidado) { e.preventDefault(); setVal('destino', 'error', '✖ Selecciona el destino del desplegable'); return false; }
  return true;
}

async function initPlaces() {
  const { Autocomplete } = await google.maps.importLibrary("places");
  const options = { componentRestrictions: { country: 'es' }, fields: ['geometry', 'formatted_address', 'place_id'], types: ['geocode'] };

  const acOrigen  = new Autocomplete(document.getElementById('pac-origen'),  options);
  const acDestino = new Autocomplete(document.getElementById('pac-destino'), options);

  acOrigen.addListener('place_changed', () => {
    const place = acOrigen.getPlace();
    if (!place.geometry) {
      setVal('origen', 'error', '✖ Selecciona una opción del desplegable');
      origenValidado = false;
      document.getElementById('origen_place_id').value = '';
      document.getElementById('origen-hidden').value   = '';
    } else {
      origenAddr = place.formatted_address;
      document.getElementById('origen_place_id').value = place.place_id;
      document.getElementById('origen-hidden').value   = origenAddr;
      origenValidado = true;
      setVal('origen', 'ok', '✔ Origen validado');
      calcularDistancia();
    }
  });

  acDestino.addListener('place_changed', () => {
    const place = acDestino.getPlace();
    if (!place.geometry) {
      setVal('destino', 'error', '✖ Selecciona una opción del desplegable');
      destinoValidado = false;
      document.getElementById('destino_place_id').value = '';
      document.getElementById('destino-hidden').value   = '';
    } else {
      destinoAddr = place.formatted_address;
      document.getElementById('destino_place_id').value = place.place_id;
      document.getElementById('destino-hidden').value   = destinoAddr;
      destinoValidado = true;
      setVal('destino', 'ok', '✔ Destino validado');
      calcularDistancia();
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  initPlaces();
  actualizarPrecio();
  var f = document.getElementById('fecha_reserva');
  if(f) f.setAttribute('min', new Date().toISOString().split('T')[0]);
  var form = document.getElementById('form-editar');
  if(form) form.addEventListener('submit', validarFormulario);
});
</script>

<script>
  (g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once."):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
    key: "<?php echo MAPS_API_KEY; ?>",
    v: "weekly",
    language: "es",
    region: "ES"
  });
</script>
</body>
</html>
