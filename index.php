<?php
session_start();
require __DIR__ . '/db.php';
$pdo = db();

function json($ok, $data = [], $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok] + $data);
    exit;
}

if (isset($_GET['api'])) {
    $a = $_GET['api'];

    if ($a === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $f = fn($k) => trim($_POST[$k] ?? '');
        $nom = $f('nom');
        $prenom = $f('prenom');
        $age = (int)($_POST['age'] ?? 0);
        $pays = $f('pays');
        $ville = $f('ville');
        $username = $f('username');
        $password = $_POST['password'] ?? '';
        if (!$nom || !$prenom || $age <= 0 || !$pays || !$ville || !$username || !$password) {
            json(false, ['error' => 'INVALID_FIELDS'], 400);
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO users(nom,prenom,age,pays,ville,username,password_hash) VALUES(?,?,?,?,?,?,?)");
            $stmt->execute([$nom, $prenom, $age, $pays, $ville, $username, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) json(false, ['error' => 'USERNAME_TAKEN'], 409);
            throw $e;
        }
        json(true, ['message' => 'REGISTER_OK']);
    }

    json(false, ['error' => 'NOT_FOUND'], 404);
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>GCIA Kiosque</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1>🎅 GCIA — A) Inscription</h1>

        <div class="card">
            <form id="form-register">
                <div class="row">
                    <div style="flex:1"><label>Nom</label><input name="nom" required></div>
                    <div style="flex:1"><label>Prénom</label><input name="prenom" required></div>
                    <div style="width:120px"><label>Âge</label><input type="number" name="age" min="1" required></div>
                </div>
                <div class="row">
                    <div style="flex:1"><label>Pays</label><input name="pays" required></div>
                    <div style="flex:1"><label>Ville</label><input name="ville" required></div>
                </div>
                <div class="row">
                    <div style="flex:1"><label>Nom d’utilisateur</label><input name="username" required></div>
                    <div style="flex:1"><label>Mot de passe</label><input type="password" name="password" required></div>
                </div>
                <button>Créer mon compte</button>
                <div id="reg-msg" class="notice"></div>
            </form>
        </div>

        <script>
            const reg = document.getElementById('form-register');
            reg?.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fd = new FormData(reg);
                const r = await fetch('?api=register', {
                    method: 'POST',
                    body: fd
                });
                const j = await r.json();
                document.getElementById('reg-msg').textContent = j.ok ? 'Inscription OK' :
                    (j.error === 'USERNAME_TAKEN' ? 'Nom d’utilisateur déjà pris' : 'Champs invalides');
            });
        </script>
    </div>
</body>

</html>