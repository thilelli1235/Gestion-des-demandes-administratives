<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Enseignant</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .container {
            width: 420px;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #1f2d3d;
            font-size: 22px;
            margin-bottom: 0.5em;
        }

        .separator {
            border: none;
            height: 1px;
            background: linear-gradient(to right, transparent, #94a3b8, transparent);
            margin: 15px 0 25px 0;
        }

        input {
            width: 100%;
            padding: 10px;
            margin-top: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            outline: none;
            font-size: 14px;
            box-sizing: border-box;
        }

        input:focus { border-color: #3b82f6; }

        input::placeholder { color: #aab8cc; }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover { background: #1d4ed8; }

        .small-text {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            margin-top: 16px;
        }

        .back-link {
            text-align: center;
            margin-top: 12px;
        }

        .back-link a {
            text-decoration: none;
            color: #2563eb;
            font-size: 14px;
            font-weight: 500;
            transition: 0.3s;
        }

        .back-link a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Espace Administratif</h2>
    <hr class="separator">

    <form action="log.php" method="POST">
        <input type="text"     name="username" placeholder="Nom d'utilisateur" required>
        <input type="password" name="password" placeholder="Mot de passe"       required>
        <button type="submit">Se connecter</button>
    </form>

    <p class="small-text">Mot de passe oublié ? Contactez votre département.</p>

    <div class="back-link">
        <a href="../interface.html">⬅ Retour à l'accueil</a>
    </div>
</div>

<script src="log.js"></script>
</body>
</html>