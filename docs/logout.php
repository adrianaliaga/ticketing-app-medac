<?php
require __DIR__.'/session_boot.php'; // asegura misma configuración

// Vaciar la sesión
$_SESSION = [];

// Eliminar cookie de sesión
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"] ?? '',
    $params["secure"] ?? false,
    $params["httponly"] ?? true
  );
}

// Destruir la sesión
session_destroy();

// Redirigir al inicio
header('Location: index.php');
exit;
?>