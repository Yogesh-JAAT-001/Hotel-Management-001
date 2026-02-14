<?php
require_once '../config.php';
require_once '../includes/pricing-engine.php';

initApiRequest(['GET'], false);

$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$checkIn = $_GET['check_in'] ?? '';
$checkOut = $_GET['check_out'] ?? '';

if ($roomId <= 0 || $checkIn === '' || $checkOut === '') {
    jsonResponse(['error' => 'room_id, check_in, and check_out are required'], 400);
}

try {
    $quote = getDynamicPriceQuote($pdo, $roomId, $checkIn, $checkOut);
    jsonResponse([
        'success' => true,
        'data' => $quote
    ]);
} catch (InvalidArgumentException $e) {
    jsonResponse(['error' => $e->getMessage()], 400);
} catch (Exception $e) {
    error_log('Pricing quote error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to calculate dynamic price'], 500);
}

