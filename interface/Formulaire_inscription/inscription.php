<?php
// ─── Connexion BDD ───────────────────────────────────────────────────────────
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

// ─── Récupération des champs ─────────────────────────────────────────────────
$departement          = trim($_POST['departement']          ?? '');
$nom                  = trim($_POST['nom']                  ?? '');
$prenom               = trim($_POST['prenom']               ?? '');
$grade                = trim($_POST['grade']                ?? '');
$email_institutionnel = trim($_POST['email_institutionnel'] ?? '');
$telephone            = trim($_POST['telephone']            ?? '');
$nom_utilisateur      = trim($_POST['nom_utilisateur']      ?? '');
$mot_de_passe         = trim($_POST['mot_de_passe']         ?? '');

// ─── Validation ──────────────────────────────────────────────────────────────
if (empty($departement) || empty($nom) || empty($prenom) ||
    empty($grade) || empty($telephone) || empty($mot_de_passe)) {
    die("Erreur : tous les champs obligatoires doivent être remplis.");
}

// ─── Vérifier si nom_utilisateur déjà pris ───────────────────────────────────
$stmt = $pdo->prepare("SELECT id_enseignant FROM enseignant WHERE nom_utilisateur = ?");
$stmt->execute([$nom_utilisateur]);
if ($stmt->fetch()) {
    die("Erreur : ce nom d'utilisateur est déjà utilisé.");
}

// ─── Hachage du mot de passe ─────────────────────────────────────────────────
$hash = password_hash($mot_de_passe, PASSWORD_BCRYPT);

// ─── Insertion ───────────────────────────────────────────────────────────────
$sql = "INSERT INTO enseignant
            (departement, nom, prenom, grade, email_institutionnel, telephone, nom_utilisateur, mot_de_passe)
        VALUES
            (:departement, :nom, :prenom, :grade, :email_institutionnel,
             :telephone, :nom_utilisateur, :mot_de_passe)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':departement'          => $departement,
    ':nom'                  => $nom,
    ':prenom'               => $prenom,
    ':grade'                => $grade,
    ':email_institutionnel' => $email_institutionnel,
    ':telephone'            => $telephone,
    ':nom_utilisateur'      => $nom_utilisateur,
    ':mot_de_passe'         => $hash,
]);

// ─── Redirection ─────────────────────────────────────────────────────────────
// inscription.php est à la RACINE
// portail_enseignant/ est un sous-dossier de la racine
header("Location: portail_enseignant/enseignant.html");
exit;