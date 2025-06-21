<?php
// get_stock.php
include 'cors.php';
session_start();
include 'db.php';
header('Content-Type: application/json');

// 1) AUTHORIZATION
$store_manager = !empty($_SESSION['is_store_manager']);
$admin         = !empty($_SESSION['is_admin']);
if (empty($_SESSION['logged_in']) || (! $store_manager && ! $admin)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Adgang nægtet.']);
    exit();
}

// 2) INPUT VALIDATION
$shopId = filter_input(INPUT_GET, 'shop_id', FILTER_VALIDATE_INT);
$search = trim($_GET['search'] ?? '');
if (! $shopId || strlen($search) < 2) {
    // too short or no shop selected → no API hit
    echo json_encode(['success'=>true,'items'=>[]]);
    exit();
}

// 3) FETCH CLOUDRETAIL TOKEN
// adjust this query if you have multiple tokens / a service column
$tokenStmt = $conn->prepare("SELECT token FROM tokens LIMIT 1");
$tokenStmt->execute();
$tokenStmt->bind_result($apiToken);
$tokenStmt->fetch();
$tokenStmt->close();

if (empty($apiToken)) {
    error_log("get_stock.php: no API token found in tokens table");
    echo json_encode(['success'=>false,'message'=>'Server-fejl (ingen token).']);
    exit();
}

// 4) BUILD CLOUDRETAIL PAYLOAD
$payload = [
    "includeOverallEntryCount"       => true,
    "offset"                         => 0,
    "count"                          => 20,
    "propertiesToLoad"               => ["value" => 1859],
    "pageEntryPropertiesToLoad"      => ["value" => 2751],
    "organizationalUnitId"           => ["value" => -1],
    "useAdditionalDisplayNameSearch" => ["value" => true],
    "ignoreIncludedInSearch"         => ["value" => true],
    "enableStateOptions"             => ["value" => 1],
    "availabilityOptionsTotal"       => ["value" => 7],
    "lastOperationDatesFilter"       => ["value" => new stdClass()],
    "searchPattern"                  => ["value" => $search],
    "includeShadow"                  => ["value" => true],
    "sortInfo"                       => [["property" => 28, "sortDirection" => 2]],
];

// 5) MAKE THE cURL CALL
$ch = curl_init('https://designcykler.cloudretailsystems.dk/api/inventory/product/getproductpage');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => [
        'accept: text/plain',
        "Authorization: {$apiToken}",
        'Content-Type: application/json-patch+json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 6) ERROR HANDLING
if ($response === false || $httpCode !== 200) {
    error_log("get_stock.php: API HTTP {$httpCode} — {$response}");
    echo json_encode(['success'=>false,'message'=>"API-fejl (HTTP {$httpCode})"]);
    exit();
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("get_stock.php: JSON decode error: ".json_last_error_msg());
    echo json_encode(['success'=>false,'message'=>'Ugyldigt svar fra API.']);
    exit();
}

// if CloudRetail itself returned an error
if (!empty($data['error']) || !empty($data['errorOrValue']['error'])) {
    $err = $data['error'] 
         ?? $data['errorOrValue']['error'] 
         ?? 'Ukendt API-fejl';
    error_log("get_stock.php: CloudRetail error: {$err}");
    echo json_encode(['success'=>false,'message'=>$err]);
    exit();
}

// 7) NORMALIZE THE RESULTS
$items = [];
if (!empty($data['errorOrValue']['value']['entries']) 
    && is_array($data['errorOrValue']['value']['entries'])) {
    foreach ($data['errorOrValue']['value']['entries'] as $entry) {
        if (isset($entry['product'])) {
            $prod = $entry['product'];
            $items[] = [
                'productIdentifier' => $prod['identifier']  ?? '',
                'title'             => $prod['displayName'] ?? '',
            ];
        }
    }
}

// 8) RETURN
echo json_encode([
    'success' => true,
    'items'   => $items
]);
