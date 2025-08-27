<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    $_SESSION['error'] = "Requête invalide";
    header('Location: gerer_demandes.php');
    exit();
}

$demande_id = (int)$_POST['id'];

try {
    // Vérifier que la demande appartient à l'employé connecté et est en attente
    $stmt = $db->prepare("
        SELECT id 
        FROM demandes_vehicules 
        WHERE id = ? AND employe_id = ? AND statut = 'en_attente'
    ");
    $stmt->execute([$demande_id, $_SESSION['id']]);
    
    if (!$stmt->fetch()) {
        throw new Exception("Vous n'êtes pas autorisé à supprimer cette demande");
    }

    // Supprimer la demande
    $stmt = $db->prepare("DELETE FROM demandes_vehicules WHERE id = ?");
    $stmt->execute([$demande_id]);

    $_SESSION['success'] = "La demande a été supprimée avec succès";
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header('Location: gerer_demandes.php');
exit(); 