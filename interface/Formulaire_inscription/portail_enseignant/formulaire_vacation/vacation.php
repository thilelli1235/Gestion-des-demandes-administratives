<?php
session_start();

$host   = "localhost";
$dbname = "administration";
$user   = "root";
$pass   = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion BDD : " . $e->getMessage());
}

$nom                 = trim($_POST['nom']                 ?? '');
$prenom              = trim($_POST['prenom']              ?? '');
$date_lieu_naissance = trim($_POST['date_lieu_naissance'] ?? '');
$grade               = trim($_POST['grade']               ?? '');
$diplome             = trim($_POST['diplome']             ?? '');
$ccp                 = trim($_POST['ccp']                 ?? '');
$adresse             = trim($_POST['adresse']             ?? '');
$date_debut          = trim($_POST['date_debut']          ?? '');
$departement_accueil = trim($_POST['departement_accueil'] ?? '');
$faculte             = trim($_POST['faculte']             ?? '');
$universite          = trim($_POST['universite']          ?? '');

$id_enseignant = $_SESSION['id_enseignant'] ?? null;

if (empty($nom) || empty($prenom) || empty($grade) || empty($date_debut)) {
    die("Erreur : les champs Nom, Prénom, Grade et Date de début sont obligatoires.");
}

// ── ÉTAPE 1 : vacation ────────────────────────────────────────
$stmtV = $pdo->prepare("
    INSERT INTO vacation
        (id_enseignant, nom, prenom, date_lieu_naissance, grade, diplome,
         ccp, adresse, date_debut, departement_accueil, faculte, universite,
         statut, date_demande)
    VALUES
        (:id_enseignant, :nom, :prenom, :date_lieu_naissance, :grade, :diplome,
         :ccp, :adresse, :date_debut, :departement_accueil, :faculte, :universite,
         'En attente', NOW())
");
$stmtV->execute([
    ':id_enseignant'       => $id_enseignant,
    ':nom'                 => $nom,
    ':prenom'              => $prenom,
    ':date_lieu_naissance' => $date_lieu_naissance,
    ':grade'               => $grade,
    ':diplome'             => $diplome,
    ':ccp'                 => $ccp,
    ':adresse'             => $adresse,
    ':date_debut'          => $date_debut,
    ':departement_accueil' => $departement_accueil,
    ':faculte'             => $faculte,
    ':universite'          => $universite,
]);
$vacation_id = $pdo->lastInsertId();

// ── ÉTAPE 2 : demandeurs ──────────────────────────────────────
$email_demandeur = null;
if ($id_enseignant) {
    $stmtEmail = $pdo->prepare("SELECT email_institutionnel FROM enseignant WHERE id_enseignant = ?");
    $stmtEmail->execute([$id_enseignant]);
    $row = $stmtEmail->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['email_institutionnel']))
        $email_demandeur = $row['email_institutionnel'];
}
if (!$email_demandeur)
    $email_demandeur = strtolower($prenom) . '.' . strtolower($nom) . '@fgei.ummto.dz';

$stmtCheck = $pdo->prepare("SELECT id FROM demandeurs WHERE email = ?");
$stmtCheck->execute([$email_demandeur]);
$existant = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($existant) {
    $demandeur_id = $existant['id'];
} else {
    $stmtD = $pdo->prepare("INSERT INTO demandeurs (nom, prenom, email) VALUES (?, ?, ?)");
    $stmtD->execute([$nom, $prenom, $email_demandeur]);
    $demandeur_id = $pdo->lastInsertId();
}

// ── ÉTAPE 3 : demandes ───────────────────────────────────────
$description = "Demande de vacation — ID vacation : $vacation_id | Grade : $grade | Dép. accueil : $departement_accueil";
$pdo->prepare("
    INSERT INTO demandes (demandeur_id, document_type, description, status, demande_le)
    VALUES (?, 'Autorisation de vacation', ?, 'Soumise', NOW())
")->execute([$demandeur_id, $description]);

$date_fmt    = $date_debut ? date('d/m/Y', strtotime($date_debut)) : '—';
$nom_complet = strtoupper($nom) . ' ' . ucfirst($prenom);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demande envoyée — Autorisation de Vacation</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: #f0f4f9;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}
.card {
    background: #fff;
    border-radius: 24px;
    padding: 48px 44px;
    max-width: 480px;
    width: 100%;
    text-align: center;
    box-shadow: 0 8px 48px rgba(26,58,92,0.12), 0 2px 8px rgba(26,58,92,0.06);
    border: 1px solid #e8eef6;
}
.icon-wrap {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 20px;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 8px 24px rgba(16,185,129,0.3);
}
.icon-wrap svg {
    width: 36px; height: 36px;
    stroke: white; fill: none;
    stroke-width: 3; stroke-linecap: round; stroke-linejoin: round;
}
h2 {
    font-family: 'Playfair Display', serif;
    font-size: 22px;
    color: #15803d;
    margin-bottom: 8px;
}
.subtitle {
    font-size: 14px;
    color: #6b82a0;
    line-height: 1.6;
    margin-bottom: 28px;
}
.info-box {
    background: #f7fafd;
    border: 1px solid #e8eef6;
    border-radius: 14px;
    padding: 4px 0;
    margin-bottom: 24px;
    text-align: left;
}
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    border-bottom: 1px solid #eef4fb;
    font-size: 13px;
}
.info-row:last-child { border-bottom: none; }
.info-row .lbl { color: #6b82a0; font-weight: 500; }
.info-row .val { font-weight: 700; color: #1a3a5c; text-align: right; max-width: 60%; }
.email-note {
    font-size: 13px;
    color: #6b82a0;
    margin-bottom: 28px;
    line-height: 1.6;
}
.email-note strong { color: #1a3a5c; }
.btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
.btn {
    padding: 13px 26px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.btn-primary {
    background: #1a3a5c;
    color: white;
    box-shadow: 0 4px 14px rgba(26,58,92,0.25);
}
.btn-primary:hover { background: #2b5080; transform: translateY(-1px); }
.btn-outline {
    background: transparent;
    color: #1a3a5c;
    border: 1.5px solid #dce8f5;
}
.btn-outline:hover { background: #f0f4f9; }
</style>
</head>
<body>
<div class="card">

    <div class="icon-wrap">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
    </div>

    <h2>Demande envoyée avec succès</h2>
    <p class="subtitle">Votre demande d'autorisation de vacation a été transmise à l'administration pour traitement.</p>

    <div class="info-box">
        <div class="info-row">
            <span class="lbl">Intervenant</span>
            <span class="val"><?= htmlspecialchars($nom_complet) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Grade</span>
            <span class="val"><?= htmlspecialchars($grade) ?></span>
        </div>
        <?php if ($departement_accueil): ?>
        <div class="info-row">
            <span class="lbl">Département d'accueil</span>
            <span class="val"><?= htmlspecialchars($departement_accueil) ?></span>
        </div>
        <?php endif; ?>
        <div class="info-row">
            <span class="lbl">Date de début</span>
            <span class="val"><?= htmlspecialchars($date_fmt) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Statut</span>
            <span class="val" style="color:#d97706;">En attente</span>
        </div>
    </div>

    <p class="email-note">
        Vous recevrez une notification à<br>
        <strong><?= htmlspecialchars($email_demandeur) ?></strong><br>
        dès que votre demande sera traitée.
    </p>

    <div class="btns">
        <a href="vacation.html" class="btn btn-primary">Nouvelle demande</a>
        <a href="../enseignant.html" class="btn btn-outline">← Retour</a>
    </div>

</div>
</body>
</html>