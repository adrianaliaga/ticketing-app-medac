<?php
error_reporting(0);
ini_set('display_errors', 0);

require 'session_boot.php';
require 'conexion.php';

header('Content-Type: application/json');

function send_error($statusCode, $message) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error(405, 'Método no permitido.');
}

if (!isset($_SESSION['user_id'])) {
    send_error(401, 'No autorizado. Debes iniciar sesión.');
}

$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    send_error(400, 'JSON mal formado.');
}

$eventId = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT);
if (!$eventId) {
    send_error(400, 'ID de evento inválido.');
}

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $connection->prepare("SELECT id FROM favorites WHERE user_id = ? AND event_id = ?");
    $stmt->bind_param("ii", $userId, $eventId);
    $stmt->execute();
    $isFavorite = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    if ($isFavorite) {
        $stmt = $connection->prepare("DELETE FROM favorites WHERE user_id = ? AND event_id = ?");
        $stmt->bind_param("ii", $userId, $eventId);
        $stmt->execute();
        echo json_encode(['status' => 'removed']);
    } else {
        $stmt = $connection->prepare("INSERT INTO favorites (user_id, event_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $eventId);
        $stmt->execute();
        echo json_encode(['status' => 'added']);
    }
    
    if ($stmt->error) {
         send_error(500, 'Error de base de datos: ' . $stmt->error);
    }

    $stmt->close();
    $connection->close();

} catch (Exception $e) {
    send_error(500, 'Error inesperado en el servidor.');
}
?>