<?php
session_start();
require_once 'auth.php';
requiere_rol('admin');

$conexion = new mysqli("localhost","root","","vtc");

// ===== CREAR CONDUCTOR =====
$ok_conductor = ''; $error_conductor = '';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['crear_conductor'])) {
    $nombre   = trim($_POST['nombre']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password']      ?? '';

    if ($nombre===''||$email===''||$telefono===''||$password==='') {
        $error_conductor = 'Todos los campos son obligatorios.';
    } elseif (strlen($password) < 6) {
        $error_conductor = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $conexion->prepare("INSERT INTO usuarios (nombre,email,rol,telefono,password) VALUES (?,?,'conductor',?,?)");
        $st->bind_param("ssss", $nombre, $email, $telefono, $hash);
        if ($st->execute()) {
            $ok_conductor = 'Conductor creado correctamente.';
        } else {
            $error_conductor = 'Error: el email ya está en uso o hay un problema en la BD.';
        }
        $st->close();
    }
    // Redirigir para evitar reenvío de formulario
    if ($ok_conductor !== '') {
        header("Location: panel_admin.php?msg=conductor_creado&tab=conductores"); exit;
    }
}

// ===== ELIMINAR CONDUCTOR =====
if (isset($_GET['eliminar_conductor']) && is_numeric($_GET['eliminar_conductor'])) {
    $id_c = intval($_GET['eliminar_conductor']);
    // Desasignar de reservas antes de eliminar
    $conexion->query("UPDATE reservas SET id_conductor=NULL, estado='pendiente' WHERE id_conductor=$id_c AND estado='asignada'");
    $st = $conexion->prepare("DELETE FROM usuarios WHERE id=? AND rol='conductor'");
    $st->bind_param("i", $id_c);
    $st->execute();
    $st->close();
    header("Location: panel_admin.php?msg=conductor_eliminado&tab=conductores"); exit;
}

// ===== ASIGNAR CONDUCTOR =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['asignar'])) {
    $id_reserva   = intval($_POST['id_reserva']);
    $id_conductor = intval($_POST['id_conductor']);
    $st = $conexion->prepare(
        "UPDATE reservas SET id_conductor=?, estado='asignada' WHERE id=?"
    );
    $st->bind_param("ii", $id_conductor, $id_reserva);
    $st->execute();
    $st->close();
    header("Location: panel_admin.php?msg=asignada&tab=".$_POST['tab_actual']); exit;
}

// ===== DESASIGNAR CONDUCTOR =====
if (isset($_GET['desasignar']) && is_numeric($_GET['desasignar'])) {
    $id_r = intval($_GET['desasignar']);
    $st = $conexion->prepare(
        "UPDATE reservas SET id_conductor=NULL, estado='pendiente' WHERE id=?"
    );
    $st->bind_param("i", $id_r);
    $st->execute();
    $st->close();
    header("Location: panel_admin.php?msg=desasignada&tab=pendientes"); exit;
}

// ===== CARGAR CONDUCTORES =====
$conductores = [];
$res = $conexion->query("SELECT id, nombre, email, telefono FROM usuarios WHERE rol='conductor' ORDER BY nombre ASC");
while ($c = $res->fetch_assoc()) $conductores[] = $c;

// ===== CARGAR TODAS LAS RESERVAS =====
$sql = "SELECT r.id, r.origen, r.destino, r.fecha_reserva, r.hora_reserva,
               r.num_pasajeros, r.distancia_km, r.precio, r.estado,
               r.silla_bebe, r.silla_bebe_info, r.mascota, r.comentarios,
               r.id_conductor,
               p.metodo_pago,
               u.nombre  AS nombre_cliente,
               u.telefono AS telefono_cliente,
               u.email   AS email_cliente,
               c.nombre  AS nombre_conductor,
               c.telefono AS telefono_conductor
        FROM reservas r
        LEFT JOIN pagos p    ON p.id_reserva = r.id
        LEFT JOIN usuarios u ON u.id = r.id_usuario
        LEFT JOIN usuarios c ON c.id = r.id_conductor
        ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC";

$todas       = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);
$conexion->close();

$pendientes  = array_filter($todas, fn($r) => $r['estado'] === 'pendiente');
$asignadas   = array_filter($todas, fn($r) => $r['estado'] === 'asignada');
$completadas = array_filter($todas, fn($r) => $r['estado'] === 'completada');
$canceladas  = array_filter($todas, fn($r) => $r['estado'] === 'cancelada');

$tab_activa  = $_GET['tab'] ?? 'pendientes';

$dias_es  = ['Sunday'=>'Dom','Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mié','Thursday'=>'Jue','Friday'=>'Vie','Saturday'=>'Sáb'];
$meses_es = ['January'=>'enero','February'=>'febrero','March'=>'marzo','April'=>'abril','May'=>'mayo','June'=>'junio','July'=>'julio','August'=>'agosto','September'=>'septiembre','October'=>'octubre','November'=>'noviembre','December'=>'diciembre'];
$iconos_pago = ['efectivo'=>'💵','tarjeta'=>'💳','bizum'=>'📱','transferencia'=>'🏦'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Admin - Prometeo VTC</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .panel-header {
      display:flex; justify-content:space-between; align-items:center;
      margin-bottom:28px; flex-wrap:wrap; gap:12px;
    }
    .panel-header h1 { margin:0; }
    .admin-badge {
      background:rgba(201,168,76,0.12); border:1px solid rgba(201,168,76,0.4);
      border-radius:10px; padding:8px 16px; font-size:13px; font-weight:700;
      color:var(--color-acento);
    }

    /* Stats */
    .stats-bar {
      display:grid; grid-template-columns:repeat(4,1fr); gap:14px;
      margin-bottom:32px;
    }
    .stat-card {
      background:white; border:1px solid rgba(0,0,0,0.08); border-radius:12px;
      padding:18px 20px; text-align:center; box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }
    .stat-num  { font-size:32px; font-weight:800; color:#111; }
    .stat-label{ font-size:12px; font-weight:700; text-transform:uppercase;
                 letter-spacing:0.07em; color:#888; margin-top:4px; }
    .stat-card.pendiente  .stat-num { color:#856404; }
    .stat-card.asignada   .stat-num { color:var(--color-acento); }
    .stat-card.completada .stat-num { color:#155724; }
    .stat-card.cancelada  .stat-num { color:#721c24; }

    /* Tabs */
    .reservas-tabs { display:flex; gap:0; margin-bottom:32px; border-bottom:2px solid rgba(0,0,0,0.08); flex-wrap:wrap; }
    .rtab-btn {
      background:none; border:none; padding:14px 24px; font-family:var(--font-body);
      font-size:14px; font-weight:700; color:#888; cursor:pointer;
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

    /* Tarjetas reservas */
    .reservas-grid {
      display:grid; grid-template-columns:repeat(auto-fill, minmax(360px,1fr)); gap:18px;
    }
    .reserva-card {
      background:white; border:1px solid rgba(0,0,0,0.09);
      border-radius:12px; padding:20px;
      box-shadow:0 2px 8px rgba(0,0,0,0.06);
    }
    .reserva-card.pendiente  { border-left:4px solid #ffc107; }
    .reserva-card.asignada   { border-left:4px solid var(--color-acento); }
    .reserva-card.completada { border-left:4px solid #28a745; opacity:0.85; }
    .reserva-card.cancelada  { border-left:4px solid #dc3545; opacity:0.75; }

    .card-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
    .card-fecha  { font-size:12px; color:#888; font-weight:600; }
    .card-hora   { font-size:20px; font-weight:800; color:#111; }

    .card-ruta { display:flex; align-items:center; gap:8px; margin-bottom:12px; font-size:14px; flex-wrap:wrap; }
    .card-ruta .origen  { font-weight:700; color:#111; }
    .card-ruta .flecha  { color:var(--color-acento); }
    .card-ruta .destino { font-weight:700; color:#111; }

    .card-datos { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:12px; }
    .card-dato  { font-size:13px; color:#555; }
    .card-dato strong { color:#111; display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:2px; }

    .card-info-box {
      background:#f8f9fa; border-radius:8px; padding:10px 14px; margin-bottom:12px;
      font-size:13px; color:#555; line-height:1.7;
      border-left:3px solid #dee2e6;
    }
    .card-info-box.cliente   { border-left-color:var(--color-acento); }
    .card-info-box.conductor { border-left-color:#28a745; }
    .card-info-box strong { color:#111; }

    .card-extras { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
    .extra-tag {
      background:rgba(201,168,76,0.1); border:1px solid rgba(201,168,76,0.3);
      border-radius:20px; padding:3px 10px; font-size:12px; color:#856404;
    }

    .card-footer {
      display:flex; justify-content:space-between; align-items:center;
      padding-top:12px; border-top:1px solid rgba(0,0,0,0.07); flex-wrap:wrap; gap:8px;
    }
    .card-precio { font-size:20px; font-weight:800; color:#111; }

    /* Formulario asignar */
    .form-asignar {
      display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:12px;
      padding-top:12px; border-top:1px solid rgba(0,0,0,0.07);
    }
    .form-asignar select {
      flex:1; min-width:160px; padding:8px 12px; border:1px solid #ddd;
      border-radius:8px; font-size:13px; font-family:var(--font-body);
    }
    .btn-asignar {
      background:var(--color-acento); color:#111; border:none; border-radius:8px;
      padding:8px 16px; font-size:13px; font-weight:700; cursor:pointer;
      white-space:nowrap; transition:opacity 0.2s;
    }
    .btn-asignar:hover { opacity:0.85; }
    .btn-desasignar {
      background:#dc3545; color:white; border:none; border-radius:8px;
      padding:8px 14px; font-size:13px; font-weight:700; cursor:pointer;
      white-space:nowrap; transition:opacity 0.2s; text-decoration:none; display:inline-block;
    }
    .btn-desasignar:hover { opacity:0.85; }

    .badge-estado {
      display:inline-block; padding:4px 12px; border-radius:20px;
      font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;
    }
    .badge-pendiente  { background:#fff3cd; color:#856404; }
    .badge-asignada   { background:#fff3cd; color:#856404; border:1px solid var(--color-acento); }
    .badge-completada { background:#d4edda; color:#155724; }
    .badge-cancelada  { background:#f8d7da; color:#721c24; }

    .sin-tab { text-align:center; padding:50px 20px; color:#888; }
    .sin-tab .sin-icono { font-size:3rem; margin-bottom:12px; }

    /* ===== PESTAÑA CONDUCTORES ===== */
    .conductores-layout {
      display:grid; grid-template-columns:1fr 1.2fr; gap:28px; align-items:flex-start;
    }
    .conductores-form-wrap {
      background:white; border:1px solid rgba(0,0,0,0.09);
      border-radius:12px; padding:24px;
      box-shadow:0 2px 8px rgba(0,0,0,0.06);
    }
    .conductores-form-wrap h3 {
      font-family:var(--font-display); font-size:1.2rem; color:#111;
      margin-bottom:18px; padding-bottom:10px;
      border-bottom:1px solid rgba(0,0,0,0.07);
    }
    .conductores-form-wrap label {
      display:block; font-size:11px; font-weight:700; text-transform:uppercase;
      letter-spacing:0.07em; color:#555; margin-bottom:5px;
    }
    .conductores-form-wrap input {
      width:100%; padding:10px 12px; border:1.5px solid rgba(0,0,0,0.12);
      border-radius:8px; font-size:14px; font-family:var(--font-body);
      color:#111; margin-bottom:14px; transition:border-color 0.2s, box-shadow 0.2s;
    }
    .conductores-form-wrap input:focus {
      border-color:var(--color-acento); box-shadow:0 0 0 3px rgba(201,168,76,0.18); outline:none;
    }
    .btn-crear-conductor {
      width:100%; background:var(--color-acento); color:#111; border:none;
      border-radius:8px; padding:12px; font-size:14px; font-weight:700;
      cursor:pointer; transition:opacity 0.2s; letter-spacing:0.05em;
    }
    .btn-crear-conductor:hover { opacity:0.85; }

    /* Lista conductores */
    .conductores-lista h3 {
      font-family:var(--font-display); font-size:1.2rem; color:#111;
      margin-bottom:16px;
    }
    .conductor-card {
      background:white; border:1px solid rgba(0,0,0,0.09); border-radius:12px;
      padding:16px 18px; margin-bottom:12px;
      box-shadow:0 2px 6px rgba(0,0,0,0.05);
      display:flex; justify-content:space-between; align-items:center; gap:12px;
      border-left:4px solid #28a745;
    }
    .conductor-card:hover { box-shadow:0 4px 14px rgba(0,0,0,0.1); }
    .conductor-info .conductor-nombre {
      font-weight:700; font-size:15px; color:#111; margin-bottom:4px;
    }
    .conductor-info .conductor-datos {
      font-size:13px; color:#666; line-height:1.6;
    }
    .btn-eliminar-conductor {
      background:none; border:1.5px solid #dc3545; color:#dc3545;
      border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700;
      cursor:pointer; transition:all 0.2s; white-space:nowrap;
    }
    .btn-eliminar-conductor:hover { background:#dc3545; color:white; }
    .sin-conductores {
      text-align:center; padding:30px; color:#888; font-size:14px;
    }

    @media(max-width:768px) {
      .conductores-layout { grid-template-columns:1fr; }
    }
    @media(max-width:600px) {
      .stats-bar { grid-template-columns:1fr 1fr; }
      .reservas-grid { grid-template-columns:1fr; }
    }
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
      <h1>⚙️ Panel de Administración</h1>
      <span class="admin-badge">👤 <?php echo htmlspecialchars($_SESSION['nombre']); ?> — Admin</span>
    </div>

    <?php
    $msgs_all = [
      'asignada'           => '✅ Conductor asignado correctamente.',
      'desasignada'        => '↩️ Reserva devuelta a pendientes.',
      'conductor_creado'   => '✅ Conductor creado correctamente.',
      'conductor_eliminado'=> '🗑️ Conductor eliminado. Sus reservas asignadas vuelven a pendientes.',
    ];
    if (isset($_GET['msg']) && isset($msgs_all[$_GET['msg']])): ?>
      <div class="alerta alerta-ok" style="margin-bottom:20px;"><?php echo $msgs_all[$_GET['msg']]; ?></div>
    <?php endif;
    if ($error_conductor !== ''): ?>
      <div class="alerta alerta-error" style="margin-bottom:20px;"><?php echo htmlspecialchars($error_conductor); ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-bar">
      <div class="stat-card pendiente">
        <div class="stat-num"><?php echo count($pendientes); ?></div>
        <div class="stat-label">Pendientes</div>
      </div>
      <div class="stat-card asignada">
        <div class="stat-num"><?php echo count($asignadas); ?></div>
        <div class="stat-label">Asignadas</div>
      </div>
      <div class="stat-card completada">
        <div class="stat-num"><?php echo count($completadas); ?></div>
        <div class="stat-label">Completadas</div>
      </div>
      <div class="stat-card cancelada">
        <div class="stat-num"><?php echo count($canceladas); ?></div>
        <div class="stat-label">Canceladas</div>
      </div>
    </div>

    <!-- TABS -->
    <div class="reservas-tabs">
      <button class="rtab-btn" id="rtab-pendientes"  onclick="activarTab('pendientes')">
        🕐 Pendientes <span class="rtab-badge"><?php echo count($pendientes); ?></span>
      </button>
      <button class="rtab-btn" id="rtab-asignadas"   onclick="activarTab('asignadas')">
        🚗 Asignadas <span class="rtab-badge"><?php echo count($asignadas); ?></span>
      </button>
      <button class="rtab-btn" id="rtab-completadas" onclick="activarTab('completadas')">
        ✅ Completadas <span class="rtab-badge"><?php echo count($completadas); ?></span>
      </button>
      <button class="rtab-btn" id="rtab-canceladas"  onclick="activarTab('canceladas')">
        ❌ Canceladas <span class="rtab-badge"><?php echo count($canceladas); ?></span>
      </button>
      <button class="rtab-btn" id="rtab-conductores" onclick="activarTab('conductores')">
        🚗 Conductores <span class="rtab-badge"><?php echo count($conductores); ?></span>
      </button>
    </div>

    <?php
    function renderCardsAdmin(array $reservas, string $tab, array $conductores, array $dias_es, array $meses_es, array $iconos_pago): void {
        $iconos_tab = ['pendientes'=>'🕐','asignadas'=>'🚗','completadas'=>'✅','canceladas'=>'❌'];
        if (empty($reservas)): ?>
          <div class="sin-tab">
            <div class="sin-icono"><?php echo $iconos_tab[$tab]??'📋'; ?></div>
            <p>No hay reservas <?php echo $tab; ?>.</p>
          </div>
        <?php return; endif; ?>

        <div class="reservas-grid">
        <?php foreach($reservas as $r):
            $ts = strtotime($r['fecha_reserva']);
            $dia_en = date('l',$ts); $mes_en = date('F',$ts);
            $fecha_es = ($dias_es[$dia_en]??$dia_en).', '.date('d',$ts).' de '.($meses_es[$mes_en]??$mes_en).' de '.date('Y',$ts);
            $extras = [];
            if($r['silla_bebe']&&$r['silla_bebe']!=='no') $extras[]='👶 Silla bebé: '.$r['silla_bebe'].($r['silla_bebe_info']?' ('.$r['silla_bebe_info'].')':'');
            if($r['mascota']==='si') $extras[]='🐾 Viaje con mascota';
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
              <div class="card-dato"><strong>Precio</strong><?php echo number_format($r['precio'],2); ?> €</div>
              <div class="card-dato"><strong>Pago</strong>
                <?php echo $r['metodo_pago'] ? ($iconos_pago[$r['metodo_pago']]??'').' '.ucfirst($r['metodo_pago']) : '—'; ?>
              </div>
            </div>

            <div class="card-info-box cliente">
              <strong>👤 Cliente</strong>
              <?php echo htmlspecialchars($r['nombre_cliente']); ?><br>
              📞 <?php echo htmlspecialchars($r['telefono_cliente']); ?> &nbsp;|&nbsp;
              ✉️ <?php echo htmlspecialchars($r['email_cliente']); ?>
            </div>

            <?php if($r['nombre_conductor']): ?>
            <div class="card-info-box conductor">
              <strong>🚗 Conductor asignado</strong>
              <?php echo htmlspecialchars($r['nombre_conductor']); ?><br>
              📞 <?php echo htmlspecialchars($r['telefono_conductor']); ?>
            </div>
            <?php endif; ?>

            <?php if(!empty($extras)): ?>
            <div class="card-extras">
              <?php foreach($extras as $e): ?>
                <span class="extra-tag"><?php echo htmlspecialchars($e); ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if($r['comentarios']): ?>
            <div class="card-info-box" style="border-left-color:#aaa;">
              💬 <?php echo htmlspecialchars($r['comentarios']); ?>
            </div>
            <?php endif; ?>

            <?php if(in_array($r['estado'], ['pendiente','asignada']) && !empty($conductores)): ?>
            <form method="post" action="panel_admin.php" class="form-asignar">
              <input type="hidden" name="asignar"    value="1">
              <input type="hidden" name="id_reserva" value="<?php echo $r['id']; ?>">
              <input type="hidden" name="tab_actual" value="<?php echo $tab; ?>">
              <select name="id_conductor" required>
                <option value="">— Seleccionar conductor —</option>
                <?php foreach($conductores as $c): ?>
                  <option value="<?php echo $c['id']; ?>"
                    <?php echo $r['id_conductor']==$c['id']?'selected':''; ?>>
                    <?php echo htmlspecialchars($c['nombre']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn-asignar">
                <?php echo $r['id_conductor'] ? '🔄 Reasignar' : '✅ Asignar'; ?>
              </button>
              <?php if($r['id_conductor']): ?>
                <a href="panel_admin.php?desasignar=<?php echo $r['id']; ?>"
                   class="btn-desasignar"
                   onclick="return confirm('¿Quitar el conductor asignado y devolver a pendientes?')">
                  ✖ Desasignar
                </a>
              <?php endif; ?>
            </form>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
        </div>
    <?php }
    ?>

    <!-- PANELES RESERVAS -->
    <div class="rtab-panel" id="rpanel-pendientes">
      <?php renderCardsAdmin($pendientes,  'pendientes',  $conductores, $dias_es, $meses_es, $iconos_pago); ?>
    </div>
    <div class="rtab-panel" id="rpanel-asignadas">
      <?php renderCardsAdmin($asignadas,   'asignadas',   $conductores, $dias_es, $meses_es, $iconos_pago); ?>
    </div>
    <div class="rtab-panel" id="rpanel-completadas">
      <?php renderCardsAdmin($completadas, 'completadas', $conductores, $dias_es, $meses_es, $iconos_pago); ?>
    </div>
    <div class="rtab-panel" id="rpanel-canceladas">
      <?php renderCardsAdmin($canceladas,  'canceladas',  $conductores, $dias_es, $meses_es, $iconos_pago); ?>
    </div>

    <!-- PANEL CONDUCTORES -->
    <div class="rtab-panel" id="rpanel-conductores">
      <div class="conductores-layout">

        <!-- Formulario crear conductor -->
        <div class="conductores-form-wrap">
          <h3>➕ Crear nuevo conductor</h3>
          <form method="post" action="panel_admin.php?tab=conductores">
            <input type="hidden" name="crear_conductor" value="1">
            <label>Nombre completo</label>
            <input type="text" name="nombre" required placeholder="Ej: Carlos García">
            <label>Correo electrónico</label>
            <input type="email" name="email" required placeholder="conductor@email.com">
            <label>Teléfono</label>
            <input type="tel" name="telefono" required placeholder="+34 600 000 000">
            <label>Contraseña</label>
            <input type="password" name="password" required placeholder="Mínimo 6 caracteres">
            <button type="submit" class="btn-crear-conductor">🚗 Crear conductor</button>
          </form>
        </div>

        <!-- Lista de conductores -->
        <div class="conductores-lista">
          <h3>👥 Conductores registrados (<?php echo count($conductores); ?>)</h3>

          <?php if (empty($conductores)): ?>
            <div class="sin-conductores">
              <div style="font-size:2.5rem;margin-bottom:10px;">🚗</div>
              <p>No hay conductores registrados todavía.</p>
            </div>
          <?php else: ?>
            <?php foreach($conductores as $c): ?>
              <div class="conductor-card">
                <div class="conductor-info">
                  <div class="conductor-nombre">🚗 <?php echo htmlspecialchars($c['nombre']); ?></div>
                  <div class="conductor-datos">
                    ✉️ <?php echo htmlspecialchars($c['email']); ?><br>
                    📞 <?php echo htmlspecialchars($c['telefono']); ?>
                  </div>
                </div>
                <button class="btn-eliminar-conductor"
                  onclick="if(confirm('¿Eliminar a <?php echo addslashes($c['nombre']); ?>? Sus reservas asignadas volverán a pendientes.')) window.location='panel_admin.php?eliminar_conductor=<?php echo $c['id']; ?>&tab=conductores'">
                  🗑️ Eliminar
                </button>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </div>

  </div>
</div>

<footer class="footer"><p>&copy; 2026 Prometeo VTC · Servicio privado de transporte en Málaga</p></footer>
</body>
</html>
