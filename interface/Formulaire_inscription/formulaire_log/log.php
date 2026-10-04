<?php
session_start();

// ─── Connexion BDD ───────────────────────────────────────────────────────────
$host   = "localhost";
$dbname = "administration";   // ← change ici
$user   = "root";             // ← change ici
$pass   = "";                 // ← change ici

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur connexion BDD : " . $e->getMessage());
}

// ─── Récupération des champs ─────────────────────────────────────────────────
$nom_utilisateur = trim($_POST['nom_utilisateur'] ?? '');
$mot_de_passe    = trim($_POST['mot_de_passe']    ?? '');

// ─── Validation ──────────────────────────────────────────────────────────────
if (empty($nom_utilisateur) || empty($mot_de_passe)) {
    die("Erreur : veuillez remplir tous les champs.");
}

// ─── Recherche de l'enseignant ───────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM enseignant WHERE nom_utilisateur = ?");
$stmt->execute([$nom_utilisateur]);
$enseignant = $stmt->fetch(PDO::FETCH_ASSOC);

// ─── Vérification mot de passe ───────────────────────────────────────────────
if ($enseignant && password_verify($mot_de_passe, $enseignant['mot_de_passe'])) {

    // Stocker les infos en session
    $_SESSION['id_enseignant']   = $enseignant['id_enseignant'];
    $_SESSION['nom']             = $enseignant['nom'];
    $_SESSION['prenom']          = $enseignant['prenom'];
    $_SESSION['nom_utilisateur'] = $enseignant['nom_utilisateur'];
    $_SESSION['departement']     = $enseignant['departement'];

    // ─── Redirection ─────────────────────────────────────────────────────────
    // log.php est dans formulaire_log/
    // portail_enseignant/ est un dossier VOISIN (même niveau que formulaire_log/)
    // donc on remonte d'un niveau avec ../ avant d'entrer dans portail_enseignant/
    header("Location: ../portail_enseignant/enseignant.html");
    exit;

} else {
    // Mauvais identifiants → retour formulaire avec message d'erreur
    header("Location: log.html?erreur=1");
    exit;
}