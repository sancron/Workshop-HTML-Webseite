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
$masterPasswordHash = '';
$credentialsPathUsed = null;
$credentialsData = [];
$adminHashSource = '';
$masterHashSource = '';

$serverAdminHash = $_SERVER['ADMIN_PASSWORD_HASH'] ?? null;
if (!empty($serverAdminHash)) {
    $adminPasswordHash = trim((string) $serverAdminHash);
    $adminHashSource = 'server';
} elseif (($envHash = getenv('ADMIN_PASSWORD_HASH'))) {
    $adminPasswordHash = trim($envHash);
    $adminHashSource = 'env';
}

$serverMasterHash = $_SERVER['MASTER_PASSWORD_HASH'] ?? null;
if (empty($serverMasterHash)) {
    $serverMasterHash = getenv('MASTER_PASSWORD_HASH') ?: null;
}
if (!empty($serverMasterHash)) {
    $masterPasswordHash = trim((string) $serverMasterHash);
    $masterHashSource = 'env';
}

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
    if (!is_array($credentials)) {
        continue;
    }

    if ($credentialsPathUsed === null) {
        $credentialsPathUsed = $credentialsPath;
        $credentialsData = $credentials;
    }

    if ($adminHashSource !== 'file' && !empty($credentials['ADMIN_PASSWORD_HASH'])) {
        $adminPasswordHash = trim((string) $credentials['ADMIN_PASSWORD_HASH']);
        $adminHashSource = 'file';
        $credentialsPathUsed = $credentialsPath;
        $credentialsData = $credentials;
    }

    if ($masterPasswordHash === '' && !empty($credentials['MASTER_PASSWORD_HASH'])) {
        $masterPasswordHash = trim((string) $credentials['MASTER_PASSWORD_HASH']);
        $masterHashSource = 'file';
        if ($credentialsPathUsed === null) {
            $credentialsPathUsed = $credentialsPath;
            $credentialsData = $credentials;
        }
    }

    if ($adminHashSource === 'file' && ($masterPasswordHash !== '' || array_key_exists('MASTER_PASSWORD_HASH', $credentials))) {
        break;
    }
}

$loginError = '';
$isAuthenticated = !empty($_SESSION['authenticated']);
$isMasterAdmin = !empty($_SESSION['is_master_admin']);

$formType = $_POST['form_type'] ?? '';

if ($formType === 'login' && isset($_POST['password'])) {
    $password = (string) $_POST['password'];
    if ($masterPasswordHash !== '' && password_verify($password, $masterPasswordHash)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['is_admin'] = true;
        $_SESSION['is_master_admin'] = true;
        $isAuthenticated = true;
        $isMasterAdmin = true;
    } elseif ($adminPasswordHash && password_verify($password, $adminPasswordHash)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['is_admin'] = true;
        $_SESSION['is_master_admin'] = false;
        $isAuthenticated = true;
        $isMasterAdmin = false;
    } else {
        $loginError = 'Ungültiges Passwort.';
    }
}

$isAuthenticated = !empty($_SESSION['authenticated']);
$isMasterAdmin = !empty($_SESSION['is_master_admin']);

$passwordChangeMessage = '';
$passwordChangeType = 'info';
$requestedAction = $_GET['action'] ?? '';
$showPasswordForm = $isMasterAdmin && $requestedAction === 'change-password';
$canChangePassword = $isMasterAdmin && $adminHashSource === 'file' && $credentialsPathUsed;

if ($requestedAction === 'change-password' && !$isMasterAdmin && $isAuthenticated) {
    http_response_code(403);
    $passwordChangeMessage = 'Du bist nicht berechtigt, das Passwort zu ändern.';
    $passwordChangeType = 'error';
}

if ($formType === 'change_password') {
    $showPasswordForm = true;
    if (!$isAuthenticated || !$isMasterAdmin) {
        http_response_code(403);
        $passwordChangeMessage = 'Nicht autorisiert.';
        $passwordChangeType = 'error';
    } else {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $errors = [];

        if ($masterPasswordHash === '' || !password_verify($currentPassword, $masterPasswordHash)) {
            $errors[] = 'Das aktuelle Master-Passwort ist ungültig.';
        }

        if ($newPassword === '') {
            $errors[] = 'Bitte gib ein neues Passwort ein.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        }

        if ($confirmPassword === '') {
            $errors[] = 'Bitte bestätige das neue Passwort.';
        } elseif ($confirmPassword !== $newPassword) {
            $errors[] = 'Die Passwortbestätigung stimmt nicht überein.';
        }

        if (!$credentialsPathUsed || $adminHashSource !== 'file') {
            $errors[] = 'Die Zugangsdaten werden nicht aus einer Datei geladen und können nicht automatisch aktualisiert werden.';
        } else {
            $credentialsDir = dirname($credentialsPathUsed);
            if (file_exists($credentialsPathUsed)) {
                if (!is_writable($credentialsPathUsed) || !is_writable($credentialsDir)) {
                    $errors[] = 'Die Credentials-Datei ist schreibgeschützt oder das Zielverzeichnis ist nicht beschreibbar.';
                }
            } elseif (!file_exists($credentialsPathUsed) && (!is_dir($credentialsDir) || !is_writable($credentialsDir))) {
                $errors[] = 'Das Credentials-Verzeichnis ist nicht beschreibbar.';
            }
        }

        if (!$errors) {
            $currentCredentials = require $credentialsPathUsed;
            if (!is_array($currentCredentials)) {
                $errors[] = 'Die Credentials-Datei hat ein ungültiges Format.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $currentCredentials['ADMIN_PASSWORD_HASH'] = $newHash;
                if (!array_key_exists('MASTER_PASSWORD_HASH', $currentCredentials) && $masterPasswordHash !== '') {
                    $currentCredentials['MASTER_PASSWORD_HASH'] = $masterPasswordHash;
                }

                if (!writeCredentialsFile($credentialsPathUsed, $currentCredentials)) {
                    $errors[] = 'Die Credentials-Datei konnte nicht aktualisiert werden.';
                } else {
                    $passwordChangeMessage = 'Das Admin-Passwort wurde erfolgreich aktualisiert.';
                    $passwordChangeType = 'success';
                    $adminPasswordHash = $newHash;
                    $credentialsData = $currentCredentials;
                    $canChangePassword = true;
                }
            }
        }

        if ($errors) {
            $passwordChangeMessage = implode("\n", $errors);
            $passwordChangeType = 'error';
        }
    }
}

if ($showPasswordForm && !$canChangePassword && $passwordChangeMessage === '') {
    $passwordChangeMessage = 'Das Passwort kann nicht geändert werden, da die Anwendung aktuell keine Credentials-Datei verwendet.';
    $passwordChangeType = 'info';
}

if (!$isAuthenticated) {
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
                    <input type="hidden" name="form_type" value="login">
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

$knownOrganizers = [];
foreach ($existingFiles as $htmlFile) {
    $jsonPath = $uploadDir . pathinfo($htmlFile, PATHINFO_FILENAME) . '.json';
    if (!is_file($jsonPath)) {
        continue;
    }

    $jsonData = json_decode(file_get_contents($jsonPath), true);
    if (!is_array($jsonData)) {
        continue;
    }

    $organizerName = trim((string)($jsonData['organizer'] ?? ''));
    if ($organizerName !== '') {
        $knownOrganizers[$organizerName] = true;
    }
}

natcasesort($existingFiles);
$existingFiles = array_values($existingFiles);
$knownOrganizers = array_keys($knownOrganizers);
natcasesort($knownOrganizers);
$knownOrganizers = array_values($knownOrganizers);

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

function writeCredentialsFile(string $path, array $data): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) || !is_writable($directory)) {
        return false;
    }

    $tempFile = tempnam($directory, 'cred_');
    if ($tempFile === false) {
        return false;
    }

    $content = "<?php\nreturn " . var_export($data, true) . ";\n";

    if (file_put_contents($tempFile, $content, LOCK_EX) === false) {
        @unlink($tempFile);
        return false;
    }

    @chmod($tempFile, 0640);

    if (!@rename($tempFile, $path)) {
        @unlink($tempFile);
        return false;
    }

    @chmod($path, 0640);

    return true;
}

$existingParam = $_POST['existing_file'] ?? ($_GET['edit'] ?? '');
$existingFile = normalizeHtmlFilename($existingParam, $existingFiles, $uploadDir);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formType === 'modset') {
    $_POST['existing_file'] = $existingFile;
}
$loadedData = [
    'organizer' => '',
    'date' => '',
    'event' => '',
    'funkmod' => '',
    'medical_system' => ''
];
$message = '';
$messageType = 'success';

if ($formType === 'modset' && isset($_POST['delete']) && $existingFile) {
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
} elseif ($formType === 'modset' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['organizer'])) {
    $organizer = htmlspecialchars(trim((string)$_POST['organizer']));
    $date = htmlspecialchars(trim((string)($_POST['date'] ?? '')));
    $event = htmlspecialchars(trim((string)($_POST['event'] ?? '')));
    $funkmod = htmlspecialchars(trim((string)($_POST['funkmod'] ?? '')));
    $medicalSystem = htmlspecialchars(trim((string)($_POST['medical_system'] ?? ($_POST['mediksystem'] ?? ''))));
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
    } elseif ($organizer === '' || $date === '' || $funkmod === '' || $medicalSystem === '') {
        $message = 'Bitte fülle alle Pflichtfelder aus.';
        $messageType = 'error';
    } else {
        $finalFileName = $isEdit ? $existingFile : basename($htmlFile);

        if ($hasUploadedFile && $finalFileName !== '') {
            move_uploaded_file($tmpName, $uploadDir . $finalFileName);
        }

        if ($finalFileName !== '') {
            $payload = [
                'organizer' => $organizer,
                'date' => $date,
                'event' => $event,
                'funkmod' => $funkmod,
                'medical_system' => $medicalSystem,
                'mediksystem' => $medicalSystem
            ];

            $jsonData = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            file_put_contents($uploadDir . pathinfo($finalFileName, PATHINFO_FILENAME) . '.json', $jsonData);

            $message = $isEdit ? 'Eintrag erfolgreich aktualisiert.' : 'Neuer Eintrag erfolgreich gespeichert.';
            $existingFile = $finalFileName;
            if (!in_array($finalFileName, $existingFiles, true)) {
                $existingFiles[] = $finalFileName;
                natcasesort($existingFiles);
                $existingFiles = array_values($existingFiles);
            }
            if ($organizer !== '' && !in_array($organizer, $knownOrganizers, true)) {
                $knownOrganizers[] = $organizer;
                natcasesort($knownOrganizers);
                $knownOrganizers = array_values($knownOrganizers);
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
        if (is_array($jsonData)) {
            $loadedData['organizer'] = $jsonData['organizer'] ?? $loadedData['organizer'];
            $loadedData['date'] = $jsonData['date'] ?? $loadedData['date'];
            $loadedData['event'] = $jsonData['event'] ?? $loadedData['event'];
            $loadedData['funkmod'] = $jsonData['funkmod'] ?? $loadedData['funkmod'];
            if (isset($jsonData['medical_system'])) {
                $loadedData['medical_system'] = $jsonData['medical_system'];
            } elseif (isset($jsonData['mediksystem'])) {
                $loadedData['medical_system'] = $jsonData['mediksystem'];
            }
        }
    }
}

$currentPresetName = $existingFile ? pathinfo($existingFile, PATHINFO_FILENAME) : '';

$funkmodOptions = ['Ohne', 'ACRE', 'TFAR'];
if ($loadedData['funkmod'] !== '' && !in_array($loadedData['funkmod'], $funkmodOptions, true)) {
    $funkmodOptions[] = $loadedData['funkmod'];
}

$medicalSystemOptions = ['Vanilla', 'ACE', 'KAT', 'ACM'];
if ($loadedData['medical_system'] !== '' && !in_array($loadedData['medical_system'], $medicalSystemOptions, true)) {
    $medicalSystemOptions[] = $loadedData['medical_system'];
}
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
            <?php if ($isMasterAdmin): ?>
                <?php if ($showPasswordForm): ?>
                    <a href="upload.php" class="btn btn-secondary">Zurück zur Verwaltung</a>
                <?php else: ?>
                    <a href="?action=change-password" class="btn btn-secondary">Passwort ändern</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary">Zurück zur Übersicht</a>
        </div>
    </header>

    <?php if ($passwordChangeMessage): ?>
        <?php
        $passwordAlertClass = 'info';
        if ($passwordChangeType === 'error') {
            $passwordAlertClass = 'error';
        } elseif ($passwordChangeType === 'success') {
            $passwordAlertClass = 'success';
        }
        ?>
        <div class="alert alert-<?= $passwordAlertClass ?>">
            <?= nl2br(htmlspecialchars($passwordChangeMessage, ENT_QUOTES, 'UTF-8'), false) ?>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType === 'error' ? 'error' : 'success' ?>"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($showPasswordForm): ?>
        <div class="glass-card form-card">
            <div>
                <h2 class="form-title">Admin-Passwort ändern</h2>
                <p class="form-description">Setze ein neues Passwort für reguläre Administratoren.</p>
            </div>
            <form method="post" class="section-split">
                <input type="hidden" name="form_type" value="change_password">
                <div class="form-group">
                    <label for="current_password">Aktuelles Master-Passwort</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="new_password">Neues Passwort</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" required autocomplete="new-password" minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Neues Passwort bestätigen</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required autocomplete="new-password" minlength="8">
                </div>
                <?php if (!$canChangePassword): ?>
                    <div class="alert alert-info">Die Zugangsdaten werden derzeit nicht aus einer Datei geladen oder die Datei kann nicht beschrieben werden.</div>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"<?= !$canChangePassword ? ' disabled' : '' ?>>Passwort aktualisieren</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="glass-card form-card">
        <div class="section-split">
            <div>
                <h1 class="form-title">Modset Upload &amp; Verwaltung</h1>
                <p class="form-description">Lade neue Presets hoch oder aktualisiere bestehende Einträge für deine Organisation.</p>
            </div>

            <form method="post" enctype="multipart/form-data" class="section-split">
                <input type="hidden" name="form_type" value="modset">
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

                <?php if ($currentPresetName): ?>
                    <div class="form-group">
                        <label>Aktuelle Preset-Datei</label>
                        <p class="status-text"><strong><?= htmlspecialchars($currentPresetName) ?></strong> (Dateiname)</p>
                        <p class="status-text">Der Dateiname bestimmt automatisch den angezeigten Preset-Namen.</p>
                    </div>
                <?php endif; ?>

                <div class="form-grid form-grid--two">
                    <div class="form-group">
                        <label for="organizer">Veranstalter</label>
                        <input type="text" name="organizer" id="organizer" class="form-control" required
                               value="<?= htmlspecialchars($loadedData['organizer']) ?>"
                               list="organizer-list" placeholder="Veranstalter auswählen oder hinzufügen">
                        <datalist id="organizer-list">
                            <?php foreach ($knownOrganizers as $knownOrganizer): ?>
                                <option value="<?= htmlspecialchars($knownOrganizer) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <?php if ($knownOrganizers): ?>
                            <p class="status-text">Bereits bekannte Veranstalter stehen als Auswahl zur Verfügung.</p>
                        <?php else: ?>
                            <p class="status-text">Lege bei Bedarf neue Veranstalter durch Eingabe fest.</p>
                        <?php endif; ?>
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
                            <?php foreach ($funkmodOptions as $option): ?>
                                <option value="<?= htmlspecialchars($option) ?>" <?= $loadedData['funkmod'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="medical_system">Medical-System</label>
                        <select name="medical_system" id="medical_system" class="form-select" required>
                            <option value="">-- auswählen --</option>
                            <?php foreach ($medicalSystemOptions as $option): ?>
                                <option value="<?= htmlspecialchars($option) ?>" <?= $loadedData['medical_system'] === $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="html_file">HTML-Datei (optional zum Ersetzen)</label>
                    <input type="file" name="html_file" id="html_file" class="form-control">
                    <div class="alert alert-info">Für neue Presets ist eine HTML-Datei zwingend erforderlich. Der Dateiname der Datei wird automatisch als Preset-Name verwendet.</div>
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
