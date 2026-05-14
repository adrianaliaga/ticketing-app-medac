<?php
require_once "conexion.php";

function parse_birthdate_to_mysql(?string $raw): ?string {
    if (!$raw) return null;
    $raw = trim($raw);
    $raw = str_replace('-', '/', $raw);

    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m)) {
        [$all, $d, $mth, $y] = $m;
        if (checkdate((int)$mth, (int)$d, (int)$y)) {
            return sprintf('%04d-%02d-%02d', $y, $mth, $d);
        }
        return null;
    }

    if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $raw)) {
        return $raw;
    }

    return null;
}

$mensaje = "";
$esExito = false;

// Si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username  = trim($_POST['nombre_user'] ?? '');
    $email     = trim($_POST['email_user'] ?? '');
    $password  = $_POST['password_user'] ?? '';
    $phone     = trim($_POST['phone_user'] ?? '');
    $birth_raw = trim($_POST['birthdate_user'] ?? '');

    if ($username === '' || $email === '' || $password === '') {
        $mensaje = "❌ Todos los campos obligatorios deben estar completos.";
    } else {
        $birthdate = parse_birthdate_to_mysql($birth_raw);
        if ($birth_raw !== '' && $birthdate === null) {
            $mensaje = "❌ Fecha inválida. Usa el formato dd/mm/aaaa.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $sql  = "INSERT INTO users (username, email, password, phone, birthdate)
                     VALUES (?, ?, ?, ?, ?)";
            $stmt = $connection->prepare($sql);

            if (!$stmt) {
                $mensaje = "❌ Error al preparar la consulta.";
            } else {
                $stmt->bind_param("sssss", $username, $email, $hash, $phone, $birthdate);
                if ($stmt->execute()) {
                    $mensaje = "✅ Usuario registrado correctamente. <a href='login.php'>Inicia sesión aquí</a>.";
                    $esExito = true;
                } else {
                    $mensaje = "❌ Error al registrar el usuario. Puede que el correo o nombre ya estén en uso.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <title>Registro - EventosApp</title>
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
      
      .register-container {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        backdrop-filter: blur(10px);
        padding: 3rem;
        margin-top: 3rem;
        max-width: 500px;
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
      
      .register-title {
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
      
      .btn-register {
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
      
      .btn-register:hover {
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
      
      .alert-success {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: white;
      }
      
      .login-link {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
        color: #6c757d;
      }
      
      .login-link a {
        color: #6f00ff;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
      }
      
      .login-link a:hover {
        color: #845ef7;
        text-decoration: underline;
      }
      
      /* Animación de entrada */
      .register-container {
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
        .register-container {
          margin: 2rem 1rem;
          padding: 2rem 1.5rem;
        }
        
        .logo-container img {
          max-width: 150px;
        }
        
        .register-title {
          font-size: 1.5rem;
        }
      }
    </style>
  </head>
  <body>
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12">
          <div class="register-container mx-auto">
            <!-- Logo -->
            <div class="logo-container">
              <img src="uploads/eventos/logo6.png" alt="EventosApp" class="img-fluid">
            </div>
            
            <form action="registro.php" method="POST">
              <h1 class="register-title">
                <i class="bi bi-person-plus me-2"></i>
                Crear Cuenta
              </h1>

              <!-- Mostrar mensajes -->
              <?php if ($mensaje): ?>
                <div class="alert <?= $esExito ? 'alert-success' : 'alert-danger' ?> d-flex align-items-center" role="alert">
                  <i class="bi bi-<?= $esExito ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                  <?= $mensaje ?>
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
              
              <!-- Campo Email -->
              <div class="input-group">
                <i class="bi bi-envelope input-icon"></i>
                <input
                  type="email"
                  class="form-control with-icon"
                  name="email_user"
                  placeholder="Correo electrónico"
                  required
                  autocomplete="email"
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
                  autocomplete="new-password"
                />
              </div>
              
              <!-- Campo Teléfono -->
              <div class="input-group">
                <i class="bi bi-telephone input-icon"></i>
                <input
                  type="tel"
                  class="form-control with-icon"
                  name="phone_user"
                  placeholder="Número de teléfono"
                  required
                  autocomplete="tel"
                />
              </div>
              
              <!-- Campo Fecha de Nacimiento -->
              <div class="input-group">
                <i class="bi bi-calendar input-icon"></i>
                <input
                  type="text"
                  class="form-control with-icon"
                  name="birthdate_user"
                  placeholder="Fecha de nacimiento (dd/mm/aaaa)"
                  pattern="^(0?[1-9]|[12]\d|3[01])/(0?[1-9]|1[0-2])/\d{4}$"
                  required
                />
              </div>
              
              <!-- Botón Registro -->
              <button type="submit" class="btn btn-register" name="registro">
                <i class="bi bi-person-plus me-2"></i>
                Crear Cuenta
              </button>
            </form>
            
            <!-- Link de login -->
            <div class="login-link">
              <p class="mb-0">
                ¿Ya tienes una cuenta?<br>
                <a href="login.php">
                  <i class="bi bi-box-arrow-in-right me-1"></i>
                  Inicia sesión aquí
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

      // Script para formatear fecha
      document
        .querySelector('input[name="birthdate_user"]')
        .addEventListener("blur", (e) => {
          const v = e.target.value.trim();
          const m = v.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
          if (!m) return;
          const dd = String(m[1]).padStart(2, "0");
          const mm = String(m[2]).padStart(2, "0");
          const yyyy = m[3];
          e.target.value = `${dd}/${mm}/${yyyy}`;
        });
    </script>
  </body>
</html>
