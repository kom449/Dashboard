<?php
// Define the list of allowed origins
$allowedOrigins = [
    'https://dashboard.designcykler.dk.linux100.curanetserver.dk',
    'https://www.google.com',
    'https://www.designcykler.dk'  // Add your image domain if needed
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
} else {
    // Optionally set a default origin or handle disallowed origins
    header("Access-Control-Allow-Origin: https://dashboard.designcykler.dk.linux100.curanetserver.dk");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
