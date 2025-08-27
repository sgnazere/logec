<?php
/**
 * Fichier utilitaire pour la gestion des licences
 */

if (!defined('LICENSE_UTILS_LOADED')) {
    define('LICENSE_UTILS_LOADED', true);

    /**
     * Génère une clé de licence unique
     * @param string $expirationDate
     * @return string
     */
    function generateLicenseKey($expirationDate = null) {
        if ($expirationDate === null) {
            $expirationDate = calculateExpirationDate(365); // 1 an par défaut
        }
        return hash('sha256', LICENSE_SECRET_KEY . $expirationDate);
    }

    /**
     * Vérifie si une clé de licence est valide
     * @param string $key
     * @param string $expirationDate
     * @return bool
     */
    function isValidLicenseKey($key, $expirationDate) {
        $expectedKey = generateLicenseKey($expirationDate);
        return $key === $expectedKey;
    }

    /**
     * Vérifie si une licence est expirée
     * @param string $expiration_date
     * @return bool
     */
    function isLicenseExpired($expiration_date) {
        return strtotime($expiration_date) < time();
    }

    /**
     * Calcule la date d'expiration basée sur la durée en jours
     * @param int $duration_days
     * @return string
     */
    function calculateExpirationDate($duration_days) {
        return date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
    }

    /**
     * Formate une date d'expiration pour l'affichage
     * @param string $date
     * @return string
     */
    function formatExpirationDate($date) {
        return date('d/m/Y H:i', strtotime($date));
    }

    /**
     * Met à jour la date du dernier contrôle
     * @param int $license_id
     * @return bool
     */
    function updateLastCheck($license_id) {
        global $db;
        $stmt = $db->prepare("UPDATE licenses SET last_check = CURRENT_TIMESTAMP WHERE id = ?");
        return $stmt->execute([$license_id]);
    }

    /**
     * Vérifie le statut complet d'une licence
     * @param array $license
     * @return array
     */
    function checkLicenseStatus($license) {
        $is_valid = isValidLicenseKey($license['license_key'], $license['expiration_date']);
        $is_expired = isLicenseExpired($license['expiration_date']);
        $days_remaining = ceil((strtotime($license['expiration_date']) - time()) / (60 * 60 * 24));

        if ($is_valid && !$is_expired && isset($license['id'])) {
            updateLastCheck($license['id']);
        }

        return [
            'is_valid' => $is_valid,
            'is_expired' => $is_expired,
            'days_remaining' => $days_remaining,
            'activation_date' => isset($license['activation_date']) ? $license['activation_date'] : null,
            'last_check' => isset($license['last_check']) ? $license['last_check'] : null
        ];
    }
}
?> 