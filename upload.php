<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$password = 'YourSuperSecretPassword';

if (isset($_POST['password']) && $_POST['password'] === $password) {
    $_SESSION['authenticated'] = true;
    $_SESSION['is_admin'] = true;
}

if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">
    <div class="container mt-5">
        <h1>Login</h1>
        <form method="post">
            <div class="mb-3">
                <label for="password" class="form-label">Passwort:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Einloggen</button>
        </form>
    </div>
</body>
</html>
<?php
    exit;
}

$uploadDir = 'html_files/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$existingFiles = glob($uploadDir . '*.html');
$existingFile = $_POST['existing_file'] ?? ($_GET['edit'] ?? '');
$loadedData = [
    'preset_name' => '',
    'organizer' => '',
    'date' => '',
    'event' => '',
    'funkmod' => '',
    'mediksystem' => ''
];
$message = '';

if (isset($_POST['delete']) && !empty($_POST['existing_file'])) {
    $fileToDelete = basename($_POST['existing_file']);
    $htmlPath = $uploadDir . $fileToDelete;
    $jsonPath = $uploadDir . pathinfo($fileToDelete, PATHINFO_FILENAME) . '.json';

    if (file_exists($htmlPath)) unlink($htmlPath);
    if (file_exists($jsonPath)) unlink($jsonPath);

    $message = "Eintrag <strong>$fileToDelete</strong> erfolgreich gelöscht.";
    $existingFile = '';
    $loadedData = array_fill_keys(array_keys($loadedData), '');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preset_name'], $_POST['organizer'])) {
    $presetName = htmlspecialchars($_POST['preset_name']);
    $organizer = htmlspecialchars($_POST['organizer']);
    $date = htmlspecialchars($_POST['date']);
    $event = htmlspecialchars($_POST['event'] ?? '');
    $funkmod = htmlspecialchars($_POST['funkmod']);
    $mediksystem = htmlspecialchars($_POST['mediksystem']);
    $htmlFile = $_FILES['html_file']['name'];
    $isEdit = !empty($_POST['existing_file']);

    $finalFileName = $isEdit ? basename($_POST['existing_file']) : $htmlFile;

    if (!empty($_FILES['html_file']['tmp_name'])) {
        move_uploaded_file($_FILES['html_file']['tmp_name'], $uploadDir . $finalFileName);
    }

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
}

if ($existingFile) {
    $jsonPath = $uploadDir . pathinfo($existingFile, PATHINFO_FILENAME) . '.json';
    if (file_exists($jsonPath)) {
        $jsonData = json_decode(file_get_contents($jsonPath), true);
        $loadedData = array_merge($loadedData, $jsonData);
    }
    $loadedData['preset_name'] = pathinfo($existingFile, PATHINFO_FILENAME);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Modset Upload / Bearbeiten</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
<body class="bg-dark text-white">
<div class="container mt-5">
    <h1>Modset Upload / Bearbeiten</h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mb-5">
        <div class="mb-3">
            <label for="existing_file" class="form-label">Existierenden Eintrag bearbeiten:</label>
            <select name="existing_file" id="existing_file" class="form-select" onchange="loadSelectedFile(this)">
                <option value="">-- Neue Datei --</option>
                <?php foreach ($existingFiles as $file): ?>
                    <?php $basename = basename($file); ?>
                    <option value="<?= $basename ?>" <?= ($basename === $existingFile ? 'selected' : '') ?>>
                        <?= $basename ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="preset_name" class="form-label">Preset-Name (Dateiname)</label>
            <input type="text" name="preset_name" id="preset_name" class="form-control" required
                   value="<?= htmlspecialchars($loadedData['preset_name']) ?>"
                   <?= $existingFile ? 'readonly' : '' ?>>
        </div>

        <div class="mb-3">
            <label for="organizer" class="form-label">Veranstalter</label>
            <input type="text" name="organizer" id="organizer" class="form-control" required
                   value="<?= htmlspecialchars($loadedData['organizer']) ?>">
        </div>

        <div class="mb-3">
            <label for="date" class="form-label">Datum</label>
            <input type="date" name="date" id="date" class="form-control" required
                   value="<?= htmlspecialchars($loadedData['date']) ?>">
        </div>

        <div class="mb-3">
            <label for="event" class="form-label">Event (optional)</label>
            <input type="text" name="event" id="event" class="form-control"
                   value="<?= htmlspecialchars($loadedData['event']) ?>">
        </div>

        <div class="mb-3">
            <label for="funkmod" class="form-label">Funkmod</label>
            <select name="funkmod" id="funkmod" class="form-select" required>
                <option value="">-- auswählen --</option>
                <option value="ACRE" <?= $loadedData['funkmod'] === 'ACRE' ? 'selected' : '' ?>>ACRE</option>
                <option value="TFAR" <?= $loadedData['funkmod'] === 'TFAR' ? 'selected' : '' ?>>TFAR</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="mediksystem" class="form-label">Mediksystem</label>
            <select name="mediksystem" id="mediksystem" class="form-select" required>
                <option value="">-- auswählen --</option>
                <option value="Vanilla" <?= $loadedData['mediksystem'] === 'Vanilla' ? 'selected' : '' ?>>Vanilla</option>
                <option value="ACE" <?= $loadedData['mediksystem'] === 'ACE' ? 'selected' : '' ?>>ACE</option>
                <option value="KAT" <?= $loadedData['mediksystem'] === 'KAT' ? 'selected' : '' ?>>KAT</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="html_file" class="form-label">HTML-Datei (optional zum Ersetzen)</label>
            <input type="file" name="html_file" id="html_file" class="form-control">
        </div>

        <button type="submit" class="btn btn-success">Speichern</button>
        <?php if ($existingFile): ?>
            <button type="submit" name="delete" value="1" class="btn btn-danger" onclick="return confirm('Eintrag wirklich löschen?')">
                Löschen
            </button>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
