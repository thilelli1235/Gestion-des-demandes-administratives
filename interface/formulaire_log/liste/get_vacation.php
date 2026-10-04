<?php
// ================================================
//  GET_VACATION.PHP
//  Retourne les données d'une demande vacation en JSON
//  Utilisé par le modal dans listes_demandes.php
// ================================================
header('Content-Type: application/json; charset=utf-8');

$host   = "localhost";
$dbname = "administration";
$user   = "root";
$pass   = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Connexion BDD échouée']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID invalide']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        id, id_enseignant, nom, prenom, date_lieu_naissance,
        grade, diplome, ccp, adresse, date_debut,
        departement_accueil, faculte, universite,
        statut, date_demande
    FROM vacation
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Vacation introuvable']);
    exit;
}

echo json_encode($row, JSON_UNESCAPED_UNICODE);