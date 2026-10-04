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

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    header("Location: login.php?erreur=1");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM responsable WHERE username = ?");
$stmt->execute([$username]);
$responsable = $stmt->fetch(PDO::FETCH_ASSOC);

// Vérification mot de passe — supporte les deux : hashé et en clair (legacy)
$mdp_ok = false;
if ($responsable) {
    if (password_verify($password, $responsable['mot_de_passe'])) {
        $mdp_ok = true;
    } elseif ($password === $responsable['mot_de_passe']) {
        // Mot de passe en clair (ancien compte) — accepté mais à migrer
        $mdp_ok = true;
    }
}

if ($responsable && $mdp_ok) {
    $_SESSION['id']       = $responsable['id'];
    $_SESSION['nom']      = $responsable['nom'];
    $_SESSION['prenom']   = $responsable['prenom'];
    $_SESSION['username'] = $responsable['username'];
    $_SESSION['role']     = $responsable['role'];

    // Redirection selon le rôle
    if ($responsable['role'] === 'cheffe_departement') {
        header("Location: liste/dashboard.php");
    } else {
        // secretaire ou Administrateur → liste des demandes
        header("Location: liste/listes_demandes.php");
    }
    exit;

} else {
    header("Location: login.php?erreur=1");
    exit;
}
?>