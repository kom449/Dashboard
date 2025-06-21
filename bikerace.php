<?php
// bikerace.php
session_start();
include __DIR__ . '/../cors.php';

// only logged-in users:
if (empty($_SESSION['logged_in'])) {
    echo "<p>Adgang nægtet.</p>";
    exit();
}

// path to your JSON file
$jsonFile = __DIR__ . '/cms/races.json';

// handle save (raw JSON POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = file_get_contents('php://input');
    $data    = json_decode($payload, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        file_put_contents(
            $jsonFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    }
    exit;
}

// on GET, load existing races
$races = [];
if (file_exists($jsonFile)) {
    $raw = file_get_contents($jsonFile);
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $races = $decoded;
    }
}
?>

<div id="bikerace-cms" class="tab" style="display:none;">
    <h1>Bikerace CMS</h1>

    <section id="race-management">
        <table id="racesTable">
            <thead>
                <tr>
                    <th>Dato</th>
                    <th>Løb</th>
                    <th>By</th>
                    <th>Type</th>
                    <th>Handling</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($races as $race): ?>
                    <tr>
                        <td>
                            <input
                                type="text"
                                class="date"
                                value="<?= htmlspecialchars($race['date']) ?>"
                                placeholder="DD.MM.YY">
                        </td>

                        <td class="race-cell">
                            <input
                                type="text"
                                class="race"
                                value="<?= htmlspecialchars($race['race']) ?>"
                                placeholder="Løbsnavn">
                            <input
                                type="text"
                                class="raceLink"
                                value="<?= htmlspecialchars($race['link'] ?? '') ?>"
                                placeholder="Link til løb (https://…)">
                        </td>

                        <td>
                            <input
                                type="text"
                                class="city"
                                value="<?= htmlspecialchars($race['city']) ?>"
                                placeholder="By">
                        </td>

                        <td>
                            <input
                                type="text"
                                class="type"
                                value="<?= htmlspecialchars($race['type']) ?>"
                                placeholder="Type">
                        </td>

                        <td>
                            <button type="button" class="deleteBtn action-btn">−</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>

        <div class="cms-actions">
            <button type="button" id="addRowBtn" class="action-btn">+ Tilføj løb</button>
            <button type="button" id="saveBtn" class="action-btn primary">Gem ændringer</button>
        </div>
    </section>
</div>