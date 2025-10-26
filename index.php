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
<header class="menu-bar" data-menu-bar>
    <div class="menu-bar__inner">
        <div class="menu-bar__brand">
            <img src="logo.png" alt="Projektlogo" class="brand-logo">
            <span class="brand-name">Modset Übersicht</span>
        </div>
        <nav class="menu-bar__nav" aria-label="Hauptnavigation">
            <a href="#modsets" class="menu-bar__link">Presets</a>
        </nav>
        <div class="menu-bar__actions">
            <button class="menu-bar__settings" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="settingsMenu">
                <span class="visually-hidden">Einstellungen</span>
                <svg class="menu-bar__icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm9-3.5c0-.49-.04-.98-.12-1.46l2.07-1.62a.75.75 0 0 0 .18-.94l-1.97-3.41a.75.75 0 0 0-.9-.34l-2.44.98a7.6 7.6 0 0 0-2.53-1.46l-.37-2.6a.75.75 0 0 0-.74-.64H9.82a.75.75 0 0 0-.74.64l-.37 2.6a7.6 7.6 0 0 0-2.53 1.46l-2.44-.98a.75.75 0 0 0-.9.34L.87 7.98a.75.75 0 0 0 .18.94L3.12 10.5a8.1 8.1 0 0 0 0 2.92l-2.07 1.62a.75.75 0 0 0-.18.94l1.97 3.41c.2.36.63.51 1 .36l2.44-.98c.75.62 1.6 1.12 2.53 1.46l.37 2.6c.06.37.37.64.74.64h3.36c.37 0 .68-.27.74-.64l.37-2.6a7.6 7.6 0 0 0 2.53-1.46l2.44.98c.37.15.8 0 1-.36l1.97-3.41a.75.75 0 0 0-.18-.94l-2.07-1.62c.08-.48.12-.97.12-1.46Z"/>
                </svg>
            </button>
            <time class="menu-bar__clock" data-time-display>--:--</time>
            <div class="menu-bar__dropdown" id="settingsMenu" data-dropdown>
                <ul class="menu-bar__dropdown-list">
                    <li class="menu-bar__dropdown-item">
                        <a href="upload.php" class="menu-bar__dropdown-link">
                            <?= $isAdmin ? 'Modset verwalten' : 'Admin Login &amp; Upload' ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
<div class="app-wrapper">
    <header class="hero">
        <div class="hero-content">
            <h1 class="hero-title">Modsets für Missionen &amp; Events der virtuellen Panzerbrigade 21</h1>
            <p class="hero-subtitle">Hier findest du alle aktuell verfügbaren Presets – die Übersicht unten zeigt dir, was gerade bereitsteht.</p>
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
                            <div class="modset-card__row">
                                <div class="modset-card__info">
                                    <h4 class="modset-card__title"><?= htmlspecialchars($entry['preset_name']) ?></h4>
                                    <div class="modset-card__content">
                                        <p class="modset-card__meta"><span>Datum</span><span><?= $formattedDate ?></span></p>
                                        <?php if (!empty($entry['event'])): ?>
                                            <p class="modset-card__meta"><span>Event</span><span><?= htmlspecialchars($entry['event']) ?></span></p>
                                        <?php endif; ?>
                                        <p class="modset-card__meta"><span>Funkmod</span><span><?= htmlspecialchars($entry['funkmod']) ?></span></p>
                                        <p class="modset-card__meta"><span>Mediksystem</span><span><?= htmlspecialchars($entry['mediksystem']) ?></span></p>
                                    </div>
                                </div>
                                <div class="modset-card__actions">
                                    <a href="<?= htmlspecialchars($entry['html']) ?>" class="btn btn-primary-glass" target="_blank">Vorschau anzeigen</a>
                                    <a href="<?= htmlspecialchars($entry['html']) ?>" class="btn btn-download" download>HTML herunterladen</a>
                                    <?php if ($isAdmin): ?>
                                        <a href="upload.php?edit=<?= urlencode(basename($entry['html'])) ?>" class="btn btn-danger">Bearbeiten</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </main>
</div>
<script src="assets/js/app.js" defer></script>
</body>
</html>
