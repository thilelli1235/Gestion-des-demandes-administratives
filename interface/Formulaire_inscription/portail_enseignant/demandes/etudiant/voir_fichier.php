<?php
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die("❌ Fichier non spécifié.");
}

$nomFichier = basename($_GET['file']);

// Recherche automatique de uploads/
$uploadDir = null;
$dir = __DIR__;
for ($i = 0; $i < 6; $i++) {
    if (is_dir($dir . "/uploads")) {
        $uploadDir = $dir . "/uploads/";
        break;
    }
    $dir = dirname($dir);
}

if (!$uploadDir) {
    die("<b>❌ Dossier uploads/ introuvable.</b><br>Chemin cherché depuis : <code>" . __DIR__ . "</code>");
}

$cheminComplet = $uploadDir . $nomFichier;

if (!file_exists($cheminComplet)) {
    die("<b>❌ Fichier introuvable.</b><br>Chemin : <code>" . htmlspecialchars($cheminComplet) . "</code>");
}

$extension = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];

$mime = $mimeTypes[$extension] ?? 'application/octet-stream';
$inlineTypes = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'];

// Pour les images et PDF → afficher dans une page HTML avec bouton retour
// Pour les autres types → téléchargement direct
if (!in_array($extension, $inlineTypes)) {
    header("Content-Type: $mime");
    header("Content-Disposition: attachment; filename=\"$nomFichier\"");
    header("Content-Length: " . filesize($cheminComplet));
    readfile($cheminComplet);
    exit();
}

// Encoder le fichier en base64 pour l'afficher inline
$base64 = base64_encode(file_get_contents($cheminComplet));
$dataUri = "data:$mime;base64,$base64";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Justificatif — <?= htmlspecialchars($nomFichier) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: #1a1a2e;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* TOPBAR */
.topbar {
    background: #1a3a5c;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
    box-shadow: 0 2px 12px rgba(0,0,0,0.3);
}
.topbar-left { display: flex; align-items: center; gap: 14px; }

.btn-back {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 20px;
    background: rgba(255,255,255,0.12);
    color: white;
    border: 1.5px solid rgba(255,255,255,0.2);
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-back:hover { background: rgba(255,255,255,0.22); transform: translateY(-1px); }

.file-name {
    color: rgba(255,255,255,0.7);
    font-size: 13px;
    max-width: 400px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.btn-download {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 20px;
    background: #b8973a;
    color: white;
    border: none;
    border-radius: 50px;
    font-size: 13px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-download:hover { background: #9e7e2e; transform: translateY(-1px); }

/* VIEWER */
.viewer {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

.viewer img {
    max-width: 100%;
    max-height: calc(100vh - 120px);
    border-radius: 8px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
    object-fit: contain;
}

.viewer iframe {
    width: 100%;
    height: calc(100vh - 120px);
    border: none;
    border-radius: 8px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.5);
    background: white;
}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <a href="#" onclick="history.back(); return false;" class="btn-back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Retour
        </a>
        <span class="file-name">📄 <?= htmlspecialchars($nomFichier) ?></span>
    </div>
    <a href="data:<?= $mime ?>;base64,<?= $base64 ?>" download="<?= htmlspecialchars($nomFichier) ?>" class="btn-download">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Télécharger
    </a>
</div>

<div class="viewer">
    <?php if ($extension === 'pdf'): ?>
        <iframe src="<?= $dataUri ?>"></iframe>
    <?php else: ?>
        <img src="<?= $dataUri ?>" alt="<?= htmlspecialchars($nomFichier) ?>">
    <?php endif; ?>
</div>

</body>
</html>