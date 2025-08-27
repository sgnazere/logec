<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = isset($_POST['id']) ? $_POST['id'] : null;
        $marque = $_POST['marque'];
        $modele = $_POST['modele'];
        $immatriculation = $_POST['immatriculation'];
        $type_vehicule = $_POST['type_vehicule'];
        $capacite = $_POST['capacite'];
        $kilometrage = $_POST['kilometrage'];
        $annee_mise_service = $_POST['annee_mise_service'];
        $statut = $_POST['statut'];
        $type_carburant = $_POST['type_carburant'];

        if ($id) {
            // Modification
            $stmt = $db->prepare("
                UPDATE vehicules 
                SET marque = ?, 
                    modele = ?, 
                    immatriculation = ?, 
                    type_vehicule = ?, 
                    capacite = ?, 
                    kilometrage = ?, 
                    annee_mise_service = ?, 
                    statut = ?, 
                    energie = ? 
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $marque, 
                $modele, 
                $immatriculation, 
                $type_vehicule, 
                $capacite, 
                $kilometrage, 
                $annee_mise_service, 
                $statut, 
                $type_carburant, 
                $id
            ]);

            if ($result) {
                $_SESSION['success'] = "Véhicule modifié avec succès.";
            } else {
                throw new Exception("Erreur lors de la mise à jour du véhicule");
            }
        } else {
            // Ajout
            $stmt = $db->prepare("
                INSERT INTO vehicules 
                (marque, modele, immatriculation, type_vehicule, capacite, kilometrage, annee_mise_service, statut, energie) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $marque, 
                $modele, 
                $immatriculation, 
                $type_vehicule, 
                $capacite, 
                $kilometrage, 
                $annee_mise_service, 
                $statut, 
                $type_carburant
            ]);

            if ($result) {
                $_SESSION['success'] = "Véhicule ajouté avec succès.";
            } else {
                throw new Exception("Erreur lors de l'ajout du véhicule");
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de l'opération : " . $e->getMessage();
        error_log("Erreur SQL : " . $e->getMessage());
    }
    
    header('Location: gerer_vehicules.php');
    exit();
}

// Récupérer la liste des marques pour le filtre
$marques = $db->query("SELECT DISTINCT marque FROM vehicules ORDER BY marque")->fetchAll(PDO::FETCH_COLUMN);

// Mettre à jour le statut de tous les véhicules à 'disponible'
try {
    $db->query("UPDATE vehicules SET statut = 'disponible'");
} catch (PDOException $e) {
    error_log("Erreur lors de la mise à jour des statuts : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Véhicules</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header h2 i {
            color: var(--primary-color);
        }

        .back-btn {
            background: var(--accent-color);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            background: #f4511e;
        }

        .vehicule-form-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
            border: 1px solid var(--primary-light);
        }

        .form-title {
            color: var(--primary-color);
            font-size: 1.75rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--primary-light);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10mm;
            padding: 10mm;
        }

        .form-group {
            margin-bottom: 10mm;
            background: var(--background-light);
            padding: 1rem;
            border-radius: 8px;
            transition: transform 0.2s ease;
        }

        .form-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
            transform: scale(1.02);
        }

        .submit-btn {
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 0.6rem 1.2rem;
            border: 1px solid var(--primary-color);
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            justify-content: center;
            width: auto;
            margin-top: 5mm;
            font-weight: 500;
            letter-spacing: 0.3px;
        }

        .submit-btn:hover {
            background: var(--primary-color);
            color: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .vehicule-list-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-light);
        }

        .list-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-section {
            background: var(--background-light);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .filter-group select {
            padding: 0.5rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            background: var(--white);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background-color: var(--primary-light);
            color: var(--primary-dark);
            border: 1px solid var(--primary-color);
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-edit, .btn-delete {
            padding: 0.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-edit {
            background-color: var(--warning);
            color: var(--text-primary);
        }

        .btn-delete {
            background-color: var(--danger);
            color: var(--white);
        }

        .btn-edit:hover, .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .statut-actif {
            color: var(--success);
            font-weight: 500;
        }

        .statut-inactif {
            color: var(--danger);
            font-weight: 500;
        }

        .statut-disponible {
            color: var(--success);
            font-weight: 500;
        }

        .statut-en_mission {
            color: var(--warning);
            font-weight: 500;
        }

        .statut-en_maintenance {
            color: var(--accent-color);
            font-weight: 500;
        }

        .statut-hors_service {
            color: var(--danger);
            font-weight: 500;
        }

        table.dataTable {
            border-collapse: collapse;
            width: 100%;
        }

        table.dataTable th, table.dataTable td {
            padding: 1rem;
            border-bottom: 1px solid var(--divider-color);
        }

        table.dataTable thead th {
            background-color: var(--background-light);
            color: var(--text-primary);
            font-weight: 600;
        }

        table.dataTable tbody tr:hover {
            background-color: var(--background-light);
        }

        /* Styles pour les statuts */
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-badge.disponible {
            color: var(--success);
            background-color: rgba(40, 167, 69, 0.1);
        }

        .status-badge.en_mission {
            color: var(--warning);
            background-color: rgba(255, 193, 7, 0.1);
        }

        .status-badge.en_maintenance {
            color: var(--accent-color);
            background-color: rgba(255, 87, 34, 0.1);
        }

        .status-badge.hors_service {
            color: var(--danger);
            background-color: rgba(220, 53, 69, 0.1);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="page-header">
            <h2><i class="fas fa-car"></i> Gestion des Véhicules</h2>
            <a href="gestion_demande_vehicule.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="vehicule-form-container">
            <h3 class="form-title"><i class="fas fa-plus-circle"></i> Ajouter un véhicule</h3>
            <form id="vehiculeForm" method="POST">
                <input type="hidden" name="id" id="vehicule_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="marque">Marque</label>
                        <input type="text" id="marque" name="marque" required>
                    </div>
                    <div class="form-group">
                        <label for="modele">Modèle</label>
                        <input type="text" id="modele" name="modele" required>
                    </div>
                    <div class="form-group">
                        <label for="immatriculation">Immatriculation</label>
                        <input type="text" id="immatriculation" name="immatriculation" required>
                    </div>
                    <div class="form-group">
                        <label for="type_vehicule">Type de véhicule</label>
                        <select id="type_vehicule" name="type_vehicule" required>
                            <option value="">Sélectionner un type de véhicule</option>
                            <option value="Berline">Berline</option>
                            <option value="SUV">SUV</option>
                            <option value="4x4">4x4</option>
                            <option value="Minibus">Minibus</option>
                            <option value="Pick-up">Pick-up</option>
                            <option value="Fourgonnette">Fourgonnette</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="capacite">Capacité (places)</label>
                        <input type="number" id="capacite" name="capacite" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="kilometrage">Kilométrage</label>
                        <input type="number" id="kilometrage" name="kilometrage" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="annee_mise_service">Année de mise en service</label>
                        <input type="number" id="annee_mise_service" name="annee_mise_service" min="1900" max="<?php echo date('Y'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="statut">Statut</label>
                        <select name="statut" id="statut" required>
                            <option value="">Sélectionner un statut</option>
                            <option value="disponible">Disponible</option>
                            <option value="en_mission">En Mission</option>
                            <option value="en_maintenance">En Maintenance</option>
                            <option value="hors_service">Hors Service</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="type_carburant">Type de carburant</label>
                        <select name="type_carburant" id="type_carburant" required>
                            <option value="">Sélectionner un type de carburant</option>
                            <option value="Diesel">Diesel</option>
                            <option value="Essence">Essence</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>

        <div class="vehicule-list-container">
            <div class="list-header">
                <h3 class="list-title"><i class="fas fa-list"></i> Liste des véhicules</h3>
            </div>

            <div class="filter-section">
                <div class="filter-group">
                    <label for="filter_marque">
                        <i class="fas fa-filter"></i> Filtrer par marque:
                    </label>
                    <select id="filter_marque" onchange="filterTable()">
                        <option value="">Toutes les marques</option>
                        <?php foreach ($marques as $marque): ?>
                            <option value="<?php echo htmlspecialchars($marque); ?>">
                                <?php echo htmlspecialchars($marque); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <table id="vehiculesTable" class="display">
                <thead>
                    <tr>
                        <th>Marque</th>
                        <th>Modèle</th>
                        <th>Immatriculation</th>
                        <th>Type</th>
                        <th>Capacité</th>
                        <th>Kilométrage</th>
                        <th>Année</th>
                        <th>Énergie</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $vehicules = $db->query("
                        SELECT * FROM vehicules 
                        ORDER BY marque, modele
                    ")->fetchAll();

                    foreach ($vehicules as $vehicule): ?>
                        <tr>
                            <td><?= htmlspecialchars($vehicule['marque']) ?></td>
                            <td><?= htmlspecialchars($vehicule['modele']) ?></td>
                            <td><?= htmlspecialchars($vehicule['immatriculation']) ?></td>
                            <td><?= htmlspecialchars($vehicule['type_vehicule']) ?></td>
                            <td><?= htmlspecialchars($vehicule['capacite']) ?></td>
                            <td><?= number_format($vehicule['kilometrage'], 0, ',', ' ') ?> km</td>
                            <td><?= htmlspecialchars($vehicule['annee_mise_service']) ?></td>
                            <td><?= htmlspecialchars($vehicule['energie']) ?></td>
                            <td>
                                <span class="status-badge <?= strtolower($vehicule['statut']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $vehicule['statut'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="editVehicule(<?= htmlspecialchars(json_encode($vehicule)) ?>)" class="edit-btn">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteVehicule(<?= $vehicule['id'] ?>)" class="delete-btn">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script>
        let dataTable;

        $(document).ready(function() {
            dataTable = $('#vehiculesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                },
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tous"]]
            });
        });

        function filterTable() {
            const marque = document.getElementById('filter_marque').value;
            dataTable.column(1).search(marque).draw();
        }

        function editVehicule(vehicule) {
            document.getElementById('vehicule_id').value = vehicule.id;
            document.getElementById('marque').value = vehicule.marque;
            document.getElementById('modele').value = vehicule.modele;
            document.getElementById('immatriculation').value = vehicule.immatriculation;
            document.getElementById('type_vehicule').value = vehicule.type_vehicule;
            document.getElementById('capacite').value = vehicule.capacite;
            document.getElementById('kilometrage').value = vehicule.kilometrage;
            document.getElementById('annee_mise_service').value = vehicule.annee_mise_service;
            document.getElementById('type_carburant').value = vehicule.energie;
            document.getElementById('statut').value = vehicule.statut;
            
            document.querySelector('.form-title').innerHTML = '<i class="fas fa-edit"></i> Modifier le véhicule';
            
            document.querySelector('.vehicule-form-container').scrollIntoView({ behavior: 'smooth' });
        }

        function deleteVehicule(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce véhicule ?')) {
                window.location.href = 'gerer_vehicules.php?delete=' + id;
            }
        }

        // Réinitialiser le formulaire
        document.getElementById('vehiculeForm').addEventListener('reset', function() {
            document.getElementById('vehicule_id').value = '';
        });
    </script>
</body>
</html> 