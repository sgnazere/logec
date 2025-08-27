<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'license_utils.php';

function saveLicense($expirationDate, $licenseKey) {
    global $db;
    
    $query = $db->prepare("
        INSERT INTO licenses (expiration_date, license_key)
        VALUES (:expiration_date, :license_key)
        ON DUPLICATE KEY UPDATE
        license_key = :license_key
    ");
    
    return $query->execute([
        ':expiration_date' => $expirationDate,
        ':license_key' => $licenseKey
    ]);
}

function getLicenseInfo() {
    global $db;
    
    $query = $db->prepare("SELECT * FROM licenses ORDER BY expiration_date DESC LIMIT 1");
    $query->execute();
    
    return $query->fetch();
}

function checkLicenseExpiration() {
    $license = getLicenseInfo();
    
    if (!$license) {
        return false;
    }
    
    $expirationDate = strtotime($license['expiration_date']);
    $currentDate = time();
    
    // Vérifier si la licence est expirée
    if ($currentDate > $expirationDate) {
        return false;
    }
    
    // Vérifier si la clé de licence est valide
    $expectedKey = generateLicenseKey($license['expiration_date']);
    if ($license['license_key'] !== $expectedKey) {
        return false;
    }
    
    return true;
}

// Créer la table des licences si elle n'existe pas
function createLicenseTable() {
    global $db;
    
    $query = $db->prepare("
        CREATE TABLE IF NOT EXISTS licenses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            expiration_date DATE NOT NULL,
            license_key VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    return $query->execute();
}

// Initialiser la table des licences
createLicenseTable();

// Si c'est une nouvelle installation, créer une licence initiale valide pour 1 an
if (!getLicenseInfo()) {
    $expirationDate = date('Y-m-d', strtotime('+1 year'));
    $licenseKey = generateLicenseKey($expirationDate);
    saveLicense($expirationDate, $licenseKey);
}

// Si appelé directement, retourner le statut en JSON
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('Content-Type: application/json');
    echo json_encode(checkLicenseExpiration());
}

// Récupérer la dernière licence
$query = $db->query("SELECT * FROM licenses WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
$license = $query->fetch(PDO::FETCH_ASSOC);

// Vérifier si une nouvelle licence a été soumise
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_license'])) {
    $new_license = trim($_POST['new_license']);
    
    if (isValidLicenseKey($new_license)) {
        // Désactiver l'ancienne licence
        if ($license) {
            $db->prepare("UPDATE licenses SET is_active = 0 WHERE id = ?")->execute([$license['id']]);
        }
        
        // Ajouter la nouvelle licence
        $expiration_date = calculateExpirationDate(365); // Licence valide pour 1 an
        $query = $db->prepare("
            INSERT INTO licenses (license_key, expiration_date, is_active, created_at)
            VALUES (?, ?, 1, CURRENT_TIMESTAMP)
        ");
        $query->execute([$new_license, $expiration_date]);
        
        // Rediriger vers la page principale
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "La clé de licence n'est pas valide.";
    }
}

// Statut de la licence actuelle
$license_status = $license ? checkLicenseStatus($license) : null;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Licence Expirée - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .license-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-box {
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .license-form {
            margin-top: 20px;
        }
        .license-input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .submit-button {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .submit-button:hover {
            background: #218838;
        }
        .error-message {
            color: #dc3545;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="license-container">
        <h1>Licence Expirée</h1>
        
        <?php if ($license): ?>
            <div class="status-box">
                <h3>Statut de la licence actuelle</h3>
                <p>Clé: <?php echo htmlspecialchars($license['license_key']); ?></p>
                <p>Date d'expiration: <?php echo formatExpirationDate($license['expiration_date']); ?></p>
                <p>Statut: <?php echo $license_status['is_expired'] ? 'Expirée' : 'Invalide'; ?></p>
            </div>
        <?php endif; ?>

        <div class="license-form">
            <h2>Entrer une nouvelle licence</h2>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="text" name="new_license" class="license-input" 
                       placeholder="XXXX-XXXX-XXXX-XXXX" 
                       pattern="[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}"
                       required>
                <button type="submit" class="submit-button">Activer la licence</button>
            </form>
            
            <p>
                Pour obtenir une nouvelle licence, veuillez contacter le support technique :
                <br>Email: support@logec.com
                <br>Téléphone: +225 XX XX XX XX
            </p>
        </div>
    </div>
</body>
</html> 