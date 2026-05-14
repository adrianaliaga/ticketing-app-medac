<?php
// docs/generar_entrada_pdf.php
// Genera una entrada/ticket en PDF para una reserva (tabla `reservations`).
// Usa Dompdf.

require_once __DIR__ . '/conexion.php';

$vendor = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendor)) {
    die('Dependencia faltante: ejecuta composer require dompdf/dompdf en la raíz del proyecto.');
}
require_once $vendor;

use Dompdf\Dompdf;
use Dompdf\Options;

// Parámetro: id de la reserva
$reservation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($reservation_id <= 0) {
    die('ID de reserva no válido. Pasa ?id=NN al script.');
}

// Consultar reserva y evento
$sql = "SELECT r.id AS reservation_id, r.event_id, r.user_id, r.quantity, r.reservation_date, r.total_amount, r.transaction_id,
               e.title AS event_title, e.start_at AS event_start, e.location AS event_location
        FROM reservations r
        LEFT JOIN events e ON e.id = r.event_id
        WHERE r.id = ? LIMIT 1";

$data = null;
if ($stmt = $connection->prepare($sql)) {
    $stmt->bind_param('i', $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
} else {
    die('Error en la consulta: ' . $connection->error);
}

if (!$data) {
    die('Reserva no encontrada.');
}

// Seguridad: permitir sólo al propietario si hay sesión
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $data['user_id']) {
    die('No autorizado para ver esta entrada.');
}

// Preparar datos
$res_id = htmlspecialchars($data['reservation_id']);
$event_title = htmlspecialchars($data['event_title'] ?? 'Evento');
$event_date = htmlspecialchars($data['event_start'] ?? '');
$event_location = htmlspecialchars($data['event_location'] ?? '');
$quantity = (int)($data['quantity'] ?? 1);
$total = number_format((float)($data['total_amount'] ?? 0), 2, ',', '.');
$txn = htmlspecialchars($data['transaction_id'] ?? '');

// Preparar QR (payload simple: Reserva:id;Txn:txnid)
$qrPayload = "Reserva:" . $res_id . ";Txn:" . $txn;
$qrEncoded = rawurlencode($qrPayload);
$qrRemoteUrl = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl={$qrEncoded}&choe=UTF-8";

// Generar QR localmente con endroid/qr-code 
$qrSrc = $qrRemoteUrl; // fallback: URL remota si todo lo demás falla
if (class_exists(\Endroid\QrCode\QrCode::class) && class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
    try {
        $qrCode = new \Endroid\QrCode\QrCode($qrPayload);
        // Preferir SVG (no requiere GD) y fallback a PNG
        if (class_exists(\Endroid\QrCode\Writer\SvgWriter::class)) {
            $writer = new \Endroid\QrCode\Writer\SvgWriter();
            $result = $writer->write($qrCode);
            $qrData = $result->getString();
            if ($qrData !== null) {
                $qrSrc = 'data:image/svg+xml;base64,' . base64_encode($qrData);
            }
        } elseif (class_exists(\Endroid\QrCode\Writer\PngWriter::class)) {
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $result = $writer->write($qrCode);
            $qrData = $result->getString();
            if ($qrData !== null) {
                $qrSrc = 'data:image/png;base64,' . base64_encode($qrData);
            }
        }
    } catch (\Throwable $e) {
        // si falla, dejamos el fallback remoto y continuamos
        $qrSrc = $qrRemoteUrl;
    }
} else {
    // Helper: intentar descargar con cURL y si no está disponible probar file_get_contents
    function fetch_url_contents(string $url)
    {
        // intentar cURL primero (común en instalaciones Windows/XAMPP)
        if (function_exists('curl_version')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            // en entornos locales puede no tener CA; permitir temporalmente no verificar
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($data !== false && $http >= 200 && $http < 300) {
                return $data;
            }
        }

        // fallback: file_get_contents si allow_url_fopen está habilitado
        if (ini_get('allow_url_fopen')) {
            $data = @file_get_contents($url);
            if ($data !== false) return $data;
        }

        return false;
    }

    $qrData = fetch_url_contents($qrRemoteUrl);
    if ($qrData !== false) {
        $qrSrc = 'data:image/png;base64,' . base64_encode($qrData);
    } else {
        // Si no podemos descargar la imagen remota, intentar generar un placeholder simple con GD
        if (function_exists('imagecreatetruecolor')) {
            $w = 300; $h = 300;
            $im = imagecreatetruecolor($w, $h);
            $white = imagecolorallocate($im, 255,255,255);
            $black = imagecolorallocate($im, 0,0,0);
            imagefilledrectangle($im, 0,0,$w,$h,$white);
            // Texto corto "QR" centrado
            $fontSize = 5;
            $text = "QR\nentrada";
            // Dibujar rectángulo y texto central
            imagerectangle($im, 10,10,$w-10,$h-10,$black);
            $lines = explode("\n", $text);
            $lineH = 12;
            $y = ($h - count($lines)*$lineH) / 2;
            foreach ($lines as $line) {
                $textBox = imagefontwidth($fontSize) * strlen($line);
                $x = ($w - $textBox) / 2;
                imagestring($im, $fontSize, (int)$x, (int)$y, $line, $black);
                $y += $lineH;
            }
            ob_start();
            imagepng($im);
            imagedestroy($im);
            $png = ob_get_clean();
            if ($png !== false) {
                $qrSrc = 'data:image/png;base64,' . base64_encode($png);
            }
        }
    }
}

// HTML del ticket
$html = <<<HTML
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Entrada #$res_id</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #222; }
        .ticket { border: 2px dashed #333; padding: 20px; max-width: 700px; margin: 0 auto; }
        h1 { text-align: center; font-size: 20px; margin-bottom: 8px; }
        /* Layout using a 2-column table for maximum PDF compatibility */
        .ticket-table { width: 100%; border-collapse: collapse; }
        .ticket-table td.left { vertical-align: top; padding-right: 12px; }
        .ticket-table td.right { width: 180px; vertical-align: top; text-align: center; }
        .qr-frame { padding:6px; border:1px solid #ddd; display:inline-block; background:#fff; }
        .footer { text-align:center; margin-top:18px; font-size:12px; color:#666; }
        .big { font-size:1.1em; font-weight:700; }
        hr.sep { border: none; border-top: 1px solid #ddd; margin: 18px 0; }
    </style>
</head>
<body>
  <div class="ticket">
    <h1>Entrada - {$event_title}</h1>
        <table class="ticket-table">
            <tr>
                <td class="left">
                    <div><strong>Fecha:</strong> {$event_date}</div>
                    <div><strong>Lugar:</strong> {$event_location}</div>
                    <div style="margin-top:8px;"><strong>Reserva #:</strong> {$res_id}</div>
                    <div><strong>Entradas:</strong> {$quantity}</div>
                </td>
                <td class="right">
                    <div class="qr-frame">
                        <img src="{$qrSrc}" style="width:150px; height:150px; display:block;" alt="QR entrada" />
                    </div>
                    <div style="font-size:11px; margin-top:6px; color:#666;">Escanea para validar</div>
                </td>
            </tr>
        </table>
    <hr class="sep">
    <p class="big">Total: {$total} €</p>
    <p>Transacción: {$txn}</p>
    <div class="footer">Presenta esta entrada en la entrada del evento.</div>
  </div>
</body>
</html>
HTML;

// Generar PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
// Permitir carga remota (necesario si usamos la URL remota como fallback para el QR)
$options->setIsRemoteEnabled(true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'entrada_' . $res_id . '.pdf';
$dompdf->stream($filename, ["Attachment" => 0]);

?>