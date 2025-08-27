<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit();
}

$id = (int)$_POST['id'];

try {
    // Vérifier si le chauffeur est en mission
    $stmt = $db->prepare("SELECT statut FROM chauffeurs WHERE id = ?");
    $stmt->execute([$id]);
    $chauffeur = $stmt->fetch();

    if ($chauffeur && $chauffeur['statut'] === 'en_mission') {
        echo json_encode([
            'success' => false,
            'message' => 'Ce chauffeur ne peut pas être supprimé car il est actuellement en mission.'
        ]);
        exit();
    }

    // Supprimer le chauffeur
    $stmt = $db->prepare("DELETE FROM chauffeurs WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chauffeur non trouvé']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
}
?> 