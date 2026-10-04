<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdo = new PDO(
    "mysql:host=localhost;dbname=administration;charset=utf8",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$email_etudiant = trim($_POST['email_etudiant']   ?? '');
$organisme      = trim($_POST['organisme_accueil'] ?? '');
$date_debut     = trim($_POST['date_debut']        ?? '');

if (empty($email_etudiant) || empty($organisme) || empty($date_debut)) {
    die("❌ Tous les champs obligatoires doivent être remplis.");
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO convention_stage
            (email_etudiant, organisme_accueil, date_debut, statut, date_demande)
        VALUES (:email, :organisme, :date_debut, 'En attente', NOW())
    ");
    $stmt->execute([
        ':email'      => $email_etudiant,
        ':organisme'  => $organisme,
        ':date_debut' => $date_debut,
    ]);
    $cnv_id = (int) $pdo->lastInsertId();

    if ($cnv_id === 0) die("❌ Erreur : insertion échouée.");

    $parts      = explode('@', $email_etudiant);
    $pseudo     = $parts[0] ?? 'etudiant';
    $name_parts = explode('.', $pseudo);
    $prenom     = ucfirst(strtolower($name_parts[0] ?? 'Prénom'));
    $nom        = ucfirst(strtolower($name_parts[1] ?? 'Nom'));

    $chk = $pdo->prepare("SELECT id FROM demandeurs WHERE email = ?");
    $chk->execute([$email_etudiant]);
    $existing = $chk->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $demandeur_id = (int) $existing['id'];
    } else {
        $ins = $pdo->prepare("INSERT INTO demandeurs (nom, prenom, email) VALUES (?, ?, ?)");
        $ins->execute([$nom, $prenom, $email_etudiant]);
        $demandeur_id = (int) $pdo->lastInsertId();
    }

    $description = "Convention de stage — ID cnv : {$cnv_id} | Organisme : {$organisme} | Début : {$date_debut}";
    $pdo->prepare("
        INSERT INTO demandes (demandeur_id, document_type, description, status, demande_le)
        VALUES (?, 'Convention de stage', ?, 'Soumise', NOW())
    ")->execute([$demandeur_id, $description]);

} catch (PDOException $e) {
    die("❌ Erreur SQL : " . $e->getMessage());
}

// Formatage date
$date_fmt = $date_debut ? date('d/m/Y', strtotime($date_debut)) : '—';
$nom_complet = strtoupper($nom) . ' ' . ucfirst($prenom);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demande envoyée — Convention de Stage</title>
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
.icon-wrap svg { width: 36px; height: 36px; stroke: white; fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
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
    font-size: 13.5px;
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
    <p class="subtitle">Votre convention de stage a été transmise à l'administration pour traitement.</p>

    <div class="info-box">
        <div class="info-row">
            <span class="lbl">Demandeur</span>
            <span class="val"><?= htmlspecialchars($nom_complet) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Email</span>
            <span class="val"><?= htmlspecialchars($email_etudiant) ?></span>
        </div>
        <div class="info-row">
            <span class="lbl">Organisme d'accueil</span>
            <span class="val"><?= htmlspecialchars($organisme) ?></span>
        </div>
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
        <strong><?= htmlspecialchars($email_etudiant) ?></strong><br>
        dès que votre demande sera traitée.
    </p>

    <div class="btns">
        <a href="conventiondestage.html" class="btn btn-primary">Nouvelle demande</a>
        <a href="../interface.html" class="btn btn-outline">← Accueil</a>
    </div>
</div>
</body>
</html>