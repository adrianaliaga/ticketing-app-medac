<?php
require 'session_boot.php';
require 'conexion.php';

//Determinar si el usuario es administrador
$isAdmin = !empty($_SESSION['is_admin']) && (int)$_SESSION['is_admin'] === 1;

//Recoger filtros de la URL de forma segura
$busqueda = trim($_GET['busqueda'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$lugar = trim($_GET['lugar'] ?? '');

//IDs de los eventos favoritos del usuario
$userFavorites = [];
if (isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $connection->prepare("SELECT event_id FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userFavorites = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'event_id');
    $stmt->close();
}

//Construir la consulta SQL para los eventos filtrados
$sql = "SELECT * FROM events WHERE is_public = 1";
$params = [];
$types = '';

if ($busqueda !== '') {
    $sql .= " AND title LIKE ?";
    $params[] = "%" . $busqueda . "%";
    $types .= 's';
}
if ($categoria !== '') {
    $sql .= " AND category = ?";
    $params[] = $categoria;
    $types .= 's';
}
if ($lugar !== '') {
    $sql .= " AND location LIKE ?";
    $params[] = "%" . $lugar . "%";
    $types .= 's';
}
$sql .= " ORDER BY start_at ASC";

$stmt = $connection->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>EventosApp</title>
  <link rel="icon" type="image/png" href="uploads/eventos/logo5.png"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__.'/style.css') ?>">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body class="bg-custom text-white">
<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark bg-custom-navbar shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="index.php">
      <img src="uploads/eventos/logo4.png" alt="Inicio" class="logo-navbar">EventosApp
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMenu">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-4">
        <li class="nav-item"><a class="nav-link" href="mis_reservas.php"><i class="bi bi-calendar-check me-2"></i>Mis reservas</a></li>
        <li class="nav-item"><a class="nav-link" href="favoritos.php"><i class="bi bi-heart"></i> Favoritos</a></li>
        <li class="nav-item"><a class="nav-link" href="eventos.php">Eventos</a></li>
      </ul>

<!-- Usuario -->
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item dropdown user-hover">
          <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="me-1"><i class="bi bi-person-circle"></i></span>
            <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Invitado'; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-start" aria-labelledby="userMenu">
            <?php if (!isset($_SESSION['username'])): ?>
              <li><a class="dropdown-item" href="login.php"><i class="bi bi-box-arrow-in-right me-2"></i> Iniciar sesión</a></li>
              <li><a class="dropdown-item" href="registro.php"><i class="bi bi-pencil-square me-2"></i> Registrarse</a></li>
            <?php else: ?>
              <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person-circle me-2"></i> Mi perfil</a></li>
              <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar sesión</a></li>
              <?php if ($isAdmin): ?>
                <li><a class="dropdown-item" href="crear_eventos.php"><i class="bi bi-plus-lg me-2"></i> Crear Eventos</a></li>
                <li><a class="dropdown-item" href="mis_eventos.php"><i class="bi bi-pencil-square me-2"></i> Mis eventos</a></li>
              <?php endif; ?>
            <?php endif; ?>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<!-- CARRUSEL -->
<div id="eventCarousel" class="carousel slide" data-bs-ride="carousel">
  <div class="carousel-inner position-relative">
    <!-- Franja vertical oscura con texto -->
    <div class="borde-vertical-oscuro">
      <h2 class="texto-vertical-global">Descubre<br>Eventos<br>Increíbles<br>Cerca de Ti</h2>
    </div>
    <!-- Slide 1 -->
    <div class="carousel-item active position-relative">
      <img src="uploads/eventos/bernabeu.jpg" class="d-block w-100 img-fluid event-img" alt="Tour Bernabeu">
      <div class="carousel-caption  bg-white bg-opacity-100 rounded p-2 text-dark" style="max-width: 500px; margin: 0 auto;">
        <h5 >Tour Santiago Bernabeu</h5>
        <p>12 Oct 2025 - Estadio Santiago Bernabeu</p>
        <a href="#" class="btn btn-reservar-personalizado mt-2">Reservar</a>
      </div>
    </div>
    <!-- Slide 2 -->
    <div class="carousel-item position-relative">
      <img src="uploads/eventos/programar.jpg" class="d-block w-100 img-fluid event-img" alt="Taller de Programación">
      <div class="carousel-caption  bg-white bg-opacity-100 rounded p-2 text-dark" style="max-width: 500px; margin: 0 auto;">
        <h5>Taller de Programación Web</h5>
        <p>15 Oct 2025 - Aula Virtual Medac</p>
        <a href="#" class="btn btn-reservar-personalizado mt-2">Reservar</a>
      </div>
    </div>
    <!-- Slide 3 -->
    <div class="carousel-item position-relative">
      <img src="uploads/eventos/artesanal.jpg" class="d-block w-100 img-fluid event-img" alt="Mercado Artesanal">
      <div class="carousel-caption  bg-white bg-opacity-100 rounded p-2 text-dark" style="max-width: 500px; margin: 0 auto;">
        <h5>Mercado Artesanal de Otoño</h5>
        <p>18 Oct 2025 - Plaza Mayor</p>
      <a href="#" class="btn btn-reservar-personalizado mt-2">Reservar</a>
      </div>
    </div>
  </div>
  <!-- Controles -->
  <button class="carousel-control-prev" type="button" data-bs-target="#eventCarousel" data-bs-slide="prev">
    <span class="carousel-control-prev-icon"></span>
  </button>
  <button class="carousel-control-next" type="button" data-bs-target="#eventCarousel" data-bs-slide="next">
    <span class="carousel-control-next-icon"></span>
  </button>
</div>
<!--Proximos Eventos-->
<h2 class="text-center mb-4" style="color: #6f00ff;">
  <i class="bi bi-calendar-week me-2"></i> Próximos eventos
</h2>
  <?php
  $sql = "SELECT id, title, description, category, location, start_at, end_at, price, image_path
          FROM events
          WHERE is_public = 1
            AND start_at >= NOW()
          ORDER BY start_at ASC
          LIMIT 3";
  $res = $connection->query($sql);
  ?>

  <div class="row g-4">
    <?php if ($res && $res->num_rows > 0): ?>
      <?php while ($evento = $res->fetch_assoc()): ?>
        <?php $img = $evento['image_path'] ?: 'assets/default-event.jpg'; ?>
        <div class="col-md-4">
          <div class="card event-card h-100 shadow-sm">
            <img src="<?= htmlspecialchars($img) ?>"
                class="card-img-top event-img"
                alt="<?= htmlspecialchars($evento['title']) ?>"
                onerror="this.onerror=null;this.src='assets/default-event.jpg';">
            <div class="card-body">
              <h5 class="event-title"><?= htmlspecialchars($evento['title']) ?></h5>
              <p class="mb-1"><strong><i class="bi bi-calendar-event"></i> Fecha:</strong>
                <?= date('d M Y', strtotime($evento['start_at'])) ?>
              </p>
              <p class="mb-1"><strong><i class="bi bi-geo-alt"></i> Lugar:</strong>
                <?= htmlspecialchars($evento['location']) ?>
              </p>
              <span class="badge bg-secondary"><?= htmlspecialchars($evento['category']) ?></span>
            <a href="reserva.php?id=<?= $evento['id'] ?>" class="btn btn-reservar-personalizado w-100 mt-3">Reservar</a>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-warning text-center">
          <i class="bi bi-exclamation-triangle-fill"></i> No hay eventos próximos.
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<main class="container py-5">
  <section class="mb-5">
    <h2 class="text-dark">Buscador de Eventos</h2>
    <form method="GET" action="index.php" class="row g-3 align-items-end p-4 bg-light rounded text-dark">
        <div class="col-md-4">
            <label for="busqueda" class="form-label fw-semibold"><i class="bi bi-search"></i> Buscar por nombre</label>
            <input type="text" id="busqueda" name="busqueda" class="form-control" placeholder="Ej. Conferencia, Taller..." value="<?= htmlspecialchars($busqueda) ?>">
        </div>
        <div class="col-md-3">
            <label for="categoria" class="form-label fw-semibold"><i class="bi bi-tags"></i> Categoría</label>
            <select id="categoria" name="categoria" class="form-select">
                <option value="">Todas</option>
                <option value="Cultura" <?= $categoria == "Cultura" ? "selected" : "" ?>>Cultura</option>
                <option value="Tecnología" <?= $categoria == "Tecnología" ? "selected" : "" ?>>Tecnología</option>
                <option value="Ferias" <?= $categoria == "Ferias" ? "selected" : "" ?>>Ferias</option>
                <option value="Bienestar" <?= $categoria == "Bienestar" ? "selected" : "" ?>>Bienestar</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="lugar" class="form-label fw-semibold"><i class="bi bi-geo-alt-fill"></i> Lugar</label>
            <input type="text" id="lugar" name="lugar" class="form-control" placeholder="Ej. Madrid, Barcelona..." value="<?= htmlspecialchars($lugar) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn w-100" style="background-color:  #6f00ff; color: white;">
  <i class="bi bi-search"></i> Buscar
</button>
        </div>
    </form>
  </section>

  <section>
    <h2 class="text-dark">Resultados</h2>
    <div class="row g-4">
      <?php if ($resultado && $resultado->num_rows > 0): ?>
        <?php while ($evento = $resultado->fetch_assoc()): ?>
          <div class="col-md-4">
            <div class="card event-card h-100 shadow-sm text-dark">
              <img src="<?= htmlspecialchars($evento['image_path'] ?? 'uploads/eventos/default.jpg') ?>" class="card-img-top event-img" alt="<?= htmlspecialchars($evento['title']) ?>" onerror="this.src='uploads/eventos/default.jpg';">
              
              <?php if (isset($_SESSION['user_id'])): ?>
                <button 
                  class="favorite-btn <?= in_array($evento['id'], $userFavorites) ? 'active' : '' ?>" 
                  data-event-id="<?= $evento['id'] ?>"
                  aria-label="Añadir a favoritos">
                  <i class="bi bi-heart-fill"></i>
                </button>
              <?php endif; ?>

              <div class="card-body d-flex flex-column">
                <h5 class="event-title"><?= htmlspecialchars($evento['title']) ?></h5>
                <p class="mb-1"><i class="bi bi-calendar-event"></i> <?= date('d M Y', strtotime($evento['start_at'])) ?></p>
                <p class="mb-3"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($evento['location']) ?></p>
                <span class="badge bg-secondary align-self-start"><?= htmlspecialchars($evento['category']) ?></span>
                <a href="reserva.php?id=<?= $evento['id'] ?>" class="btn btn-reservar-personalizado w-100 mt-3">Reservar</a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="alert alert-warning text-center">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> No se encontraron eventos con los filtros seleccionados.
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<footer class="bg-custom-navbar text-white text-center py-4 mt-5">
  <div class="container">
    <p class="mb-1 fw-bold">EventosApp &copy; 2025</p>
    <p class="mb-0">Tu plataforma para descubrir y reservar eventos únicos</p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function(e) {
        const favoriteButton = e.target.closest('.favorite-btn');
        if (!favoriteButton) return;

        const eventId = favoriteButton.dataset.eventId;
        const url = 'toggle_favorite.php';

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ event_id: eventId })
        })
        .then(async response => {
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ error: 'Respuesta inválida del servidor' }));
                throw new Error(errorData.error || `Error del Servidor (${response.status})`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'added') {
                favoriteButton.classList.add('active');
            } else if (data.status === 'removed') {
                favoriteButton.classList.remove('active');
            }
        })
        .catch(error => {
            console.error('Error en la solicitud de favoritos:', error);
        });
    });
});
</script>
</body>
</html>
