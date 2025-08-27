<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

$db = getDBConnection();

// Traitement des filtres
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');
$type_rapport = isset($_GET['type_rapport']) ? $_GET['type_rapport'] : 'journalier';

// Fonction pour calculer le taux d'utilisation
function calculerTauxUtilisation($heures_utilisation, $heures_totales) {
    return ($heures_totales > 0) ? round(($heures_utilisation / $heures_totales) * 100, 2) : 0;
}

// 1. Rapport journalier des sorties et retours
$rapport_journalier = $db->prepare("
    SELECT 
        d.id,
        v.marque, v.modele, v.immatriculation,
        CONCAT(c.nom, ' ', c.prenoms) as chauffeur,
        d.date_depart,
        d.date_retour,
        dd.km_depart,
        dd.km_retour,
        dd.date_km_depart,
        dd.date_km_retour,
        GROUP_CONCAT(DISTINCT dv.motif SEPARATOR ', ') as motifs,
        GROUP_CONCAT(DISTINCT 
            CONCAT(
                CASE WHEN dv.districts != '' THEN CONCAT(dv.districts, ' - ') ELSE '' END,
                CASE WHEN dv.communes != '' THEN dv.communes ELSE '' END
            )
            SEPARATOR ', '
        ) as destinations
    FROM deplacements d
    JOIN vehicules v ON d.vehicule_id = v.id
    JOIN chauffeurs c ON d.chauffeur_id = c.id
    LEFT JOIN demandes_deplacements dd ON d.id = dd.deplacement_id
    LEFT JOIN demandes_vehicules dv ON dd.demande_id = dv.id
    WHERE DATE(d.date_depart) BETWEEN ? AND ?
    GROUP BY d.id
    ORDER BY d.date_depart DESC
");
$rapport_journalier->execute([$date_debut, $date_fin]);
$sorties_journalieres = $rapport_journalier->fetchAll();

// 2. Rapport hebdomadaire/mensuel
$rapport_utilisation = $db->prepare("
    SELECT 
        v.id,
        v.marque,
        v.modele,
        v.immatriculation,
        COUNT(DISTINCT d.id) as nombre_missions,
        SUM(
            CASE 
                WHEN dd.km_retour IS NOT NULL AND dd.km_depart IS NOT NULL 
                THEN dd.km_retour - dd.km_depart 
                ELSE 0 
            END
        ) as kilometres_parcourus,
        SUM(
            CASE 
                WHEN d.date_retour IS NOT NULL AND d.date_depart IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, d.date_depart, d.date_retour)
                ELSE 0 
            END
        ) as heures_roulage
    FROM vehicules v
    LEFT JOIN deplacements d ON v.id = d.vehicule_id AND DATE(d.date_depart) BETWEEN ? AND ?
    LEFT JOIN demandes_deplacements dd ON d.id = dd.deplacement_id
    GROUP BY v.id
    ORDER BY kilometres_parcourus DESC
");
$rapport_utilisation->execute([$date_debut, $date_fin]);
$utilisation_vehicules = $rapport_utilisation->fetchAll();

// 3. Rapport des demandes
$rapport_demandes = $db->prepare("
    SELECT 
        COUNT(*) as total_demandes,
        SUM(CASE WHEN dv.statut = 'validee' THEN 1 ELSE 0 END) as demandes_satisfaites,
        SUM(CASE WHEN dv.statut = 'refusee' THEN 1 ELSE 0 END) as demandes_refusees,
        SUM(CASE WHEN dv.statut = 'en_attente' THEN 1 ELSE 0 END) as demandes_en_attente,
        AVG(
            CASE 
                WHEN d.date_depart IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, dv.date_creation, d.date_depart)
                ELSE NULL 
            END
        ) as delai_moyen_minutes
    FROM demandes_vehicules dv
    LEFT JOIN demandes_deplacements dd ON dv.id = dd.demande_id
    LEFT JOIN deplacements d ON dd.deplacement_id = d.id
    WHERE DATE(dv.date_creation) BETWEEN ? AND ?
");
$rapport_demandes->execute([$date_debut, $date_fin]);
$statistiques_demandes = $rapport_demandes->fetch();

// Raisons des refus
$raisons_refus = $db->prepare("
    SELECT 
        motif as raison_refus,
        COUNT(*) as nombre
    FROM demandes_vehicules
    WHERE statut = 'refusee'
    AND DATE(date_creation) BETWEEN ? AND ?
    GROUP BY motif
");
$raisons_refus->execute([$date_debut, $date_fin]);
$stats_refus = $raisons_refus->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports | LOGEC</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --accent-color: #FF5722;
            --text-primary: #212121;
            --text-secondary: #757575;
            --divider-color: #BDBDBD;
            --background-light: #f5f5f5;
            --white: #ffffff;
            --danger: #dc3545;
            --warning: #ffc107;
            --success: #28a745;
        }

        body {
            background-color: var(--background-light);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
            width: 95%;
        }

        .page-header {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .page-header h2 {
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rapport-section {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .rapport-section h3 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }

        .stat-card {
            background: var(--background-light);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.3s ease;
            border: 1px solid var(--divider-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .chart-container {
            background: var(--white);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--divider-color);
            height: 400px;
            margin: 1.5rem 0;
        }

        .filters {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filters .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            align-items: start;
            padding: 1rem;
            background: var(--background-light);
            border-radius: 8px;
        }

        .filters .form-group {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }

        .filters .form-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }

        .filters label {
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            display: block;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .filters input, .filters select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .filters input:focus, .filters select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .filters input::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .filters .btn {
            padding: 0.75rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            height: 45px;
            margin-top: 1.9rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .table th {
            background: var(--primary-color);
            color: var(--white);
            padding: 1rem;
            text-align: left;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--divider-color);
        }

        .table tbody tr:hover {
            background: var(--background-light);
        }

        /* DataTables customization */
        .dataTables_wrapper .dataTables_filter input {
            padding: 0.5rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            margin-left: 0.5rem;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 0.5rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.5rem 1rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            margin: 0 0.25rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: var(--primary-color);
            color: var(--white) !important;
            border-color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters .row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="page-header">
            <h2><i class="fas fa-chart-line"></i> Rapports</h2>
            <?php include('menu.php'); ?>
        </div>

        <!-- Filtres -->
            <div class="filters">
            <form method="GET" class="row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Date de début</label>
                    <input type="date" 
                           name="date_debut" 
                           value="<?php echo $date_debut; ?>" 
                           class="form-control"
                           placeholder="Sélectionner la date de début">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Date de fin</label>
                    <input type="date" 
                           name="date_fin" 
                           value="<?php echo $date_fin; ?>" 
                           class="form-control"
                           placeholder="Sélectionner la date de fin">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-filter"></i> Type de rapport</label>
                    <select name="type_rapport" class="form-control">
                        <option value="" disabled>Sélectionner le type de rapport</option>
                        <option value="journalier" <?php echo $type_rapport == 'journalier' ? 'selected' : ''; ?>>Journalier</option>
                        <option value="hebdomadaire" <?php echo $type_rapport == 'hebdomadaire' ? 'selected' : ''; ?>>Hebdomadaire</option>
                        <option value="mensuel" <?php echo $type_rapport == 'mensuel' ? 'selected' : ''; ?>>Mensuel</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filtrer les rapports
                </button>
            </div>
            </form>
        </div>

        <!-- 1. Rapport journalier -->
        <div class="rapport-section">
            <h3><i class="fas fa-calendar-day"></i> Rapport journalier des sorties et retours</h3>
            <div class="table-responsive">
                <table id="sorties-table" class="table">
                    <thead>
                        <tr>
                            <th>Véhicule</th>
                            <th>Chauffeur</th>
                            <th>Départ</th>
                            <th>Retour</th>
                            <th>Km Départ</th>
                            <th>Km Retour</th>
                            <th>Km Parcourus</th>
                            <th>Motif</th>
                            <th>Destinations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sorties_journalieres as $sortie): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sortie['marque'] . ' ' . $sortie['modele'] . ' (' . $sortie['immatriculation'] . ')'); ?></td>
                                <td><?php echo htmlspecialchars($sortie['chauffeur']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($sortie['date_depart'])); ?></td>
                                <td><?php echo $sortie['date_retour'] ? date('d/m/Y H:i', strtotime($sortie['date_retour'])) : '-'; ?></td>
                                <td><?php echo $sortie['km_depart'] ?? '-'; ?></td>
                                <td><?php echo $sortie['km_retour'] ?? '-'; ?></td>
                                <td><?php echo ($sortie['km_retour'] && $sortie['km_depart']) ? ($sortie['km_retour'] - $sortie['km_depart']) : '-'; ?></td>
                                <td><?php echo htmlspecialchars($sortie['motifs']); ?></td>
                                <td><?php echo htmlspecialchars($sortie['destinations']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Rapport d'utilisation -->
        <div class="rapport-section">
            <h3><i class="fas fa-chart-bar"></i> Rapport d'utilisation des véhicules</h3>
            <div class="stats-grid">
                <?php foreach ($utilisation_vehicules as $vehicule): ?>
                <div class="stat-card">
                        <h4><?php echo htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele']); ?></h4>
                        <div class="stat-value"><?php echo $vehicule['nombre_missions']; ?></div>
                        <div class="stat-label">Missions effectuées</div>
                        <div class="stat-value"><?php echo $vehicule['kilometres_parcourus']; ?> km</div>
                        <div class="stat-label">De roulage</div>
                        <div class="stat-value"><?php echo $vehicule['heures_roulage']; ?> h</div>
                    </div>
                <?php endforeach; ?>
                    </div>
                    <div class="chart-container">
                <canvas id="utilisationChart"></canvas>
            </div>
        </div>

        <!-- 3. Rapport des demandes -->
        <div class="rapport-section">
            <h3><i class="fas fa-clipboard-list"></i> Rapport des demandes</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $statistiques_demandes['total_demandes']; ?></div>
                    <div class="stat-label">Demandes totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $statistiques_demandes['demandes_satisfaites']; ?></div>
                    <div class="stat-label">Demandes satisfaites</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $statistiques_demandes['demandes_refusees']; ?></div>
                    <div class="stat-label">Demandes refusées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo round($statistiques_demandes['delai_moyen_minutes'] / 60, 1); ?>h</div>
                    <div class="stat-label">Délai moyen de traitement</div>
                </div>
            </div>

            <!-- Graphique des raisons de refus -->
            <div class="chart-container">
                <canvas id="refusChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        $(document).ready(function() {
            // Initialisation de DataTables avec export
            $('#sorties-table').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'print'
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                },
                order: [[2, 'desc']] // Tri par date de départ par défaut
            });

            // Graphique d'utilisation des véhicules
            const utilisationData = <?php echo json_encode($utilisation_vehicules); ?>;
            new Chart(document.getElementById('utilisationChart'), {
                type: 'bar',
                data: {
                    labels: utilisationData.map(v => v.marque + ' ' + v.modele),
                    datasets: [{
                        label: 'Kilomètres parcourus',
                        data: utilisationData.map(v => v.kilometres_parcourus),
                        backgroundColor: '#4CAF50'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Kilomètres'
                            }
                        }
                    }
                }
            });

            // Graphique des raisons de refus
            const refusData = <?php echo json_encode($stats_refus); ?>;
            new Chart(document.getElementById('refusChart'), {
                type: 'pie',
                data: {
                    labels: refusData.map(r => r.raison_refus),
                    datasets: [{
                        data: refusData.map(r => r.nombre),
                        backgroundColor: ['#4CAF50', '#FFC107', '#F44336', '#2196F3']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                            title: {
                                display: true,
                            text: 'Répartition des motifs de refus'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html> 