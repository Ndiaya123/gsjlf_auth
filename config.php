<?php
$session_lifetime = 3600; // Durée de vie de la session en secondes (1 heure)
// $host = 'localhost'; // Adresse du serveur MySQL
// $user = 'root'; // Changez-le par votre nom d'utilisateur de la base de données
// $pass = ''; // Changez-le par le mot de passe de votre base de données
// $db1 = 'uahb';
$host = 'o86fy.myd.infomaniak.com'; // Adresse du serveur
$user = 'o86fy_yacouba'; // Changez-le par votre nom d'utilisateur de la base de données
$pass = 'Passer1234'; // Changez-le par le mot de passe de votre base de données
$db1 = 'o86fy_yacouba';
$dsn = "mysql:host=$host;dbname=$db1;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];


try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}


$user2 = 'o86fy_yacouba';
$pass2 = 'Passer1234'; // Changez-le par le mot de passe de votre base de données
$options2 = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
// Connexion à la première base de données
$db1 = 'o86fy_ent';
$dsn1 = "mysql:host=o86fy.myd.infomaniak.com;dbname=$db1;charset=utf8mb4";

try {
    $pdoENT = new PDO($dsn1, $user2, $pass2, $options2);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Connexion à la deuxième base de données
$db2 = 'o86fy_cmjlf'; // Changez-le par le nom de votre deuxième base de données
$dsn2 = "mysql:host=o86fy.myd.infomaniak.com;dbname=$db2;charset=utf8mb4";

try {
    $pdoCMJLF = new PDO($dsn2, $user2, $pass2, $options2);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
