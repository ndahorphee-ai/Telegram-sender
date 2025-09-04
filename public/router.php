<?php
// public/router.php - router pour le serveur PHP intégré (Windows compatible)

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Chemin vers le fichier demandé sur disque
$requested = __DIR__ . DIRECTORY_SEPARATOR . ltrim($uri, '/');

// Si c'est un fichier statique réel, laisse le serveur intégré le servir
if ($uri !== '/' && file_exists($requested)) {
    return false;
}

// Calcul du front controller (index.php)
$indexFile = __DIR__ . DIRECTORY_SEPARATOR . 'index.php';

if (!file_exists($indexFile)) {
    // Message d'erreur clair pour debug
    header("HTTP/1.1 500 Internal Server Error");
    echo "Erreur : front controller introuvable.\n";
    echo "Chemin attendu : {$indexFile}\n";
    echo "Répertoire courant : " . __DIR__ . "\n";
    echo "Requête : " . $uri . "\n\n";
    echo "Vérifie que le fichier public/index.php existe et que le nom n'a pas de faute de frappe.\n";
    exit(1);
}

// Inclure l'index pour laisser Slim traiter la route
require $indexFile;
