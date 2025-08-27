<?php
require_once 'config.php';
require_once 'check_auth.php';

// Récupération des statistiques
$stats = [
    'vehicules' => [
        'total' => $db->query("SELECT COUNT(*) FROM vehicules")->fetchColumn(),
        'disponibles' => $db->query("SELECT COUNT(*) FROM vehicules WHERE statut = 'disponible'")->fetchColumn(),
        'en_mission' => $db->query("SELECT COUNT(*) FROM vehicules WHERE statut = 'en_mission'")->fetchColumn(),
        'en_maintenance' => $db->query("SELECT COUNT(*) FROM vehicules WHERE statut = 'en_maintenance'")->fetchColumn()
    ],
    'chauffeurs' => [
        'total' => $db->query("SELECT COUNT(*) FROM chauffeurs")->fetchColumn(),
        'disponibles' => $db->query("SELECT COUNT(*) FROM chauffeurs WHERE statut = 'disponible'")->fetchColumn(),
        'en_service' => $db->query("SELECT COUNT(*) FROM chauffeurs WHERE statut = 'en_service'")->fetchColumn(),
        'en_conge' => $db->query("SELECT COUNT(*) FROM chauffeurs WHERE statut = 'en_conge'")->fetchColumn()
    ],
    'demandes' => [
        'total' => $db->query("SELECT COUNT(*) FROM demandes_vehicules")->fetchColumn(),
        'en_attente' => $db->query("SELECT COUNT(*) FROM demandes_vehicules WHERE statut = 'en_attente'")->fetchColumn(),
        'approuvees' => $db->query("SELECT COUNT(*) FROM demandes_vehicules WHERE statut = 'approuvee'")->fetchColumn(),
        'rejetees' => $db->query("SELECT COUNT(*) FROM demandes_vehicules WHERE statut = 'rejetee'")->fetchColumn()
    ],
   /* 'destinations' => [
        'total' => $db->query("SELECT COUNT(*) FROM destinations")->fetchColumn()
    ]*/
];

// Récupération des dernières demandes
$query = $db->query("
    SELECT 
        dv.*,
        e.nom as employe_nom,
        e.prenoms as employe_prenoms,
        d.nom as destination_nom,
        d.ville as destination_ville
    FROM demandes_vehicules dv
    LEFT JOIN employees e ON dv.employe_id = e.id
    LEFT JOIN destinations d ON dv.destination_id = d.id
    ORDER BY dv.created_at DESC
    LIMIT 5
");
$dernieres_demandes = $query->fetchAll();

// Récupération des véhicules en mission
$query = $db->query("
    SELECT 
        v.*,
        c.nom as chauffeur_nom,
        c.prenoms as chauffeur_prenoms,
        d.nom as destination_nom,
        d.ville as destination_ville,
        dv.date_retour
    FROM vehicules v
    LEFT JOIN demandes_vehicules dv ON v.id = dv.vehicule_id
    LEFT JOIN chauffeurs c ON dv.chauffeur_id = c.id
    LEFT JOIN destinations d ON dv.destination_id = d.id
    WHERE v.statut = 'en_mission'
    ORDER BY dv.date_retour ASC
");
$vehicules_en_mission = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.2em;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .stat-detail {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-button {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #3498db;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .action-button:hover {
            background: #2980b9;
        }
        .action-button i {
            margin-right: 10px;
            font-size: 1.2em;
        }
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }
        .content-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .content-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .list-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .list-item:last-child {
            border-bottom: none;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        .status-en_attente { background: #ffc107; color: black; }
        .status-approuvee { background: #28a745; color: white; }
        .status-rejetee { background: #dc3545; color: white; }
        .status-en_mission { background: #17a2b8; color: white; }
        .status-disponible { background: #28a745; color: white; }
        .status-en_maintenance { background: #dc3545; color: white; }
    </style>
</head>
<body>
    <div class="dashboard">
        <h1>Tableau de Bord</h1>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-car"></i> Véhicules</h3>
                <div class="stat-number"><?php echo $stats['vehicules']['total']; ?></div>
                <div class="stat-detail">
                    <span>Disponibles: <?php echo $stats['vehicules']['disponibles']; ?></span>
                    <span>En mission: <?php echo $stats['vehicules']['en_mission']; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Chauffeurs</h3>
                <div class="stat-number"><?php echo $stats['chauffeurs']['total']; ?></div>
                <div class="stat-detail">
                    <span>Disponibles: <?php echo $stats['chauffeurs']['disponibles']; ?></span>
                    <span>En service: <?php echo $stats['chauffeurs']['en_service']; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-file-alt"></i> Demandes</h3>
                <div class="stat-number"><?php echo $stats['demandes']['total']; ?></div>
                <div class="stat-detail">
                    <span>En attente: <?php echo $stats['demandes']['en_attente']; ?></span>
                    <span>Approuvées: <?php echo $stats['demandes']['approuvees']; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-map-marker-alt"></i> Destinations</h3>
                <div class="stat-number"><?php echo $stats['destinations']['total']; ?></div>
                <div class="stat-detail">
                    <span>Points desservis</span>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="quick-actions">
            <a href="gerer_demandes.php" class="action-button">
                <i class="fas fa-plus-circle"></i>
                Nouvelle demande
            </a>
            <a href="gerer_vehicules.php" class="action-button">
                <i class="fas fa-car"></i>
                Gérer les véhicules
            </a>
            <a href="gerer_chauffeurs.php" class="action-button">
                <i class="fas fa-users"></i>
                Gérer les chauffeurs
            </a>
            <a href="gerer_destinations.php" class="action-button">
                <i class="fas fa-map-marker-alt"></i>
                Gérer les destinations
            </a>
        </div>

        <!-- Contenu principal -->
        <div class="content-grid">
            <!-- Dernières demandes -->
            <div class="content-card">
                <h3>Dernières demandes</h3>
                <?php foreach ($dernieres_demandes as $demande): ?>
                    <div class="list-item">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo htmlspecialchars($demande['employe_nom'] . ' ' . $demande['employe_prenoms']); ?></strong>
                                <div style="font-size: 0.9em; color: #666;">
                                    <?php echo htmlspecialchars($demande['destination_nom'] . ' (' . $demande['destination_ville'] . ')'); ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $demande['statut']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $demande['statut'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Véhicules en mission -->
            <div class="content-card">
                <h3>Véhicules en mission</h3>
                <?php foreach ($vehicules_en_mission as $vehicule): ?>
                    <div class="list-item">
                        <div>
                            <strong><?php echo htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele']); ?></strong>
                            <div style="font-size: 0.9em; color: #666;">
                                Chauffeur: <?php echo htmlspecialchars($vehicule['chauffeur_nom'] . ' ' . $vehicule['chauffeur_prenoms']); ?><br>
                                Destination: <?php echo htmlspecialchars($vehicule['destination_nom'] . ' (' . $vehicule['destination_ville'] . ')'); ?><br>
                                Retour prévu: <?php echo date('d/m/Y H:i', strtotime($vehicule['date_retour'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html> 