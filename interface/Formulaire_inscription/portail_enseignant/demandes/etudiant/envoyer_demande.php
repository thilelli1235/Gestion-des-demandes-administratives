<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);


/* CONNEXION BASE DE DONNÉES*/
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=administration;charset=utf8",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("❌ Connexion DB échouée : " . $e->getMessage());
}

/*RÉCUPÉRATION DES DONNÉES*/
$prenom        = strtolower(trim($_POST['prenom'] ?? ''));
$nom           = strtolower(trim($_POST['nom'] ?? ''));
$matricule     = trim($_POST['matricule'] ?? '');
$motif         = trim($_POST['motif'] ?? '');
$date_absence  = trim($_POST['date_absence'] ?? '');
$matiere       = trim($_POST['matiere'] ?? '');
$description   = trim($_POST['description'] ?? '');
$enseignant_id = (int)($_POST['enseignant_id'] ?? 0);

/*EMAIL ÉTUDIANT */
function removeAccents($str)
{
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);

    return preg_replace('/[^a-z0-9._-]/', '', strtolower($str));
}

$email = removeAccents($prenom) . "." . removeAccents($nom) . "@fgei.ummto.dz";

$nomComplet = strtoupper($nom) . " " . ucfirst($prenom);

/*RÉCUPÉRER L'ENSEIGNANT*/
if ($enseignant_id <= 0) {
    die("❌ Enseignant non sélectionné.");
}

$stmtProf = $pdo->prepare("
    SELECT nom, prenom, email_institutionnel
    FROM enseignant
    WHERE id_enseignant = ?
");

$stmtProf->execute([$enseignant_id]);

$prof = $stmtProf->fetch(PDO::FETCH_ASSOC);

if (!$prof) {
    die("❌ Enseignant introuvable.");
}

$emailEnseignant = $prof['email_institutionnel'];

$nomEnseignant =
    strtoupper($prof['nom']) . ' ' .
    ucfirst($prof['prenom']);

/*UPLOAD FICHIER*/
$uploadDir =
    dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    "uploads" .
    DIRECTORY_SEPARATOR;

if (!is_dir($uploadDir)) {

    if (!mkdir($uploadDir, 0777, true)) {
        die("❌ Impossible de créer uploads/");
    }
}

if (
    !isset($_FILES['fichier']) ||
    $_FILES['fichier']['error'] !== UPLOAD_ERR_OK
) {

    $codes = [
        1 => "Trop grand (php.ini)",
        2 => "Trop grand",
        3 => "Incomplet",
        4 => "Aucun fichier",
        6 => "Dossier tmp manquant",
        7 => "Échec écriture"
    ];

    $code = $_FILES['fichier']['error'] ?? 4;

    die("❌ Erreur upload : " . ($codes[$code] ?? "Code $code"));
}

$allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

$ext = strtolower(
    pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION)
);

if (!in_array($ext, $allowed)) {
    die("❌ Type non autorisé : .$ext");
}

if ($_FILES['fichier']['size'] > 5 * 1024 * 1024) {
    die("❌ Fichier trop volumineux (max 5 Mo).");
}

$fileName =
    time() . "_" .
    basename($_FILES['fichier']['name']);

if (
    !move_uploaded_file(
        $_FILES['fichier']['tmp_name'],
        $uploadDir . $fileName
    )
) {
    die("❌ Impossible de déplacer le fichier vers uploads/");
}

/*INSERTION DEMANDE */
try {

    $stmt = $pdo->prepare("
        INSERT INTO demandes_enseignant
        (
            nom_etudiant,
            email_etudiant,
            matricule,
            motif,
            date_absence,
            matiere,
            description_absence,
            fichier_justificatif
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $nomComplet,
        $email,
        $matricule,
        $motif,
        $date_absence,
        $matiere,
        $description,
        $fileName
    ]);

} catch (PDOException $e) {

    die("<b>❌ Erreur SQL :</b> " . $e->getMessage());
}

/* EMAIL ENSEIGNANT */
$emailEnvoye = false;

if (!empty($emailEnseignant)) {

    $pmBase = dirname(__DIR__) . '/PHPMailer/src/';
    if (!is_dir($pmBase)) $pmBase = dirname(__DIR__) . '/phpmailer/src/';
    require $pmBase . 'Exception.php';
    require $pmBase . 'PHPMailer.php';
    require $pmBase . 'SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username = 'ania.moulouel@fgei.ummto.dz';

        $mail->Password = 'jmgq npip rnjv pcuu';

        $mail->SMTPSecure =
            \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            'ania.moulouel@fgei.ummto.dz',
            'Portail FGEI'
        );

        $mail->addAddress(
            $emailEnseignant,
            $nomEnseignant
        );

        $mail->isHTML(false);

        $mail->Subject =
            "📋 Nouvelle demande de justificatif — $nomComplet";

        $mail->Body =
            "Bonjour $nomEnseignant,\n\n" .

            "Un étudiant vient de soumettre une demande de justificatif d'absence vous concernant.\n\n" .

            "═══════════════════════════════════\n" .
            "  DÉTAILS DE LA DEMANDE\n" .
            "═══════════════════════════════════\n" .

            "• Étudiant    : $nomComplet\n" .
            "• Matricule   : $matricule\n" .
            "• Email       : $email\n" .
            "• Motif       : $motif\n" .
            "• Date abs.   : $date_absence\n" .

            ($matiere
                ? "• Matière     : $matiere\n"
                : "") .

            ($description
                ? "• Précisions  : $description\n"
                : "") .

            "═══════════════════════════════════\n\n" .

            "Connectez-vous à votre espace enseignant pour consulter le justificatif joint et rendre votre décision.\n\n" .

            "Cordialement,\n" .
            "Portail Numérique FGEI — Université Mouloud Mammeri de Tizi-Ouzou";

        $mail->send();

        $emailEnvoye = true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {

        $emailEnvoye = false;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demande envoyée</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family:'DM Sans',sans-serif;
    background:#f0f4f9;
    min-height:100vh;
    display:flex; align-items:center; justify-content:center;
    padding:24px;
}
.card {
    background:#fff; border-radius:24px; padding:48px 44px;
    max-width:480px; width:100%; text-align:center;
    box-shadow:0 8px 48px rgba(26,58,92,0.12);
    border:1px solid #e8eef6;
}
.icon-wrap {
    width:72px; height:72px;
    background:linear-gradient(135deg,#10b981,#059669);
    border-radius:20px;
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 20px;
    box-shadow:0 8px 24px rgba(16,185,129,0.3);
}
.icon-wrap svg { width:36px; height:36px; stroke:white; fill:none; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; }
h2 { font-family:'Playfair Display',serif; font-size:22px; color:#15803d; margin-bottom:8px; }
.subtitle { font-size:14px; color:#6b82a0; line-height:1.6; margin-bottom:28px; }
.info-box {
    background:#f7fafd; border:1px solid #e8eef6;
    border-radius:14px; padding:4px 0; margin-bottom:24px; text-align:left;
}
.info-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:12px 20px; border-bottom:1px solid #eef4fb; font-size:13px;
}
.info-row:last-child { border-bottom:none; }
.info-row .lbl { color:#6b82a0; font-weight:500; }
.info-row .val { font-weight:700; color:#1a3a5c; text-align:right; max-width:60%; }
.email-note { font-size:13px; color:#6b82a0; margin-bottom:28px; line-height:1.6; }
.email-note strong { color:#1a3a5c; }
.btns { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
.btn {
    padding:13px 26px; border-radius:12px;
    font-size:14px; font-weight:600;
    font-family:'DM Sans',sans-serif;
    text-decoration:none; border:none; cursor:pointer; transition:all .2s;
}
.btn-primary { background:#1a3a5c; color:white; box-shadow:0 4px 14px rgba(26,58,92,.25); }
.btn-primary:hover { background:#2b5080; transform:translateY(-1px); }
.btn-outline { background:transparent; color:#1a3a5c; border:1.5px solid #dce8f5; }
.btn-outline:hover { background:#f0f4f9; }
</style>
</head>
<body>
<div class="card">
    <div class="icon-wrap">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h2>Demande envoyée avec succès</h2>
    <p class="subtitle">Votre justificatif a été transmis à l'enseignant concerné pour traitement.</p>

    <div class="info-box">
        <div class="info-row">
            <span class="lbl">Étudiant</span>
            <span class="val"><?= htmlspecialchars($nomComplet) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Matricule</span>
            <span class="val"><?= htmlspecialchars($matricule) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Motif</span>
            <span class="val"><?= htmlspecialchars($motif) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Date absence</span>
            <span class="val"><?= htmlspecialchars($date_absence) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Enseignant notifié</span>
            <span class="val"><?= htmlspecialchars($nomEnseignant) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Statut</span>
            <span class="val" style="color:#d97706;">En attente</span>
        </div>
    </div>

    <p class="email-note">
        Vous recevrez une réponse à<br>
        <strong><?= htmlspecialchars($email) ?></strong><br>
        dès que votre demande sera traitée.
    </p>

    <div class="btns">
        <a href="formulaire_demande.php" class="btn btn-primary">Nouvelle demande</a>
        <a href="../../../../interface.html" class="btn btn-outline">← Accueil</a>
    </div>
</div>
</body>
</html>