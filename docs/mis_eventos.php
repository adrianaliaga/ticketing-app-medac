<?php
require 'session_boot.php';
require 'conexion.php';    

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$sql = "SELECT 
            id, title, location, start_at, image_path, is_public
        FROM 
            events
        WHERE 
            user_id = ?
        ORDER BY 
            start_at DESC"; 

$stmt = $connection->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultado = $stmt->get_result();

$eventos_creados = $resultado->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis Eventos - EventosApp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
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
    body { background: #ffffff; min-height: 100vh; }
    .main-container { background: rgba(255,255,255,0.95); border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); backdrop-filter: blur(10px); margin: 2rem 0; }
    .page-header { background: linear-gradient(135deg, #b58aff 0%, #6f00ff 100%); color: #fff; border-radius: 20px 20px 0 0; padding: 1.5rem 2rem; height: 80px; display: flex; align-items: center; width: 100%; position: relative; overflow: hidden; }
    .page-header::before { display: none; }
    .page-title { margin: 0; font-weight: 700; font-size: 1.8rem; display: flex; align-items: center; gap: 0.5rem; }
    .stats-badge { background: rgba(255, 255, 255, 0.25); border: 1px solid rgba(255, 255, 255, 0.4); backdrop-filter: blur(10px); padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.9rem; font-weight: 600; color: #fff; white-space: nowrap; }
    .page-header .d-flex { width: 100%; }
    .event-card { border:none; border-radius:16px; box-shadow:0 8px 25px rgba(0,0,0,0.08); transition:all .3s ease; overflow:hidden; background:#fff; }
    .event-card:hover { transform: translateY(-5px); box-shadow:0 15px 35px rgba(0,0,0,0.15); }
    .event-img { width:100%; height:200px; object-fit:cover; }
    .card-content { padding:1.5rem; }
    .event-title { font-size:1.25rem; font-weight:600; color:#2d3748; margin-bottom:.5rem; }
    .event-meta { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1rem; }
    .meta-item { display:flex; align-items:center; gap:.5rem; font-size:.9rem; color:#4a5568; }
    .meta-icon { width:16px; color:#6f00ff; }
    .status-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.5rem 1rem; border-radius:50px; font-size:.8rem; font-weight:600; text-transform:uppercase; letter-spacing:.5px; }
    .badge-public { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); color:#fff; box-shadow:0 4px 15px rgba(72,187,120,.3); }
    .badge-private { background: linear-gradient(135deg, #a0aec0 0%, #718096 100%); color:#fff; }
    .action-buttons { display:flex; gap:.75rem; margin-top:1.5rem; }
    .btn-edit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border:none; color:#fff; padding:.75rem 1.5rem; border-radius:12px; font-weight:600; transition: all .3s ease; }
    .btn-edit:hover { transform: translateY(-2px); box-shadow:0 8px 20px rgba(102, 126, 234, 0.4); color:#fff; }
    .btn-delete { background: linear-gradient(135deg, #fc8181 0%, #f56565 100%); border:none; color:#fff; padding:.75rem 1.5rem; border-radius:12px; font-weight:600; transition: all .3s ease; }
    .btn-delete:hover { transform: translateY(-2px); box-shadow:0 8px 20px rgba(252,129,129,.4); color:#fff; }
    .btn-create { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); border: none; border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 600; color: white; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
    .btn-create:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(72, 187, 120, 0.3); color: white; text-decoration: none; }
    .empty-state { text-align:center; padding:4rem 2rem; background:#fff; border-radius:20px; margin:2rem 0; }
    .empty-icon { font-size:4rem; color:#cbd5e0; margin-bottom:1rem; }
    .empty-title { font-size:1.5rem; font-weight:600; color:#4a5568; margin-bottom:0.5rem; }
    .empty-text { color:#718096; margin-bottom:2rem; }
    @media (max-width:576px) { .main-container { margin:1rem; border-radius:16px; } .page-header { padding:1rem; height: 70px; } .page-title { font-size:1rem; } .action-buttons { flex-direction:column; } .event-img { height:150px; } }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-custom-navbar shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="uploads/eventos/logo3.png" alt="EventosApp" class="brand-logo">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-house me-1"></i>Inicio</a></li>
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="bi bi-search me-1"></i>Explorar</a></li>
        <li class="nav-item"><a class="nav-link" href="mis_reservas.php"><i class="bi bi-ticket-perforated me-1"></i>Mis Reservas</a></li>
        <li class="nav-item"><a class="nav-link active" href="mis_eventos.php"><i class="bi bi-calendar-plus me-1"></i>Mis Eventos</a></li>
      </ul>
      <div class="d-flex align-items-center gap-3">
        <span class="text-light">
          <i class="bi bi-person-circle me-1"></i>
          <?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?>
        </span>
        <a class="btn btn-outline-light" href="logout.php">
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
          <i class="bi bi-calendar-plus me-3"></i>
          Mis Eventos
        </h1>
        <div class="stats-badge">
          <?= count($eventos_creados) ?> evento<?= count($eventos_creados) !== 1 ? 's' : '' ?>
        </div>
      </div>
    </div>

    <div class="p-4">
      <!-- Botón crear evento -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <p class="lead mb-0">Administra todos los eventos que has publicado en la plataforma.</p>
        <a href="crear_eventos.php" class="btn-create">
          <i class="bi bi-plus-circle-fill"></i>
          Crear Evento
        </a>
      </div>

      <?php if (count($eventos_creados) > 0): ?>
        <div class="row g-4">
          <?php foreach ($eventos_creados as $evento): ?>
            <div class="col-lg-6 col-xl-4">
              <div class="event-card h-100">
                <!-- Imagen del evento -->
                <?php if (!empty($evento['image_path'])): ?>
                  <img src="<?= htmlspecialchars($evento['image_path']) ?>" 
                       class="event-img" 
                       alt="<?= htmlspecialchars($evento['title']) ?>"
                       onerror="this.src='https://via.placeholder.com/400x200/6f42c1/ffffff?text=Evento'">
                <?php else: ?>
                  <div class="event-img d-flex align-items-center justify-content-center bg-light">
                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                  </div>
                <?php endif; ?>

                <div class="card-content">
                  <!-- Título y estado -->
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <h3 class="event-title"><?= htmlspecialchars($evento['title']) ?></h3>
                    <span class="status-badge <?= $evento['is_public'] ? 'badge-public' : 'badge-private' ?>">
                      <i class="bi bi-<?= $evento['is_public'] ? 'eye' : 'eye-slash' ?>"></i>
                      <?= $evento['is_public'] ? 'Público' : 'Privado' ?>
                    </span>
                  </div>

                  <!-- Información del evento -->
                  <div class="event-meta">
                    <div class="meta-item">
                      <i class="bi bi-calendar-event meta-icon"></i>
                      <span><?= date('d/m/Y H:i', strtotime($evento['start_at'])) ?></span>
                    </div>
                    
                    <div class="meta-item">
                      <i class="bi bi-geo-alt meta-icon"></i>
                      <span><?= htmlspecialchars($evento['location']) ?></span>
                    </div>
                  </div>

                  <!-- Botones de acción -->
                  <div class="action-buttons">
                    <a href="editar_evento.php?id=<?= (int)$evento['id'] ?>" class="btn btn-edit">
                      <i class="bi bi-pencil-square me-2"></i>Editar
                    </a>
                    <button type="button" class="btn btn-delete" onclick="eliminarEvento(<?= (int)$evento['id'] ?>, '<?= htmlspecialchars($evento['title'], ENT_QUOTES) ?>')">
                      <i class="bi bi-trash3 me-2"></i>Eliminar
                    </button>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <!-- Estado vacío -->
        <div class="empty-state">
          <div class="empty-icon">
            <i class="bi bi-calendar-plus"></i>
          </div>
          <h2 class="empty-title">Aún no has creado ningún evento</h2>
          <p class="empty-text">¿Tienes una idea para un evento? ¡Compártela con la comunidad!</p>
          <a href="crear_eventos.php" class="btn-create">
            <i class="bi bi-plus-lg me-2"></i>Crear mi primer evento
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>