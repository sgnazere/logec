<?php
require_once 'check_auth.php';
require_once 'config.php';

header('Content-Type: application/json');

try {
    $db = getDBConnection();

    // Récupérer la liste des employés avec leur service
    $query = $db->query("
        SELECT e.*, s.nom as nom_service 
        FROM employees e 
        LEFT JOIN services s ON e.service_id = s.id 
        WHERE (e.status = 'actif' OR e.status IS NULL)
        ORDER BY e.nom, e.prenoms
    ");
    $employes = $query->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($employes as $employe) {
        // Formater les dates seulement si elles ne sont pas nulles
        $date_naissance = !empty($employe['date_naissance']) ? 
            date('d/m/Y', strtotime($employe['date_naissance'])) : '-';
        $date_embauche = !empty($employe['date_embauche']) ? 
            date('d/m/Y', strtotime($employe['date_embauche'])) : '-';

        // Préparer les données pour DataTables
        $data[] = [
            htmlspecialchars($employe['nom']), // 0
            htmlspecialchars($employe['prenoms']), // 1
            htmlspecialchars($employe['projet'] ?: '-'), // 2
            htmlspecialchars($employe['type_contrat'] ?: '-'), // 3
            htmlspecialchars($employe['poste'] ?: '-'), // 4
            // Actions // 5
            sprintf(
                '<div class="action-buttons">
                    <button type="button" class="btn btn-edit" data-id="%d" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-delete" data-id="%d" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>',
                (int)$employe['id'],
                (int)$employe['id']
            ),
            // Colonnes cachées pour l'infobulle
            htmlspecialchars($employe['sexe'] ?? '-'), // 6
            htmlspecialchars($employe['adresse'] ?? '-'), // 7
            htmlspecialchars($employe['telephone'] ?: '-'), // 8
            htmlspecialchars($employe['email'] ?: '-'), // 9
            $date_naissance, // 10
            $date_embauche, // 11
            htmlspecialchars($employe['numero_secu'] ?? '-'), // 12
            htmlspecialchars($employe['numero_urgence'] ?? '-'), // 13
            htmlspecialchars($employe['nom_service'] ?: '-') // 14
        ];
    }

    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => count($employes),
        'recordsFiltered' => count($employes),
        'data' => $data
    ]);
} catch(PDOException $e) {
    error_log("Erreur get_employee_list.php : " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : 1,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Erreur lors de la récupération des employés : ' . $e->getMessage()
    ]);
}
?>
