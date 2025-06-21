<?php
// DEBUG: show all errors in JSON responses
ini_set('display_errors','0');
ini_set('display_startup_errors','0');
error_reporting(0);

// force JSON response
header('Content-Type: application/json');

// 1) Autoload & config
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/email_config.php';

include 'cors.php';
session_start();
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2) Auth check
$store_manager = !empty($_SESSION['is_store_manager']);
$admin         = !empty($_SESSION['is_admin']);

if (empty($_SESSION['logged_in']) || (! $store_manager && ! $admin)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Adgang nægtet']);
    exit();
}

// 3) Parse JSON input
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data) || empty($data['requests'])) {
    echo json_encode(['success'=>false,'message'=>'Ingen overførselsanmodninger angivet']);
    exit();
}
$requests     = $data['requests'];
$destStoreId  = $_SESSION['store_id'];

// 4) Lookup destination store name & email
$destQ = $conn->prepare("
    SELECT shop_name, contact_email
      FROM shops
     WHERE shop_id = ?
");
$destQ->bind_param("s", $destStoreId);
$destQ->execute();
$destQ->bind_result($destStoreName, $destStoreEmail);
$destQ->fetch();
$destQ->close();

// 5) Gather all product titles in one go
$productIds   = array_unique(array_column($requests,'product'));
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$stmt         = $conn->prepare("
    SELECT Id, title 
      FROM items 
     WHERE Id IN ($placeholders)
");
$types = str_repeat('s', count($productIds));
$stmt->bind_param($types, ...$productIds);
$stmt->execute();
$res    = $stmt->get_result();
$titles = [];
while ($row = $res->fetch_assoc()) {
    $titles[$row['Id']] = $row['title'];
}
$stmt->close();

// 6) Cache each source‐store’s contact_email & name
$storeQ = $conn->prepare("
    SELECT contact_email, shop_name
      FROM shops
     WHERE shop_id = ?
");
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

// 7) Insert each request into DB
$ins = $conn->prepare("
    INSERT INTO transfer_requests
      (productIdentifier, source_store_id, dest_store_id, quantity)
    VALUES (?,?,?,?)
");
foreach ($requests as $r) {
    $ins->bind_param(
        "ssii",
        $r['product'],
        $r['source'],
        $destStoreId,
        $r['quantity']
    );
    $ins->execute();
}
$ins->close();

// 8) PHPMailer helper
function sendMail($to, $toName, $subject, $bodyText) {
    try {
        $mail = new PHPMailer(true);
        $mail->CharSet    = 'UTF-8';
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
        error_log("Mail-fejl til {$to}: " . $mail->ErrorInfo);
    }
}

// 9) Notify requester
$userQ = $conn->prepare("SELECT email, username FROM admin_auth WHERE id = ?");
$userQ->bind_param("i", $_SESSION['user_id']);
$userQ->execute();
$userQ->bind_result($reqEmail, $reqName);
$userQ->fetch();
$userQ->close();

$bodyReq  = "Hej {$reqName},\n\n";
$bodyReq .= "Din overførselsanmodning er modtaget og sat i kø:\n\n";
foreach ($requests as $r) {
    $id    = $r['product'];
    $title = $titles[$id] ?? $id;
    $qty   = $r['quantity'];
    $src   = $storeContacts[$r['source']]['name'] ?? $r['source'];
    $bodyReq .= "- {$title} ({$id}) × {$qty} fra {$src} til {$destStoreName}\n";
}
sendMail(
    $reqEmail,
    $reqName,
    'Din overførselsanmodning er modtaget',
    $bodyReq
);

// 10) Notify each source store
foreach ($requests as $r) {
    $src  = $r['source'];
    $info = $storeContacts[$src] ?? null;
    if (empty($info['email'])) continue;

    $id    = $r['product'];
    $title = $titles[$id] ?? $id;
    $qty   = $r['quantity'];

    $bodySrc  = "Hej {$info['name']},\n\n";
    $bodySrc .= "Butik {$destStoreName} har anmodet om {$title} ({$id}) × {$qty} fra jeres butik.\n\n";
    $bodySrc .= "Log venligst ind for at bekræfte afsendelse.\n\n";
    $bodySrc .= "Venlig hilsen\n";
    $bodySrc .= "{$destStoreName}\n\n";
    $bodySrc .= "Hvis du har spørgsmål, kontakt butikken direkte på {$destStoreEmail} – svar venligst ikke på denne e-mail.";

    sendMail(
        $info['email'],
        $info['name'],
        'Ny overførselsanmodning',
        $bodySrc
    );
}

// 11) Notify destination store once
if (!empty($destStoreEmail)) {
    $bodyDest  = "Hej {$destStoreName},\n\n";
    $bodyDest .= "Du har modtaget følgende overførselsanmodning(er):\n\n";
    foreach ($requests as $r) {
        $id    = $r['product'];
        $title = $titles[$id] ?? $id;
        $qty   = $r['quantity'];
        $src   = $storeContacts[$r['source']]['name'] ?? $r['source'];
        $bodyDest .= "- {$title} ({$id}) × {$qty} fra {$src}\n";
    }
    $bodyDest .= "\nLog ind for at bekræfte modtagelse.\n\n";
    $bodyDest .= "Venlig hilsen\n\n{$FROM_NAME}\n"; // or use FROM_NAME

    sendMail(
        $destStoreEmail,
        $destStoreName,
        'Ny overførselsanmodning modtaget',
        $bodyDest
    );
}

// 12) Notify web admin (web@designcykler.dk)
$webEmail = 'web@designcykler.dk';
$webName  = 'Design Cykler Web';
$bodyWeb  = "Der er afgivet en ny overførselsanmodning af {$reqName}:\n\n";
foreach ($requests as $r) {
    $id    = $r['product'];
    $title = $titles[$id] ?? $id;
    $qty   = $r['quantity'];
    $src   = $storeContacts[$r['source']]['name'] ?? $r['source'];
    $bodyWeb .= "- {$title} ({$id}) × {$qty} fra {$src} til {$destStoreName}\n";
}
$bodyWeb .= "\n--\nDette er en automatisk bekræftelse.";

sendMail(
    $webEmail,
    $webName,
    'Bekræftelse: Overførselsanmodning modtaget',
    $bodyWeb
);

// 13) Final JSON response
echo json_encode([
    'success' => true,
    'message' => 'Sat i kø: ' . count($requests) . ' anmodning(er).'
]);


// 11) Final JSON response
echo json_encode([
    'success' => true,
    'message' => 'Sat i kø: ' . count($requests) . ' anmodning(er).'
]);
