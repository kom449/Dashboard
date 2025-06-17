<?php
// DEBUG: show all errors in JSON responses
ini_set('display_errors',           '1');
ini_set('display_startup_errors',   '1');
error_reporting(E_ALL);
header('Content-Type: application/json');
// place_transfer_request.php

// 1) Autoload & config
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/email_config.php';

include 'cors.php';
session_start();
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// JSON svar
header('Content-Type: application/json');

// ————— 2) Auth check —————
$store_manager = !empty($_SESSION['is_store_manager']) && $_SESSION['is_store_manager'];
$admin         = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (empty($_SESSION['logged_in']) || (!$store_manager && !$admin)) {
    http_response_code(403);
    echo json_encode([ 'success' => false, 'requests' => [] ]);
    exit();
}

// ————— 3) Parse JSON input —————
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if ($data === null) {
    echo json_encode(['success' => false, 'message' => 'Ugyldig JSON']);
    exit();
}

// Normalize til array af anmodninger
if (isset($data['requests']) && is_array($data['requests'])) {
    $requests = $data['requests'];
} elseif (isset($data['source'], $data['product'], $data['qty'])) {
    $requests = [[
        'source'   => $data['source'],
        'product'  => $data['product'],
        'quantity' => (int)$data['qty']
    ]];
} else {
    echo json_encode(['success' => false, 'message' => 'Ingen overførselsanmodninger angivet']);
    exit();
}

$destStore = $_SESSION['store_id'];

// ————— 4) Prepare to insert —————
$insertStmt = $conn->prepare(
    "INSERT INTO transfer_requests 
       (productIdentifier, source_store_id, dest_store_id, quantity)
     VALUES (?, ?, ?, ?)"
);

// ————— 5) Hent e‐mails —————
// 5a) Afsenderens e‐mail & navn
$userQ = $conn->prepare("SELECT email, username FROM admin_auth WHERE id = ?");
$userQ->bind_param("i", $_SESSION['user_id']);
$userQ->execute();
$userQ->bind_result($requesterEmail, $requesterName);
$userQ->fetch();
$userQ->close();

// 5b) Cache hver kildebutiks kontakt‐e‐mail & navn
$storeQ = $conn->prepare(
    "SELECT contact_email, shop_name 
       FROM shops 
      WHERE shop_id = ?"
);
$storeContacts = [];
foreach ($requests as $r) {
    $src = $r['source'];
    if (!isset($storeContacts[$src])) {
        $storeQ->bind_param("s", $src);
        $storeQ->execute();
        $storeQ->bind_result($contactEmail, $shopName);
        $storeQ->fetch();
        $storeContacts[$src] = [
            'email' => $contactEmail,
            'name'  => $shopName
        ];
    }
}
$storeQ->close();

// ————— 6) Indsæt hver anmodning —————
foreach ($requests as $r) {
    $insertStmt->bind_param(
        "ssii",
        $r['product'],
        $r['source'],
        $destStore,
        $r['quantity']
    );
    $insertStmt->execute();
}
$insertStmt->close();

// ————— 7) Mail-helper —————
function sendMail($to, $toName, $subject, $bodyText)
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to, $toName);
        $mail->Subject = $subject;
        $mail->Body    = $bodyText;

        $mail->send();
    } catch (Exception $e) {
        error_log("Mail‐fejl til {$to}: " . $mail->ErrorInfo);
    }
}

// ————— 8) Underret anmoder —————
$bodyReq = "Hej {$requesterName},\n\n"
    . "Din overførselsanmodning er modtaget og sat i kø:\n\n";
foreach ($requests as $r) {
    $bodyReq .= "- Vare {$r['product']} × {$r['quantity']} fra butik {$r['source']} til din butik {$destStore}\n";
}
sendMail(
    $requesterEmail,
    $requesterName,
    'Din overførselsanmodning er modtaget',
    $bodyReq
);

// ————— 9) Underret hver kildebutik —————
foreach ($requests as $r) {
    $src = $r['source'];
    $info = $storeContacts[$src] ?? null;
    if (empty($info['email'])) continue;

    $bodySrc = "Hej {$info['name']},\n\n"
        . "Butik {$destStore} har anmodet om vare {$r['product']} × {$r['quantity']} fra jeres butik.\n\n"
        . "Log venligst ind for at bekræfte afsendelse.\n";
    sendMail(
        $info['email'],
        $info['name'],
        'Ny overførselsanmodning',
        $bodySrc
    );
}

// ————— 10) Endeligt svar —————
echo json_encode([
    'success' => true,
    'message' => 'Sat i kø: ' . count($requests) . ' overførselsanmodning(er).'
]);
