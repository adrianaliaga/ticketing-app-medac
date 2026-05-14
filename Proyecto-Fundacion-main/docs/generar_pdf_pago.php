<?php
// docs/generar_pdf_pago.php
// Genera un PDF con el resumen de una reserva (tabla `reservations`) usando Dompdf.

require_once __DIR__ . '/conexion.php';

// Autoload de Composer (se instalará en vendor/)
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    die('Dependencia faltante: ejecuta composer require dompdf/dompdf en la raíz del proyecto.');
}
require_once $vendorAutoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// Validar parámetro
$reservation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($reservation_id <= 0) {
    die('ID de reserva inválido. Pasa ?id=NN al script.');
}

// Consultar la reserva
$sql = "SELECT id, event_id, user_id, quantity, reservation_date, estado, payment_status, transaction_id, total_amount, payment_date FROM reservations WHERE id = ? LIMIT 1";
$reservation = null;
if ($stmt = $connection->prepare($sql)) {
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $reservation = $row;
    }
    $stmt->close();
} else {
    die('Error en la consulta: ' . $connection->error);
}

if (!$reservation) {
    die('Reserva no encontrada.');
}

// Seguridad básica: permitir sólo al usuario propietario si hay sesión
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $reservation['user_id']) {
    die('No autorizado para ver este recibo.');
}

// Preparar datos
$recibo_id = htmlspecialchars($reservation['id']);
$event_id = htmlspecialchars($reservation['event_id']);
$user_id = htmlspecialchars($reservation['user_id']);
$quantity = htmlspecialchars($reservation['quantity']);
$reservation_date = htmlspecialchars($reservation['reservation_date']);
$estado = htmlspecialchars($reservation['estado']);
$payment_status = htmlspecialchars($reservation['payment_status']);
$transaction_id = htmlspecialchars($reservation['transaction_id']);
$total_amount = number_format((float)($reservation['total_amount'] ?? 0), 2, ',', '.');
$payment_date = htmlspecialchars($reservation['payment_date'] ?? '');

// HTML del recibo
$html = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Recibo de reserva #{$recibo_id}</title>
  <style>
    body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #222; }
    .container { max-width: 700px; margin: 0 auto; padding: 20px; }
    h1 { text-align: center; color: #0073aa; }
    .meta { margin: 20px 0; }
    .meta th { text-align: left; padding-right: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    td, th { padding: 8px; border-bottom: 1px solid #eee; }
    .total { font-size: 1.2em; font-weight: bold; }
    .footer { margin-top: 30px; font-size: 0.9em; color: #666; text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Recibo de reserva</h1>
    <p>Resumen de la reserva:</p>
    <table class="meta">
      <tr><th>Recibo #:</th><td>{$recibo_id}</td></tr>
      <tr><th>Evento (ID):</th><td>{$event_id}</td></tr>
      <tr><th>Fecha de reserva:</th><td>{$reservation_date}</td></tr>
      <tr><th>Estado:</th><td>{$estado}</td></tr>
      <tr><th>Estado pago:</th><td>{$payment_status}</td></tr>
    </table>

    <table>
      <tr>
        <th>Descripción</th>
        <th style="text-align:right">Importe</th>
      </tr>
      <tr>
        <td>Entradas x {$quantity}</td>
        <td style="text-align:right">{$total_amount} €</td>
      </tr>
      <tr>
        <td class="total">Total</td>
        <td class="total" style="text-align:right">{$total_amount} €</td>
      </tr>
    </table>

    <div class="footer">
      <p>Transacción: {$transaction_id}</p>
      <p>Fecha pago: {$payment_date}</p>
    </div>
  </div>
</body>
</html>
HTML;

// Generar PDF con Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'recibo_reserva_' . $recibo_id . '.pdf';
$dompdf->stream($filename, ["Attachment" => 0]);

?>
