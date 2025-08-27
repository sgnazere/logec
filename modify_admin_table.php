<?php
require_once 'config.php';

try {
    $db = getDBConnection();
    
    // Ajout des colonnes nom et prenoms
    $db->exec('ALTER TABLE admin ADD COLUMN nom VARCHAR(100) AFTER email, ADD COLUMN prenoms VARCHAR(100) AFTER nom');
    
    // Mise à jour des données pour l'utilisateur AFFIBA
    $stmt = $db->prepare('UPDATE admin SET nom = ?, prenoms = ? WHERE username = ?');
    $stmt->execute(['AFFIBA', 'JOSEPHINE NOUMOUA', 'affiba.j']);
    
    echo "Table admin modifiée avec succès !\n";
    echo "Colonnes ajoutées : nom, prenoms\n";
    echo "Données mises à jour pour l'utilisateur affiba.j\n";
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
} 