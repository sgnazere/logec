<?php
/**
 * Fichier utilitaire pour la gestion centralisée de la licence.
 */

if (!defined('LICENSE_UTILS_LOADED')) {
    define('LICENSE_UTILS_LOADED', true);

    /**
     * Vérifie la validité de la licence installée.
     * C'est la fonction principale à appeler pour toute vérification de licence.
     *
     * @return array Un tableau contenant le statut de la licence.
     *               - 'valid' (bool): True si la signature est correcte et la licence non expirée.
     *               - 'expired' (bool): True si la licence est expirée.
     *               - 'message' (string): Un message décrivant le statut.
     *               - 'data' (array|null): Les données de la licence si elle est valide.
     */
    function verify_license() {
        // Inclure la configuration pour la clé secrète
        require_once 'config.php';

        $license_path = __DIR__ . '/config/license.txt';

        if (!file_exists($license_path)) {
            return [
                'valid' => false,
                'expired' => false,
                'message' => "Fichier de licence introuvable. Veuillez installer une licence.",
                'data' => null
            ];
        }

        $license_content = file_get_contents($license_path);
        $license = json_decode($license_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($license['data']) || !isset($license['signature'])) {
            return [
                'valid' => false,
                'expired' => false,
                'message' => "Format du fichier de licence invalide.",
                'data' => null
            ];
        }

        // Vérification de la signature
        $expected_signature = hash_hmac('sha256', $license['data'], LICENSE_SECRET_KEY);

        if (!hash_equals($expected_signature, $license['signature'])) {
            return [
                'valid' => false,
                'expired' => false,
                'message' => "Signature de la licence invalide. La licence a peut-être été modifiée.",
                'data' => null
            ];
        }

        // Si la signature est valide, on peut faire confiance aux données
        $license_data = json_decode(base64_decode($license['data']), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'expired' => false,
                'message' => "Données de la licence corrompues.",
                'data' => null
            ];
        }

        // Vérification de la date d'expiration
        $expiry_date = strtotime($license_data['expiry_date']);
        if ($expiry_date < time()) {
            return [
                'valid' => false,
                'expired' => true,
                'message' => "Votre licence a expiré le " . date('d/m/Y', $expiry_date) . ".",
                'data' => $license_data
            ];
        }

        // Si tout est bon
        return [
            'valid' => true,
            'expired' => false,
            'message' => "Licence valide.",
            'data' => $license_data
        ];
    }
}
?> 