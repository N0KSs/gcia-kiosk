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

    // (optionnel) Déconnexion
    if ($a === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        session_destroy();
        json(true, ['message' => 'LOGOUT_OK']);
    }

    // C) Modifier identifiants
    if ($a === 'update_credentials' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['uid'])) json(false, ['error' => 'AUTH_REQUIRED'], 401);

        $newUser = trim($_POST['username'] ?? '');
        $newPass = trim($_POST['password'] ?? '');

        if (!$newUser && !$newPass) json(false, ['error' => 'NOTHING'], 400);

        if ($newUser) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username=? AND id<>?");
            $chk->execute([$newUser, $_SESSION['uid']]);
            if ($chk->fetch()) json(false, ['error' => 'USERNAME_TAKEN'], 409);
        }

        if ($newUser && $newPass) {
            $q = $pdo->prepare("UPDATE users SET username=?, password_hash=? WHERE id=?");
            $q->execute([$newUser, password_hash($newPass, PASSWORD_DEFAULT), $_SESSION['uid']]);
        } elseif ($newUser) {
            $q = $pdo->prepare("UPDATE users SET username=? WHERE id=?");
            $q->execute([$newUser, $_SESSION['uid']]);
        } else {
            $q = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $q->execute([password_hash($newPass, PASSWORD_DEFAULT), $_SESSION['uid']]);
        }

        json(true, ['message' => 'UPDATE_OK']);
    }

    // D) Ajouter une demande de cadeau
    if ($a === 'add_gift' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['uid'])) json(false, ['error' => 'AUTH_REQUIRED'], 401);
        $type = trim($_POST['type'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $prix = (float)($_POST['prix'] ?? 0);
        if (!$type || !$nom || !$desc || $prix <= 0) json(false, ['error' => 'INVALID_FIELDS'], 400);
        $stmt = $pdo->prepare("INSERT INTO gifts(user_id,type,nom,description,prix_estime) VALUES(?,?,?,?,?)");
        $stmt->execute([$_SESSION['uid'], $type, $nom, $desc, $prix]);
        json(true, ['message' => 'GIFT_OK']);
    }

    // E) Lister mes demandes
    if ($a === 'list_gifts') {
        if (!isset($_SESSION['uid'])) json(false, ['error' => 'AUTH_REQUIRED'], 401);
        $stmt = $pdo->prepare("SELECT type, nom, description, prix_estime, created_at FROM gifts WHERE user_id=? ORDER BY id DESC");
        $stmt->execute([$_SESSION['uid']]);
        json(true, ['items' => $stmt->fetchAll()]);
    }

    // API non trouvée
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
        <h1>🎅 GCIA — A) Inscription • B) Connexion • C) Modifier identifiants • D) Demande • E) Mes demandes</h1>

        <!-- A) Inscription -->
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

        <!-- B) Connexion -->
        <div class="card">
            <h3>🅱️ Connexion</h3>
            <form id="form-login">
                <label>Nom d’utilisateur</label><input name="username" required>
                <label>Mot de passe</label><input type="password" name="password" required>
                <button>Se connecter</button>
                <div id="login-msg" class="notice"></div>
            </form>
        </div>

        <!-- C) Modifier identifiants -->
        <div class="card">
            <h3>🅲 Modifier mes informations de connexion</h3>
            <form id="form-update">
                <label>Nouveau nom d’utilisateur (optionnel)</label>
                <input name="username" placeholder="laisser vide si inchangé">
                <label>Nouveau mot de passe (optionnel)</label>
                <input type="password" name="password" placeholder="laisser vide si inchangé">
                <button>Enregistrer</button>
                <div id="upd-msg" class="notice"></div>
            </form>
        </div>

        <!-- D) Demande de cadeau -->
        <div class="card">
            <h3>🅳 Demande de cadeau</h3>
            <form id="form-gift">
                <label>Type</label>
                <select name="type" required>
                    <option value="">Choisir…</option>
                    <option>Jouet</option>
                    <option>Vêtement</option>
                    <option>Électronique</option>
                    <option>Autre</option>
                </select>
                <label>Nom</label><input name="nom" required>
                <label>Description</label><textarea name="description" required></textarea>
                <label>Prix estimé</label><input type="number" step="0.01" name="prix" required>
                <button>Soumettre</button>
                <div id="gift-msg" class="notice"></div>
            </form>
        </div>

        <!-- E) Mes demandes -->
        <div class="card">
            <h3>🅴 Mes demandes</h3>
            <button id="btn-load">Charger mes demandes</button>
            <div id="list"></div>
        </div>
    </div>

    <script>
        // A) Inscription
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

        // B) Connexion
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

        // C) Modifier identifiants
        const fupd = document.getElementById('form-update');
        fupd?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const r = await fetch('?api=update_credentials', {
                method: 'POST',
                body: new FormData(fupd)
            });
            const j = await r.json();
            document.getElementById('upd-msg').textContent =
                j.ok ? 'Mise à jour OK' :
                (j.error === 'AUTH_REQUIRED' ? 'Connecte-toi d’abord' :
                    j.error === 'USERNAME_TAKEN' ? 'Nom déjà pris' :
                    'Rien à mettre à jour');
        });

        // D) Demande de cadeau
        const fg = document.getElementById('form-gift');
        fg?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const r = await fetch('?api=add_gift', {
                method: 'POST',
                body: new FormData(fg)
            });
            const j = await r.json();
            document.getElementById('gift-msg').textContent =
                j.ok ? 'Demande enregistrée' :
                (j.error === 'AUTH_REQUIRED' ? 'Connecte-toi d’abord' : 'Champs invalides');
        });

        // E) Mes demandes
        document.getElementById('btn-load')?.addEventListener('click', async () => {
            const r = await fetch('?api=list_gifts');
            const j = await r.json();
            const box = document.getElementById('list');
            if (!j.ok) {
                box.innerHTML = '<div class="error">Connecte-toi d’abord</div>';
                return;
            }
            if (!j.items.length) {
                box.innerHTML = '<div class="notice">Aucune demande pour l’instant.</div>';
                return;
            }
            box.innerHTML = '<table><tr><th>Type</th><th>Nom</th><th>Description</th><th>Prix</th><th>Quand</th></tr>' +
                j.items.map(x => `<tr><td>${x.type}</td><td>${x.nom}</td><td>${x.description}</td><td>${x.prix_estime}</td><td>${x.created_at}</td></tr>`).join('') +
                '</table>';
        });
    </script>
</body>

</html>