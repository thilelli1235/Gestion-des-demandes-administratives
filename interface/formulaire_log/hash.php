<?php
// hash.php - Interface web pour générer un hash de mot de passe
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Générer un hash de mot de passe</title>
    <style>
        * { font-family: Arial, sans-serif; }
        body { background: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 500px; }
        h2 { color: #333; margin-bottom: 20px; text-align: center; }
        input { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; background: #007bff; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .result { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-top: 20px; word-break: break-all; }
        .result h3 { margin-top: 0; }
        code { background: #f4f4f4; padding: 10px; display: block; margin-top: 10px; word-break: break-all; border-radius: 5px; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 5px; margin-top: 15px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 Générer un hash de mot de passe</h2>
        
        <form method="POST">
            <input type="text" name="mot_de_passe" placeholder="Entrez le mot de passe" required>
            <button type="submit">Générer le hash</button>
        </form>
        
        <?php if (isset($_POST['mot_de_passe'])): ?>
            <?php
            $mot_de_passe = $_POST['mot_de_passe'];
            $hash = password_hash($mot_de_passe, PASSWORD_DEFAULT);
            ?>
            <div class="result">
                <h3>✅ Hash généré :</h3>
                <code><?= htmlspecialchars($hash) ?></code>
                <p><strong>Mot de passe original :</strong> <?= htmlspecialchars($mot_de_passe) ?></p>
                <p><strong>À copier dans la colonne <code>password_hash</code> de la table <code>responsable</code></strong></p>
            </div>
            <div class="warning">
                ⚠️ Attention : Ce hash est unique pour ce mot de passe.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>