<?php
require 'session_boot.php';
require 'conexion.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$mensaje = "";
$esExito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $birthdate_raw = trim($_POST['birthdate'] ?? '');

    if ($username === '' || $email === '') {
        $mensaje = "❌ El nombre de usuario y el correo electrónico son obligatorios.";
    } else {
        $birthdate = !empty($birthdate_raw) ? date('Y-m-d', strtotime(str_replace('/', '-', $birthdate_raw))) : null;

        $sql = "UPDATE users SET username = ?, email = ?, phone = ?, birthdate = ? WHERE id = ?";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("ssssi", $username, $email, $phone, $birthdate, $userId);

        if ($stmt->execute()) {
            $mensaje = "✅ Perfil actualizado correctamente.";
            $esExito = true;
            $_SESSION['username'] = $username;
        } else {
            $mensaje = "❌ Error al actualizar el perfil. Es posible que el nombre de usuario o el correo electrónico ya estén en uso.";
        }
    }
}

$stmt = $connection->prepare("SELECT username, email, phone, birthdate FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    die("Error: Usuario no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi Perfil - EventosApp</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__.'/style.css') ?>">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-custom-navbar shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="index.php">EventosApp</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="logout.php">
            <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <h4 class="mb-0"><i class="bi bi-person-circle me-2"></i>Mi Perfil</h4>
        </div>
        <div class="card-body">
          <?php if ($mensaje): ?>
            <div class="alert <?= $esExito ? 'alert-success' : 'alert-danger' ?>" role="alert">
              <?= $mensaje ?>
            </div>
          <?php endif; ?>
          <form action="perfil.php" method="POST">
            <div class="mb-3">
              <label for="username" class="form-label">Nombre de usuario</label>
              <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Correo electrónico</label>
              <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="mb-3">
              <label for="phone" class="form-label">Teléfono</label>
              <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>">
            </div>
            <div class="mb-3">
              <label for="birthdate" class="form-label">Fecha de nacimiento</label>
              <input type="date" class="form-control" id="birthdate" name="birthdate" value="<?= htmlspecialchars($user['birthdate']) ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-save me-2"></i>Guardar Cambios
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>