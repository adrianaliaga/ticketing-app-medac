<?php
// session_boot.php
ini_set('session.use_strict_mode','1');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'httponly' => true,
  'samesite' => 'Lax',
  // 'secure' => true,
]);

$__sess_dir = __DIR__ . '/sessions';
if (!is_dir($__sess_dir)) { @mkdir($__sess_dir, 0777, true); }
ini_set('session.save_path', $__sess_dir);

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once "conexion.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['nombre_user'] ?? '');
    $password = $_POST['password_user'] ?? '';

    if ($username === '' || $password === '') {
        $error = "❌ Usuario y contraseña requeridos.";
    } else {
        // Consulta solo con las columnas que existen
        $sql = "SELECT id, username, password, is_admin
                FROM users
                WHERE username = ?
                LIMIT 1";
        $stmt = $connection->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true); // seguridad

                $_SESSION['user_id']  = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = (int)($user['is_admin'] ?? 0);
                // No asignamos role porque la columna no existe
                $_SESSION['role']     = null;

                header("Location: index.php");
                exit;
            } else {
                $error = "❌ Contraseña incorrecta.";
            }
        } else {
            $error = "❌ Usuario no encontrado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <title>Iniciar Sesión - EventosApp</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
      body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }
      
      .login-container {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        backdrop-filter: blur(10px);
        padding: 3rem;
        margin-top: 5rem;
        max-width: 450px;
      }
      
      .logo-container {
        text-align: center;
        margin-bottom: 2rem;
      }
      
      .logo-container img {
        max-width: 200px;
        height: auto;
        filter: drop-shadow(0 4px 15px rgba(0,0,0,0.1));
      }
      
      .login-title {
        color: #2d3748;
        font-weight: 700;
        margin-bottom: 2rem;
        text-align: center;
        font-size: 1.8rem;
      }
      
      .form-control {
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        padding: 0.9rem 1.2rem;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.9);
      }
      
      .form-control:focus {
        border-color: #6f00ff;
        box-shadow: 0 0 0 0.2rem rgba(111, 0, 255, 0.25);
        transform: translateY(-2px);
      }
      
      .input-group {
        margin-bottom: 1.5rem;
        position: relative;
      }
      
      .input-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        z-index: 10;
        font-size: 1.1rem;
      }
      
      .form-control.with-icon {
        padding-left: 3rem;
      }
      
      .btn-login {
        background: linear-gradient(135deg, #6f00ff 0%, #845ef7 100%);
        border: none;
        border-radius: 12px;
        padding: 0.9rem 2rem;
        font-weight: 600;
        font-size: 1.1rem;
        width: 100%;
        color: white;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
      .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(111, 66, 193, 0.3);
        color: white;
      }
      
      .alert {
        border-radius: 12px;
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        font-weight: 500;
      }
      
      .alert-danger {
        background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        color: white;
      }
      
      .register-link {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
        color: #6c757d;
      }
      
      .register-link a {
        color: #6f00ff;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
      }
      
      .register-link a:hover {
        color: #845ef7;
        text-decoration: underline;
      }
      
      /* Animación de entrada */
      .login-container {
        animation: slideUp 0.6s ease-out;
      }
      
      @keyframes slideUp {
        from {
          opacity: 0;
          transform: translateY(30px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }
      
      /* Responsive */
      @media (max-width: 576px) {
        .login-container {
          margin: 2rem 1rem;
          padding: 2rem 1.5rem;
        }
        
        .logo-container img {
          max-width: 150px;
        }
        
        .login-title {
          font-size: 1.5rem;
        }
      }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12">
          <div class="login-container mx-auto">
            <!-- Logo -->
            <div class="logo-container">
              <img src="uploads/eventos/logo6.png" alt="EventosApp" class="img-fluid">
            </div>
            
            <form action="login.php" method="POST">
              <h1 class="login-title">
                <i class="bi bi-person-circle me-2"></i>
                Iniciar Sesión
              </h1>

              <!-- Mostrar error si existe -->
              <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <?= htmlspecialchars($error) ?>
                </div>
              <?php endif; ?>

              <!-- Campo Usuario -->
              <div class="input-group">
                <i class="bi bi-person input-icon"></i>
                <input
                  type="text"
                  class="form-control with-icon"
                  name="nombre_user"
                  placeholder="Nombre de usuario"
                  required
                  autocomplete="username"
                />
              </div>
              
              <!-- Campo Contraseña -->
              <div class="input-group">
                <i class="bi bi-lock input-icon"></i>
                <input
                  type="password"
                  class="form-control with-icon"
                  name="password_user"
                  placeholder="Contraseña"
                  required
                  autocomplete="current-password"
                />
              </div>
              
              <!-- Botón Login -->
              <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>
                Iniciar Sesión
              </button>
            </form>
            
            <!-- Link de registro -->
            <div class="register-link">
              <p class="mb-0">
                ¿No tienes una cuenta?<br>
                <a href="registro.html">
                  <i class="bi bi-person-plus me-1"></i>
                  Regístrate aquí
                </a>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Scripts de Bootstrap 5 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
      // Pequeña animación para los inputs
      document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.form-control');
        
        inputs.forEach(input => {
          input.addEventListener('focus', function() {
            this.parentElement.querySelector('.input-icon').style.color = '#6f00ff';
          });
          
          input.addEventListener('blur', function() {
            this.parentElement.querySelector('.input-icon').style.color = '#6c757d';
          });
        });
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
          setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
          }, 5000);
        });
      });
    </script>
  </body>
</html>
