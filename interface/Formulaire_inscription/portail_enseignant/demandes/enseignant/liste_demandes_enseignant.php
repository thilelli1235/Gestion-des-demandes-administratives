<?php
session_start();

$pdo = new PDO(
    "mysql:host=localhost;dbname=administration;charset=utf8",
    "root",
    "",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ============================================================
//  NETTOYAGE AUTOMATIQUE
//  Supprime les demandes traitées de plus d'un mois
//  Exécuté à chaque chargement de la page
// ============================================================
$suppression = $pdo->prepare("
    DELETE FROM demandes_enseignant
    WHERE statut = 'Traité'
    AND date_demande < DATE_SUB(NOW(), INTERVAL 1 MONTH)
");
$suppression->execute();
$nbSupprimes = $suppression->rowCount();

// Filtres GET
$filtreStatut = $_GET['statut'] ?? '';
$filtreMotif  = $_GET['motif'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where  = [];
$params = [];

if ($filtreStatut) { $where[] = "statut = ?";  $params[] = $filtreStatut; }
if ($filtreMotif)  { $where[] = "motif = ?";   $params[] = $filtreMotif; }
if ($search)       {
    $where[]  = "(nom_etudiant LIKE ? OR matricule LIKE ? OR email_etudiant LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$sql = "SELECT * FROM demandes_enseignant";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY id DESC";

$req = $pdo->prepare($sql);
$req->execute($params);
$demandes = $req->fetchAll(PDO::FETCH_ASSOC);

$total     = count($demandes);
$enAttente = count(array_filter($demandes, fn($d) => $d['statut'] === 'En attente'));
$traite    = count(array_filter($demandes, fn($d) => $d['statut'] === 'Traité'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Demandes justificatifs — Enseignant</title>
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
body { font-family: 'DM Sans', sans-serif; background: #f0f4f9; color: var(--text); }

.topbar {
    background: var(--primary); color: #fff;
    padding: 14px 32px;
    display: flex; align-items: center; justify-content: space-between;
}
.topbar .brand { font-family: 'Playfair Display', serif; font-size: 17px; }
.topbar .brand small { font-family: 'DM Sans', sans-serif; font-size: 11px; opacity: 0.6; display: block; }
.topbar nav a { color: rgba(255,255,255,0.75); text-decoration: none; font-size: 13px; margin-left: 20px; transition: color 0.2s; }
.topbar nav a:hover { color: #fff; }

.main { max-width: 1200px; margin: 32px auto; padding: 0 24px; }

/* Bannière suppression automatique */
.auto-delete-banner {
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-radius: 12px;
    padding: 12px 18px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #5d4037;
    display: flex;
    align-items: center;
    gap: 10px;
}
.auto-delete-banner.deleted {
    background: #fce4ec;
    border-color: #f48fb1;
    color: #880e4f;
}

.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: #fff; border-radius: 14px; padding: 20px 24px; border: 1px solid var(--border); display: flex; align-items: center; gap: 16px; }
.stat-icon { font-size: 32px; }
.stat-val { font-size: 28px; font-weight: 700; color: var(--primary); }
.stat-lbl { font-size: 12px; color: var(--muted); }

.filters-bar {
    background: #fff; border: 1px solid var(--border); border-radius: 14px;
    padding: 16px 20px; margin-bottom: 20px;
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
}
.filters-bar input[type="text"], .filters-bar select {
    padding: 9px 14px; border: 1.5px solid var(--border); border-radius: 9px;
    font-size: 13px; font-family: 'DM Sans', sans-serif; color: var(--text); outline: none; transition: border-color 0.2s;
}
.filters-bar input[type="text"] { flex: 1; min-width: 180px; }
.filters-bar input:focus, .filters-bar select:focus { border-color: var(--accent); }
.filters-bar button { padding: 9px 20px; background: var(--primary); color: #fff; border: none; border-radius: 9px; font-size: 13px; cursor: pointer; font-family: 'DM Sans', sans-serif; }
.filters-bar a.reset { font-size: 12px; color: var(--muted); text-decoration: none; padding: 9px 12px; }
.filters-bar a.reset:hover { color: var(--danger); }

.table-card { background: #fff; border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 2px 16px rgba(26,58,92,0.06); }
.table-header { padding: 16px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.table-header h2 { font-size: 16px; font-weight: 600; color: var(--primary); }
.table-header .meta { display: flex; align-items: center; gap: 10px; }
.count { font-size: 12px; color: var(--muted); background: var(--light); padding: 4px 12px; border-radius: 20px; }
.auto-delete-info { font-size: 11px; color: var(--muted); display: flex; align-items: center; gap: 4px; }

table { width: 100%; border-collapse: collapse; }
thead th { background: #f7fafd; padding: 11px 16px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px; color: var(--muted); border-bottom: 1px solid var(--border); }
tbody td { padding: 13px 16px; font-size: 13px; border-bottom: 1px solid #f2f6fb; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover { background: #fafcff; }

/* Ligne en attente → légère teinte orange */
tbody tr.row-attente { border-left: 3px solid #f59e0b; }
/* Ligne traitée → légère teinte verte */
tbody tr.row-traite  { border-left: 3px solid #10b981; opacity: 0.85; }

.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge-attente { background: #fff3cd; color: #856404; }
.badge-traite  { background: #d1e7dd; color: #0f5132; }
.badge-accepte { background: #d1e7dd; color: #0f5132; }
.badge-refuse  { background: #f8d7da; color: #842029; }

.btn-action { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 500; text-decoration: none; transition: all 0.15s; font-family: 'DM Sans', sans-serif; }
.btn-traiter { background: var(--primary); color: #fff; }
.btn-traiter:hover { background: #142e4a; }
.btn-voir { background: var(--light); color: var(--accent); border: 1px solid var(--border); }
.btn-voir:hover { background: var(--border); }

.no-data { text-align: center; padding: 60px 20px; color: var(--muted); }
.no-data .icon { font-size: 40px; margin-bottom: 10px; }
.matricule { font-family: monospace; font-size: 12px; color: var(--muted); }

/* Countdown sur les lignes traitées */
.expiry { font-size: 11px; color: #f59e0b; margin-top: 2px; }
.expiry.soon { color: var(--danger); font-weight: 600; }
</style>
</head>
<body>

<div class="topbar">
    <div class="brand">
        Portail Enseignant
        <small>FGEI — Université Mouloud Mammeri de Tizi-Ouzou</small>
    </div>
    <nav>
        
        <a href="#" onclick="ouvrirModalLogout();return false;"> Déconnexion</a>
    </nav>
</div>

<div class="main">

    <?php if ($nbSupprimes > 0): ?>
    <div class="auto-delete-banner deleted">
        🗑️ <strong><?= $nbSupprimes ?> demande<?= $nbSupprimes > 1 ? 's' : '' ?> traitée<?= $nbSupprimes > 1 ? 's' : '' ?></strong>
        supprimée<?= $nbSupprimes > 1 ? 's' : '' ?> automatiquement (plus d'un mois après traitement).
    </div>
    <?php else: ?>
    <div class="auto-delete-banner">
        🕐 Les demandes traitées sont <strong>supprimées automatiquement après 1 mois</strong>.
    </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon">📁</div>
            <div><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Total demandes</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div><div class="stat-val" style="color:#d4800a"><?= $enAttente ?></div><div class="stat-lbl">En attente</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div><div class="stat-val" style="color:var(--success)"><?= $traite ?></div><div class="stat-lbl">Traitées</div></div>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" class="filters-bar">
        <input type="text" name="q" placeholder="🔍 Rechercher par nom, matricule, email…" value="<?= htmlspecialchars($search) ?>">
        <select name="statut">
            <option value="">Tous les statuts</option>
            <option value="En attente" <?= $filtreStatut === 'En attente' ? 'selected' : '' ?>>⏳ En attente</option>
            <option value="Traité"     <?= $filtreStatut === 'Traité'     ? 'selected' : '' ?>>✅ Traité</option>
        </select>
        <select name="motif">
            <option value="">Tous les motifs</option>
            <option value="Maladie"               <?= $filtreMotif === 'Maladie'               ? 'selected' : '' ?>>Maladie</option>
            <option value="Décès dans la famille"  <?= $filtreMotif === 'Décès dans la famille'  ? 'selected' : '' ?>>Décès dans la famille</option>
            <option value="Hospitalisation"        <?= $filtreMotif === 'Hospitalisation'        ? 'selected' : '' ?>>Hospitalisation</option>
            <option value="Accident"               <?= $filtreMotif === 'Accident'               ? 'selected' : '' ?>>Accident</option>
            <option value="Convocation officielle" <?= $filtreMotif === 'Convocation officielle' ? 'selected' : '' ?>>Convocation officielle</option>
            <option value="Problème de transport"  <?= $filtreMotif === 'Problème de transport'  ? 'selected' : '' ?>>Problème de transport</option>
            <option value="Raison personnelle"     <?= $filtreMotif === 'Raison personnelle'     ? 'selected' : '' ?>>Raison personnelle</option>
            <option value="Autre"                  <?= $filtreMotif === 'Autre'                  ? 'selected' : '' ?>>Autre</option>
        </select>
        <button type="submit">Filtrer</button>
        <a href="liste_demandes_enseignant.php" class="reset">✕ Réinitialiser</a>
    </form>

    <!-- Tableau -->
    <div class="table-card">
        <div class="table-header">
            <h2>Demandes de justificatifs</h2>
            <div class="meta">
                <span class="auto-delete-info">🗑️ Suppression auto après 1 mois</span>
                <span class="count"><?= count($demandes) ?> résultat<?= count($demandes) > 1 ? 's' : '' ?></span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Étudiant</th>
                    <th>Matricule</th>
                    <th>Email</th>
                    <th>Motif</th>
                    <th>Date absence</th>
                    <th>Statut</th>
                    <th>Suppression</th>
                    <th>Justificatif</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($demandes)): ?>
                <tr><td colspan="10"><div class="no-data"><div class="icon">📭</div>Aucune demande trouvée</div></td></tr>
            <?php else: ?>
                <?php foreach ($demandes as $d):
                    $traite   = $d['statut'] === 'Traité';
                    $rowClass = $traite ? 'row-traite' : 'row-attente';

                    // Calcul jours restants avant suppression
                    $expiryHtml = '—';
                    if ($traite && !empty($d['date_demande'])) {
                        $dateDemande = new DateTime($d['date_demande']);
                        $expiry      = clone $dateDemande;
                        $expiry->modify('+1 month');
                        $now         = new DateTime();
                        $diff        = $now->diff($expiry);
                        $joursRestants = (int)$diff->days;
                        if ($expiry > $now) {
                            $cls = $joursRestants <= 7 ? 'expiry soon' : 'expiry';
                            $expiryHtml = "<span class='$cls'>dans $joursRestants j.</span>";
                        } else {
                            $expiryHtml = "<span class='expiry soon'>Expiré</span>";
                        }
                    }

                    $dec = $d['decision_enseignant'] ?? '';
                    $cls = match($d['statut']) {
                        'En attente' => 'badge-attente',
                        'Traité'     => ($dec === 'Accepté' ? 'badge-accepte' : ($dec === 'Refusé' ? 'badge-refuse' : 'badge-traite')),
                        default      => 'badge-traite'
                    };
                ?>
                <tr class="<?= $rowClass ?>">
                    <td><b><?= $d['id'] ?></b></td>
                    <td><?= htmlspecialchars($d['nom_etudiant']) ?></td>
                    <td><span class="matricule"><?= htmlspecialchars($d['matricule'] ?? '—') ?></span></td>
                    <td><?= htmlspecialchars($d['email_etudiant']) ?></td>
                    <td><?= htmlspecialchars($d['motif'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($d['date_absence'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $cls ?>">
                            <?= htmlspecialchars($d['statut']) ?><?= $dec ? " — $dec" : "" ?>
                        </span>
                    </td>
                    <td><?= $expiryHtml ?></td>
                    <td>
                        <a class="btn-action btn-voir"
                           href="../etudiant/voir_fichier.php?file=<?= urlencode($d['fichier_justificatif']) ?>">📄 Voir</a>
                    </td>
                    <td>
                        <?php if ($d['statut'] === 'En attente'): ?>
                        <a class="btn-action btn-traiter" href="traiter_demande.php?id=<?= $d['id'] ?>">⚙ Traiter</a>
                        <?php else: ?>
                        <a class="btn-action btn-voir"    href="traiter_demande.php?id=<?= $d['id'] ?>">👁 Détails</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
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