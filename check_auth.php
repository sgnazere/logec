<?php
require_once 'config.php';
require_once 'check_license.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = "Veuillez vous connecter pour accéder à cette page.";
    header('Location: index.php');
    exit();
}

// Vérifier si la session n'a pas expiré
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    $_SESSION['error'] = "Votre session a expiré. Veuillez vous reconnecter.";
    header('Location: index.php');
    exit();
}

// Mettre à jour le timestamp de dernière activité
$_SESSION['last_activity'] = time();

// Vérifier la licence
checkLicense();

// Vérifier les permissions d'administrateur si nécessaire
if (basename($_SERVER['PHP_SELF']) !== 'dashboard.php' && !isAdmin()) {
    header('Location: dashboard.php');
    exit();
}

// Récupérer le nom et prénom de l'utilisateur s'ils ne sont pas déjà en session
if (!isset($_SESSION['admin_nom']) || !isset($_SESSION['admin_prenoms'])) {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare('SELECT nom, prenoms FROM admin WHERE username = ?');
        $stmt->execute([$_SESSION['admin_username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['admin_nom'] = $user['nom'];
            $_SESSION['admin_prenoms'] = $user['prenoms'];
        }
    } catch(PDOException $e) {
        error_log("Erreur lors de la récupération des informations de l'utilisateur : " . $e->getMessage());
    }
}
?>