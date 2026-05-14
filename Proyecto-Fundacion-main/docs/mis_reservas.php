<?php
require 'session_boot.php';
require 'conexion.php';

// --- CSRF ---
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$__csrf = $_SESSION['csrf'];

// --- Detectar AJAX de forma robusta ---
$isAjax = (
  (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
  || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
  || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
);

// --- Handler robusto de eliminación (AJAX / normal) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['eliminar_reserva']) || isset($_POST['reservation_id']))) {
  header_remove('X-Powered-By');

  $reservationId = (int)($_POST['reservation_id'] ?? 0);
  $userId = (int)($_SESSION['user_id'] ?? 0);

  // Validación básica
  if ($reservationId <= 0 || $userId <= 0) {
    if ($isAjax) {
      header('Content-Type: application/json; charset=utf-8');
      http_response_code(400);
      echo json_encode(['ok'=>false,'error'=>'Parámetros inválidos']);
      exit;
    }
    $_SESSION['flash'] = 'No se pudo eliminar la reserva (parámetros inválidos).';
    header('Location: '.$_SERVER['REQUEST_URI']);
    exit;
  }

  // CSRF (si viene token, lo validamos)
  if (isset($_POST['csrf']) && (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf']))) {
    if ($isAjax) {
      header('Content-Type: application/json; charset=utf-8');
      http_response_code(403);
      echo json_encode(['ok'=>false,'error'=>'CSRF inválido']);
      exit;
    }
    $_SESSION['flash'] = 'CSRF inválido al eliminar la reserva.';
    header('Location: '.$_SERVER['REQUEST_URI']);
    exit;
  }

  // Ejecutar DELETE protegido por user_id
  $stmt = $connection->prepare('DELETE FROM reservations WHERE id = ? AND user_id = ?');
  if (!$stmt) {
    if ($isAjax) {
      header('Content-Type: application/json; charset=utf-8');
      http_response_code(500);
      echo json_encode(['ok'=>false,'error'=>'Error preparando consulta']);
      exit;
    }
    $_SESSION['flash'] = 'Error preparando la consulta.';
    header('Location: '.$_SERVER['REQUEST_URI']);
    exit;
  }
  $stmt->bind_param('ii', $reservationId, $userId);
  $stmt->execute();
  $deleted = $stmt->affected_rows > 0;
  $stmt->close();

  if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    if ($deleted) {
      echo json_encode(['ok'=>true,'deleted_id'=>$reservationId]);
    } else {
      http_response_code(404);
      echo json_encode(['ok'=>false,'error'=>'Reserva no encontrada o no pertenece al usuario']);
    }
    exit;
  } else {
    $_SESSION['flash'] = $deleted ? 'Reserva eliminada correctamente.' : 'Reserva no encontrada o no pertenece al usuario.';
    header('Location: '.$_SERVER['REQUEST_URI']);
    exit;
  }
}

// --- Verificar usuario logueado ---
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  header('Location: login.php');
  exit;
}

// --- Obtener reservas del usuario ---
$sql = "
  SELECT
    r.id               AS reservation_id,
    r.quantity,
    r.reservation_date,
    r.estado,
    r.payment_status,
    r.transaction_id,
    r.total_amount,
    e.id               AS event_id,
    e.title,
    e.description,
    e.start_at,
    e.end_at,
    e.location,
    e.image_path,
    e.price,
    CASE WHEN e.end_at < NOW() THEN 1 ELSE 0 END AS is_past
  FROM reservations r
  INNER JOIN events e ON e.id = r.event_id
  WHERE r.user_id = ?
  ORDER BY e.start_at DESC
";
$stmt = $connection->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$reservas = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mis Reservas - EventosApp</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" href="empresa.png" type="image/x-icon">

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  
  <style>
  /* === Navbar optimizado === */
  .bg-custom-navbar { background: linear-gradient(135deg, #6f00ff 0%, #7b33ff 100%); height: 90px; display: flex; align-items: center; padding: 0 2rem; color: #fff; font-weight: 700; font-size: 1.8rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); border-radius: 0 0 20px 20px; }
  .navbar-brand { display:flex; align-items:center; gap:.8rem; font-size: 1.8rem; }
  .brand-logo { height: 70px; width:auto; display:block; vertical-align:middle; transition: transform 0.3s ease; }
  .brand-logo:hover { transform: scale(1.05); }
  .navbar-nav .nav-link { font-size: 1.2rem; font-weight: 500; padding: 0.75rem 1rem; }
  .navbar-nav .nav-link i { font-size: 1.3rem; margin-right: 0.5rem; }
  .text-light { font-size: 1.2rem; font-weight: 500; }
  .btn-outline-light { font-size: 1.1rem; padding: 0.6rem 1.2rem; font-weight: 500; }

  /* === Resto del diseño === */
  body { background: linear-gradient(135deg, #ffffffff 0%, #fdfdfdff 100%); min-height: 100vh; }
  .main-container { background: rgba(255,255,255,0.95); border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); backdrop-filter: blur(10px); margin: 2rem 0; }
  .page-header { background: linear-gradient(135deg, #b58aff 0%, #6f00ff 100%); color: #fff; border-radius: 20px 20px 0 0; padding: 1.5rem 2rem; height: 80px; display: flex; align-items: center; width: 100%; position: relative; overflow: hidden; }
  .page-header::before { display: none; }
  .page-title { margin: 0; font-weight: 700; font-size: 1.8rem; display: flex; align-items: center; gap: 0.5rem; }
  .stats-badge { background: rgba(255, 255, 255, 0.25); border: 1px solid rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.9rem; font-weight: 600; color: #fff; white-space: nowrap; }
  .page-header .d-flex { width: 100%; }
  .reservation-card { border:none; border-radius:16px; box-shadow:0 8px 25px rgba(0,0,0,0.08); transition:all .3s ease; overflow:hidden; background:#fff; }
  .reservation-card:hover { transform: translateY(-5px); box-shadow:0 15px 35px rgba(0,0,0,0.15); }
  .event-image { width:100%; height:200px; object-fit:cover; }
  .event-image.past { filter: grayscale(50%) brightness(0.9); }
  .card-content { padding:1.5rem; }
  .event-title { font-size:1.25rem; font-weight:600; color:#2d3748; margin-bottom:.5rem; }
  .event-meta { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1rem; }
  .meta-item { display:flex; align-items:center; gap:.5rem; font-size:.9rem; color:#4a5568; }
  .meta-icon { width:16px; color:#6f42c1; }
  .status-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.5rem 1rem; border-radius:50px; font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
  .badge-upcoming { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color:#fff; box-shadow:0 4px 15px rgba(72,187,120,.3); }
  .badge-past { background: linear-gradient(135deg, #a0aec0 0%, #6f00ff 100%); color:#fff; }
  .price-info { background: linear-gradient(135deg, #ffffffff 0%, #6f00ff 100%); color:#fff; padding:1rem; border-radius:12px; margin:1rem 0; text-align:center; }
  .price-amount { font-size:1.5rem; font-weight:700; margin:0; }
  .price-label { font-size:.8rem; opacity:.9; margin:0; }
  .action-buttons { display:flex; gap:.75rem; margin-top:1.5rem; }
  .btn-view { background: linear-gradient(135deg, #fdfdfdff 0%, #efeff0ff 100%); border:none; color:#fff; padding:.75rem 1.5rem; border-radius:12px; font-weight:600; transition: all .3s ease; }
  .btn-view:hover { transform: translateY(-2px); box-shadow:0 8px 20px rgba(243, 243, 243, 0.4); color:#fff; }
  .btn-cancel { background: linear-gradient(135deg, #ff2a2aff 0%, #fd5353ff 100%); border:none; color:#fff; padding:.75rem 1.5rem; border-radius:12px; font-weight:600; transition: all .3s ease; flex:1; }
  .btn-cancel:hover { transform: translateY(-2px); box-shadow:0 8px 20px rgba(252,129,129,.4); color:#fff; }
  .btn-disabled { background:#e2e8f0; color:#a0aec0; cursor:not-allowed; }
  .empty-state { text-align:center; padding:4rem 2rem; background:#fff; border-radius:20px; margin:2rem 0; }
  .empty-icon { font-size:4rem; color:#cbd5e0; margin-bottom:1rem; }
  .reservation-item { transition: opacity .25s ease, transform .25s ease; }
  .deleting { animation: fadeOutScale .5s ease-out forwards; }
  @keyframes fadeOutScale { 0% { opacity:1; transform:scale(1); } 100% { opacity:0; transform:scale(.95); } }
  .filter-container { background:#fff; padding:1.5rem; border-radius:16px; margin-bottom:2rem; box-shadow:0 4px 15px rgba(0,0,0,.05); }
  .filter-btn { padding:.75rem 1.5rem; border-radius:50px; border:2px solid #e2e8f0; background:#fff; color:#4a5568; font-weight:600; transition:all .3s ease; }
  .filter-btn:hover, .filter-btn:checked + .filter-btn { background: linear-gradient(135deg, #ebeaeeff 0%, #6f00ff 100%); border-color:#6f42c1; color:#fff; transform: translateY(-2px); box-shadow:0 8px 20px rgba(245, 245, 245, 0.3); }
  @media (max-width:991px) { .bg-custom-navbar { height: 80px; font-size: 1.6rem; } .brand-logo { height: 60px; } .navbar-nav .nav-link { font-size: 1.1rem; } }
  @media (max-width:576px) { .bg-custom-navbar { height: 70px; font-size: 1.4rem; } .brand-logo { height: 50px; } .navbar-nav .nav-link { font-size: 1rem; } .main-container { margin:1rem; border-radius:16px; } .page-header { padding:1rem; height: 70px; } .page-title { font-size:1rem; } .action-buttons { flex-direction:column; } .event-image { height:150px; } }
</style>
<!-- Favicon -->
<link rel="icon" type="image/png" href="/Proyecto-Fundacion/docs/uploads/eventos/logo4.png"/>

</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-custom-navbar shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php"> <img src="uploads/eventos/logo4.png" alt="EventosApp" class="brand-logo">EventosApp</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i>Inicio</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-search me-1"></i>Explorar</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="text-light"><i class="bi bi-person-circle me-1"></i><?= h($_SESSION['username'] ?? 'Usuario') ?></span>
        <a class="btn btn-outline-light btn-sm" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
      </div>
    </div>
  </div>
</nav>

<div class="container">
  <div class="main-container">
    <!-- Header -->
    <div class="page-header">
      <div class="d-flex justify-content-between align-items-center">
        <h1 class="page-title"><i class="bi bi-ticket-perforated me-3"></i>Mis Reservas</h1>
        <div class="stats-badge badge fs-6"><?= count($reservas) ?> reserva<?= count($reservas) !== 1 ? 's' : '' ?></div>
      </div>
    </div>

    <div class="p-4">
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle me-2"></i><?= h($_SESSION['flash']); unset($_SESSION['flash']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$reservas): ?>
        <div class="empty-state">
          <div class="empty-icon"><i class="bi bi-ticket-perforated"></i></div>
          <h2 class="empty-title">No tienes reservas aún</h2>
          <p class="empty-text">¡Explora nuestros increíbles eventos y haz tu primera reserva!</p>
          <a href="index.php" class="btn btn-primary btn-lg"><i class="bi bi-search me-2"></i>Explorar Eventos</a>
        </div>
      <?php else: ?>
        <!-- Filtros -->
        <div class="filter-container">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="filter" id="all" autocomplete="off" checked>
                <label class="filter-btn btn" for="all">Todas (<?= count($reservas) ?>)</label>

                <input type="radio" class="btn-check" name="filter" id="upcoming" autocomplete="off">
                <label class="filter-btn btn" for="upcoming">Próximas (<?= count(array_filter($reservas, fn($r)=>!$r['is_past'])) ?>)</label>

                <input type="radio" class="btn-check" name="filter" id="past" autocomplete="off">
                <label class="filter-btn btn" for="past">Pasadas (<?= count(array_filter($reservas, fn($r)=>$r['is_past'])) ?>)</label>
              </div>
            </div>
            <div class="col-md-4 text-end">
              <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Organiza tus reservas por estado</small>
            </div>
          </div>
        </div>

        <!-- Grid de reservas -->
        <div id="reservasGrid" class="row g-4" data-reservation-container>
          <?php foreach ($reservas as $r): ?>
            <?php
              $isPast = (bool)$r['is_past'];
              $startDate = new DateTime($r['start_at']);
              $endDate = new DateTime($r['end_at']);
              $reservationDate = new DateTime($r['reservation_date']);
              $totalPrice = (float)($r['total_amount'] ?? ((float)$r['price'] * (int)$r['quantity']));
              $paymentStatus = $r['payment_status'] ?? 'free';
            ?>
            <div class="col-lg-6 col-xl-4 reservation-item <?= $isPast ? 'past-event-item' : 'upcoming-event-item' ?>">
              <div class="reservation-card <?= $isPast ? 'past-event' : '' ?>">
                <?php if (!empty($r['image_path'])): ?>
                  <img src="<?= h($r['image_path']) ?>" class="event-image <?= $isPast ? 'past' : '' ?>" alt="<?= h($r['title']) ?>" onerror="this.src='https://via.placeholder.com/400x200/6f42c1/ffffff?text=Evento'">
                <?php else: ?>
                  <div class="event-image d-flex align-items-center justify-content-center bg-light <?= $isPast ? 'past' : '' ?>"><i class="bi bi-image text-muted" style="font-size:3rem;"></i></div>
                <?php endif; ?>

                <div class="card-content">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <h3 class="event-title"><?= h($r['title']) ?></h3>
                    <div class="d-flex flex-column align-items-end gap-1">
                      <span class="status-badge <?= $isPast ? 'badge-past' : 'badge-upcoming' ?>">
                        <i class="bi bi-<?= $isPast ? 'clock-history' : 'calendar-check' ?>"></i><?= $isPast ? 'Finalizado' : 'Próximo' ?>
                      </span>
                      <?php if ($paymentStatus !== 'free'): ?>
                        <span class="badge <?= $paymentStatus === 'completed' ? 'bg-success' : ($paymentStatus === 'pending' ? 'bg-warning' : 'bg-danger') ?>">
                          <?= $paymentStatus === 'completed' ? 'Pagado' : ($paymentStatus === 'pending' ? 'Pendiente' : 'Fallido') ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <div class="event-meta">
                    <div class="meta-item"><i class="bi bi-calendar-event meta-icon"></i><span><?= $startDate->format('d/m/Y') ?> - <?= $startDate->format('H:i') ?></span></div>
                    <div class="meta-item"><i class="bi bi-clock meta-icon"></i><span>Hasta <?= $endDate->format('d/m/Y H:i') ?></span></div>
                    <div class="meta-item"><i class="bi bi-geo-alt meta-icon"></i><span><?= h($r['location']) ?></span></div>
                    <div class="meta-item"><i class="bi bi-people meta-icon"></i><span><?= (int)$r['quantity'] ?> persona<?= (int)$r['quantity'] !== 1 ? 's' : '' ?></span></div>
                    <div class="meta-item"><i class="bi bi-calendar-plus meta-icon"></i><span>Reservado el <?= $reservationDate->format('d/m/Y') ?></span></div>
                    <?php if (!empty($r['transaction_id']) && $r['transaction_id'] !== 'FREE_' . $r['reservation_id']): ?>
                      <div class="meta-item"><i class="bi bi-receipt meta-icon"></i><span>ID: <?= h(substr($r['transaction_id'], 0, 16)) ?>...</span></div>
                    <?php endif; ?>
                  </div>

                  <div class="price-info">
                    <?php if ($totalPrice > 0): ?>
                      <p class="price-amount"><?= number_format($totalPrice, 2, ',', '.') ?> €</p>
                      <p class="price-label">Total pagado</p>
                      <a href="generar_entrada_pdf.php?id=<?= (int)$r['reservation_id'] ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-file-earmark-pdf me-1"></i> Generar Entrada</a>
                    <?php else: ?>
                      <p class="price-amount">GRATIS</p>
                      <p class="price-label">Evento gratuito</p>
                    <?php endif; ?>
                  </div>

                  <div class="action-buttons">
                    <?php if (!$isPast): ?>
                      <form method="post" class="flex-fill">
                        <input type="hidden" name="reservation_id" value="<?= (int)$r['reservation_id'] ?>">
                        <input type="hidden" name="csrf" value="<?= $__csrf ?>">
                        <button type="submit" name="eliminar_reserva" class="btn btn-cancel w-100"><i class="bi bi-trash3 me-2"></i>Cancelar</button>
                      </form>
                    <?php else: ?>
                      <button class="btn btn-disabled flex-fill" disabled><i class="bi bi-check-circle me-2"></i>Completado</button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div id="emptyState" class="empty-state d-none">
          <div class="empty-icon"><i class="bi bi-funnel"></i></div>
          <h2 class="empty-title">No hay reservas en esta categoría</h2>
          <p class="empty-text">Cambia el filtro para ver otras reservas</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Intercepción AJAX del formulario de eliminar
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('form').forEach(function(form){
    const submitBtn = form.querySelector('button[name="eliminar_reserva"]');
    const idField = form.querySelector('input[name="reservation_id"]');
    if (!submitBtn || !idField) return;

    form.addEventListener('submit', function(ev){
      ev.preventDefault();

      if (!confirm('¿Estás seguro de que deseas cancelar esta reserva?\n\nEsta acción no se puede deshacer.')) return;

      const card = form.closest('.reservation-item');
      const fd = new FormData(form);

      fd.append('eliminar_reserva', '1');
      fd.append('ajax', '1');

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Cancelando...';

      fetch(location.pathname, {
        method: 'POST',
        body: fd,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        }
      }).then(async (r) => {
        let data = null;
        try { data = await r.json(); } catch(_){}
        if (!r.ok || !data || !data.ok) {
          const msg = (data && data.error) ? data.error : ('Error ' + r.status);
          if (!data) { 
            const txt = await r.text().catch(()=> '');
            console.debug('Respuesta no JSON:', txt.slice(0, 500));
          }
          alert('No se pudo eliminar: ' + msg);
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="bi bi-trash3 me-2"></i>Cancelar';
          return;
        }
        if (card) {
          card.classList.add('deleting');
          card.addEventListener('animationend', () => {
            const grid = document.getElementById('reservasGrid');
            card.remove();
            updateFilterCounts();
            if (grid && grid.querySelectorAll('.reservation-item').length === 0) {
              const empty = document.getElementById('emptyState');
              if (empty) empty.classList.remove('d-none');
            }
          }, { once: true });
        }
      }).catch((e) => {
        console.error(e);
        alert('Error de red al eliminar la reserva');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-trash3 me-2"></i>Cancelar';
      });
    });
  });
});

// Filtros de eventos
document.addEventListener('DOMContentLoaded', function() {
  const filterButtons = document.querySelectorAll('input[name="filter"]');
  const reservationItems = document.querySelectorAll('.reservation-item');

  filterButtons.forEach(button => {
    button.addEventListener('change', function() {
      const filter = this.id;
      reservationItems.forEach(item => {
        switch(filter) {
          case 'all': item.style.display = 'block'; break;
          case 'upcoming': item.style.display = item.classList.contains('upcoming-event-item') ? 'block' : 'none'; break;
          case 'past': item.style.display = item.classList.contains('past-event-item') ? 'block' : 'none'; break;
        }
      });
      const visibleItems = Array.from(reservationItems).filter(i => i.style.display !== 'none');
      const emptyState = document.getElementById('emptyState');
      if (visibleItems.length === 0 && emptyState) emptyState.classList.remove('d-none');
      else if (emptyState) emptyState.classList.add('d-none');
    });
  });
});

// Actualiza contadores de filtros
function updateFilterCounts() {
  const allItems = document.querySelectorAll('.reservation-item');
  const upcomingItems = document.querySelectorAll('.upcoming-event-item');
  const pastItems = document.querySelectorAll('.past-event-item');

  const lAll  = document.querySelector('label[for="all"]');
  const lUp   = document.querySelector('label[for="upcoming"]');
  const lPast = document.querySelector('label[for="past"]');

  if (lAll)  lAll.textContent  = `Todas (${allItems.length})`;
  if (lUp)   lUp.textContent   = `Próximas (${upcomingItems.length})`;
  if (lPast) lPast.textContent = `Pasadas (${pastItems.length})`;
}

// Auto-ocultar alertas
document.addEventListener('DOMContentLoaded', function() {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => { new bootstrap.Alert(alert).close(); }, 5000);
  });
});
</script>
</body>
</html>
