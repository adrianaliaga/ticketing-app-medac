<?php
require 'session_boot.php';
require 'conexion.php';

// Proteger: solo para usuarios logueados
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener datos del evento y cantidad desde la sesión o parámetros
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;

if ($event_id === 0) {
    die("Error: Evento no especificado.");
}

// Consultar información del evento
$stmt = $connection->prepare("SELECT id, title, price, location, start_at, image_path FROM events WHERE id = ? AND is_public = 1");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$resultado = $stmt->get_result();
$evento = $resultado->fetch_assoc();

if (!$evento) {
    die("Error: Evento no encontrado.");
}

$precio_unitario = (float)$evento['price'];
$total = $precio_unitario * $quantity;

// Si el evento es gratis, redirigir directamente al procesamiento
if ($total <= 0) {
    header("Location: procesar_reserva.php?event_id=$event_id&quantity=$quantity&payment_method=free");
    exit;
}

$mensaje_error = '';
$pago_exitoso = false;

// Procesar el pago si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $card_number = $_POST['card_number'] ?? '';
    $card_name = $_POST['card_name'] ?? '';
    $card_expiry = $_POST['card_expiry'] ?? '';
    $card_cvv = $_POST['card_cvv'] ?? '';
    
    // Validación básica
    if ($payment_method === 'card') {
        if (empty($card_number) || empty($card_name) || empty($card_expiry) || empty($card_cvv)) {
            $mensaje_error = 'Todos los campos de la tarjeta son obligatorios.';
        } elseif (strlen($card_number) < 13 || strlen($card_number) > 19) {
            $mensaje_error = 'Número de tarjeta inválido.';
        } elseif (strlen($card_cvv) < 3 || strlen($card_cvv) > 4) {
            $mensaje_error = 'CVV inválido.';
        } else {
            // Simular procesamiento de pago con tarjeta
            if (simular_pago_tarjeta($card_number, $card_name, $card_expiry, $card_cvv, $total)) {
                $pago_exitoso = true;
            } else {
                $mensaje_error = 'Error al procesar el pago. Verifique los datos de su tarjeta.';
            }
        }
    } elseif ($payment_method === 'paypal') {
        // Simular procesamiento de PayPal
        if (simular_pago_paypal($total)) {
            $pago_exitoso = true;
        } else {
            $mensaje_error = 'Error al procesar el pago con PayPal.';
        }
    } elseif ($payment_method === 'bizum') {
        // Simular procesamiento de Bizum
        $phone = $_POST['bizum_phone'] ?? '';
        if (empty($phone) || strlen($phone) < 9) {
            $mensaje_error = 'Número de teléfono para Bizum inválido.';
        } else {
            if (simular_pago_bizum($phone, $total)) {
                $pago_exitoso = true;
            } else {
                $mensaje_error = 'Error al procesar el pago con Bizum.';
            }
        }
    } else {
        $mensaje_error = 'Método de pago no válido.';
    }
    
    // Si el pago fue exitoso, redirigir al procesamiento de la reserva
    if ($pago_exitoso) {
        // Guardar información del pago en la sesión
        $_SESSION['payment_info'] = [
            'method' => $payment_method,
            'amount' => $total,
            'transaction_id' => 'TXN_' . uniqid() . '_' . time(),
            'event_id' => $event_id,
            'quantity' => $quantity
        ];
        
        header("Location: procesar_reserva.php?event_id=$event_id&quantity=$quantity&payment_method=$payment_method");
        exit;
    }
}

// Funciones para simular el procesamiento de pagos
function simular_pago_tarjeta($numero, $nombre, $expiry, $cvv, $total) {
    // Simulación: rechazar si el número termina en 0000
    if (substr($numero, -4) === '0000') {
        return false;
    }
    
    // Simular un pequeño delay de procesamiento
    usleep(500000); // 0.5 segundos
    
    // 95% de probabilidad de éxito
    return rand(1, 100) <= 95;
}

function simular_pago_paypal($total) {
    // Simular delay de procesamiento
    usleep(800000); // 0.8 segundos
    
    // 98% de probabilidad de éxito
    return rand(1, 100) <= 98;
}

function simular_pago_bizum($phone, $total) {
    // Simular delay de procesamiento
    usleep(300000); // 0.3 segundos
    
    // 97% de probabilidad de éxito
    return rand(1, 100) <= 97;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago - <?= htmlspecialchars($evento['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__.'/style.css') ?>">
    <style>
        .payment-container {
            max-width: 800px;
            margin: 2rem auto;
        }
        .payment-method {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .payment-method:hover {
            border-color: #6f00ff;
            background-color: #f8f9fa;
        }
        .payment-method.active {
            border-color: #6f00ff;
            background-color: #f8f9fa;
        }
        .payment-method input[type="radio"] {
            display: none;
        }
        .payment-details {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .payment-details.active {
            display: block;
        }
        .order-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .btn-pay {
            background: linear-gradient(135deg, var(--brand-blue) 0%, var(--brand-blue-dark) 100%);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            width: 100%;
            font-size: 1.1rem;
        }
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(111, 0, 255, 0.18);
            color: white;
        }
        .payment-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #e8f5e8;
            color: #155724;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
    </style>
</head>

<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-custom-navbar shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php">
            <img src="uploads/eventos/logo4.png" alt="Inicio" class="logo-navbar">EventosApp
        </a>
    </div>
</nav>

<div class="container payment-container">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-credit-card me-2"></i>Procesar Pago</h4>
                </div>
                <div class="card-body">
                    <?php if ($mensaje_error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($mensaje_error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Resumen del pedido -->
                    <div class="order-summary">
                        <h5><i class="bi bi-cart-check me-2"></i>Resumen del pedido</h5>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?= htmlspecialchars($evento['title']) ?></span>
                            <span><?= number_format($precio_unitario, 2) ?> €</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Cantidad: <?= $quantity ?></span>
                            <span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Fecha: <?= date('d/m/Y H:i', strtotime($evento['start_at'])) ?></span>
                            <span></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center fw-bold h5">
                            <span>Total:</span>
                            <span><?= number_format($total, 2) ?> €</span>
                        </div>
                    </div>

                    <form method="POST" id="paymentForm">
                        <!-- Métodos de pago -->
                        <h5 class="mb-3"><i class="bi bi-wallet me-2"></i>Selecciona método de pago</h5>

                        <!-- Tarjeta de crédito/débito -->
                        <div class="payment-method" onclick="selectPaymentMethod('card')">
                            <input type="radio" name="payment_method" value="card" id="card">
                            <div class="d-flex align-items-center">
                                <div class="payment-icon text-primary me-3">
                                    <i class="bi bi-credit-card"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Tarjeta de Crédito/Débito</h6>
                                    <small class="text-muted">Visa, MasterCard, American Express</small>
                                </div>
                            </div>
                            
                            <div class="payment-details" id="card-details">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Número de tarjeta</label>
                                        <input type="text" name="card_number" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Nombre del titular</label>
                                        <input type="text" name="card_name" class="form-control" placeholder="Juan Pérez">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">Fecha de vencimiento</label>
                                        <input type="text" name="card_expiry" class="form-control" placeholder="MM/AA" maxlength="5">
                                    </div>
                                    <div class="col-6 mb-3">
                                        <label class="form-label">CVV</label>
                                        <input type="text" name="card_cvv" class="form-control" placeholder="123" maxlength="4">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PayPal -->
                        <div class="payment-method" onclick="selectPaymentMethod('paypal')">
                            <input type="radio" name="payment_method" value="paypal" id="paypal">
                            <div class="d-flex align-items-center">
                                <div class="payment-icon text-warning me-3">
                                    <i class="bi bi-paypal"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">PayPal</h6>
                                    <small class="text-muted">Paga de forma segura con tu cuenta PayPal</small>
                                </div>
                            </div>
                            
                            <div class="payment-details" id="paypal-details">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Serás redirigido a PayPal para completar el pago de forma segura.
                                </div>
                            </div>
                        </div>

                        <!-- Bizum -->
                        <div class="payment-method" onclick="selectPaymentMethod('bizum')">
                            <input type="radio" name="payment_method" value="bizum" id="bizum">
                            <div class="d-flex align-items-center">
                                <div class="payment-icon text-success me-3">
                                    <i class="bi bi-phone"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Bizum</h6>
                                    <small class="text-muted">Pago móvil instantáneo</small>
                                </div>
                            </div>
                            
                            <div class="payment-details" id="bizum-details">
                                <div class="mb-3">
                                    <label class="form-label">Número de teléfono</label>
                                    <input type="tel" name="bizum_phone" class="form-control" placeholder="600 123 456">
                                </div>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Recibirás una notificación en tu móvil para confirmar el pago.
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="secure-badge mb-3">
                                <i class="bi bi-shield-check"></i>
                                <span>Pago 100% seguro</span>
                            </div>
                            
                            <button type="submit" class="btn btn-pay" id="payBtn" disabled>
                                <i class="bi bi-credit-card me-2"></i>
                                Pagar <?= number_format($total, 2) ?> €
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Detalles del evento</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($evento['image_path'])): ?>
                        <img src="<?= htmlspecialchars($evento['image_path']) ?>" class="card-img-top mb-3" alt="Evento" style="height: 150px; object-fit: cover; border-radius: 8px;">
                    <?php endif; ?>
                    
                    <h6><?= htmlspecialchars($evento['title']) ?></h6>
                    <p class="text-muted mb-2">
                        <i class="bi bi-calendar me-2"></i>
                        <?= date('d/m/Y H:i', strtotime($evento['start_at'])) ?>
                    </p>
                    <p class="text-muted mb-2">
                        <i class="bi bi-geo-alt me-2"></i>
                        <?= htmlspecialchars($evento['location']) ?>
                    </p>
                    <p class="text-muted">
                        <i class="bi bi-people me-2"></i>
                        <?= $quantity ?> entrada<?= $quantity > 1 ? 's' : '' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectPaymentMethod(method) {
    // Limpiar selecciones anteriores
    document.querySelectorAll('.payment-method').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.payment-details').forEach(el => el.classList.remove('active'));
    
    // Activar método seleccionado
    document.querySelector(`#${method}`).checked = true;
    document.querySelector(`#${method}`).closest('.payment-method').classList.add('active');
    document.querySelector(`#${method}-details`).classList.add('active');
    
    // Habilitar botón de pago
    document.getElementById('payBtn').disabled = false;
    
    // Actualizar texto del botón
    const btnText = method === 'paypal' ? 'Continuar con PayPal' : 
                   method === 'bizum' ? 'Pagar con Bizum' : 
                   'Procesar Pago';
    document.getElementById('payBtn').innerHTML = `<i class="bi bi-credit-card me-2"></i>${btnText} <?= number_format($total, 2) ?> €`;
}

// Formatear número de tarjeta
document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    if (formattedValue !== value) {
        e.target.value = formattedValue;
    }
});

// Formatear fecha de vencimiento
document.querySelector('input[name="card_expiry"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substring(0, 2) + '/' + value.substring(2, 4);
    }
    e.target.value = value;
});

// Solo números para CVV
document.querySelector('input[name="card_cvv"]')?.addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/[^0-9]/g, '');
});

// Formatear teléfono Bizum
document.querySelector('input[name="bizum_phone"]')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 3 && value.length <= 6) {
        value = value.substring(0, 3) + ' ' + value.substring(3);
    } else if (value.length > 6) {
        value = value.substring(0, 3) + ' ' + value.substring(3, 6) + ' ' + value.substring(6, 9);
    }
    e.target.value = value;
});

// Validación del formulario
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const method = document.querySelector('input[name="payment_method"]:checked')?.value;
    
    if (!method) {
        e.preventDefault();
        alert('Por favor, selecciona un método de pago.');
        return;
    }
    
    if (method === 'card') {
        const cardNumber = document.querySelector('input[name="card_number"]').value;
        const cardName = document.querySelector('input[name="card_name"]').value;
        const cardExpiry = document.querySelector('input[name="card_expiry"]').value;
        const cardCvv = document.querySelector('input[name="card_cvv"]').value;
        
        if (!cardNumber || !cardName || !cardExpiry || !cardCvv) {
            e.preventDefault();
            alert('Por favor, completa todos los campos de la tarjeta.');
            return;
        }
    }
    
    if (method === 'bizum') {
        const phone = document.querySelector('input[name="bizum_phone"]').value;
        if (!phone || phone.length < 9) {
            e.preventDefault();
            alert('Por favor, introduce un número de teléfono válido.');
            return;
        }
    }
    
    // Mostrar loading
    const btn = document.getElementById('payBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
});
</script>

</body>
</html>