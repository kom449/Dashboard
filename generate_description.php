<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(["error" => "Adgang nægtet. Log venligst ind."]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || !isset($body['produktnavn'], $body['produktnummer'], $body['pris'])) {
    http_response_code(400);
    echo json_encode(["error" => "Ugyldigt payload."]);
    exit;
}

$produktnavn   = $body['produktnavn'];
$produktnummer = $body['produktnummer'];
$ean           = $body['ean'] ?? "";
$pris          = $body['pris'];
$beskrivManual = $body['beskriv_manual'] ?? "";
$csvText       = $body['csv_data'] ?? "";

$matchedRow = [];
if (!empty($csvText)) {
    $lines = preg_split('/\r\n|\r|\n/', trim($csvText));
    if (count($lines) > 1) {
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, $csvText);
        rewind($fh);

        $header = fgetcsv($fh, 0, ",");
        $cols = array_map('trim', $header);

        $nummerIndex = array_search('Produktnummer', $cols);
        if ($nummerIndex !== false) {
            while (($row = fgetcsv($fh, 0, ",")) !== false) {
                $trimmed = array_map('trim', $row);
                if (isset($trimmed[$nummerIndex]) && $trimmed[$nummerIndex] === $produktnummer) {
                    foreach ($cols as $i => $colName) {
                        $matchedRow[$colName] = $trimmed[$i] ?? "";
                    }
                    break;
                }
            }
        }
        fclose($fh);
    }
}

// Byg prompt til Assistants‐API. Vi beder om JSON‐output med de fire nøgler.
$promptLines = [
    "Du er en AI‐assistent, der laver produktbeskrivelser på dansk. Ud fra de følgende data skal du returnere et JSON‐objekt med nøglerne:",
    "  - kort_beskrivelse",
    "  - udvidet_beskrivelse",
    "  - title_tag",
    "  - metatag_beskrivelse",
    "",
    "Produktinformation fra formular:",
    "- Produktnavn: $produktnavn",
    "- Produktnummer: $produktnummer",
    "- EAN / Stregkode: $ean",
    "- Pris: kr. $pris",
];

if (!empty($beskrivManual)) {
    $promptLines[] = "- Beskrivelse (manuel): $beskrivManual";
}

if (!empty($matchedRow)) {
    $promptLines[] = "\nFundet i CSV (matchende Produktnummer):";
    foreach ($matchedRow as $col => $val) {
        $promptLines[] = "- $col: $val";
    }
} else {
    $promptLines[] = "\nIngen matchende række fundet i CSV for Produktnummer.";
}

// Tilføj instruktion om output‐formatet
$promptLines[] = "\nSkriv nu en kort beskrivelse (2–3 sætninger), en udvidet beskrivelse (4–6 sætninger), samt en Title Tag (under 60 tegn) og en Metatag beskrivelse (under 155 tegn).";
$promptLines[] = "DU SKAL KUN SVARE MED ET GELDELIGT JSON‐OBJEKT. INGEN ANDRE TEKSTUDBRUD.";

$userMessage = implode("\n", $promptLines);

$assistant_id = getenv('OPENAI_ASSISTANT_ID');
$apiKey       = getenv('OPENAI_API_KEY');
if (!$assistant_id || !$apiKey) {
    http_response_code(500);
    echo json_encode(["error" => "Assistant ID eller API‐nøgle mangler på serveren."]);
    exit;
}

$baseUrl = "https://api.openai.com/v1";
$headers = [
    "Authorization: Bearer {$apiKey}",
    "Content-Type: application/json",
    "OpenAI-Beta: assistans=v1"
];

function curl_setup($url, $headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    return $ch;
}

$threadCh = curl_setup("$baseUrl/threads", $headers);
curl_setopt($threadCh, CURLOPT_POST, true);
curl_setopt($threadCh, CURLOPT_POSTFIELDS, json_encode([]));
$threadResp = curl_exec($threadCh);
if (curl_errno($threadCh)) {
    http_response_code(500);
    echo json_encode(["error" => "Thread‐oprettelse fejlede: " . curl_error($threadCh)]);
    curl_close($threadCh);
    exit;
}
curl_close($threadCh);

$threadData = json_decode($threadResp, true);
if (!isset($threadData['id'])) {
    http_response_code(500);
    echo json_encode(["error" => "Kunne ikke oprette thread."]);
    exit;
}
$thread_id = $threadData['id'];

$msgBody = json_encode([
    "role"    => "user",
    "content" => $userMessage
]);
$msgCh = curl_setup("$baseUrl/threads/$thread_id/messages", $headers);
curl_setopt($msgCh, CURLOPT_POST, true);
curl_setopt($msgCh, CURLOPT_POSTFIELDS, $msgBody);
$msgResp = curl_exec($msgCh);
if (curl_errno($msgCh)) {
    http_response_code(500);
    echo json_encode(["error" => "Kunne ikke tilføje brugerbesked: " . curl_error($msgCh)]);
    curl_close($msgCh);
    exit;
}
curl_close($msgCh);

$runBody = json_encode([ "assistant_id" => $assistant_id ]);
$runCh = curl_setup("$baseUrl/threads/$thread_id/runs", $headers);
curl_setopt($runCh, CURLOPT_POST, true);
curl_setopt($runCh, CURLOPT_POSTFIELDS, $runBody);
$runResp = curl_exec($runCh);
if (curl_errno($runCh)) {
    http_response_code(500);
    echo json_encode(["error" => "Kunne ikke oprette run: " . curl_error($runCh)]);
    curl_close($runCh);
    exit;
}
curl_close($runCh);

$runData = json_decode($runResp, true);
if (!isset($runData['id'])) {
    http_response_code(500);
    echo json_encode(["error" => "Kunne ikke starte run."]);
    exit;
}
$run_id = $runData['id'];

$status = $runData['status'];
while ($status !== "completed") {
    usleep(500000); // 0.5 s
    $pollCh = curl_setup("$baseUrl/threads/$thread_id/runs/$run_id", $headers);
    curl_setopt($pollCh, CURLOPT_HTTPGET, true);
    $pollResp = curl_exec($pollCh);
    curl_close($pollCh);
    if (!$pollResp) {
        break;
    }
    $pollData = json_decode($pollResp, true);
    $status = $pollData['status'] ?? 'failed';
    if ($status === "failed") {
        http_response_code(500);
        echo json_encode(["error" => "Assistant run fejlede."]);
        exit;
    }
}

$fetchCh = curl_setup("$baseUrl/threads/$thread_id/messages", $headers);
curl_setopt($fetchCh, CURLOPT_HTTPGET, true);
$fetchResp = curl_exec($fetchCh);
curl_close($fetchCh);

$messages = json_decode($fetchResp, true);
if (!isset($messages['data']) || !is_array($messages['data'])) {
    http_response_code(500);
    echo json_encode(["error" => "Kunne ikke hente beskeder."]);
    exit;
}

$assistantReply = "";
foreach (array_reverse($messages['data']) as $msg) {
    if (isset($msg['role']) && $msg['role'] === "assistant") {
        $assistantReply = $msg['content'] ?? "";
        break;
    }
}

if (empty($assistantReply)) {
    http_response_code(500);
    echo json_encode(["error" => "Ingen svar fra assistent fundet."]);
    exit;
}

$aiJson = json_decode($assistantReply, true);
if (json_last_error() !== JSON_ERROR_NONE || 
    !isset($aiJson['kort_beskrivelse'], $aiJson['udvidet_beskrivelse'], $aiJson['title_tag'], $aiJson['metatag_beskrivelse'])) {
    http_response_code(500);
    echo json_encode([
      "error" => "Uventet AI‐respons. Forventet JSON med fire nøgler."
    ]);
    exit;
}

echo json_encode([
    "kort_beskrivelse"     => $aiJson['kort_beskrivelse'],
    "udvidet_beskrivelse"  => $aiJson['udvidet_beskrivelse'],
    "title_tag"            => $aiJson['title_tag'],
    "metatag_beskrivelse"  => $aiJson['metatag_beskrivelse']
]);
exit;
