<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { header("Location: login.php?mensaje=debes_login"); exit; }

// Cancelar reserva
if (isset($_GET['cancelar']) && is_numeric($_GET['cancelar'])) {
    $id_r = intval($_GET['cancelar']);
    $con  = new mysqli("localhost","root","","vtc");
    $st   = $con->prepare("UPDATE reservas SET estado='cancelada' WHERE id=? AND id_usuario=?");
    $st->bind_param("ii",$id_r,$_SESSION['id_usuario']);
    $st->execute();
    $afectadas = $st->affected_rows;
    $st->close(); $con->close();
    header("Location: lista_reservas.php?msg=cancelada&tab=historial&debug=".$afectadas); exit;
}

$id_usuario = $_SESSION['id_usuario'];
$conexion   = new mysqli("localhost","root","","vtc");
$hoy        = date('Y-m-d');

// Auto-completar reservas pasadas que siguen como "pendiente"
$conexion->query(
    "UPDATE reservas SET estado='completada'
     WHERE id_usuario={$id_usuario}
       AND estado IN ('pendiente','asignada')
       AND fecha_reserva < '{$hoy}'"
);

// ===== PRÓXIMAS: pendientes o confirmadas con fecha >= hoy, ordenadas por fecha/hora del servicio =====
$stmt_prox = $conexion->prepare(
    "SELECT r.id, r.origen, r.destino, r.fecha_reserva, r.hora_reserva,
            r.num_pasajeros, r.distancia_km, r.precio, r.estado,
            r.silla_bebe, r.mascota, r.fecha_creacion,
            p.metodo_pago,
            c.nombre AS nombre_conductor, c.telefono AS telefono_conductor
     FROM reservas r
     LEFT JOIN pagos p ON p.id_reserva = r.id
     LEFT JOIN usuarios c ON c.id = r.id_conductor
     WHERE r.id_usuario = ?
       AND r.estado IN ('pendiente','confirmada','asignada')
       AND r.fecha_reserva >= ?
     ORDER BY r.fecha_reserva ASC, r.hora_reserva ASC"
);
$stmt_prox->bind_param("is", $id_usuario, $hoy);
$stmt_prox->execute();
$proximas = $stmt_prox->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_prox->close();

// ===== HISTORIAL: todo lo que NO está en próximas =====
$stmt_hist = $conexion->prepare(
    "SELECT r.id, r.origen, r.destino, r.fecha_reserva, r.hora_reserva,
            r.num_pasajeros, r.distancia_km, r.precio, r.estado,
            r.silla_bebe, r.mascota, r.fecha_creacion,
            p.metodo_pago,
            c.nombre AS nombre_conductor, c.telefono AS telefono_conductor
     FROM reservas r
     LEFT JOIN pagos p ON p.id_reserva = r.id
     LEFT JOIN usuarios c ON c.id = r.id_conductor
     WHERE r.id_usuario = ?
       AND NOT (r.estado IN ('pendiente','confirmada','asignada') AND r.fecha_reserva >= ?)
     ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC"
);
$stmt_hist->bind_param("is", $id_usuario, $hoy);
$stmt_hist->execute();
$historial = $stmt_hist->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_hist->close();
$conexion->close();

// Tab activa por defecto
$tab_activa = isset($_GET['tab']) ? $_GET['tab'] : 'proximas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis reservas - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Tabs */
    .reservas-tabs { display:flex; gap:0; margin-bottom:32px; border-bottom:2px solid rgba(0,0,0,0.08); }
    .rtab-btn {
      background:none; border:none; padding:14px 28px; font-family:var(--font-body);
      font-size:15px; font-weight:700; color:#888; cursor:pointer;
      border-bottom:3px solid transparent; margin-bottom:-2px; transition:color 0.2s, border-color 0.2s;
      display:flex; align-items:center; gap:8px;
    }
    .rtab-btn.activo { color:var(--color-acento); border-bottom-color:var(--color-acento); }
    .rtab-btn:hover:not(.activo) { color:#444; }
    .rtab-badge {
      background:#e0e0e0; color:#555; font-size:11px; font-weight:700;
      border-radius:20px; padding:2px 8px; min-width:20px; text-align:center;
    }
    .rtab-btn.activo .rtab-badge { background:var(--color-acento); color:#111; }
    .rtab-panel { display:none; }
    .rtab-panel.activo { display:block; }

    /* Cabecera de grupo de fecha */
    .fecha-grupo-header {
      display:flex; align-items:center; gap:12px;
      margin:24px 0 10px; padding:0 4px;
    }
    .fecha-grupo-header .fecha-label {
      font-size:12px; font-weight:700; text-transform:uppercase;
      letter-spacing:0.1em; color:#888;
      white-space:nowrap;
    }
    .fecha-grupo-header .fecha-linea {
      flex:1; height:1px; background:rgba(0,0,0,0.08);
    }
    .fecha-grupo-header .fecha-label.hoy  { color:#256029; }
    .fecha-grupo-header .fecha-label.pronto { color:#856404; }

    /* Filas */
    .tabla-reservas td { vertical-align:middle; }
    .tabla-reservas tr.pasada td { opacity:0.65; }

    /* Separadores de fecha en historial */
    .fecha-separador td {
      padding: 18px 12px 6px !important;
      border-bottom: none !important;
      background: transparent !important;
    }
    .fecha-separador .fecha-sep-texto {
      font-size: 11px; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.1em; color: #888;
    }
    .fecha-separador .fecha-sep-linea {
      display: inline-block; height: 1px;
      background: rgba(0,0,0,0.08); vertical-align: middle;
      margin-left: 10px; width: 60%;
    }

    /* Sin reservas */
    .sin-tab {
      text-align:center; padding:50px 20px; color:#888;
    }
    .sin-tab .sin-icono { font-size:3rem; margin-bottom:12px; }
    .sin-tab p { font-size:15px; }

    /* Columna conductor */
    .conductor-info { font-size:12px; color:#444; line-height:1.6; }
    .conductor-info .cond-nombre { font-weight:700; color:#111; }
    .conductor-info .cond-tel    { color:#666; }
  </style>
  <script>
    function activarTab(id) {
      document.querySelectorAll('.rtab-btn').forEach(b=>b.classList.remove('activo'));
      document.querySelectorAll('.rtab-panel').forEach(p=>p.classList.remove('activo'));
      document.getElementById('rtab-'+id).classList.add('activo');
      document.getElementById('rpanel-'+id).classList.add('activo');
      history.replaceState(null,'','?tab='+id);
    }

    function toggleAcciones(id){
      document.querySelectorAll('.acciones-menu').forEach(function(m){ if(m.id!=='menu-'+id) m.classList.remove('open'); });
      document.getElementById('menu-'+id).classList.toggle('open');
    }
    document.addEventListener('click',function(e){
      if(!e.target.closest('.acciones-reserva')) document.querySelectorAll('.acciones-menu').forEach(function(m){ m.classList.remove('open'); });
    });

    document.addEventListener('DOMContentLoaded',function(){
      // Leer tab de la URL
      var params = new URLSearchParams(window.location.search);
      var tab = params.get('tab') || '<?php echo $tab_activa; ?>';
      activarTab(tab);
    });
  </script>
</head>
<body>
<?php include 'header.php'; ?>

<div class="pagina-tabla">
  <div class="caja-tabla">
    <h1>Mis reservas</h1>

    <?php if(isset($_GET['msg'])): ?>
      <?php $msgs=['cancelada'=>'La reserva ha sido cancelada correctamente.']; ?>
      <div class="alerta alerta-aviso" style="margin-bottom:20px;"><?php echo $msgs[$_GET['msg']]??''; ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="reservas-tabs">
      <button class="rtab-btn" id="rtab-proximas" onclick="activarTab('proximas')">
        🗓️ Próximas
        <span class="rtab-badge"><?php echo count($proximas); ?></span>
      </button>
      <button class="rtab-btn" id="rtab-historial" onclick="activarTab('historial')">
        📋 Historial
        <span class="rtab-badge"><?php echo count($historial); ?></span>
      </button>
    </div>

    <?php
    function renderTabla(array $reservas, bool $mostrar_acciones = false): void {
        $iconos_pago = ['efectivo'=>'💵','tarjeta'=>'💳','bizum'=>'📱','transferencia'=>'🏦'];
        $badges = [
            'pendiente'  => ['clase'=>'badge-pendiente',  'texto'=>'Pendiente'],
            'confirmada' => ['clase'=>'badge-confirmada', 'texto'=>'Confirmada'],
            'asignada'   => ['clase'=>'badge-asignada',   'texto'=>'Asignada'],
            'completada' => ['clase'=>'badge-completada', 'texto'=>'Completada'],
            'cancelada'  => ['clase'=>'badge-cancelada',  'texto'=>'Cancelada'],
        ];

        if (empty($reservas)): ?>
          <div class="sin-tab">
            <div class="sin-icono"><?php echo $mostrar_acciones ? '🗓️' : '📂'; ?></div>
            <p><?php echo $mostrar_acciones ? 'No tienes reservas próximas.' : 'No hay reservas en el historial.'; ?></p>
          </div>
        <?php return; endif;

        $grupos  = [];
        foreach ($reservas as $f) $grupos[$f['fecha_reserva']][] = $f;

        $hoy    = date('Y-m-d');
        $manana = date('Y-m-d', strtotime('+1 day'));
        $dias_es  = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
        $meses_es = ['January'=>'enero','February'=>'febrero','March'=>'marzo','April'=>'abril','May'=>'mayo','June'=>'junio','July'=>'julio','August'=>'agosto','September'=>'septiembre','October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];
    ?>
        <div class="tabla-responsive">
          <table class="tabla-reservas">
            <thead>
              <tr>
                <th>Origen</th><th>Destino</th><th>Hora</th>
                <th>Pasaj.</th><th>KM</th><th>Precio</th>
                <th>Extras</th><th>Pago</th><th>Estado</th><th>Conductor</th>
                <?php if($mostrar_acciones): ?><th></th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
        <?php foreach ($grupos as $fecha => $filas):
            $ts      = strtotime($fecha);
            $dia_en  = date('l',$ts); $mes_en = date('F',$ts);
            $dia_num = date('d',$ts); $anio = date('Y',$ts);
            $fecha_es = ($dias_es[$dia_en]??$dia_en).', '.$dia_num.' de '.($meses_es[$mes_en]??$mes_en).' de '.$anio;

            if ($fecha===$hoy)       { $etiqueta='HOY · '.$fecha_es;     $estilo='color:#256029;font-weight:800;'; }
            elseif($fecha===$manana) { $etiqueta='MAÑANA · '.$fecha_es;  $estilo='color:#856404;font-weight:800;'; }
            elseif($fecha < $hoy)    { $etiqueta=$fecha_es;               $estilo='color:#888;'; }
            else                     { $etiqueta=$fecha_es;               $estilo='color:#444;font-weight:700;'; }
            $colspan = $mostrar_acciones ? 11 : 10;
        ?>
              <tr class="fecha-separador">
                <td colspan="<?php echo $colspan; ?>">
                  <span class="fecha-sep-texto" style="<?php echo $estilo; ?>"><?php echo $etiqueta; ?></span>
                </td>
              </tr>
        <?php foreach($filas as $f):
              $estado  = $f['estado'] ?? 'pendiente';
              $badge   = $badges[$estado] ?? ['clase'=>'badge-pendiente','texto'=>ucfirst($estado)];
        ?>
              <tr>
                <td><?php echo htmlspecialchars($f['origen']); ?></td>
                <td><?php echo htmlspecialchars($f['destino']); ?></td>
                <td><?php echo htmlspecialchars(substr($f['hora_reserva'],0,5)); ?></td>
                <td><?php echo htmlspecialchars($f['num_pasajeros']); ?></td>
                <td><?php echo htmlspecialchars($f['distancia_km']); ?></td>
                <td><?php echo number_format($f['precio'],2); ?> €</td>
                <td><?php
                  $extras=[];
                  if($f['silla_bebe']&&$f['silla_bebe']!=='no') $extras[]='👶 '.$f['silla_bebe'];
                  if($f['mascota']==='si') $extras[]='🐾';
                  echo $extras ? implode(' ',$extras) : '—';
                ?></td>
                <td><?php if($f['metodo_pago']): ?>
                  <span style="font-size:13px;font-weight:600;color:#256029;"><?php echo ($iconos_pago[$f['metodo_pago']]??'').' '.ucfirst(htmlspecialchars($f['metodo_pago'])); ?></span>
                <?php else: ?><span style="color:#aaa;font-size:13px;">—</span><?php endif; ?></td>
                <td>
                  <span class="<?php echo $badge['clase']; ?>">
                    <?php echo $badge['texto']; ?>
                  </span>
                </td>
                <td>
                  <?php if(in_array($estado,['asignada','completada']) && !empty($f['nombre_conductor'])): ?>
                    <div class="conductor-info">
                      <div class="cond-nombre">🚗 <?php echo htmlspecialchars($f['nombre_conductor']); ?></div>
                      <div class="cond-tel">📞 <?php echo htmlspecialchars($f['telefono_conductor']); ?></div>
                    </div>
                  <?php else: ?>
                    <span style="color:#ccc;font-size:13px;">—</span>
                  <?php endif; ?>
                </td>
                <?php if($mostrar_acciones): ?>
                <td>
                  <?php if($estado==='pendiente'): ?>
                    <div class="acciones-reserva">
                      <button class="acciones-btn" onclick="toggleAcciones(<?php echo $f['id']; ?>)" title="Opciones">•••</button>
                      <div class="acciones-menu" id="menu-<?php echo $f['id']; ?>">
                        <a href="editar_reserva.php?id=<?php echo $f['id']; ?>">✏️ Editar</a>
                        <a href="lista_reservas.php?cancelar=<?php echo $f['id']; ?>" class="cancelar"
                           onclick="return confirm('¿Seguro que quieres cancelar esta reserva?')">❌ Cancelar</a>
                      </div>
                    </div>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
              </tr>
        <?php endforeach; endforeach; ?>
            </tbody>
          </table>
        </div>
    <?php } ?>

    <!-- PANEL PRÓXIMAS -->
    <div class="rtab-panel" id="rpanel-proximas">
      <?php renderTabla($proximas, true); ?>
    </div>

    <!-- PANEL HISTORIAL -->
    <div class="rtab-panel" id="rpanel-historial">
      <?php renderTabla($historial, false); ?>
    </div>

    <div class="acciones-tabla">
      <a href="reserva.php" class="btn primary">Nueva reserva</a>
    </div>
  </div>
</div>

<footer class="footer"><p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p></footer>
</body>
</html>
