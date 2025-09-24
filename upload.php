<?php
$debugValue = $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG');
$debugEnabled = false;

if ($debugValue !== false && $debugValue !== null) {
    $debugEnabled = filter_var($debugValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
}

if ($debugEnabled) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}
session_start();

$adminPasswordHash = '';

if (!empty($_SERVER['ADMIN_PASSWORD_HASH'])) {
    $adminPasswordHash = (string) $_SERVER['ADMIN_PASSWORD_HASH'];
} elseif (($envHash = getenv('ADMIN_PASSWORD_HASH'))) {
    $adminPasswordHash = $envHash;
} else {
    $credentialFiles = [];

    $configuredFile = $_SERVER['CREDENTIALS_FILE'] ?? getenv('CREDENTIALS_FILE');
    if (!empty($configuredFile)) {
        $credentialFiles[] = $configuredFile;
    }

    $credentialFiles[] = dirname(__DIR__) . '/config/credentials.php';

    $localFallback = __DIR__ . '/config/credentials.php';
    if (!in_array($localFallback, $credentialFiles, true)) {
        $credentialFiles[] = $localFallback;
    }

    foreach ($credentialFiles as $credentialsPath) {
        if (!$credentialsPath || !is_readable($credentialsPath)) {
            continue;
        }

        $credentials = require $credentialsPath;
        if (is_array($credentials) && !empty($credentials['ADMIN_PASSWORD_HASH'])) {
            $adminPasswordHash = (string) $credentials['ADMIN_PASSWORD_HASH'];
            break;
        }
    }
}

$adminPasswordHash = trim($adminPasswordHash);
$loginError = '';

if (isset($_POST['password'])) {
    if ($adminPasswordHash && password_verify($_POST['password'], $adminPasswordHash)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['is_admin'] = true;
    } else {
        $loginError = 'Ungültiges Passwort.';
    }
}

if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-body app-body--centered">
    <div class="app-wrapper app-wrapper--narrow">
        <header class="page-header">
            <a href="index.php" class="brand page-header__brand-link">
                <img src="logo.png" alt="Projektlogo" class="brand-logo">
                <span class="brand-name">Modset Übersicht</span>
            </a>
        </header>
        <main>
            <div class="glass-card form-card">
                <div>
                    <h1 class="form-title">Admin Login</h1>
                    <p class="form-description">Melden Sie sich an, um Presets zu verwalten und neue Dateien hochzuladen.</p>
                </div>
                <form method="post" class="section-split">
                    <?php if ($loginError): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Einloggen</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
<?php
    exit;
}

$uploadDir = 'html_files/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$existingFiles = array_map('basename', glob($uploadDir . '*.html'));

function normalizeHtmlFilename(?string $value, array $allowedFiles, string $baseDir): string
{
    $value = (string) $value;
    if ($value === '') {
        return '';
    }

    $base = basename($value);
    if ($base === '') {
        return '';
    }

    $filename = pathinfo($base, PATHINFO_FILENAME);
    if ($filename === '') {
        return '';
    }

    $normalized = $filename . '.html';

    if ($allowedFiles && !in_array($normalized, $allowedFiles, true)) {
        return '';
    }

    if (!is_file($baseDir . $normalized)) {
        return '';
    }

    return $normalized;
}

$existingParam = $_POST['existing_file'] ?? ($_GET['edit'] ?? '');
$existingFile = normalizeHtmlFilename($existingParam, $existingFiles, $uploadDir);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_POST['existing_file'] = $existingFile;
}
$loadedData = [
    'preset_name' => '',
    'organizer' => '',
    'date' => '',
    'event' => '',
    'funkmod' => '',
    'mediksystem' => ''
];
$message = '';
$messageType = 'success';

if (isset($_POST['delete']) && $existingFile) {
    $fileToDelete = $existingFile;
    $htmlPath = $uploadDir . $fileToDelete;
    $jsonPath = $uploadDir . pathinfo($fileToDelete, PATHINFO_FILENAME) . '.json';

    if (file_exists($htmlPath)) unlink($htmlPath);
    if (file_exists($jsonPath)) unlink($jsonPath);

    $message = "Eintrag <strong>$fileToDelete</strong> erfolgreich gelöscht.";
    $existingFile = '';
    if (($key = array_search($fileToDelete, $existingFiles, true)) !== false) {
        unset($existingFiles[$key]);
        $existingFiles = array_values($existingFiles);
    }
    $loadedData = array_fill_keys(array_keys($loadedData), '');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preset_name'], $_POST['organizer'])) {
    $presetName = htmlspecialchars($_POST['preset_name']);
    $organizer = htmlspecialchars($_POST['organizer']);
    $date = htmlspecialchars($_POST['date']);
    $event = htmlspecialchars($_POST['event'] ?? '');
    $funkmod = htmlspecialchars($_POST['funkmod']);
    $mediksystem = htmlspecialchars($_POST['mediksystem']);
    $fileInfo = $_FILES['html_file'] ?? ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE];
    $htmlFile = $fileInfo['name'] ?? '';
    $tmpName = $fileInfo['tmp_name'] ?? '';
    $fileError = $fileInfo['error'] ?? UPLOAD_ERR_NO_FILE;
    $hasUploadedFile = !empty($tmpName) && $fileError === UPLOAD_ERR_OK;
    $isEdit = $existingFile !== '';

    if ($fileError !== UPLOAD_ERR_OK && $fileError !== UPLOAD_ERR_NO_FILE) {
        $message = 'Die HTML-Datei konnte nicht hochgeladen werden. Bitte versuche es erneut.';
        $messageType = 'error';
    } elseif (!$isEdit && !$hasUploadedFile) {
        $message = 'Für neue Presets muss eine HTML-Datei hochgeladen werden.';
        $messageType = 'error';
    } else {
        $finalFileName = $isEdit ? $existingFile : basename($htmlFile);

        if ($hasUploadedFile && $finalFileName !== '') {
            move_uploaded_file($tmpName, $uploadDir . $finalFileName);
        }

        if ($finalFileName !== '') {
            $jsonData = json_encode([
                'organizer' => $organizer,
                'date' => $date,
                'event' => $event,
                'funkmod' => $funkmod,
                'mediksystem' => $mediksystem
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            file_put_contents($uploadDir . pathinfo($finalFileName, PATHINFO_FILENAME) . '.json', $jsonData);

            $message = $isEdit ? 'Eintrag erfolgreich aktualisiert.' : 'Neuer Eintrag erfolgreich gespeichert.';
            $existingFile = $finalFileName;
            if (!in_array($finalFileName, $existingFiles, true)) {
                $existingFiles[] = $finalFileName;
            }
            $messageType = 'success';
        } else {
            $message = 'Es konnte kein gültiger Dateiname ermittelt werden.';
            $messageType = 'error';
        }
    }
}

if ($existingFile) {
    $jsonPath = $uploadDir . pathinfo($existingFile, PATHINFO_FILENAME) . '.json';
    if (file_exists($jsonPath)) {
        $jsonData = json_decode(file_get_contents($jsonPath), true);
        $loadedData = array_merge($loadedData, $jsonData);
    }
    $loadedData['preset_name'] = pathinfo($existingFile, PATHINFO_FILENAME);
}

$isPresetLocked = $existingFile !== '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Modset Upload / Bearbeiten</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/app.css">
    <script>
        function loadSelectedFile(sel) {
            if (sel.value) {
                window.location.href = "?edit=" + encodeURIComponent(sel.value);
            } else {
                window.location.href = "upload.php";
            }
        }
    </script>
</head>
<body class="app-body">
<div class="app-wrapper">
    <header class="page-header">
        <a href="index.php" class="brand page-header__brand-link">
            <img src="logo.png" alt="Projektlogo" class="brand-logo">
            <span class="brand-name">Modset Übersicht</span>
        </a>
        <div class="nav-actions">
            <a href="index.php" class="btn btn-secondary">Zurück zur Übersicht</a>
        </div>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="glass-card form-card">
        <div class="section-split">
            <div>
                <h1 class="form-title">Modset Upload &amp; Verwaltung</h1>
                <p class="form-description">Lade neue Presets hoch oder aktualisiere bestehende Einträge für deine Organisation.</p>
            </div>

            <form method="post" enctype="multipart/form-data" class="section-split">
                <div class="form-group">
                    <label for="existing_file">Existierenden Eintrag bearbeiten</label>
                    <select name="existing_file" id="existing_file" class="form-select" onchange="loadSelectedFile(this)">
                        <option value="">-- Neue Datei --</option>
                        <?php foreach ($existingFiles as $basename): ?>
                            <option value="<?= htmlspecialchars($basename) ?>" <?= ($basename === $existingFile ? 'selected' : '') ?>>
                                <?= htmlspecialchars($basename) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid form-grid--two">
                    <div class="form-group">
                        <label for="preset_name">
                            Preset-Name (Dateiname)
                            <?php if ($isPresetLocked): ?>
                                <span class="field-status field-status--locked">Gesperrt</span>
                            <?php endif; ?>
                        </label>
                        <input type="text" name="preset_name" id="preset_name" class="form-control<?= $isPresetLocked ? ' is-readonly' : '' ?>" required
                               value="<?= htmlspecialchars($loadedData['preset_name']) ?>"
                               <?= $isPresetLocked ? 'readonly aria-describedby="preset_name_hint"' : '' ?>>
                        <?php if ($isPresetLocked): ?>
                            <p id="preset_name_hint" class="form-hint form-hint--readonly">
                                Der Preset-Name wird beim Bearbeiten automatisch aus dem Dateinamen übernommen und kann hier nicht geändert werden.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="organizer">Veranstalter</label>
                        <input type="text" name="organizer" id="organizer" class="form-control" required
                               value="<?= htmlspecialchars($loadedData['organizer']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="date">Datum</label>
                        <input type="date" name="date" id="date" class="form-control" required
                               value="<?= htmlspecialchars($loadedData['date']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="event">Event (optional)</label>
                        <input type="text" name="event" id="event" class="form-control"
                               value="<?= htmlspecialchars($loadedData['event']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="funkmod">Funkmod</label>
                        <select name="funkmod" id="funkmod" class="form-select" required>
                            <option value="">-- auswählen --</option>
                            <option value="ACRE" <?= $loadedData['funkmod'] === 'ACRE' ? 'selected' : '' ?>>ACRE</option>
                            <option value="TFAR" <?= $loadedData['funkmod'] === 'TFAR' ? 'selected' : '' ?>>TFAR</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="mediksystem">Mediksystem</label>
                        <select name="mediksystem" id="mediksystem" class="form-select" required>
                            <option value="">-- auswählen --</option>
                            <option value="Vanilla" <?= $loadedData['mediksystem'] === 'Vanilla' ? 'selected' : '' ?>>Vanilla</option>
                            <option value="ACE" <?= $loadedData['mediksystem'] === 'ACE' ? 'selected' : '' ?>>ACE</option>
                            <option value="KAT" <?= $loadedData['mediksystem'] === 'KAT' ? 'selected' : '' ?>>KAT</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="html_file">HTML-Datei (optional zum Ersetzen)</label>
                    <input type="file" name="html_file" id="html_file" class="form-control">
                    <div class="alert alert-info">Für neue Presets ist eine HTML-Datei zwingend erforderlich.</div>
                    <p class="status-text">Lade nur eine Datei hoch, wenn du den bestehenden Inhalt ersetzen möchtest.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Speichern</button>
                    <?php if ($existingFile): ?>
                        <button type="submit" name="delete" value="1" class="btn btn-danger" onclick="return confirm('Eintrag wirklich löschen?')">
                            Löschen
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
