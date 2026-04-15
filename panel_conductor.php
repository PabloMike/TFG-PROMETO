<?php
session_start();
require_once 'auth.php';
requiere_rol('conductor');

$id_conductor = $_SESSION['id_usuario'];
$conexion     = new mysqli("localhost","root","","vtc");
$hoy          = date('Y-m-d');

// Marcar reserva como completada
if (isset($_GET['completar']) && is_numeric($_GET['completar'])) {
    $id_r = intval($_GET['completar']);
    $st   = $conexion->prepare(
        "UPDATE reservas SET estado='completada' WHERE id=? AND id_conductor=? AND estado='asignada'"
    );
    $st->bind_param("ii", $id_r, $id_conductor);
    $st->execute();
    $st->close();
    header("Location: panel_conductor.php?msg=completada"); exit;
}

// Cargar reservas asignadas a este conductor
$stmt = $conexion->prepare(
    "SELECT r.id, r.origen, r.destino, r.fecha_reserva, r.hora_reserva,
            r.num_pasajeros, r.distancia_km, r.precio, r.estado,
            r.silla_bebe, r.silla_bebe_info, r.mascota, r.comentarios,
            r.fecha_creacion, p.metodo_pago,
            u.nombre AS nombre_cliente, u.telefono AS telefono_cliente, u.email AS email_cliente
     FROM reservas r
     LEFT JOIN pagos p ON p.id_reserva = r.id
     LEFT JOIN usuarios u ON u.id = r.id_usuario
     WHERE r.id_conductor = ?
     ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC"
);
$stmt->bind_param("i", $id_conductor);
$stmt->execute();
$todas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conexion->close();

// Separar por estado
$asignadas   = array_filter($todas, fn($r) => $r['estado'] === 'asignada');
$completadas = array_filter($todas, fn($r) => $r['estado'] === 'completada');
$canceladas  = array_filter($todas, fn($r) => $r['estado'] === 'cancelada');

$tab_activa = $_GET['tab'] ?? 'asignadas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Conductor - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .panel-header {
      display: flex; justify-content: space-between; align-items: center;
      margin-bottom: 28px; flex-wrap: wrap; gap: 12px;
    }
    .panel-header h1 { margin: 0; }
    .conductor-info {
      background: rgba(201,168,76,0.08); border: 1px solid rgba(201,168,76,0.3);
      border-radius: 10px; padding: 10px 18px; font-size: 14px; color: #555;
    }
    .conductor-info strong { color: #111; }

    .reservas-tabs { display:flex; gap:0; margin-bottom:32px; border-bottom:2px solid rgba(0,0,0,0.08); }
    .rtab-btn {
      background:none; border:none; padding:14px 28px; font-family:var(--font-body);
      font-size:15px; font-weight:700; color:#888; cursor:pointer;
      border-bottom:3px solid transparent; margin-bottom:-2px;
      transition:color 0.2s, border-color 0.2s; display:flex; align-items:center; gap:8px;
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

    .sin-tab { text-align:center; padding:50px 20px; color:#888; }
    .sin-tab .sin-icono { font-size:3rem; margin-bottom:12px; }

    /* Tarjetas de reserva */
    .reservas-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
      gap: 18px;
    }
    .reserva-card {
      background: white; border: 1px solid rgba(0,0,0,0.1);
      border-radius: 12px; padding: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      transition: box-shadow 0.2s;
    }
    .reserva-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
    .reserva-card.asignada { border-left: 4px solid var(--color-acento); }
    .reserva-card.completada { border-left: 4px solid #28a745; opacity: 0.85; }
    .reserva-card.cancelada  { border-left: 4px solid #dc3545; opacity: 0.75; }

    .card-header {
      display: flex; justify-content: space-between; align-items: flex-start;
      margin-bottom: 14px;
    }
    .card-fecha { font-size: 13px; color: #888; font-weight: 600; }
    .card-hora  { font-size: 22px; font-weight: 800; color: #111; }

    .card-ruta {
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 14px; font-size: 14px;
    }
    .card-ruta .origen  { font-weight: 700; color: #111; }
    .card-ruta .flecha  { color: var(--color-acento); font-size: 18px; }
    .card-ruta .destino { font-weight: 700; color: #111; }

    .card-datos {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 8px; margin-bottom: 14px;
    }
    .card-dato { font-size: 13px; color: #555; }
    .card-dato strong { color: #111; display: block; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 2px; }

    .card-cliente {
      background: rgba(201,168,76,0.06); border: 1px solid rgba(201,168,76,0.2);
      border-radius: 8px; padding: 10px 14px; margin-bottom: 14px;
    }
    .card-cliente p { margin: 0; font-size: 13px; color: #555; line-height: 1.6; }
    .card-cliente p strong { color: #111; }

    .card-extras {
      display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px;
    }
    .extra-tag {
      background: rgba(201,168,76,0.1); border: 1px solid rgba(201,168,76,0.3);
      border-radius: 20px; padding: 3px 10px; font-size: 12px; color: #856404;
    }

    .card-comentario {
      background: #f8f9fa; border-radius: 8px; padding: 10px 14px;
      font-size: 13px; color: #555; margin-bottom: 14px;
      border-left: 3px solid #dee2e6;
    }

    .card-footer {
      display: flex; justify-content: space-between; align-items: center;
      padding-top: 14px; border-top: 1px solid rgba(0,0,0,0.07);
    }
    .card-precio { font-size: 20px; font-weight: 800; color: #111; }
    .card-pago   { font-size: 13px; color: #888; }

    .btn-completar {
      background: #28a745; color: white; border: none; border-radius: 8px;
      padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer;
      transition: background 0.2s;
    }
    .btn-completar:hover { background: #218838; }

    .badge-asignada   { background:#fff3cd; color:#856404; border:1px solid #ffc107; }
    .badge-completada { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
    .badge-cancelada  { background:#f8d7da; color:#721c24; border:1px solid #f1aeb5; }
    .badge-estado {
      display:inline-block; padding:4px 12px; border-radius:20px;
      font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;
    }

    .dias_es { display:none; }
  </style>
  <script>
    function activarTab(id) {
      document.querySelectorAll('.rtab-btn').forEach(b=>b.classList.remove('activo'));
      document.querySelectorAll('.rtab-panel').forEach(p=>p.classList.remove('activo'));
      document.getElementById('rtab-'+id).classList.add('activo');
      document.getElementById('rpanel-'+id).classList.add('activo');
      history.replaceState(null,'','?tab='+id);
    }
    document.addEventListener('DOMContentLoaded', function(){
      var params = new URLSearchParams(window.location.search);
      activarTab(params.get('tab') || '<?php echo $tab_activa; ?>');
    });
  </script>
</head>
<body>
<?php include 'header.php'; ?>

<div class="pagina-tabla">
  <div class="caja-tabla">

    <div class="panel-header">
      <h1>🚗 Panel del Conductor</h1>
      <div class="conductor-info">
        Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong>
      </div>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg']==='completada'): ?>
      <div class="alerta alerta-ok" style="margin-bottom:20px;">✅ Reserva marcada como completada correctamente.</div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="reservas-tabs">
      <button class="rtab-btn" id="rtab-asignadas" onclick="activarTab('asignadas')">
        🗓️ Asignadas
        <span class="rtab-badge"><?php echo count($asignadas); ?></span>
      </button>
      <button class="rtab-btn" id="rtab-completadas" onclick="activarTab('completadas')">
        ✅ Completadas
        <span class="rtab-badge"><?php echo count($completadas); ?></span>
      </button>
      <button class="rtab-btn" id="rtab-canceladas" onclick="activarTab('canceladas')">
        ❌ Canceladas
        <span class="rtab-badge"><?php echo count($canceladas); ?></span>
      </button>
    </div>

    <?php
    $dias_es  = ['Sunday'=>'Dom','Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mié','Thursday'=>'Jue','Friday'=>'Vie','Saturday'=>'Sáb'];
    $meses_es = ['January'=>'enero','February'=>'febrero','March'=>'marzo','April'=>'abril','May'=>'mayo','June'=>'junio','July'=>'julio','August'=>'agosto','September'=>'septiembre','October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];

    function renderCards(array $reservas, string $estado, array $dias_es, array $meses_es): void {
        if (empty($reservas)): ?>
          <div class="sin-tab">
            <div class="sin-icono"><?php echo $estado==='asignadas'?'🗓️':($estado==='completadas'?'✅':'❌'); ?></div>
            <p>No tienes reservas <?php echo $estado; ?>.</p>
          </div>
        <?php return; endif; ?>

        <div class="reservas-grid">
        <?php foreach($reservas as $r):
            $ts      = strtotime($r['fecha_reserva']);
            $dia_en  = date('l',$ts); $mes_en = date('F',$ts);
            $fecha_es = ($dias_es[$dia_en]??$dia_en).', '.date('d',$ts).' de '.($meses_es[$mes_en]??$mes_en).' de '.date('Y',$ts);
            $extras = [];
            if($r['silla_bebe'] && $r['silla_bebe']!=='no') $extras[] = '👶 Silla bebé: '.$r['silla_bebe'].($r['silla_bebe_info']?' ('.$r['silla_bebe_info'].')':'');
            if($r['mascota']==='si') $extras[] = '🐾 Viaje con mascota';
            $iconos_pago = ['efectivo'=>'💵','tarjeta'=>'💳','bizum'=>'📱','transferencia'=>'🏦'];
        ?>
          <div class="reserva-card <?php echo htmlspecialchars($r['estado']); ?>">
            <div class="card-header">
              <div>
                <div class="card-fecha"><?php echo $fecha_es; ?></div>
                <div class="card-hora"><?php echo substr($r['hora_reserva'],0,5); ?></div>
              </div>
              <span class="badge-estado badge-<?php echo htmlspecialchars($r['estado']); ?>">
                <?php echo ucfirst($r['estado']); ?>
              </span>
            </div>

            <div class="card-ruta">
              <span class="origen"><?php echo htmlspecialchars($r['origen']); ?></span>
              <span class="flecha">→</span>
              <span class="destino"><?php echo htmlspecialchars($r['destino']); ?></span>
            </div>

            <div class="card-datos">
              <div class="card-dato"><strong>Pasajeros</strong><?php echo $r['num_pasajeros']; ?></div>
              <div class="card-dato"><strong>Distancia</strong><?php echo $r['distancia_km']; ?> km</div>
              <div class="card-dato"><strong>Pago</strong>
                <?php echo $r['metodo_pago'] ? ($iconos_pago[$r['metodo_pago']]??'').' '.ucfirst($r['metodo_pago']) : '—'; ?>
              </div>
            </div>

            <!-- Datos del cliente -->
            <div class="card-cliente">
              <p><strong>👤 Cliente:</strong> <?php echo htmlspecialchars($r['nombre_cliente']); ?></p>
              <p><strong>📞 Teléfono:</strong> <?php echo htmlspecialchars($r['telefono_cliente']); ?></p>
              <p><strong>✉️ Email:</strong> <?php echo htmlspecialchars($r['email_cliente']); ?></p>
            </div>

            <?php if(!empty($extras)): ?>
            <div class="card-extras">
              <?php foreach($extras as $e): ?>
                <span class="extra-tag"><?php echo htmlspecialchars($e); ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if($r['comentarios']): ?>
            <div class="card-comentario">
              💬 <?php echo htmlspecialchars($r['comentarios']); ?>
            </div>
            <?php endif; ?>

            <div class="card-footer">
              <div>
                <div class="card-precio"><?php echo number_format($r['precio'],2); ?> €</div>
              </div>
              <?php if($r['estado']==='asignada'): ?>
                <a href="panel_conductor.php?completar=<?php echo $r['id']; ?>"
                   class="btn-completar"
                   onclick="return confirm('¿Confirmas que este servicio ha sido completado?')">
                  ✅ Marcar como completada
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
    <?php }
    ?>

    <!-- PANEL ASIGNADAS -->
    <div class="rtab-panel" id="rpanel-asignadas">
      <?php renderCards($asignadas, 'asignadas', $dias_es, $meses_es); ?>
    </div>

    <!-- PANEL COMPLETADAS -->
    <div class="rtab-panel" id="rpanel-completadas">
      <?php renderCards($completadas, 'completadas', $dias_es, $meses_es); ?>
    </div>

    <!-- PANEL CANCELADAS -->
    <div class="rtab-panel" id="rpanel-canceladas">
      <?php renderCards($canceladas, 'canceladas', $dias_es, $meses_es); ?>
    </div>

  </div>
</div>

<footer class="footer"><p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p></footer>
</body>
</html>
