<?php
// Pas besoin de session_start() ici car il est déjà appelé dans le fichier principal

// Vérifier si l'utilisateur est un employé actif
try {
    require_once 'config.php';
    $stmt = $db->prepare('SELECT id, nom, prenoms, email, status FROM employees WHERE status = "actif" LIMIT 1');
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Définir les variables de session si elles ne sont pas déjà définies
        if (!isset($_SESSION['id'])) {
            $_SESSION['id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenoms'] = $user['prenoms'];
        }
    } else {
        die("Aucun employé actif trouvé dans le système.");
    }
} catch(PDOException $e) {
    error_log("Erreur lors de la vérification de l'employé : " . $e->getMessage());
    die("Une erreur est survenue lors de la vérification de l'employé.");
}
?> 