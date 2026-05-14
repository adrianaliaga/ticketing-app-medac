<?php
require 'session_boot.php'; // Usa tu manejador de sesiones
require 'conexion.php';     // Usa tu conexión a la BD

// 1. Proteger la página: solo para usuarios logueados
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 2. Obtener el ID del evento de la URL de forma segura
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($event_id === 0) {
    die("Error: Evento no especificado.");
}

// 3. Consultar la BD para obtener los datos del evento
$stmt = $connection->prepare("SELECT title, description, location, start_at, image_path, price FROM events WHERE id = ? AND is_public = 1");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$resultado = $stmt->get_result();
$evento = $resultado->fetch_assoc();

if (!$evento) {
    die("Error: Evento no encontrado o no está disponible.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reservar - <?= htmlspecialchars($evento['title']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__.'/style.css') ?>">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-custom-navbar shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="index.php">EventosApp</a>
  </div>
</nav>

<div class="container py-5">
  <div class="row">
    <div class="col-md-6">
      <div class="card">
        <img src="<?= htmlspecialchars($evento['image_path']) ?>" class="card-img-top" alt="Imagen del evento">
        <div class="card-body">
          <h1 class="card-title"><?= htmlspecialchars($evento['title']) ?></h1>
          <p class="text-muted">
            <strong><i class="bi bi-calendar-event"></i> Fecha:</strong> <?= date('d M Y, H:i', strtotime($evento['start_at'])) ?>h
          </p>
          <p class="text-muted">
            <strong><i class="bi bi-geo-alt"></i> Lugar:</strong> <?= htmlspecialchars($evento['location']) ?>
          </p>
          <?php if ((float)$evento['price'] > 0): ?>
          <p class="text-muted">
            <strong><i class="bi bi-currency-euro"></i> Precio:</strong> <?= number_format((float)$evento['price'], 2) ?> € por entrada
          </p>
          <?php else: ?>
          <p class="text-success">
            <strong><i class="bi bi-gift"></i> Evento gratuito</strong>
          </p>
          <?php endif; ?>
          <hr>
          <p><?= nl2br(htmlspecialchars($evento['description'])) ?></p>
        </div>
      </div>
    </div>

    <div class="col-md-6">
      <h2>Confirmar Reserva</h2>
      <p>Estás reservando como: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>.</p>
      
      <form action="<?= (float)$evento['price'] > 0 ? 'pago.php' : 'procesar_reserva.php' ?>" method="<?= (float)$evento['price'] > 0 ? 'GET' : 'POST' ?>" class="card p-4">
        <input type="hidden" name="event_id" value="<?= $event_id ?>">
        
        <div class="mb-3">
          <label for="quantity" class="form-label fw-bold">Número de Entradas</label>
          <input type="number" id="quantity" name="quantity" class="form-control" min="1" max="10" value="1" required onchange="updateTotal()">
          <div class="form-text">Máximo 10 entradas por persona.</div>
        </div>
        
        <?php if ((float)$evento['price'] > 0): ?>
        <div class="mb-3">
          <div class="card bg-light">
            <div class="card-body text-center">
              <h5 class="card-title">Total a pagar</h5>
              <h3 class="card-text text-primary" id="totalAmount"><?= number_format((float)$evento['price'], 2) ?> €</h3>
            </div>
          </div>
        </div>
        
        <p>Al hacer clic en "Continuar al pago", serás redirigido a la página de pago seguro.</p>
        
        <button type="submit" class="btn btn-primary w-100 btn-lg">
          <i class="bi bi-credit-card me-2"></i> Continuar al pago
        </button>
        <?php else: ?>
        <p>Al hacer clic en "Confirmar", tu plaza quedará reservada sin costo.</p>
        
        <button type="submit" class="btn btn-success w-100 btn-lg">
          <i class="bi bi-check-circle-fill me-2"></i> Confirmar Reserva Gratuita
        </button>
        <?php endif; ?>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateTotal() {
  const quantity = document.getElementById('quantity').value;
  const pricePerTicket = <?= (float)$evento['price'] ?>;
  const total = quantity * pricePerTicket;
  
  const totalElement = document.getElementById('totalAmount');
  if (totalElement) {
    totalElement.textContent = total.toFixed(2) + ' €';
  }
}
</script>
</body>
</html>
