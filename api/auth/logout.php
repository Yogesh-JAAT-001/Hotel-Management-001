<?php
require_once '../../config.php';

initApiRequest(['POST']);

clearAdminRememberCookie($pdo);
clearSession();

jsonResponse([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>
