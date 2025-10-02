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

    json(false, ['error' => 'NOT_IMPLEMENTED'], 404);
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
        <h1>🎅 GCIA — Kiosque Enfants</h1>
        <p class="notice">On va ajouter les sections A → E étape par étape.</p>

        <div class="card">
            <b>Test rapide en local</b><br>
            <code>php -S localhost:8000</code> puis ouvre <a href="http://localhost:8000">http://localhost:8000</a>
        </div>
    </div>
</body>

</html>