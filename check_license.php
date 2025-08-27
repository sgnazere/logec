<?php
require_once 'config.php';
require_once 'license_utils.php';

// Vérifier si une licence existe
$query = $db->query("SELECT * FROM licenses WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
$license = $query->fetch(PDO::FETCH_ASSOC);

if (!$license) {
    header('Location: install_license.php');
    exit;
}

// Vérifier le statut de la licence
$status = checkLicenseStatus($license);

if (!$status['is_valid'] || $status['is_expired']) {
    header('Location: expire_license.php');
    exit;
}

// La licence est valide, on continue
define('LICENSE_CHECKED', true);

function checkLicense() {
    global $db;
    $query = $db->query("SELECT * FROM licenses WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
    $license = $query->fetch(PDO::FETCH_ASSOC);
    
    if (!$license || isLicenseExpired($license['expiration_date'])) {
        die("Licence expirée ou invalide. Veuillez contacter l'administrateur.");
    }
    return true;
} 