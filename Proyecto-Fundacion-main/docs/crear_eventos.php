<?php
// session_boot.php
ini_set('session.use_strict_mode','1');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',      // MUY IMPORTANTE: visible en / y /docs
  'httponly' => true,
  'samesite' => 'Lax',
  // 'secure' => true,    // solo si usas HTTPS
]);

// Guarda sesiones en carpeta LOCAL del proyecto (evita problemas de XAMPP)
$__sess_dir = __DIR__ . '/sessions';
if (!is_dir($__sess_dir)) { @mkdir($__sess_dir, 0777, true); }
ini_set('session.save_path', $__sess_dir);

session_start();

require __DIR__ . '/conexion.php'; // $connection (mysqli)

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php'); exit;
}

date_default_timezone_set('Europe/Madrid');

function dtl_to_mysql(?string $s): ?string {
  if (!$s) return null;
  $s = str_replace('T', ' ', trim($s));
  try { return (new DateTime($s))->format('Y-m-d H:i:s'); }
  catch (Exception $e) { return null; }
}
function slug(string $s): string {
  if (function_exists('iconv')) $s = iconv('UTF-8','ASCII//TRANSLIT',$s);
  $s = strtolower($s);
  $s = preg_replace('/[^a-z0-9]+/','-',$s);
  $s = trim($s,'-');
  return $s ?: 'evento';
}

$errors = [];
$val = [
  'title' => '', 'description' => '', 'category' => 'Others', 'location' => '',
  'start_at' => '', 'end_at' => '', 'capacity' => '', 'price' => '0', 'is_public' => '1'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $val['title']       = trim($_POST['title'] ?? '');
  $val['description'] = trim($_POST['description'] ?? '');
  $val['category']    = trim($_POST['category'] ?? 'Others');
  $val['location']    = trim($_POST['location'] ?? '');
  $val['start_at']    = trim($_POST['start_at'] ?? '');
  $val['end_at']      = trim($_POST['end_at'] ?? '');
  $val['capacity']    = trim($_POST['capacity'] ?? '');
  $val['price']       = trim($_POST['price'] ?? '0');
  $val['is_public']   = isset($_POST['is_public']) ? '1' : '0';

  $start_at = dtl_to_mysql($val['start_at']);
  $end_at   = $val['end_at'] !== '' ? dtl_to_mysql($val['end_at']) : null;
  $capacity = $val['capacity'] !== '' ? (int)$val['capacity'] : null;
  $price    = is_numeric($val['price']) ? (float)$val['price'] : 0.0;
  $is_public = (int)$val['is_public'];

  if ($val['title'] === '')     $errors[] = 'El título es obligatorio.';
  if ($val['location'] === '')  $errors[] = 'La ubicación es obligatoria.';
  if (!$start_at)               $errors[] = 'La fecha/hora de inicio no es válida.';
  if ($capacity !== null && $capacity < 0) $errors[] = 'La capacidad no puede ser negativa.';
  if ($price < 0)               $errors[] = 'El precio no puede ser negativo.';

  $image_ok = false; $image_tmp = null; $ext = null;
  if (!empty($_FILES['poster']['name']) && ($_FILES['poster']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $image_tmp = $_FILES['poster']['tmp_name'];
    $size = (int)$_FILES['poster']['size'];
    if ($size > 2*1024*1024) {
      $errors[] = 'La imagen no puede superar 2MB.';
    } else {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime  = $finfo->file($image_tmp);
      $allow = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
      if (!isset($allow[$mime])) {
        $errors[] = 'Formato no permitido (JPG, PNG o WebP).';
      } else {
        $ext = $allow[$mime];
        $image_ok = true;
      }
    }
  }

  if (!$errors) {
    $connection->begin_transaction();

    $stmt = $connection->prepare(
      "INSERT INTO events (user_id,title,description,category,location,start_at,end_at,capacity,price,image_path,is_public)
       VALUES (?,?,?,?,?,?,?,?,?,NULL,?)"
    );
    $uid = (int)$_SESSION['user_id'];
    $desc = $val['description'] !== '' ? $val['description'] : null;
    $stmt->bind_param(
      "issssssidi",
      $uid,
      $val['title'],
      $desc,
      $val['category'],
      $val['location'],
      $start_at,
      $end_at,
      $capacity,
      $price,
      $is_public
    );
    $ok_insert = $stmt->execute();

    if (!$ok_insert) {
      $connection->rollback();
      $errors[] = 'No se pudo guardar el evento.';
    } else {
      $event_id = $connection->insert_id;
      $image_path = null;

      if ($image_ok) {
        $dir = __DIR__ . '/uploads/eventos';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

        $filename = sprintf('ev_%d_%s_%s.%s',
          $event_id, slug($val['title']), substr(bin2hex(random_bytes(4)),0,8), $ext);
        $dest = $dir . '/' . $filename;

        if (move_uploaded_file($image_tmp, $dest)) {
          $image_path = 'uploads/eventos/' . $filename;
          $up = $connection->prepare("UPDATE events SET image_path=? WHERE id=?");
          $up->bind_param("si", $image_path, $event_id);
          if (!$up->execute()) {
            $connection->rollback();
            $errors[] = 'El evento se creó, pero no se pudo asociar la imagen.';
          }
        } else {
          $connection->rollback();
          $errors[] = 'No se pudo mover la imagen al servidor.';
        }
      }

      if (!$errors) {
        $connection->commit();
        $_SESSION['flash'] = '✅ Evento creado correctamente';
        header('Location: index.php'); exit;
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear Evento - EventosApp</title>
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
    .page-subtitle { color: rgba(255,255,255,0.9); font-size: 1rem; margin: 0; font-weight: 400; }
    
    .form-container { padding: 3rem; }
    .form-control, .form-select { border-radius: 12px; border: 2px solid #e2e8f0; padding: 0.9rem 1.2rem; font-size: 1rem; transition: all 0.3s ease; background: rgba(255, 255, 255, 0.9); }
    .form-control:focus, .form-select:focus { border-color: #6f00ff; box-shadow: 0 0 0 0.2rem rgba(111, 0, 255, 0.25); transform: translateY(-2px); }
    .form-label { font-weight: 600; color: #2d3748; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
    .form-label i { color: #6f00ff; }
    
    .btn-submit { background: linear-gradient(135deg, #6f00ff 0%, #845ef7 100%); border: none; border-radius: 12px; padding: 1rem 2rem; font-weight: 600; font-size: 1.1rem; width: 100%; color: white; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px; }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(111, 66, 193, 0.3); color: white; }
    
    .alert { border-radius: 12px; border: none; padding: 1rem 1.5rem; margin-bottom: 1.5rem; font-weight: 500; }
    .alert-danger { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%); color: white; }
    .alert-danger ul { margin: 0; padding-left: 1.5rem; }
    
    .form-check-input:checked { background-color: #6f00ff; border-color: #6f00ff; }
    .form-check-label { color: #4a5568; font-weight: 500; }
    
    .input-group-custom { position: relative; margin-bottom: 1.5rem; }
    .input-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; z-index: 10; font-size: 1.1rem; }
    .form-control.with-icon { padding-left: 3rem; }
    
    /* Responsive */
    @media (max-width:576px) { .main-container { margin:1rem; border-radius:16px; } .page-header { padding:1rem; height: 70px; } .page-title { font-size:1.5rem; } .form-container { padding: 2rem 1.5rem; } }
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
        <li class="nav-item"><a class="nav-link" href="mis_eventos.php"><i class="bi bi-calendar-plus me-1"></i>Mis Eventos</a></li>
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
  <div class="main-container" style="max-width: 900px; margin: 2rem auto;">
    <!-- Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">
          <i class="bi bi-plus-circle me-3"></i>
          Crear Evento
        </h1>
        <p class="page-subtitle">Publica un nuevo evento y compártelo con la comunidad</p>
      </div>
    </div>

    <div class="form-container">
      <!-- Errores de validación -->
      <?php if (!empty($errores)): ?>
        <div class="alert alert-danger d-flex align-items-start" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2" style="margin-top: 2px;"></i>
          <div>
            <strong>Por favor corrige los siguientes errores:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($errores as $campo => $error): ?>
                <?php if ($campo !== 'general' && $campo !== 'poster'): ?>
                  <li><?= htmlspecialchars($error) ?></li>
                <?php endif; ?>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" novalidate>
        <div class="row g-4">
          <!-- Título -->
          <div class="col-12">
            <label class="form-label">
              <i class="bi bi-card-text"></i>
              Título del evento *
            </label>
            <input type="text" name="title" class="form-control" 
                   required maxlength="150" 
                   placeholder="Ej: Conferencia de Tecnología 2025"
                   value="<?= htmlspecialchars($val['title']) ?>">
          </div>

          <!-- Categoría y Ubicación -->
          <div class="col-md-6">
            <label class="form-label">
              <i class="bi bi-tags"></i>
              Categoría
            </label>
            <select name="category" class="form-select">
              <?php
                $cats = ['Cultura','Tecnología','Ferias','Bienestar','Others'];
                foreach ($cats as $c) {
                  $sel = ($val['category'] === $c) ? 'selected' : '';
                  echo "<option $sel>".htmlspecialchars($c)."</option>";
                }
              ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">
              <i class="bi bi-geo-alt"></i>
              Ubicación *
            </label>
            <input type="text" name="location" class="form-control" required
                   placeholder="Ej: Centro de Convenciones, Madrid"
                   value="<?= htmlspecialchars($val['location']) ?>">
          </div>

          <!-- Fechas -->
          <div class="col-md-6">
            <label class="form-label">
              <i class="bi bi-calendar-event"></i>
              Fecha y hora de inicio *
            </label>
            <input type="datetime-local" name="start_at" class="form-control" required
                   value="<?= htmlspecialchars($val['start_at']) ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">
              <i class="bi bi-calendar-check"></i>
              Fecha y hora de fin
            </label>
            <input type="datetime-local" name="end_at" class="form-control"
                   value="<?= htmlspecialchars($val['end_at']) ?>">
          </div>

          <!-- Capacidad, Precio y Visibilidad -->
          <div class="col-md-4">
            <label class="form-label">
              <i class="bi bi-people"></i>
              Capacidad máxima
            </label>
            <input type="number" name="capacity" min="0" class="form-control"
                   placeholder="Sin límite"
                   value="<?= htmlspecialchars($val['capacity']) ?>">
          </div>

          <div class="col-md-4">
            <label class="form-label">
              <i class="bi bi-currency-euro"></i>
              Precio (€)
            </label>
            <input type="number" name="price" min="0" step="0.01" class="form-control"
                   placeholder="0.00"
                   value="<?= htmlspecialchars($val['price']) ?>">
          </div>

          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_public" id="is_public"
                     <?= $val['is_public']==='1'?'checked':''; ?>>
              <label class="form-check-label" for="is_public">
                <i class="bi bi-eye me-1"></i>
                Evento público
              </label>
            </div>
          </div>

          <!-- Descripción -->
          <div class="col-12">
            <label class="form-label">
              <i class="bi bi-file-text"></i>
              Descripción del evento
            </label>
            <textarea name="description" rows="4" class="form-control"
                      placeholder="Describe tu evento: agenda, ponentes, qué pueden esperar los asistentes..."><?= htmlspecialchars($val['description']) ?></textarea>
          </div>

          <!-- Imagen -->
          <div class="col-12">
            <label class="form-label">
              <i class="bi bi-image"></i>
              Imagen del evento
            </label>
            <input type="file" name="poster" accept="image/jpeg,image/png,image/webp" class="form-control">
            <div class="form-text">
              <i class="bi bi-info-circle me-1"></i>
              Formatos: JPG, PNG o WebP. Tamaño máximo: 2MB
            </div>
          </div>

          <!-- Botón -->
          <div class="col-12 pt-3">
            <button class="btn-submit" type="submit">
              <i class="bi bi-plus-circle me-2"></i>
              Crear Evento
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Validación de formulario
(function() {
  'use strict';
  window.addEventListener('load', function() {
    var forms = document.getElementsByClassName('needs-validation');
    var validation = Array.prototype.filter.call(forms, function(form) {
      form.addEventListener('submit', function(event) {
        if (form.checkValidity() === false) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  }, false);
})();

// Preview de imagen
document.querySelector('input[type="file"]').addEventListener('change', function(e) {
  const file = e.target.files[0];
  if (file) {
    const formText = this.nextElementSibling;
    formText.innerHTML = `<i class="bi bi-check-circle me-1 text-success"></i>Imagen seleccionada: ${file.name}`;
  }
});
</script>

</body>
</html>
