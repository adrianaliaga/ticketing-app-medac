<?php
//inicia y configura la sesión
ini_set('session.use_strict_mode', '1');

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',
]);

// Carpeta local para guardar sesiones PHP
$__sess_dir = __DIR__ . '/sessions';
if (!is_dir($__sess_dir)) { @mkdir($__sess_dir, 0777, true); }
ini_set('session.save_path', $__sess_dir);

// Iniciar sesión si no está ya iniciada
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
?>