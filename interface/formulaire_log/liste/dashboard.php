<?php
session_start();

// Protection : seule la cheffe peut accéder
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cheffe_departement') {
    header("Location: ../login.php");
    exit;
}

$pdo = new PDO(
    'mysql:host=localhost;dbname=administration;charset=utf8',
    'root', '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ==========================
// STATS GLOBALES
// ==========================
$stats = $pdo->query("SELECT status, COUNT(*) as nb FROM demandes GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
$total = $pdo->query("SELECT COUNT(*) FROM demandes")->fetchColumn();

$par_statut = [];
foreach ($stats as $s) {
    $par_statut[$s['status']] = (int)$s['nb'];
}

$traitees  = ($par_statut['Prête'] ?? 0) + ($par_statut['Refusée'] ?? 0);
$enAttente = $par_statut['Soumise'] ?? 0;
$enCours   = ($par_statut['En traitement'] ?? 0) + ($par_statut['En validation'] ?? 0);

$pct_traitees  = $total > 0 ? round($traitees  / $total * 100) : 0;
$pct_attente   = $total > 0 ? round($enAttente / $total * 100) : 0;
$pct_cours     = $total > 0 ? round($enCours   / $total * 100) : 0;

// ==========================
// STATS PAR MOIS (6 derniers mois)
// ==========================
$mois_stats = $pdo->query("
    SELECT
        DATE_FORMAT(demande_le, '%Y-%m') as mois,
        DATE_FORMAT(demande_le, '%b %Y') as mois_label,
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('Prête','Refusée') THEN 1 ELSE 0 END) as traitees
    FROM demandes
    WHERE demande_le >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY mois, mois_label
    ORDER BY mois ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// STATS PAR TYPE DE DOCUMENT
// ==========================
$par_type = $pdo->query("
    SELECT document_type, COUNT(*) as nb
    FROM demandes
    GROUP BY document_type
    ORDER BY nb DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// TOUTES LES DEMANDES (lecture seule)
// ==========================
$search        = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');

$sql = "
    SELECT demandes.id, demandeurs.nom, demandeurs.prenom, demandeurs.email,
           demandes.document_type, demandes.status, demandes.demande_le
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

$req = $pdo->prepare($sql);
$req->execute($params);
$demandes = $req->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord — Cheffe de Département</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body { background:#f1f5f9; font-family:'Inter','Segoe UI',sans-serif; font-size:14px; color:#1e293b; padding:28px; }

/* HEADER */
.header { display:flex; justify-content:space-between; align-items:center; margin-bottom:28px; flex-wrap:wrap; gap:16px; }
.header h1 { color:#0f172a; font-size:24px; font-weight:700; }
.header-right { display:flex; align-items:center; gap:14px; }

.bonjour-badge { display:flex; align-items:center; gap:12px; background:linear-gradient(135deg,#6b9fd4,#7eb3e8); color:white; padding:10px 18px 10px 10px; border-radius:14px; box-shadow:0 4px 14px rgba(107,159,212,.4); }
.bonjour-avatar { width:42px; height:42px; border-radius:10px; background:rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.bonjour-avatar svg { width:22px; height:22px; }
.bonjour-text strong { font-size:14px; font-weight:700; display:block; }
.bonjour-text span { font-size:11px; opacity:.85; }

.logout { display:inline-flex; align-items:center; gap:8px; background:#1e3a5f; color:white; padding:10px 22px; border-radius:50px; text-decoration:none; font-weight:600; font-size:14px; transition:background .2s; }
.logout:hover { background:#162d4a; }
.logout svg { width:16px; height:16px; }

.badge-role { background:#e0f2fe; color:#0369a1; font-size:11px; font-weight:700; padding:4px 12px; border-radius:20px; letter-spacing:0.5px; }

/* STATS KPI */
.kpi-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:18px; margin-bottom:28px; }
.kpi-card { background:white; border-radius:14px; padding:20px 24px; border:1px solid #dce8f5; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.kpi-label { font-size:12px; color:#6b82a0; font-weight:500; margin-bottom:8px; text-transform:uppercase; letter-spacing:0.5px; }
.kpi-value { font-size:32px; font-weight:700; color:#0f172a; margin-bottom:6px; }
.kpi-sub { font-size:12px; color:#6b82a0; }

/* BARRE PROGRESSION */
.progress-bar-wrap { height:8px; background:#e8eef6; border-radius:99px; margin-top:10px; overflow:hidden; }
.progress-bar { height:100%; border-radius:99px; transition:width 1s ease; }
.bar-green  { background:linear-gradient(to right,#10b981,#059669); }
.bar-orange { background:linear-gradient(to right,#f59e0b,#d97706); }
.bar-blue   { background:linear-gradient(to right,#3b82f6,#2563eb); }
.bar-gray   { background:#cbd5e1; }

/* GRAPHIQUES */
.charts-row { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:28px; }
.chart-card { background:white; border-radius:14px; padding:22px 24px; border:1px solid #dce8f5; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.chart-title { font-size:15px; font-weight:600; color:#0f172a; margin-bottom:18px; }

/* FILTRES */
.filters { background:white; padding:16px 20px; border-radius:14px; margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:center; border:1px solid #dce8f5; }
.filters input, .filters select { padding:9px 14px; border:1.5px solid #dce8f5; border-radius:9px; font-size:13px; font-family:'Inter',sans-serif; outline:none; transition:border-color .2s; }
.filters input { flex:1; min-width:180px; }
.filters input:focus, .filters select:focus { border-color:#3b82f6; }
.btn-filter { padding:9px 20px; background:#1e3a5f; color:white; border:none; border-radius:9px; font-size:13px; cursor:pointer; font-family:'Inter',sans-serif; font-weight:600; }

/* TABLE */
.table-card { background:white; border-radius:14px; border:1px solid #dce8f5; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.table-header { padding:16px 24px; border-bottom:1px solid #dce8f5; display:flex; justify-content:space-between; align-items:center; }
.table-header h2 { font-size:15px; font-weight:600; color:#0f172a; }
.readonly-badge { background:#fef9c3; color:#854d0e; font-size:11px; font-weight:600; padding:4px 10px; border-radius:20px; }

table { width:100%; border-collapse:collapse; }
thead th { background:#f7fafd; padding:11px 16px; text-align:left; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.8px; color:#6b82a0; border-bottom:1px solid #dce8f5; }
tbody td { padding:13px 16px; font-size:13px; border-bottom:1px solid #f2f6fb; vertical-align:middle; }
tbody tr:hover { background:#fafcff; }
tbody tr:last-child td { border-bottom:none; }

.badge { display:inline-block; padding:4px 11px; border-radius:20px; font-size:11px; font-weight:600; }
.badge-Soumise        { background:#fef9c3; color:#854d0e; }
.badge-En_traitement  { background:#dbeafe; color:#1d4ed8; }
.badge-En_validation  { background:#ffedd5; color:#c2410c; }
.badge-Prête          { background:#dcfce7; color:#15803d; }
.badge-Refusée        { background:#fee2e2; color:#b91c1c; }

.no-action { color:#94a3b8; font-size:12px; font-style:italic; }
.empty { padding:50px; text-align:center; color:#94a3b8; }
</style>
</head>
<body>

<div class="header">
    <div>
        <h1>📊 Tableau de bord</h1>
        <span class="badge-role">👁 MODE LECTURE SEULE — Cheffe de Département</span>
    </div>
    <div class="header-right">
        <div class="bonjour-badge">
            <div class="bonjour-avatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
            </div>
            <div class="bonjour-text">
                <strong><?= htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']) ?></strong>
                <span>Cheffe de Département</span>
            </div>
        </div>
        <button onclick="ouvrirModalLogout()" class="logout" style="cursor:pointer;border:none;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Déconnexion
        </button>
    </div>
</div>

<!-- KPI -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total demandes</div>
        <div class="kpi-value"><?= $total ?></div>
        <div class="kpi-sub">Toutes périodes confondues</div>
        <div class="progress-bar-wrap"><div class="progress-bar bar-blue" style="width:100%"></div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Traitées</div>
        <div class="kpi-value" style="color:#15803d"><?= $traitees ?></div>
        <div class="kpi-sub"><?= $pct_traitees ?>% du total</div>
        <div class="progress-bar-wrap"><div class="progress-bar bar-green" style="width:<?= $pct_traitees ?>%"></div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">En attente</div>
        <div class="kpi-value" style="color:#d97706"><?= $enAttente ?></div>
        <div class="kpi-sub"><?= $pct_attente ?>% du total</div>
        <div class="progress-bar-wrap"><div class="progress-bar bar-orange" style="width:<?= $pct_attente ?>%"></div></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">En cours</div>
        <div class="kpi-value" style="color:#2563eb"><?= $enCours ?></div>
        <div class="kpi-sub"><?= $pct_cours ?>% du total</div>
        <div class="progress-bar-wrap"><div class="progress-bar bar-blue" style="width:<?= $pct_cours ?>%"></div></div>
    </div>
</div>

<!-- GRAPHIQUES -->
<div class="charts-row">
    <div class="chart-card">
        <div class="chart-title">📈 Évolution des demandes — 6 derniers mois</div>
        <canvas id="chartMois" height="100"></canvas>
    </div>
    <div class="chart-card">
        <div class="chart-title">🗂 Répartition par type</div>
        <canvas id="chartType" height="200"></canvas>
    </div>
</div>

<!-- FILTRES -->
<form method="GET" class="filters">
    <input type="text" name="search" placeholder="🔍 Rechercher par nom, email..." value="<?= htmlspecialchars($search) ?>">
    <select name="status_filter">
        <option value="">Tous les statuts</option>
        <option value="Soumise"       <?= $status_filter==='Soumise'       ?'selected':'' ?>>Soumise</option>
        <option value="En traitement" <?= $status_filter==='En traitement' ?'selected':'' ?>>En traitement</option>
        <option value="En validation" <?= $status_filter==='En validation' ?'selected':'' ?>>En validation</option>
        <option value="Prête"         <?= $status_filter==='Prête'         ?'selected':'' ?>>Prête</option>
        <option value="Refusée"       <?= $status_filter==='Refusée'       ?'selected':'' ?>>Refusée</option>
    </select>
    <button type="submit" class="btn-filter">Filtrer</button>
</form>

<!-- TABLE LECTURE SEULE -->
<div class="table-card">
    <div class="table-header">
        <h2>Liste des demandes</h2>
        <span class="readonly-badge">👁 Lecture seule — aucune action possible</span>
    </div>
    <?php if (empty($demandes)): ?>
        <div class="empty">Aucune demande trouvée.</div>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Demandeur</th>
                <th>Email</th>
                <th>Document</th>
                <th>Statut</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($demandes as $d):
            $badge = 'badge-' . str_replace(' ', '_', $d['status']);
        ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td><strong><?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?></strong></td>
                <td><?= htmlspecialchars($d['email']) ?></td>
                <td><?= htmlspecialchars($d['document_type']) ?></td>
                <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($d['status']) ?></span></td>
                <td><?= date('d/m/Y H:i', strtotime($d['demande_le'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
// Données PHP → JS
var moisLabels  = <?= json_encode(array_column($mois_stats, 'mois_label'), JSON_UNESCAPED_UNICODE) ?>;
var moisTotal   = <?= json_encode(array_column($mois_stats, 'total')) ?>;
var moisTraites = <?= json_encode(array_column($mois_stats, 'traitees')) ?>;

var typeLabels  = <?= json_encode(array_column($par_type, 'document_type'), JSON_UNESCAPED_UNICODE) ?>;
var typeNb      = <?= json_encode(array_column($par_type, 'nb')) ?>;

// Graphique courbes — évolution mensuelle
new Chart(document.getElementById('chartMois'), {
    type: 'bar',
    data: {
        labels: moisLabels,
        datasets: [
            {
                label: 'Total',
                data: moisTotal,
                backgroundColor: 'rgba(59,130,246,0.15)',
                borderColor: '#3b82f6',
                borderWidth: 2,
                borderRadius: 6,
                type: 'bar'
            },
            {
                label: 'Traitées',
                data: moisTraites,
                borderColor: '#10b981',
                backgroundColor: 'transparent',
                borderWidth: 2.5,
                pointBackgroundColor: '#10b981',
                pointRadius: 4,
                tension: 0.4,
                type: 'line'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});

// Graphique donut — répartition par type
new Chart(document.getElementById('chartType'), {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{
            data: typeNb,
            backgroundColor: ['#3b82f6','#f59e0b','#10b981','#a855f7','#ef4444','#14b8a6'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 12 } } }
        },
        cutout: '65%'
    }
});
</script>


<!-- MODAL DÉCONNEXION -->
<div id="modalLogout" style="
    display:none; position:fixed; inset:0;
    background:rgba(15,23,46,.55);
    backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
    z-index:9999; align-items:center; justify-content:center; padding:20px;
">
<div style="
    background:#fff; border-radius:24px; padding:44px 48px;
    text-align:center; max-width:400px; width:100%;
    box-shadow:0 32px 80px rgba(0,0,0,.2);
    animation:popInLogout .28s cubic-bezier(.34,1.56,.64,1);
">
    <style>
    @keyframes popInLogout {
        from { transform:scale(.82); opacity:0; }
        to   { transform:scale(1);   opacity:1; }
    }
    </style>
    <div style="font-size:52px;margin-bottom:16px;">👋</div>
    <h3 style="font-size:19px;font-weight:700;color:#0f172a;margin-bottom:8px;font-family:'Inter',sans-serif;">Quitter votre session ?</h3>
    <p style="font-size:14px;color:#64748b;margin-bottom:30px;line-height:1.6;font-family:'Inter',sans-serif;">Vous allez être redirigé vers la page d'accueil.</p>
    <div style="display:flex;gap:12px;justify-content:center;">
        <button onclick="fermerModalLogout()" style="
            padding:12px 30px; border-radius:50px; font-size:14px; font-weight:600;
            cursor:pointer; border:1.5px solid #e2e8f0; background:#f1f5f9;
            color:#475569; font-family:'Inter',sans-serif; transition:background .2s;
        " onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
            Annuler
        </button>
        <button onclick="deconnecterLogout()" style="
            padding:12px 30px; border-radius:50px; font-size:14px; font-weight:600;
            cursor:pointer; border:none;
            background:linear-gradient(135deg,#1e3a5f,#2b5080);
            color:white; font-family:'Inter',sans-serif;
            box-shadow:0 4px 18px rgba(30,58,95,.35); transition:all .2s;
        " onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
            Oui, déconnecter
        </button>
    </div>
</div>
</div>
<script>
function ouvrirModalLogout() {
    var m = document.getElementById('modalLogout');
    m.style.display = 'flex';
}
function fermerModalLogout() {
    document.getElementById('modalLogout').style.display = 'none';
}
function deconnecterLogout() {
    document.body.style.transition = 'opacity 0.4s ease';
    document.body.style.opacity = '0';
    setTimeout(function() { window.location.href = '../../interface.html'; }, 400);
}
document.getElementById('modalLogout').addEventListener('click', function(e) {
    if (e.target === this) fermerModalLogout();
});
</script>

</body>
</html>