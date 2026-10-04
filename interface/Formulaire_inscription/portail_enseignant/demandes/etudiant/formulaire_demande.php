<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Justificatif d'absence — FGEI UMMTO</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Playfair+Display:wght@600&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1a3a5c;
    --accent: #2e7dd1;
    --light: #eef4fb;
    --border: #c8d8ec;
    --success: #1a7a4a;
    --text: #1e2d3d;
    --muted: #6b82a0;
}
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
.page-wrapper { width: 100%; max-width: 520px; }
.header-logo { text-align: center; margin-bottom: 28px; }
.header-logo .uni-name { font-family: 'Playfair Display', serif; font-size: 15px; color: var(--primary); letter-spacing: 0.5px; line-height: 1.6; }
.header-logo .dept { font-size: 12px; color: var(--muted); margin-top: 2px; }
.card { background: #fff; border-radius: 20px; padding: 36px 38px; box-shadow: 0 8px 40px rgba(26,58,92,0.10), 0 1px 4px rgba(26,58,92,0.06); border: 1px solid var(--border); }
.card-title { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--primary); margin-bottom: 4px; }
.card-subtitle { font-size: 13px; color: var(--muted); margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid #e8eef6; }
.section-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--accent); margin: 20px 0 12px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { margin-bottom: 14px; }
label { display: block; font-size: 12px; font-weight: 500; color: var(--text); margin-bottom: 6px; }
label span.req { color: #e05252; margin-left: 2px; }
input[type="text"], select, textarea {
    width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 10px;
    font-size: 13.5px; font-family: 'DM Sans', sans-serif; color: var(--text); background: #fff;
    transition: border-color 0.2s, box-shadow 0.2s; outline: none;
}
input:focus, select:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(46,125,209,0.12); }
input.readonly-field { background: var(--light); color: var(--primary); font-weight: 500; cursor: default; }
textarea { resize: vertical; min-height: 90px; }
.file-upload-wrapper { position: relative; }
.file-upload-wrapper input[type="file"] { position: absolute; opacity: 0; width: 100%; height: 100%; cursor: pointer; top: 0; left: 0; }
.file-upload-box { border: 2px dashed var(--border); border-radius: 10px; padding: 18px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fafcff; }
.file-upload-box:hover { border-color: var(--accent); background: var(--light); }
.file-upload-box .icon { font-size: 28px; margin-bottom: 6px; }
.file-upload-box .label { font-size: 13px; color: var(--muted); }
.file-upload-box .label b { color: var(--accent); }
.file-upload-box .formats { font-size: 11px; color: #aab8cc; margin-top: 4px; }
#fileName { margin-top: 8px; font-size: 12px; color: var(--success); font-weight: 500; min-height: 16px; }
.submit-btn { width: 100%; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: 12px; font-size: 15px; font-weight: 600; font-family: 'DM Sans', sans-serif; cursor: pointer; margin-top: 24px; letter-spacing: 0.3px; transition: background 0.2s, transform 0.1s; }
.submit-btn:hover { background: #142e4a; }
.submit-btn:active { transform: scale(0.99); }
.back-link { text-align: center; margin-top: 18px; font-size: 13px; }
.back-link a { color: var(--muted); text-decoration: none; transition: color 0.2s; }
.back-link a:hover { color: var(--accent); }
.divider { height: 1px; background: #e8eef6; margin: 20px 0; }

/* Enseignant select info */
.enseignant-info {
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 12px;
    color: var(--primary);
    margin-top: 6px;
    display: none;
}
.enseignant-info span { font-weight: 600; }

/* TOAST */
.toast {
    position: fixed; top: 24px; right: 24px;
    background: #c0392b; color: #fff;
    padding: 14px 18px; border-radius: 12px;
    font-size: 13px; font-weight: 500;
    box-shadow: 0 8px 28px rgba(0,0,0,0.20);
    z-index: 9999; display: flex; align-items: center;
    gap: 10px; max-width: 320px;
    transform: translateX(140%);
    transition: transform 0.4s cubic-bezier(.22,.68,0,1.2);
}
.toast.show { transform: translateX(0); }
.toast-icon { font-size: 20px; flex-shrink: 0; }
.toast-msg { flex: 1; line-height: 1.4; }
.toast-close { background: rgba(255,255,255,0.25); border: none; color: #fff; border-radius: 6px; width: 22px; height: 22px; cursor: pointer; font-size: 13px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
</style>
</head>
<body>

<div class="toast" id="toast">
    <span class="toast-icon">⚠️</span>
    <span class="toast-msg" id="toastMsg">Fichier trop volumineux (max 5 Mo)</span>
    <button class="toast-close" onclick="hideToast()">✕</button>
</div>

<div class="page-wrapper">
    <div class="header-logo">
        <div class="uni-name">Université Mouloud Mammeri de Tizi-Ouzou</div>
        <div class="dept">Faculté de Génie Électrique et Informatique</div>
    </div>

    <div class="card">
        <div class="card-title">Justificatif d'absence</div>
        <div class="card-subtitle">Soumettez votre demande à l'enseignant concerné</div>

        <form action="envoyer_demande.php" method="POST" enctype="multipart/form-data" id="mainForm">

            <div class="section-label">Informations personnelles</div>

            <div class="form-row">
                <div class="form-group">
                    <label for="prenom">Prénom <span class="req">*</span></label>
                    <input type="text" id="prenom" name="prenom" placeholder="Votre prénom" required>
                </div>
                <div class="form-group">
                    <label for="nom">Nom <span class="req">*</span></label>
                    <input type="text" id="nom" name="nom" placeholder="Votre nom" required>
                </div>
            </div>

            <div class="form-group">
                <label for="matricule">Matricule <span class="req">*</span></label>
                <input type="text" id="matricule" name="matricule" placeholder="ex: 20241234" required maxlength="20">
            </div>

            <div class="form-group">
                <label for="email">Email institutionnel</label>
                <input type="text" id="email" name="email" class="readonly-field" readonly placeholder="prénom.nom@fgei.ummto.dz">
            </div>

            <div class="divider"></div>
            <div class="section-label">Enseignant concerné</div>

            <!-- Sélection de l'enseignant -->
            <div class="form-group">
                <label for="enseignant_id">Enseignant <span class="req">*</span></label>
                <?php
                $pdo = new PDO("mysql:host=localhost;dbname=administration;charset=utf8","root","");
                $profs = $pdo->query("SELECT id_enseignant, nom, prenom, grade, email_institutionnel FROM enseignant ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <select id="enseignant_id" name="enseignant_id" required onchange="afficherInfoEnseignant(this)">
                    <option value="" disabled selected>— Sélectionner un enseignant —</option>
                    <?php foreach ($profs as $p): ?>
                    <option value="<?= $p['id_enseignant'] ?>"
                            data-email="<?= htmlspecialchars($p['email_institutionnel'] ?? '') ?>"
                            data-grade="<?= htmlspecialchars($p['grade']) ?>">
                        <?= htmlspecialchars(strtoupper($p['nom']) . ' ' . ucfirst($p['prenom'])) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="enseignant-info" id="enseignantInfo">
                    📧 Email : <span id="enseignantEmail">—</span> &nbsp;|&nbsp; 🎓 Grade : <span id="enseignantGrade">—</span>
                </div>
            </div>

            <div class="divider"></div>
            <div class="section-label">Détails de l'absence</div>

            <div class="form-group">
                <label for="motif">Motif de l'absence <span class="req">*</span></label>
                <select id="motif" name="motif" required>
                    <option value="" disabled selected>— Sélectionner un motif —</option>
                    <option value="Maladie">Maladie</option>
                    <option value="Décès dans la famille">Décès dans la famille</option>
                    <option value="Hospitalisation">Hospitalisation</option>
                    <option value="Accident">Accident</option>
                    <option value="Convocation officielle">Convocation officielle</option>
                    <option value="Problème de transport">Problème de transport</option>
                    <option value="Raison personnelle">Raison personnelle</option>
                    <option value="Autre">Autre</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date_absence">Date d'absence <span class="req">*</span></label>
                    <input type="text" id="date_absence" name="date_absence" placeholder="ex: 20/05/2026" required>
                </div>
                <div class="form-group">
                    <label for="matiere">Matière / Module</label>
                    <input type="text" id="matiere" name="matiere" placeholder="ex: Algorithmique">
                </div>
            </div>

            <div class="form-group">
                <label for="description">Précisions (facultatif)</label>
                <textarea id="description" name="description" placeholder="Ajoutez des détails supplémentaires…"></textarea>
            </div>

            <div class="divider"></div>
            <div class="section-label">Pièce justificative</div>

            <div class="form-group">
                <div class="file-upload-wrapper">
                    <input type="file" id="fichier" name="fichier"
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required
                           onchange="handleFile(this)">
                    <div class="file-upload-box" id="dropBox">
                        <div class="icon">📎</div>
                        <div class="label">Glissez votre fichier ici ou <b>cliquez pour parcourir</b></div>
                        <div class="formats">PDF, JPG, PNG, DOC — max 5 Mo</div>
                    </div>
                </div>
                <div id="fileName"></div>
            </div>

            <button type="submit" class="submit-btn">Envoyer la demande →</button>

        </form>
    </div>

    <div class="back-link">
        <a href="../../../../interface.html">⬅ Retour à l'accueil</a>
    </div>
</div>

<script>
const MAX_SIZE = 5 * 1024 * 1024;

let toastTimer;
function showToast(msg) {
    document.getElementById('toastMsg').textContent = msg;
    document.getElementById('toast').classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(hideToast, 4000);
}
function hideToast() { document.getElementById('toast').classList.remove('show'); }

function handleFile(input) {
    const el = document.getElementById('fileName');
    if (!input.files || !input.files[0]) { el.textContent = ''; return; }
    const file = input.files[0];
    if (file.size > MAX_SIZE) {
        showToast('⚠️ Fichier trop volumineux — maximum autorisé : 5 Mo');
        input.value = ''; el.textContent = ''; return;
    }
    el.textContent = '✔ ' + file.name + '  (' + (file.size/1024/1024).toFixed(2) + ' Mo)';
}

document.getElementById('mainForm').addEventListener('submit', function(e) {
    const input = document.getElementById('fichier');
    if (input.files && input.files[0] && input.files[0].size > MAX_SIZE) {
        e.preventDefault();
        showToast('⚠️ Fichier trop volumineux — maximum autorisé : 5 Mo');
    }
});

function generateEmail() {
    let prenom = document.getElementById('prenom').value.toLowerCase().trim().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    let nom    = document.getElementById('nom').value.toLowerCase().trim().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    document.getElementById('email').value = (prenom && nom) ? prenom + '.' + nom + '@fgei.ummto.dz' : '';
}
document.getElementById('prenom').addEventListener('input', generateEmail);
document.getElementById('nom').addEventListener('input', generateEmail);

function afficherInfoEnseignant(sel) {
    const opt   = sel.options[sel.selectedIndex];
    const email = opt.dataset.email || '—';
    const grade = opt.dataset.grade || '—';
    const info  = document.getElementById('enseignantInfo');
    document.getElementById('enseignantEmail').textContent = email;
    document.getElementById('enseignantGrade').textContent = grade;
    info.style.display = email !== '—' ? 'block' : 'none';
}
</script>

</body>
</html>