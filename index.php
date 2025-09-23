<?php
session_start(); // ⬅ Session starten

$uploadDir = 'html_files/';
$entries = [];

// Admin prüfen über Session
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

foreach (glob($uploadDir . '*.json') as $jsonFile) {
    $basename = basename($jsonFile, '.json');
    $htmlPath = $uploadDir . $basename . '.html';

    if (!file_exists($htmlPath)) continue;

    $data = json_decode(file_get_contents($jsonFile), true);
    $data['preset_name'] = $basename;
    $data['html'] = $htmlPath;

    $organizer = $data['organizer'] ?? 'Unbekannt';
    if (!isset($entries[$organizer])) {
        $entries[$organizer] = [];
    }
    $entries[$organizer][] = $data;
}

uksort($entries, function ($a, $b) {
    return ($a === 'Panzerbrigade 21') ? -1 : (($b === 'Panzerbrigade 21') ? 1 : strcasecmp($a, $b));
});

foreach ($entries as $organizer => &$organizerEntries) {
    usort($organizerEntries, fn($a, $b) => strcmp($b['date'], $a['date']));
}
unset($organizerEntries);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Modset Übersicht</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-fixed {
            min-height: 320px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-title {
            font-size: 1.25rem;
            font-weight: bold;
        }
        .category-header {
            margin-top: 4rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #ccc;
            padding-bottom: 0.5rem;
        }
        .btn-group-custom .btn {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body class="bg-dark text-white">
<div class="container py-5">
    <h1 class="mb-5 text-center">Verfügbare Modset Presets</h1>

    <?php foreach ($entries as $organizer => $modsets): ?>
        <div class="category-header">
            <h2><?= htmlspecialchars($organizer) ?></h2>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($modsets as $entry): ?>
                <?php
                    $date = DateTime::createFromFormat('Y-m-d', $entry['date']);
                    $formattedDate = $date ? $date->format('d.m.Y') : htmlspecialchars($entry['date']);
                ?>
                <div class="col">
                    <div class="card bg-secondary text-white h-100 card-fixed shadow rounded-4">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($entry['preset_name']) ?></h5>
                            <p class="card-text"><strong>Datum:</strong> <?= $formattedDate ?></p>
                            <?php if (!empty($entry['event'])): ?>
                                <p class="card-text"><strong>Event:</strong> <?= htmlspecialchars($entry['event']) ?></p>
                            <?php endif; ?>
                            <p class="card-text"><strong>Funkmod:</strong> <?= htmlspecialchars($entry['funkmod']) ?></p>
                            <p class="card-text"><strong>Mediksystem:</strong> <?= htmlspecialchars($entry['mediksystem']) ?></p>

                            <div class="btn-group-custom mt-auto">
                                <a href="<?= htmlspecialchars($entry['html']) ?>" class="btn btn-light" target="_blank">
                                    Vorschau anzeigen
                                </a>
                                <a href="<?= htmlspecialchars($entry['html']) ?>" class="btn btn-outline-light" download>
                                    HTML herunterladen
                                </a>
                                <?php if ($isAdmin): ?>
                                    <a href="upload.php?edit=<?= urlencode($entry['preset_name']) ?>" class="btn btn-warning">
                                        Bearbeiten
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
