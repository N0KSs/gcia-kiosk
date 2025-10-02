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

    // A) Inscription
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

    // B) Connexion
    if ($a === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT id, prenom, password_hash FROM users WHERE username=?");
        $stmt->execute([$username]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($password, $u['password_hash'])) {
            json(false, ['error' => 'BAD_CREDENTIALS'], 401);
        }
        $_SESSION['uid'] = $u['id'];
        $_SESSION['name'] = $u['prenom'];
        json(true, ['message' => 'LOGIN_OK', 'name' => $u['prenom']]);
    }

    // Déconnexion (optionnel)
    if ($a === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        session_destroy();
        json(true, ['message' => 'LOGOUT_OK']);
    }

    // Si aucune API trouvée
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
        <h1>🎅 GCIA — A) Inscription & B) Connexion</h1>

        <!-- Formulaire A : Inscription -->
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

        <!-- Formulaire B : Connexion -->
        <div class="card">
            <h3>🅱️ Connexion</h3>
            <form id="form-login">
                <label>Nom d’utilisateur</label><input name="username" required>
                <label>Mot de passe</label><input type="password" name="password" required>
                <button>Se connecter</button>
                <div id="login-msg" class="notice"></div>
            </form>
        </div>
    </div>

    <script>
        // A) JS pour Inscription
        const reg = document.getElementById('form-register');
        reg?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(reg);
            const r = await fetch('?api=register', {
                method: 'POST',
                body: fd
            });
            const j = await r.json();
            document.getElementById('reg-msg').textContent =
                j.ok ? 'Inscription OK' :
                (j.error === 'USERNAME_TAKEN' ? 'Nom d’utilisateur déjà pris' : 'Champs invalides');
        });

        // B) JS pour Connexion
        const flog = document.getElementById('form-login');
        flog?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const r = await fetch('?api=login', {
                method: 'POST',
                body: new FormData(flog)
            });
            const j = await r.json();
            document.getElementById('login-msg').textContent =
                j.ok ? ('Bonjour ' + j.name) : 'Identifiants incorrects';
        });
    </script>
</body>

</html>
