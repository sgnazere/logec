<?php
require_once 'config.php';

try {
    $db = getDBConnection();
    
    $username = 'affiba.j';
    $password = password_hash('AJN@2024Ec', PASSWORD_DEFAULT);
    $email = 'asrh.int@ec-ci.org';
    
    $stmt = $db->prepare('INSERT INTO admin (username, password, email) VALUES (?, ?, ?)');
    $stmt->execute([$username, $password, $email]);
    
    echo "Utilisateur créé avec succès !\n";
    echo "Nom d'utilisateur : " . $username . "\n";
    echo "Email : " . $email . "\n";
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
} 