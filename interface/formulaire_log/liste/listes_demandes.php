<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$pdo = new PDO(
    'mysql:host=localhost;dbname=administration;charset=utf8',
    'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ============================================================
//  ARCHIVAGE AUTOMATIQUE
//  Demandes traitées (Prête/Refusée) de plus de 3 mois
//  → copiées dans archives puis supprimées de demandes
// ============================================================
$pdo->exec("
    CREATE TABLE IF NOT EXISTS archives (
        id            INT(11) NOT NULL,
        demandeur_id  INT(11) DEFAULT NULL,
        document_type VARCHAR(100) DEFAULT NULL,
        description   TEXT DEFAULT NULL,
        status        VARCHAR(50) DEFAULT NULL,
        demande_le    DATETIME DEFAULT NULL,
        archive_le    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    INSERT INTO archives (id, demandeur_id, document_type, description, status, demande_le)
    SELECT id, demandeur_id, document_type, description, status, demande_le
    FROM demandes
    WHERE status IN ('Prête','Refusée')
    AND demande_le < DATE_SUB(NOW(), INTERVAL 3 MONTH)
    AND id NOT IN (SELECT id FROM archives)
");

$nb_archives_auto = $pdo->exec("
    DELETE FROM demandes
    WHERE status IN ('Prête','Refusée')
    AND demande_le < DATE_SUB(NOW(), INTERVAL 3 MONTH)
");

// ============================================================
//  CHANGEMENT DE STATUT
// ============================================================
$notification_message = '';
$message_popup = '';

if (isset($_POST['changer_statut'])) {
    $demande_id     = $_POST['demande_id'];
    $nouveau_status = $_POST['nouveau_status'];

    $stmt = $pdo->prepare("SELECT status FROM demandes WHERE id=?");
    $stmt->execute([$demande_id]);
    $ancien = $stmt->fetch();

    if ($ancien) {
        $pdo->prepare("UPDATE demandes SET status=? WHERE id=?")->execute([$nouveau_status, $demande_id]);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS historique_statuts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                demande_id INT,
                ancien_status VARCHAR(50),
                nouveau_status VARCHAR(50),
                modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->prepare("INSERT INTO historique_statuts (demande_id, ancien_status, nouveau_status) VALUES (?,?,?)")
            ->execute([$demande_id, $ancien['status'], $nouveau_status]);

        if (in_array($nouveau_status, ['Prête', 'Refusée'])) {
            $email_sql = $pdo->prepare("
                SELECT demandeurs.email, demandeurs.nom, demandeurs.prenom, demandes.document_type
                FROM demandes
                JOIN demandeurs ON demandes.demandeur_id = demandeurs.id
                WHERE demandes.id=?
            ");
            $email_sql->execute([$demande_id]);
            $etudiant = $email_sql->fetch();

            if ($etudiant) {
                require 'PHPMailer/src/PHPMailer.php';
                require 'PHPMailer/src/SMTP.php';
                require 'PHPMailer/src/Exception.php';

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'ania.moulouel@fgei.ummto.dz';
                    $mail->Password   = 'szlo yhqp tlxg vrmy';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;
                    $mail->setFrom('ania.moulouel@fgei.ummto.dz', 'Administration');
                    $mail->addAddress($etudiant['email']);
                    $mail->CharSet = 'UTF-8';

                    if ($nouveau_status == 'Prête') {
                        $mail->Subject = "📄 Document prêt";
                        $mail->Body    = "Bonjour " . $etudiant['prenom'] . ",\n\nVotre document '" . $etudiant['document_type'] . "' est maintenant disponible.\n\nCordialement,\nAdministration";
                    } else {
                        $mail->Subject = "❌ Demande refusée";
                        $mail->Body    = "Bonjour " . $etudiant['prenom'] . ",\n\nVotre demande concernant '" . $etudiant['document_type'] . "' a été refusée.\n\nCordialement,\nAdministration";
                    }
                    $mail->send();
                    $message_popup = "✅ Statut mis à jour — email envoyé.";
                } catch (Exception $e) {
                    $message_popup = "✅ Statut mis à jour — email non envoyé.";
                }
            }
        } else {
            $message_popup = "✅ Statut mis à jour.";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?msg=" . urlencode($message_popup));
    exit;
}

if (isset($_GET['msg'])) {
    $notification_message = htmlspecialchars($_GET['msg']);
}

// ============================================================
//  FILTRES
// ============================================================
$search        = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');

$sql = "
    SELECT demandes.id, demandeurs.nom, demandeurs.prenom, demandeurs.email,
           demandes.document_type, demandes.description, demandes.status, demandes.demande_le
    FROM demandes
    JOIN demandeurs ON demandes.demandeur_id = demandeurs.id
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (demandeurs.nom LIKE ? OR demandeurs.prenom LIKE ? OR demandes.document_type LIKE ?)";
    $s = "%$search%";
    $params = [$s, $s, $s];
}
if ($status_filter !== '') {
    $sql .= " AND demandes.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY demandes.demande_le DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = $pdo->query("SELECT status, COUNT(*) as nb FROM demandes GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$total = $pdo->query("SELECT COUNT(*) FROM demandes")->fetchColumn();
$nb_archives_total = $pdo->query("SELECT COUNT(*) FROM archives")->fetchColumn();

// Vacation & Convention data
$vacation_ids = []; $vacations_data = []; $demande_to_vac = [];
$cnv_ids = []; $cnv_data = []; $demande_to_cnv = [];

foreach ($demandes as $d) {
    if ($d['document_type'] === 'Autorisation de vacation' && preg_match('/ID vacation\s*:\s*(\d+)/i', $d['description'] ?? '', $m))
        $vacation_ids[(int)$d['id']] = (int)$m[1];
    if ($d['document_type'] === 'Convention de stage' && preg_match('/ID cnv\s*:\s*(\d+)/i', $d['description'] ?? '', $m))
        $cnv_ids[(int)$d['id']] = (int)$m[1];
}

if (!empty($vacation_ids)) {
    $ph = implode(',', array_fill(0, count(array_unique(array_values($vacation_ids))), '?'));
    $stmtV = $pdo->prepare("SELECT * FROM vacation WHERE id IN ($ph)");
    $stmtV->execute(array_unique(array_values($vacation_ids)));
    foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $v) $vacations_data[(int)$v['id']] = $v;
    foreach ($vacation_ids as $did => $vid) if (isset($vacations_data[$vid])) $demande_to_vac[$did] = $vacations_data[$vid];
}

if (!empty($cnv_ids)) {
    $ph = implode(',', array_fill(0, count(array_unique(array_values($cnv_ids))), '?'));
    $stmtC = $pdo->prepare("SELECT * FROM convention_stage WHERE id IN ($ph)");
    $stmtC->execute(array_unique(array_values($cnv_ids)));
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $c) $cnv_data[(int)$c['id']] = $c;
    foreach ($cnv_ids as $did => $cid) if (isset($cnv_data[$cid])) $demande_to_cnv[$did] = $cnv_data[$cid];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestion des demandes — Administration</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body { background:#f1f5f9; font-family:'Inter','Segoe UI',sans-serif; font-size:14px; color:#1e293b; }

/* ── TOPBAR ── */
.topbar {
    background:#fff;
    border-bottom:1px solid #e8eef6;
    padding:0 32px;
    height:64px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    position:sticky;
    top:0;
    z-index:100;
    box-shadow:0 1px 8px rgba(0,0,0,.06);
}

.topbar-left { display:flex; align-items:center; gap:16px; }

.topbar-title {
    font-size:17px;
    font-weight:700;
    color:#0f172a;
    display:flex;
    align-items:center;
    gap:10px;
}

.topbar-title::before {
    content:'';
    display:inline-block;
    width:4px;
    height:22px;
    background:linear-gradient(to bottom,#3b82f6,#1d4ed8);
    border-radius:99px;
}

.topbar-right { display:flex; align-items:center; gap:12px; }

.admin-badge {
    display:flex;
    align-items:center;
    gap:10px;
    background:linear-gradient(135deg,#eef4fb,#e0ecfa);
    border:1px solid #c8d8ec;
    padding:8px 16px;
    border-radius:50px;
    font-size:13px;
    font-weight:600;
    color:#1a3a5c;
}

.admin-badge .dot {
    width:8px; height:8px;
    background:#10b981;
    border-radius:50%;
    box-shadow:0 0 6px rgba(16,185,129,.5);
    flex-shrink:0;
}

.btn-logout {
    display:inline-flex; align-items:center; gap:7px;
    background:#1a3a5c; color:#fff;
    padding:9px 20px; border-radius:50px;
    text-decoration:none; font-size:13px; font-weight:600;
    transition:background .2s;
}
.btn-logout:hover { background:#2b5080; }

/* ── MAIN ── */
.main { max-width:1200px; margin:28px auto; padding:0 24px; }

/* ── KPI ── */
.kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }

.kpi {
    background:#fff;
    border-radius:14px;
    padding:18px 22px;
    border:1px solid #e8eef6;
    box-shadow:0 2px 8px rgba(0,0,0,.04);
    display:flex;
    align-items:center;
    gap:14px;
}

.kpi-icon {
    width:44px; height:44px;
    border-radius:12px;
    display:flex; align-items:center; justify-content:center;
    font-size:18px;
    flex-shrink:0;
}

.kpi-val { font-size:26px; font-weight:700; color:#0f172a; line-height:1; }
.kpi-lbl { font-size:11px; color:#6b82a0; margin-top:3px; text-transform:uppercase; letter-spacing:.5px; }

/* Archivage banner */
.archive-banner {
    background:linear-gradient(135deg,#eff6ff,#dbeafe);
    border:1px solid #bfdbfe;
    border-radius:12px;
    padding:12px 18px;
    margin-bottom:20px;
    display:flex;
    align-items:center;
    gap:12px;
    font-size:13px;
    color:#1e40af;
}

.archive-banner.active {
    background:linear-gradient(135deg,#fce7f3,#fdf2f8);
    border-color:#f9a8d4;
    color:#9d174d;
}

/* ── FILTRES ── */
.filters {
    background:#fff;
    border:1px solid #e8eef6;
    border-radius:14px;
    padding:14px 18px;
    margin-bottom:18px;
    display:flex;
    gap:10px;
    align-items:center;
    flex-wrap:wrap;
}

.filters input, .filters select {
    padding:9px 14px;
    border:1.5px solid #e8eef6;
    border-radius:9px;
    font-size:13px;
    font-family:'Inter',sans-serif;
    color:#1e293b;
    outline:none;
    transition:border-color .2s;
}

.filters input { flex:1; min-width:200px; }
.filters input:focus, .filters select:focus { border-color:#3b82f6; }

.btn-filter {
    padding:9px 20px;
    background:#1a3a5c; color:#fff;
    border:none; border-radius:9px;
    font-size:13px; font-weight:600;
    font-family:'Inter',sans-serif;
    cursor:pointer;
    transition:background .2s;
}
.btn-filter:hover { background:#2b5080; }

.btn-reset-link {
    font-size:12px; color:#6b82a0;
    text-decoration:none;
    padding:9px 10px;
}
.btn-reset-link:hover { color:#ef4444; }

/* ── TABLE ── */
.table-card {
    background:#fff;
    border-radius:16px;
    border:1px solid #e8eef6;
    overflow:hidden;
    box-shadow:0 2px 12px rgba(0,0,0,.05);
}

.table-header {
    padding:16px 22px;
    border-bottom:1px solid #e8eef6;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

.table-header h2 {
    font-size:15px;
    font-weight:600;
    color:#0f172a;
}

.count-badge {
    background:#eef4fb;
    color:#1a3a5c;
    font-size:12px;
    font-weight:600;
    padding:4px 12px;
    border-radius:20px;
}

table { width:100%; border-collapse:collapse; }

thead th {
    background:#f7fafd;
    padding:11px 16px;
    text-align:left;
    font-size:11px;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:.8px;
    color:#6b82a0;
    border-bottom:1px solid #e8eef6;
}

tbody td {
    padding:13px 16px;
    font-size:13px;
    border-bottom:1px solid #f5f8fc;
    vertical-align:middle;
}

tbody tr:hover { background:#fafcff; }
tbody tr:last-child td { border-bottom:none; }

.badge {
    display:inline-block;
    padding:4px 11px;
    border-radius:20px;
    font-size:11px;
    font-weight:600;
}

.badge-Soumise       { background:#fef9c3; color:#854d0e; }
.badge-En_traitement { background:#dbeafe; color:#1d4ed8; }
.badge-En_validation { background:#ffedd5; color:#c2410c; }
.badge-Prête         { background:#dcfce7; color:#15803d; }
.badge-Refusée       { background:#fee2e2; color:#b91c1c; }

.action-wrap { display:flex; gap:8px; align-items:center;  }
.action-wrap select {
    padding:6px 10px;
    border:1.5px solid #e8eef6;
    border-radius:7px;
    font-size:12px;
    cursor:pointer;
    outline:none;
    font-family:'Inter',sans-serif;
}

.btn-update {
    background:#1a3a5c; color:#fff;
    border:none; padding:7px 14px;
    border-radius:7px; cursor:pointer;
    font-size:12px; font-weight:600;
    font-family:'Inter',sans-serif;
    transition:background .2s;
    white-space:nowrap;
}
.btn-update:hover { background:#2b5080; }

.btn-doc {
    display:inline-flex; align-items:center; gap:5px;
    background:#eef4fb; color:#1a3a5c;
    border:1px solid #c8d8ec;
    padding:6px 12px; border-radius:7px;
    cursor:pointer; font-size:12px; font-weight:600;
    font-family:'Inter',sans-serif;
    transition:background .2s;
    white-space:nowrap;
}
.btn-doc:hover { background:#dce8f5; }
.btn-doc-cnv { background:#fef6e4; color:#b8973a; border-color:#f0d98a; }
.btn-doc-cnv:hover { background:#fdecc4; }

.empty { padding:50px; text-align:center; color:#94a3b8; font-size:14px; }

/* NOTIFICATION */
.notif-bar {
    background:#dcfce7;
    color:#15803d;
    border:1px solid #bbf7d0;
    border-radius:10px;
    padding:12px 18px;
    margin-bottom:18px;
    font-weight:500;
    font-size:13px;
}

/* MODAL */
.modal-overlay {
    display:none; position:fixed; inset:0;
    background:rgba(0,0,0,.5);
    z-index:1000; align-items:center;
    justify-content:center; padding:20px;
    backdrop-filter:blur(4px);
}
.modal-overlay.open { display:flex; }
.modal-box {
    background:#fff; border-radius:16px;
    width:100%; max-width:860px;
    max-height:92vh; display:flex;
    flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.25);
    overflow:hidden;
}
.modal-head {
    background:#1a3a5c; color:#fff;
    padding:16px 24px;
    display:flex; align-items:center;
    justify-content:space-between; flex-shrink:0;
}
.modal-head h2 { font-size:15px; font-weight:600; }
.modal-head-actions { display:flex; gap:10px; }
.btn-print {
    background:#b8973a; color:#fff; border:none;
    padding:7px 16px; border-radius:7px;
    cursor:pointer; font-size:13px; font-weight:600;
    display:flex; align-items:center; gap:6px;
}
.btn-close-modal {
    background:rgba(255,255,255,.15); color:#fff;
    border:none; width:32px; height:32px;
    border-radius:7px; cursor:pointer;
    font-size:18px; display:flex;
    align-items:center; justify-content:center;
}
.modal-body { overflow-y:auto; padding:28px 32px; flex:1; }

.doc-vacation { font-family:'Times New Roman',Georgia,serif; font-size:13.5px; color:#1c2330; line-height:1.6; }
.doc-sep { height:2px; background:linear-gradient(to right,#b8973a,#1a3a5c,transparent); margin:12px 0 22px; border:none; }
.doc-titre-box { text-align:center; background:#1a3a5c; padding:12px 20px; margin:0 0 26px; }
.doc-titre-box h1 { font-size:15px; font-weight:700; color:#fff; letter-spacing:2px; text-transform:uppercase; }
.doc-section-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.doc-section-num { background:#1a3a5c; color:#fff; font-size:12px; font-weight:700; min-width:26px; height:26px; display:flex; align-items:center; justify-content:center; border-radius:3px; flex-shrink:0; }
.doc-section-label { font-size:11.5px; font-weight:700; color:#1a3a5c; text-transform:uppercase; letter-spacing:.4px; }
.doc-section-line { flex:1; height:1px; background:#e8eff7; }
.doc-champ-ligne { display:flex; align-items:baseline; gap:6px; margin-bottom:9px; }
.doc-label { white-space:nowrap; font-weight:600; color:#4a5568; }
.doc-val { border-bottom:1.5px solid #c2c8d2; flex:1; min-height:20px; padding:1px 4px; color:#1c2330; outline:none; font-family:inherit; display:inline-block; }
.doc-sep-section { height:1.5px; background:linear-gradient(to right,#e8eff7,#c2c8d2,#e8eff7); margin:20px 0 18px; border:none; }
.doc-corps { font-size:13px; line-height:2.1; text-align:justify; text-indent:20px; margin-bottom:14px; }
.doc-sig-bloc { text-align:center; margin-top:16px; }
.doc-sig-bloc p { font-size:13px; color:#4a5568; line-height:1.8; }
.doc-sig-label { font-size:11.5px; font-weight:700; color:#4a5568; text-transform:uppercase; letter-spacing:.4px; }
.doc-sig-zone { width:160px; height:54px; border:1px solid #c2c8d2; border-radius:4px; background:#fafafa; margin:8px auto 0; }

@media print {
    body > *:not(#printModal) { display:none !important; }
    .modal-overlay { display:flex !important; position:static !important; background:#fff !important; padding:0 !important; }
    .modal-box { max-height:none !important; box-shadow:none !important; border-radius:0 !important; }
    .modal-head { display:none !important; }
    .modal-body { padding:10mm 14mm !important; }
}
</style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-title">Gestion des demandes</div>
    </div>
    <div class="topbar-right">
        <div class="admin-badge">
            <span class="dot"></span>
            <?= htmlspecialchars(($_SESSION['prenom'] ?? '') . ' ' . ($_SESSION['nom'] ?? 'Administrateur')) ?>
        </div>
        <button onclick="ouvrirModalLogout()" class="btn-logout" style="cursor:pointer;border:none;font-family:'Inter',sans-serif;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Déconnexion
        </button>
    </div>
</div>

<div class="main">

    <!-- Notification statut -->
    <?php if ($notification_message): ?>
    <div class="notif-bar"><?= $notification_message ?></div>
    <?php endif; ?>

    <!-- Bannière archivage -->
    <?php if ($nb_archives_auto > 0): ?>
    <div class="archive-banner active">
        🗂 <strong><?= $nb_archives_auto ?> demande<?= $nb_archives_auto > 1 ? 's' : '' ?></strong>
        archivée<?= $nb_archives_auto > 1 ? 's' : '' ?> automatiquement (plus de 3 mois).
    </div>
    <?php else: ?>
    <div class="archive-banner">
        🕐 Les demandes traitées sont <strong>archivées automatiquement après 3 mois</strong>.
        Total archivées : <strong><?= $nb_archives_total ?></strong>
    </div>
    <?php endif; ?>

    <!-- KPI -->
    <div class="kpi-row">
        <?php
        $par_statut = [];
        foreach ($stats as $s) $par_statut[$s['status']] = (int)$s['nb'];
        $kpis = [
            ['icon'=>'📁','bg'=>'#eef4fb','val'=>$total,'lbl'=>'Total demandes'],
            ['icon'=>'⏳','bg'=>'#fff8e1','val'=>$par_statut['Soumise']??0,'lbl'=>'En attente'],
            ['icon'=>'✅','bg'=>'#f0fdf4','val'=>($par_statut['Prête']??0),'lbl'=>'Prêtes'],
            ['icon'=>'🗂','bg'=>'#f5f3ff','val'=>$nb_archives_total,'lbl'=>'Archivées'],
        ];
        foreach ($kpis as $k):
        ?>
        <div class="kpi">
            <div class="kpi-icon" style="background:<?= $k['bg'] ?>"><?= $k['icon'] ?></div>
            <div>
                <div class="kpi-val"><?= $k['val'] ?></div>
                <div class="kpi-lbl"><?= $k['lbl'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtres -->
    <form method="GET" class="filters">
        <input type="text" name="search" placeholder="🔍 Rechercher par nom, email, document…" value="<?= htmlspecialchars($search) ?>">
        <select name="status_filter">
            <option value="">Tous les statuts</option>
            <option value="Soumise"       <?= $status_filter==='Soumise'       ?'selected':'' ?>>⏳ Soumise</option>
            <option value="En traitement" <?= $status_filter==='En traitement' ?'selected':'' ?>>🔄 En traitement</option>
            <option value="En validation" <?= $status_filter==='En validation' ?'selected':'' ?>>🔍 En validation</option>
            <option value="Prête"         <?= $status_filter==='Prête'         ?'selected':'' ?>>✅ Prête</option>
            <option value="Refusée"       <?= $status_filter==='Refusée'       ?'selected':'' ?>>❌ Refusée</option>
        </select>
        <button type="submit" class="btn-filter">Filtrer</button>
        <a href="listes_demandes.php" class="btn-reset-link">✕ Réinitialiser</a>
    </form>

    <!-- Tableau -->
    <div class="table-card">
        <div class="table-header">
            <h2>Demandes en cours</h2>
            <span class="count-badge"><?= count($demandes) ?> résultat<?= count($demandes) > 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($demandes)): ?>
        <div class="empty">📭 Aucune demande trouvée.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Demandeur</th>
                    <th>Email</th>
                    <th>Document</th>
                    <th>Voir</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($demandes as $d):
                $did      = (int)$d['id'];
                $badgeCls = 'badge-' . str_replace(' ', '_', $d['status']);
                $has_vac  = isset($demande_to_vac[$did]);
                $has_cnv  = isset($demande_to_cnv[$did]);
            ?>
            <tr>
                <td><strong><?= $did ?></strong></td>
                <td><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></td>
                <td><?= htmlspecialchars($d['email']) ?></td>
                <td><?= htmlspecialchars($d['document_type']) ?></td>
                <td>
                    <?php if ($has_vac): ?>
                    <button class="btn-doc" onclick="ouvrirModal(<?= $did ?>,'vac')">📄 Voir</button>
                    <?php elseif ($has_cnv): ?>
                    <button class="btn-doc btn-doc-cnv" onclick="ouvrirModal(<?= $did ?>,'cnv')">📄 Voir</button>
                    <?php else: ?>
                    <span style="color:#cbd5e1">—</span>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= $badgeCls ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                <td><?= date('d/m/Y H:i', strtotime($d['demande_le'])) ?></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="demande_id" value="<?= $did ?>">
                        <div class="action-wrap">
                            <select name="nouveau_status">
                                <option value="Soumise"       <?= $d['status']==='Soumise'       ?'selected':'' ?>>Soumise</option>
                                <option value="En traitement" <?= $d['status']==='En traitement' ?'selected':'' ?>>En traitement</option>
                                <option value="En validation" <?= $d['status']==='En validation' ?'selected':'' ?>>En validation</option>
                                <option value="Prête"         <?= $d['status']==='Prête'         ?'selected':'' ?>>Prête</option>
                                <option value="Refusée"       <?= $d['status']==='Refusée'       ?'selected':'' ?>>Refusée</option>
                            </select>
                            <button type="submit" name="changer_statut" class="btn-update">Mettre à jour</button>
                        </div>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL -->
<div class="modal-overlay" id="printModal">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="modalTitle">📄 Document</h2>
            <div class="modal-head-actions">
                <button class="btn-print" onclick="window.print()">🖨 Imprimer</button>
                <button class="btn-close-modal" onclick="fermerModal()">✕</button>
            </div>
        </div>
        <div class="modal-body">
            <div class="doc-vacation" id="docModalContent"></div>
        </div>
    </div>
</div>

<script>
var VACATIONS   = <?= json_encode($demande_to_vac,   JSON_UNESCAPED_UNICODE) ?>;
var CONVENTIONS = <?= json_encode($demande_to_cnv, JSON_UNESCAPED_UNICODE) ?>;

function ouvrirModal(id, type) {
    var data  = type === 'vac' ? VACATIONS[id] : CONVENTIONS[id];
    var title = document.getElementById('modalTitle');
    if (!data) { alert('Données introuvables pour la demande #' + id); return; }
    title.textContent = type === 'vac' ? '📄 Autorisation de Vacation' : '📄 Convention de Stage';
    document.getElementById('docModalContent').innerHTML = type === 'vac' ? buildVacation(data) : buildConvention(data);
    document.getElementById('printModal').classList.add('open');
}

function fermerModal() { document.getElementById('printModal').classList.remove('open'); }

document.getElementById('printModal').addEventListener('click', function(e) {
    if (e.target === this) fermerModal();
});

function fmtDate(s) {
    if (!s) return '';
    var d = new Date(s);
    return isNaN(d) ? s : ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+d.getFullYear();
}

function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function champ(label, val) {
    return '<div class="doc-champ-ligne"><span class="doc-label">'+label+'</span><span class="doc-val" contenteditable="true">'+esc(val)+'</span></div>';
}

function inline(val, w) {
    return '<span class="doc-val" contenteditable="true" style="min-width:'+w+'px">'+esc(val)+'</span>';
}

function buildVacation(v) {
    return '<div style="font-size:11.5px;color:#4a5568;margin-bottom:6px"><strong style="color:#1a3a5c">Université Mouloud Mammeri de Tizi-Ouzou</strong><br>Faculté de Génie Électrique et d\'Informatique</div>'
        +'<hr class="doc-sep">'
        +'<div class="doc-titre-box"><h1>Demande d\'Autorisation de Vacation</h1></div>'
        +'<div class="doc-section-header"><span class="doc-section-num">I</span><span class="doc-section-label">Renseignements intervenant</span><div class="doc-section-line"></div></div>'
        +champ('Nom :',v.nom)+champ('Prénom :',v.prenom)+champ('Date/lieu naissance :',v.date_lieu_naissance)
        +champ('Grade :',v.grade)+champ('Diplôme :',v.diplome)+champ('CCP :',v.ccp)+champ('Adresse :',v.adresse)
        +champ('À compter du :',fmtDate(v.date_debut))+champ('Département :',v.departement_accueil)+champ('Faculté :',v.faculte)
        +'<div class="doc-sig-bloc"><p>Fait à Tizi-Ouzou, le '+inline(fmtDate(v.date_demande),120)+'</p><p class="doc-sig-label">Signature</p><div class="doc-sig-zone"></div></div>'
        +'<hr class="doc-sep-section">'
        +'<div class="doc-section-header"><span class="doc-section-num">II</span><span class="doc-section-label">Chef du département d\'origine</span><div class="doc-section-line"></div></div>'
        +'<p class="doc-corps">Je soussigné, chef du département '+inline('',140)+' atteste que l\'enseignant susnommé assure la totalité de son volume horaire. J\'émets un avis favorable pour '+inline('',50)+' heures/semaine.</p>'
        +'<div class="doc-sig-bloc"><p>Fait à Tizi-Ouzou, le '+inline('',120)+'</p><p class="doc-sig-label">Cachet &amp; Signature</p><div class="doc-sig-zone"></div></div>'
        +'<hr class="doc-sep-section">'
        +'<div class="doc-section-header"><span class="doc-section-num">III</span><span class="doc-section-label">Chef du département d\'accueil</span><div class="doc-section-line"></div></div>'
        +'<p class="doc-corps">Le chef du département '+inline(v.departement_accueil,140)+' atteste que l\'enseignant a été installé le '+inline('',110)+' pour '+inline('',50)+' heures/semaine.</p>'
        +'<div class="doc-sig-bloc"><p>Fait à Tizi-Ouzou, le '+inline('',120)+'</p><p class="doc-sig-label">Cachet &amp; Signature</p><div class="doc-sig-zone"></div></div>';
}

function buildConvention(v) {
    return '<div style="font-size:11.5px;color:#4a5568;margin-bottom:6px"><strong style="color:#1a3a5c">République Algérienne Démocratique et Populaire</strong><br>Université Mouloud MAMMERI – Tizi-Ouzou<br>Faculté du Génie Électrique et d\'Informatique</div>'
        +'<hr class="doc-sep">'
        +'<div class="doc-titre-box"><h1>Convention de Stage</h1></div>'
        +'<div class="doc-section-header"><span class="doc-section-num">1</span><span class="doc-section-label">Informations</span><div class="doc-section-line"></div></div>'
        +champ('Email étudiant :',v.email_etudiant)+champ('Organisme d\'accueil :',v.organisme_accueil)
        +champ('Date de début :',fmtDate(v.date_debut))+champ('Statut :',v.statut)
        +'<div class="doc-sig-bloc"><p>Fait à Tizi-Ouzou, le '+inline(fmtDate(v.date_demande),120)+'</p><p class="doc-sig-label">Signature</p><div class="doc-sig-zone"></div></div>';
}
</script>


<!-- MODAL DÉCONNEXION -->
<div id="modalLogout" style="display:none;position:fixed;inset:0;background:rgba(15,23,46,.55);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:24px;padding:44px 48px;text-align:center;max-width:400px;width:100%;box-shadow:0 32px 80px rgba(0,0,0,.2);animation:popInLogout .28s cubic-bezier(.34,1.56,.64,1);">
        <style>@keyframes popInLogout{from{transform:scale(.82);opacity:0}to{transform:scale(1);opacity:1}}</style>
        <div style="font-size:52px;margin-bottom:16px;">👋</div>
        <h3 style="font-size:19px;font-weight:700;color:#0f172a;margin-bottom:8px;font-family:'Inter',sans-serif;">Quitter votre session ?</h3>
        <p style="font-size:14px;color:#64748b;margin-bottom:30px;line-height:1.6;font-family:'Inter',sans-serif;">Vous allez être redirigé vers la page d'accueil.</p>
        <div style="display:flex;gap:12px;justify-content:center;">
            <button onclick="fermerModalLogout()" style="padding:12px 30px;border-radius:50px;font-size:14px;font-weight:600;cursor:pointer;border:1.5px solid #e2e8f0;background:#f1f5f9;color:#475569;font-family:'Inter',sans-serif;transition:all .2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Annuler</button>
            <button onclick="deconnecterLogout()" style="padding:12px 30px;border-radius:50px;font-size:14px;font-weight:600;cursor:pointer;border:none;background:linear-gradient(135deg,#1a3a5c,#2b5080);color:white;font-family:'Inter',sans-serif;box-shadow:0 4px 18px rgba(26,58,92,.35);transition:all .2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">Oui, déconnecter</button>
        </div>
    </div>
</div>
<script>
function ouvrirModalLogout(){document.getElementById('modalLogout').style.display='flex';}
function fermerModalLogout(){document.getElementById('modalLogout').style.display='none';}
function deconnecterLogout(){document.body.style.transition='opacity 0.4s ease';document.body.style.opacity='0';setTimeout(function(){window.location.href='../../interface.html';},400);}
document.getElementById('modalLogout').addEventListener('click',function(e){if(e.target===this)fermerModalLogout();});
</script>

</body>
</html>