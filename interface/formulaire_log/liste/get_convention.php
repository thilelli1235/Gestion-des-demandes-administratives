<?php
// ================================================
//  GET_CONVENTION.PHP
//  Retourne les données d'une convention de stage en JSON
//  Utilisé par le modal dans listes_demandes.php
// ================================================
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=administration;charset=utf8",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Connexion BDD échouée']);
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID invalide']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, email_etudiant, organisme_accueil,
           date_debut, statut, date_demande
    FROM convention_stage
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Convention introuvable pour ID ' . $id]);
    exit;
}

echo json_encode($row, JSON_UNESCAPED_UNICODE);