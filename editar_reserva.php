<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header("Location: login.php"); exit; }

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

    if ($origen===''||$destino===''||$fecha_reserva===''||$hora_reserva===''||$num_pasajeros<=0||$distancia_km<=0) {
        $error='Faltan datos obligatorios.';
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
  <script>
    function actualizarPrecio(km){
      var el=document.getElementById('precio-valor');
      if(el) el.textContent=km>0?(10+(km*1.20)).toFixed(2)+' €':'—';
    }
    document.addEventListener('DOMContentLoaded',function(){
      var inp=document.getElementById('distancia_km');
      if(inp){ inp.addEventListener('input',function(){ actualizarPrecio(parseFloat(this.value)||0); }); actualizarPrecio(parseFloat(inp.value)||0); }
      var f=document.getElementById('fecha_reserva');
      if(f) f.setAttribute('min',new Date().toISOString().split('T')[0]);
    });
  </script>
</head>
<body>
<?php include 'header.php'; ?>
<div class="pagina-interior">
  <div class="caja-formulario wide">
    <h1>Editar reserva</h1>
    <p class="subtitulo-form">Modifica los datos de tu reserva pendiente</p>
    <?php if($error!==''): ?><div class="alerta alerta-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post">
      <div><label>Origen</label><input type="text" name="origen" required value="<?php echo htmlspecialchars($reserva['origen']); ?>"></div>
      <div><label>Destino</label><input type="text" name="destino" required value="<?php echo htmlspecialchars($reserva['destino']); ?>"></div>
      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;min-width:140px;"><label>Fecha</label><input type="date" name="fecha_reserva" id="fecha_reserva" required value="<?php echo htmlspecialchars($reserva['fecha_reserva']); ?>"></div>
        <div style="flex:1;min-width:120px;"><label>Hora</label><input type="time" name="hora_reserva" required value="<?php echo htmlspecialchars($reserva['hora_reserva']); ?>"></div>
      </div>
      <div style="display:flex;gap:14px;flex-wrap:wrap;">
        <div style="flex:1;min-width:130px;"><label>Pasajeros</label><input type="number" name="num_pasajeros" min="1" max="8" required value="<?php echo htmlspecialchars($reserva['num_pasajeros']); ?>"></div>
        <div style="flex:1;min-width:130px;"><label>Distancia (km)</label><input type="number" name="distancia_km" id="distancia_km" step="0.1" min="0.1" required value="<?php echo htmlspecialchars($reserva['distancia_km']); ?>"></div>
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
</body>
</html>
