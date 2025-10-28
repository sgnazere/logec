<?php
/**
 * Fichier central pour la vérification de la licence.
 * Ce script est inclus au début des pages protégées.
 */

require_once __DIR__ . '/license_utils.php';

function checkLicense() {
    // Exclure les pages de gestion de licence du contrôle pour éviter une boucle de redirection.
    $excluded_pages = ['validate_license.php', 'generate_license.php'];
    if (in_array(basename($_SERVER['PHP_SELF']), $excluded_pages)) {
        return;
    }

    $license_status = verify_license();

    if (!$license_status['valid']) {
        // Mettre le message d'erreur en session pour l'afficher sur la page de validation.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['license_error'] = $license_status['message'];

        // Rediriger vers la page de validation pour installer une nouvelle licence.
        header('Location: validate_license.php');
        exit();
    }

    // Si la licence est valide, on peut définir des constantes ou des variables globales si nécessaire.
    define('LICENSE_VALID', true);
    if (isset($license_status['data']['max_users'])) {
        define('MAX_USERS', $license_status['data']['max_users']);
    }
}

// Exécuter le contrôle de la licence lors de l'inclusion du fichier.
checkLicense();
?>