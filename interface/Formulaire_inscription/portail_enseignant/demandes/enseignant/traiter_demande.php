<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../phpmailer/src/Exception.php';
require __DIR__ . '/../phpmailer/src/PHPMailer.php';
require __DIR__ . '/../phpmailer/src/SMTP.php';

$pdo = new PDO(
    "mysql:host=localhost;dbname=administration;charset=utf8",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

if (!isset($_GET['id'])) {
    die("ID manquant");
}

$id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM demandes_enseignant WHERE id=?");
$stmt->execute([$id]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$d) {
    die("Demande introuvable");
}

/* EMAIL*/
function envoyerMail($to, $subject, $message)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ania.moulouel@fgei.ummto.dz';
        $mail->Password   = 'jmgq npip rnjv pcuu';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('ania.moulouel@fgei.ummto.dz', 'Administration FGEI');
        $mail->addAddress($to);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}
//TRAITEMENT POST 
$notification = "";
$notifType    = "";

if (isset($_POST['envoyer'])) {
    $decision    = $_POST['decision'];
    $commentaire = trim($_POST['commentaire']);
    $email       = $d['email_etudiant'];

    $icon    = $decision === 'Accepté' ? '✅' : '❌';
    $subject = $decision === 'Accepté' ? "Justificatif accepté" : "Justificatif refusé";
    $msg = "Bonjour " . $d['nom_etudiant'] . ",\n\n" .
           "Votre justificatif d'absence a été examiné.\n\n" .
           "Décision : " . strtoupper($decision) . "\n" .
           ($d['motif']        ? "Motif déclaré : " . $d['motif'] . "\n"        : "") .
           ($d['date_absence'] ? "Date d'absence : " . $d['date_absence'] . "\n" : "") .
           "\nCommentaire de l'enseignant :\n" . $commentaire . "\n\n" .
           "Cordialement,\nAdministration FGEI — Université Mouloud Mammeri de Tizi-Ouzou";

    $emailOK = envoyerMail($email, $subject, $msg);

    $upd = $pdo->prepare("
        UPDATE demandes_enseignant
        SET decision_enseignant=?,
            commentaire_enseignant=?,
            statut='Traité'
        WHERE id=?
    ");
    $upd->execute([$decision, $commentaire, $id]);

    // Recharger les données
    $stmt->execute([$id]);
    $d = $stmt->fetch(PDO::FETCH_ASSOC);

    $notification = $emailOK
        ? "✔ Décision envoyée — email notifié avec succès"
        : "⚠️ Décision enregistrée mais email non envoyé (vérifiez SMTP)";
    $notifType = $emailOK ? "success" : "warning";
}

$dejaTraite = $d['statut'] === 'Traité';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Traitement — #<?= $d['id'] ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1a3a5c;
    --accent: #2e7dd1;
    --light: #eef4fb;
    --border: #dce8f5;
    --success: #1a7a4a;
    --danger: #c0392b;
    --warning: #d4800a;
    --text: #1e2d3d;
    --muted: #6b82a0;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'DM Sans', sans-serif;
    background: #f0f4f9;
    color: var(--text);
    padding: 32px 16px;
}

.topbar {
    background: var(--primary);
    color: #fff;
    padding: 14px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
}
.topbar .brand { font-family: 'Playfair Display', serif; font-size: 16px; }
.topbar nav a { color: rgba(255,255,255,0.75); text-decoration: none; font-size: 13px; margin-left: 16px; }
.topbar nav a:hover { color: #fff; }

.page { max-width: 700px; margin: 80px auto 40px; }

.breadcrumb { font-size: 12px; color: var(--muted); margin-bottom: 20px; }
.breadcrumb a { color: var(--accent); text-decoration: none; }

.card {
    background: #fff;
    border-radius: 18px;
    border: 1px solid var(--border);
    box-shadow: 0 4px 24px rgba(26,58,92,0.08);
    overflow: hidden;
    margin-bottom: 20px;
}

.card-head {
    background: var(--primary);
    color: #fff;
    padding: 18px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-head h2 { font-family: 'Playfair Display', serif; font-size: 18px; }
.card-head .id-badge { background: rgba(255,255,255,0.15); padding: 4px 12px; border-radius: 20px; font-size: 12px; }

.card-body { padding: 24px; }

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}

.info-item .lbl {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--muted);
    margin-bottom: 4px;
    font-weight: 600;
}
.info-item .val {
    font-size: 14px;
    color: var(--text);
    font-weight: 500;
    background: var(--light);
    padding: 8px 12px;
    border-radius: 8px;
}

.file-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: var(--light);
    color: var(--accent);
    border: 1.5px solid var(--border);
    border-radius: 10px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    margin-bottom: 20px;
}
.file-btn:hover { background: var(--border); }

.section-title {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--accent);
    margin: 20px 0 12px;
    padding-top: 16px;
    border-top: 1px solid var(--border);
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13px;
    margin-bottom: 16px;
    font-weight: 500;
}
.alert.success { background: #d1e7dd; color: #0f5132; }
.alert.warning  { background: #fff3cd; color: #856404; }
.alert.info     { background: #cfe2ff; color: #084298; }

label { display: block; font-size: 12px; font-weight: 500; color: var(--text); margin-bottom: 6px; }
select, textarea {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    font-family: 'DM Sans', sans-serif;
    color: var(--text);
    background: #fff;
    outline: none;
    transition: border-color 0.2s;
    margin-bottom: 14px;
}
select:focus, textarea:focus { border-color: var(--accent); }
textarea { min-height: 100px; resize: vertical; }

.submit-row { display: flex; gap: 12px; }
.btn-submit {
    flex: 1;
    padding: 13px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-submit:hover { background: #142e4a; }
.btn-back {
    padding: 13px 20px;
    background: #fff;
    color: var(--primary);
    border: 1.5px solid var(--border);
    border-radius: 12px;
    font-size: 14px;
    font-family: 'DM Sans', sans-serif;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}
.btn-back:hover { background: var(--light); }

.decision-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}
.accepte { background: #d1e7dd; color: #0f5132; }
.refuse  { background: #f8d7da; color: #842029; }
</style>
</head>
<body>

<div class="topbar">
    <div class="brand">Portail Enseignant</div>
    <nav>
        <a href="liste_demandes_enseignant.php">⬅ Retour à la liste</a>
        <a href="#" onclick="ouvrirModalLogout();return false;"> Déconnexion</a>
    </nav>
</div>

<div class="page">

    <div class="breadcrumb">
        <a href="liste_demandes_enseignant.php">Demandes</a> › Traitement #<?= $d['id'] ?>
    </div>

    <?php if ($notification): ?>
    <div class="alert <?= $notifType ?>"><?= $notification ?></div>
    <?php endif; ?>

    <!-- Infos étudiant -->
    <div class="card">
        <div class="card-head">
            <h2>Dossier étudiant</h2>
            <span class="id-badge">Demande #<?= $d['id'] ?></span>
        </div>
        <div class="card-body">

            <div class="info-grid">
                <div class="info-item">
                    <div class="lbl">Nom complet</div>
                    <div class="val"><?= htmlspecialchars($d['nom_etudiant']) ?></div>
                </div>
                <div class="info-item">
                    <div class="lbl">Matricule</div>
                    <div class="val"><?= htmlspecialchars($d['matricule'] ?? '—') ?></div>
                </div>
                <div class="info-item">
                    <div class="lbl">Email</div>
                    <div class="val"><?= htmlspecialchars($d['email_etudiant']) ?></div>
                </div>
                <div class="info-item">
                    <div class="lbl">Date de la demande</div>
                    <div class="val"><?= date('d/m/Y H:i', strtotime($d['date_demande'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="lbl">Motif</div>
                    <div class="val"><?= htmlspecialchars($d['motif'] ?? '—') ?></div>
                </div>
                <div class="info-item">
                    <div class="lbl">Date d'absence</div>
                    <div class="val"><?= htmlspecialchars($d['date_absence'] ?? '—') ?></div>
                </div>
                <?php if (!empty($d['matiere'])): ?>
                <div class="info-item">
                    <div class="lbl">Matière</div>
                    <div class="val"><?= htmlspecialchars($d['matiere']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($d['description_absence'])): ?>
                <div class="info-item" style="grid-column: span 2">
                    <div class="lbl">Précisions</div>
                    <div class="val"><?= nl2br(htmlspecialchars($d['description_absence'])) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Justificatif -->
            <a class="file-btn"
               href="../etudiant/voir_fichier.php?file=<?= urlencode($d['fichier_justificatif']) ?>">
                📄 Ouvrir le justificatif
            </a>

            <!-- Décision si déjà traité -->
            <?php if ($dejaTraite): ?>
            <div class="alert info">
                Cette demande a déjà été traitée.
                Décision : <span class="decision-badge <?= $d['decision_enseignant'] === 'Accepté' ? 'accepte' : 'refuse' ?>">
                    <?= htmlspecialchars($d['decision_enseignant']) ?>
                </span>
            </div>
            <?php if ($d['commentaire_enseignant']): ?>
            <div class="info-item">
                <div class="lbl">Commentaire enregistré</div>
                <div class="val"><?= nl2br(htmlspecialchars($d['commentaire_enseignant'])) ?></div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Formulaire décision -->
    <div class="card">
        <div class="card-head">
            <h2><?= $dejaTraite ? 'Modifier la décision' : 'Rendre une décision' ?></h2>
        </div>
        <div class="card-body">
            <form method="POST">

                <label>Décision <span style="color:#e05252">*</span></label>
                <select name="decision" required>
                    <option value="Accepté" <?= ($d['decision_enseignant'] ?? '') === 'Accepté' ? 'selected' : '' ?>>
                        ✅ Accepter le justificatif
                    </option>
                    <option value="Refusé" <?= ($d['decision_enseignant'] ?? '') === 'Refusé' ? 'selected' : '' ?>>
                        ❌ Refuser le justificatif
                    </option>
                </select>

                <label>Commentaire à l'étudiant <span style="color:#e05252">*</span></label>
                <textarea name="commentaire" placeholder="Rédigez un message qui sera envoyé par email à l'étudiant…" required><?= htmlspecialchars($d['commentaire_enseignant'] ?? '') ?></textarea>

                <div class="submit-row">
                    <a href="liste_demandes_enseignant.php" class="btn-back">⬅ Retour</a>
                    <button type="submit" name="envoyer" class="btn-submit">
                        Envoyer la décision par email ✉
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<!-- MODAL DÉCONNEXION -->
<div id="modalLogout" style="display:none;position:fixed;inset:0;background:rgba(15,23,46,.55);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:24px;padding:44px 48px;text-align:center;max-width:400px;width:100%;box-shadow:0 32px 80px rgba(0,0,0,.2);animation:popInLogout .28s cubic-bezier(.34,1.56,.64,1);">
        <style>@keyframes popInLogout{from{transform:scale(.82);opacity:0}to{transform:scale(1);opacity:1}}</style>
        <div style="font-size:52px;margin-bottom:16px;">👋</div>
        <h3 style="font-size:19px;font-weight:700;color:#0f172a;margin-bottom:8px;font-family:'DM Sans',sans-serif;">Quitter votre session ?</h3>
        <p style="font-size:14px;color:#64748b;margin-bottom:30px;line-height:1.6;font-family:'DM Sans',sans-serif;">Vous allez être redirigé vers l'espace enseignant.</p>
        <div style="display:flex;gap:12px;justify-content:center;">
            <button onclick="fermerModalLogout()" style="padding:12px 30px;border-radius:50px;font-size:14px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#f1f5f9;color:#475569;font-family:'DM Sans',sans-serif;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Annuler</button>
            <button onclick="deconnecterLogout()" style="padding:12px 30px;border-radius:50px;font-size:14px;font-weight:600;cursor:pointer;border:none;background:linear-gradient(135deg,#1a3a5c,#2b5080);color:white;font-family:'DM Sans',sans-serif;box-shadow:0 4px 18px rgba(26,58,92,.35);" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">Oui, déconnecter</button>
        </div>
    </div>
</div>
<script>
function ouvrirModalLogout(){document.getElementById('modalLogout').style.display='flex';}
function fermerModalLogout(){document.getElementById('modalLogout').style.display='none';}
function deconnecterLogout(){document.body.style.transition='opacity 0.4s ease';document.body.style.opacity='0';setTimeout(function(){window.location.href='../../enseignant.html';},400);}
document.getElementById('modalLogout').addEventListener('click',function(e){if(e.target===this)fermerModalLogout();});
</script>

</body>
</html>