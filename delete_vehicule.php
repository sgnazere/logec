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
    // Vérifier si le véhicule est utilisé dans des demandes
    $stmt = $db->prepare("SELECT COUNT(*) FROM demandes_vehicules WHERE vehicule_id = ?");
    $stmt->execute([$id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ce véhicule ne peut pas être supprimé car il est associé à des demandes.'
        ]);
        exit();
    }

    // Supprimer le véhicule
    $stmt = $db->prepare("DELETE FROM vehicules WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Véhicule non trouvé']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()]);
} 