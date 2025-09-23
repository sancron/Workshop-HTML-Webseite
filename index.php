<?php
session_start();

$uploadDir = 'html_files/';
$entries = [];

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
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-body">
<div class="app-wrapper">
    <header class="hero">
        <nav class="hero-nav">
            <div class="brand">
                <img src="logo.png" alt="Projektlogo" class="brand-logo">
                <span class="brand-name">Modset Übersicht</span>
            </div>
            <div class="nav-actions">
                <a href="#modsets" class="nav-link">Presets</a>
                <?php if ($isAdmin): ?>
                    <a href="upload.php" class="btn btn-glass">Modset hochladen</a>
                <?php endif; ?>
            </div>
        </nav>
        <div class="hero-content">
            <h1 class="hero-title">Deine zentrale Sammlung für Arma-Modsets</h1>
            <p class="hero-subtitle">Durchstöbere kuratierte Presets, erfahre alle Details und starte direkt in die nächste Mission.</p>
            <div class="hero-actions">
                <a href="#modsets" class="btn btn-primary-glass">Modsets entdecken</a>
                <a href="html_files/" class="btn btn-secondary-glass">Alle Dateien</a>
            </div>
        </div>
    </header>

    <main id="modsets" class="app-main">
        <h2 class="visually-hidden">Modset Presets</h2>
        <?php foreach ($entries as $organizer => $modsets): ?>
            <section class="modset-section">
                <header class="section-header">
                    <h3 class="section-title"><?= htmlspecialchars($organizer) ?></h3>
                </header>

                <div class="modset-grid">
                    <?php foreach ($modsets as $entry): ?>
                        <?php
                            $date = DateTime::createFromFormat('Y-m-d', $entry['date']);
                            $formattedDate = $date ? $date->format('d.m.Y') : htmlspecialchars($entry['date']);
                        ?>
                        <article class="modset-card glass-card">
                            <div class="modset-card__content">
                                <h4 class="modset-card__title"><?= htmlspecialchars($entry['preset_name']) ?></h4>
                                <p class="modset-card__meta"><span>Datum</span><span><?= $formattedDate ?></span></p>
                                <?php if (!empty($entry['event'])): ?>
                                    <p class="modset-card__meta"><span>Event</span><span><?= htmlspecialchars($entry['event']) ?></span></p>
                                <?php endif; ?>
                                <p class="modset-card__meta"><span>Funkmod</span><span><?= htmlspecialchars($entry['funkmod']) ?></span></p>
                                <p class="modset-card__meta"><span>Mediksystem</span><span><?= htmlspecialchars($entry['mediksystem']) ?></span></p>
                            </div>
                            <div class="modset-card__actions">
                                <a href="<?= htmlspecialchars($entry['html']) ?>" class="btn btn-primary-glass" target="_blank">Vorschau anzeigen</a>
                                <a href="<?= htmlspecialchars($entry['html']) ?>" class="btn btn-secondary-glass" download>HTML herunterladen</a>
                                <?php if ($isAdmin): ?>
                                    <a href="upload.php?edit=<?= urlencode($entry['preset_name']) ?>" class="btn btn-glass">Bearbeiten</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
</div>
</body>
</html>
