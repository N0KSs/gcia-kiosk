<?php
const DB_FILE = __DIR__ . '/data.sqlite';

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $init = !file_exists(DB_FILE);
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if ($init) {
        $pdo->exec("
      CREATE TABLE users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT,
        prenom TEXT,
        age INTEGER,
        pays TEXT,
        ville TEXT,
        username TEXT UNIQUE,
        password_hash TEXT
      );
    ");
        $pdo->exec("
      CREATE TABLE gifts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        type TEXT,
        nom TEXT,
        description TEXT,
        prix_estime REAL,
        created_at TEXT DEFAULT (datetime('now'))
      );
    ");
    }
    return $pdo;
}
