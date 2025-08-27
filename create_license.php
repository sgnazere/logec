<?php
require_once 'config.php';
require_once 'license_utils.php';

try {
    // Désactiver toutes les anciennes licences
    $db->exec("UPDATE licenses SET is_active = 0");

    // Créer une nouvelle licence valide pour un an
    $activation_date = date('Y-m-d H:i:s');
    $expiration_date = calculateExpirationDate(365);
    $license_key = generateLicenseKey($expiration_date);

    $stmt = $db->prepare("
        INSERT INTO licenses (
            license_key,
            is_active,
            activation_date,
            expiration_date,
            last_check,
            created_at
        ) VALUES (
            :license_key,
            1,
            :activation_date,
            :expiration_date,
            :last_check,
            :created_at
        )
    ");

    $stmt->execute([
        ':license_key' => $license_key,
        ':activation_date' => $activation_date,
        ':expiration_date' => $expiration_date,
        ':last_check' => $activation_date,
        ':created_at' => $activation_date
    ]);

    echo "Nouvelle licence créée avec succès !\n";
    echo "Clé : " . $license_key . "\n";
    echo "Date d'activation : " . formatExpirationDate($activation_date) . "\n";
    echo "Date d'expiration : " . formatExpirationDate($expiration_date) . "\n";

} catch(PDOException $e) {
    die("Erreur : " . $e->getMessage());
} 