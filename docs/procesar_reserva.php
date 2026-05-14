<?php
require 'session_boot.php';
require 'conexion.php';

// Proteger: solo para usuarios logueados
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 1. Verificar que se ha enviado el formulario por el método POST o GET (para pagos)
if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    
    // 2. Recoger y validar datos
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : (isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0);
    $user_id = (int)$_SESSION['user_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : (isset($_GET['quantity']) ? (int)$_GET['quantity'] : 0);
    $payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : 'free';

    // Validación básica
    if ($event_id > 0 && $user_id > 0 && $quantity > 0 && $quantity <= 10) {
        
        // Verificar si el evento requiere pago
        $stmt_event = $connection->prepare("SELECT price FROM events WHERE id = ?");
        $stmt_event->bind_param("i", $event_id);
        $stmt_event->execute();
        $event_result = $stmt_event->get_result();
        $event_data = $event_result->fetch_assoc();
        
        if (!$event_data) {
            die("Error: Evento no encontrado.");
        }
        
        $event_price = (float)$event_data['price'];
        $total_amount = $event_price * $quantity;
        
        // Variables para la información de pago
        $payment_status = 'pending';
        $transaction_id = null;
        
        // Si el evento requiere pago, verificar que se haya procesado
        if ($total_amount > 0) {
            if ($payment_method === 'free') {
                die("Error: Este evento requiere pago.");
            }
            
            // Verificar información de pago en la sesión
            if (isset($_SESSION['payment_info']) && 
                $_SESSION['payment_info']['event_id'] == $event_id && 
                $_SESSION['payment_info']['quantity'] == $quantity &&
                $_SESSION['payment_info']['amount'] == $total_amount) {
                
                $payment_status = 'completed';
                $transaction_id = $_SESSION['payment_info']['transaction_id'];
                
                // Limpiar información de pago de la sesión
                unset($_SESSION['payment_info']);
            } else {
                die("Error: Información de pago no válida. Por favor, intenta nuevamente.");
            }
        } else {
            // Evento gratuito
            $payment_status = 'free';
            $transaction_id = 'FREE_' . uniqid();
        }

        // 3. Preparar la consulta para insertar los datos de forma segura
        $stmt = $connection->prepare(
            "INSERT INTO reservations (event_id, user_id, quantity, payment_status, transaction_id, total_amount) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iiissd", $event_id, $user_id, $quantity, $payment_status, $transaction_id, $total_amount);
        
        // 4. Ejecutar y verificar
        if ($stmt->execute()) {
            // Si todo va bien, redirigir a una página de éxito
            header("Location: gracias.php");
            exit();
        } else {
            // Manejar un posible error (p. ej. evento no existe)
            die("Error al procesar la reserva. Por favor, inténtalo de nuevo.");
        }
        
    } else {
        die("Datos inválidos. La cantidad debe ser entre 1 y 10.");
    }
} else {
    // Si alguien intenta acceder a este archivo directamente, lo redirigimos
    header("Location: index.php");
    exit();
}
?>
