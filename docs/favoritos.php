<?php
require 'session_boot.php';
require 'conexion.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  header('Location: login.php');
  exit;
}

$sql = "
  SELECT
    f.id AS favorite_id, e.id AS event_id, e.title, e.location, e.start_at, e.image_path,
    CASE WHEN e.end_at < NOW() THEN 1 ELSE 0 END AS is_past
  FROM favorites f
  JOIN events e ON e.id = f.event_id
  WHERE f.user_id = ?
  ORDER BY e.start_at DESC";
  
$stmt = $connection->prepare($sql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$favoritos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Mis Favoritos - EventosApp</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" href="empresa.png" type="image/x-icon">

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  
  <style>
  /* === Navbar optimizado === */
  .bg-custom-navbar { background: linear-gradient(135deg, #6f00ff 0%, #7b33ff 100%); height: 90px; display: flex; align-items: center; padding: 0 2rem; color: #fff; font-weight: 700; font-size: 1.8rem; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15); border-radius: 0 0 20px 20px; }
  .navbar-brand { display:flex; align-items:center; gap:.8rem; font-size: 1.8rem; }
  .brand-logo { height: 70px; width:auto; display:block; transition: transform 0.3s ease; }
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
  .favorite-card { border:none; border-radius:16px; box-shadow:0 8px 25px rgba(0,0,0,0.08); transition:all .3s ease; overflow:hidden; background:#fff; }
  .favorite-card:hover { transform: translateY(-5px); box-shadow:0 15px 35px rgba(0,0,0,0.15); }
  .event-image { width:100%; height:200px; object-fit:cover; }
  .event-image.past { filter: grayscale(50%) brightness(0.9); }
  .card-content { padding:1.5rem; }
  .event-title { font-size:1.25rem; font-weight:600; color:#2d3748; margin-bottom:.5rem; }
  .event-meta { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1rem; }
  .meta-item { display:flex; align-items:center; gap:.5rem; font-size:.9rem; color:#4a5568; }
  .meta-icon { width:16px; color:#6f00ff; }
  .status-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.5rem 1rem; border-radius:50px; font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
  .badge-upcoming { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color:#fff; box-shadow:0 4px 15px rgba(72,187,120,.3); }
  .badge-past { background: linear-gradient(135deg, #a0aec0 0%, #6f00ff 100%); color:#fff; }
  .action-buttons { display:flex; gap:.75rem; margin-top:1.5rem; }
  .btn-view { background: linear-gradient(135deg, #6f00ff 0%, #845ef7 100%); border:none; color:#fff; padding:.75rem 1.5rem; border-radius:12px; font-weight:600; transition: all .3s ease; flex:1; }
  .btn-view:hover { transform: translateY(-2px); box-shadow:0 8px 20px rgba(111, 66, 193, 0.4); color:#fff; }
  .btn-remove { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%); border:none; color:#fff; padding:.75rem; border-radius:12px; font-weight:600; transition: all .3s ease; }
  .btn-remove:hover { transform: translateY(-2px); box-shadow:0 8px 20px rgba(255, 107, 107, 0.4); color:#fff; }
  .empty-state { text-align:center; padding:4rem 2rem; background:#fff; border-radius:20px; margin:2rem 0; }
  .empty-icon { font-size:4rem; color:#cbd5e0; margin-bottom:1rem; }
  .empty-title { font-size:1.5rem; font-weight:600; color:#4a5568; margin-bottom:0.5rem; }
  .empty-text { color:#718096; margin-bottom:2rem; }
  .favorite-item { transition: opacity .25s ease, transform .25s ease; }
  .removing { animation: fadeOutScale .5s ease-out forwards; }
  @keyframes fadeOutScale { 0% { opacity:1; transform:scale(1); } 100% { opacity:0; transform:scale(.95); } }
  .heart-icon { color: #e53e3e; font-size: 1.2rem; }
  @media (max-width:991px) { .bg-custom-navbar { height: 80px; font-size: 1.6rem; } .brand-logo { height: 60px; } .navbar-nav .nav-link { font-size: 1.1rem; } }
  @media (max-width:576px) { .bg-custom-navbar { height: 70px; font-size: 1.4rem; } .brand-logo { height: 50px; } .navbar-nav .nav-link { font-size: 1rem; } .main-container { margin:1rem; border-radius:16px; } .page-header { padding:1rem; height: 70px; } .page-title { font-size:1.5rem; } .action-buttons { flex-direction:column; } .event-image { height:150px; } }
  </style>
  <!-- Favicon -->
  <link rel="icon" type="image/png" href="uploads/eventos/logo4.png"/>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-custom-navbar shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php"> 
      <img src="uploads/eventos/logo4.png" alt="EventosApp" class="brand-logo">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i>Inicio</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-search me-1"></i>Explorar</a></li>
        <li class="nav-item"><a class="nav-link" href="mis_reservas.php"><i class="bi bi-ticket-perforated me-1"></i>Mis Reservas</a></li>
        <li class="nav-item"><a class="nav-link active" href="favoritos.php"><i class="bi bi-heart-fill me-1"></i>Favoritos</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="text-light">
          <i class="bi bi-person-circle me-1"></i>
          <?= h($_SESSION['username'] ?? 'Usuario') ?>
        </span>
        <a class="btn btn-outline-light btn-sm" href="logout.php">
          <i class="bi bi-box-arrow-right me-1"></i>Salir
        </a>
      </div>
    </div>
  </div>
</nav>

<div class="container">
  <div class="main-container">
    <!-- Header -->
    <div class="page-header">
      <div class="d-flex justify-content-between align-items-center">
        <h1 class="page-title">
          <i class="bi bi-heart-fill me-3"></i>
          Mis Favoritos
        </h1>
        <div class="stats-badge">
          <?= count($favoritos) ?> favorito<?= count($favoritos) !== 1 ? 's' : '' ?>
        </div>
      </div>
    </div>

    <div class="p-4">
      <?php if (empty($favoritos)): ?>
        <div class="empty-state">
          <div class="empty-icon">
            <i class="bi bi-heart"></i>
          </div>
          <h2 class="empty-title">No tienes eventos favoritos aún</h2>
          <p class="empty-text">Usa el icono del corazón ❤️ en los eventos para guardarlos aquí y acceder a ellos fácilmente.</p>
          <a href="index.php" class="btn btn-primary btn-lg">
            <i class="bi bi-search me-2"></i>Explorar Eventos
          </a>
        </div>
      <?php else: ?>
        <div class="row g-4">
          <?php foreach ($favoritos as $evento): ?>
            <?php
              $isPast = (bool)$evento['is_past'];
              $startDate = new DateTime($evento['start_at']);
            ?>
            <div class="col-lg-6 col-xl-4 favorite-item">
              <div class="favorite-card <?= $isPast ? 'past-event' : '' ?>">
                <?php if (!empty($evento['image_path'])): ?>
                  <img src="<?= h($evento['image_path']) ?>" class="event-image <?= $isPast ? 'past' : '' ?>" alt="<?= h($evento['title']) ?>" onerror="this.src='https://via.placeholder.com/400x200/6f42c1/ffffff?text=Evento'">
                <?php else: ?>
                  <div class="event-image d-flex align-items-center justify-content-center bg-light <?= $isPast ? 'past' : '' ?>">
                    <i class="bi bi-image text-muted" style="font-size:3rem;"></i>
                  </div>
                <?php endif; ?>

                <div class="card-content">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <h3 class="event-title"><?= h($evento['title']) ?></h3>
                    <div class="d-flex flex-column align-items-end gap-1">
                      <span class="status-badge <?= $isPast ? 'badge-past' : 'badge-upcoming' ?>">
                        <i class="bi bi-<?= $isPast ? 'clock-history' : 'calendar-check' ?>"></i>
                        <?= $isPast ? 'Finalizado' : 'Próximo' ?>
                      </span>
                      <i class="bi bi-heart-fill heart-icon" title="En favoritos"></i>
                    </div>
                  </div>

                  <div class="event-meta">
                    <div class="meta-item">
                      <i class="bi bi-calendar-event meta-icon"></i>
                      <span><?= $startDate->format('d/m/Y') ?> - <?= $startDate->format('H:i') ?></span>
                    </div>
                    <div class="meta-item">
                      <i class="bi bi-geo-alt meta-icon"></i>
                      <span><?= h($evento['location']) ?></span>
                    </div>
                  </div>

                  <div class="action-buttons">
                    <a href="reserva.php?id=<?= $evento['event_id'] ?>" class="btn btn-view">
                      <i class="bi bi-eye me-2"></i>Ver Evento
                    </a>
                    <button type="button" class="btn btn-remove" onclick="removeFavorite(<?= $evento['favorite_id'] ?>, this)" title="Quitar de favoritos">
                      <i class="bi bi-heart-fill"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function removeFavorite(favoriteId, button) {
  if (!confirm('¿Estás seguro de que quieres quitar este evento de tus favoritos?')) {
    return;
  }
  
  const card = button.closest('.favorite-item');
  button.disabled = true;
  button.innerHTML = '<i class="bi bi-hourglass-split"></i>';
  
  setTimeout(() => {
    card.classList.add('removing');
    card.addEventListener('animationend', () => {
      card.remove();
      
      const remainingFavorites = document.querySelectorAll('.favorite-item').length;
      if (remainingFavorites === 0) {
        window.location.reload();
      } else {
        const badge = document.querySelector('.stats-badge');
        if (badge) {
          const count = remainingFavorites;
          badge.textContent = `${count} favorito${count !== 1 ? 's' : ''}`;
        }
      }
    }, { once: true });
  }, 1000);
}

document.addEventListener('DOMContentLoaded', function() {
  const cards = document.querySelectorAll('.favorite-card');
  cards.forEach((card, index) => {
    card.style.animationDelay = `${index * 0.1}s`;
    card.style.animation = 'slideInUp 0.6s ease-out forwards';
  });
});

const style = document.createElement('style');
style.textContent = `
  @keyframes slideInUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
`;
document.head.appendChild(style);
</script>
</body>
</html>