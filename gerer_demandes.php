<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

// Vérification de l'ID de l'employé
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    $_SESSION['error'] = "Votre session a expiré. Veuillez vous reconnecter.";
    header('Location: index.php');
    exit();
}

// Récupérer la liste des communes
$communes = $db->query("SELECT DISTINCT nom FROM communes ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);

// Récupérer la liste des districts
$districts = $db->query("SELECT DISTINCT nom FROM districts ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN);

// Récupérer la liste des employés pour les listes déroulantes
$employees = $db->query("SELECT id, nom, prenoms FROM employees ORDER BY nom, prenoms")->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Vérifier que l'employé existe
        $stmt = $db->prepare("SELECT id FROM employees WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Employé non trouvé");
        }

        $employe_id = $_SESSION['admin_id'];
        $date_depart = $_POST['date_depart'];
        $heure_depart = $_POST['heure_depart'];
        $date_retour = $_POST['date_retour'];
        $heure_retour = $_POST['heure_retour'];
        $communes = isset($_POST['communes']) ? implode(',', $_POST['communes']) : '';
        $districts = isset($_POST['districts']) ? implode(',', $_POST['districts']) : '';
        $sites = $_POST['sites'];
        $autres_endroits = $_POST['autres_endroits'];
        $motif = $_POST['motif'];
        $nombre_passagers = $_POST['nombre_passagers'];
        $demandeur = $_POST['demandeur'];
        $passagers = isset($_POST['passagers']) ? implode(',', $_POST['passagers']) : '';

        // Insérer la demande
        $stmt = $db->prepare("
            INSERT INTO demandes_vehicules (
                employe_id, date_depart, heure_depart, date_retour, heure_retour,
                communes, districts, sites, autres_endroits, motif, nombre_passagers,
                statut, date_creation
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW()
            )
        ");
        
        $stmt->execute([
            $employe_id, $date_depart, $heure_depart, $date_retour, $heure_retour,
            $communes, $districts, $sites, $autres_endroits, $motif, $nombre_passagers
        ]);

        $_SESSION['success'] = "Demande enregistrée avec succès.";
        header('Location: gerer_demandes.php');
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        header('Location: gerer_demandes.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: gestion_demande_vehicule.php');
        exit();
    }
}

// Vérification de l'ID de l'employé
$employe_id = $_SESSION['admin_id'];

// Récupérer les demandes de l'employé connecté
$mes_demandes = $db->prepare("
    SELECT 
        d.*,
        e.nom as employe_nom,
        e.prenoms as employe_prenoms,
        v.marque as vehicule_marque,
        v.modele as vehicule_modele,
        v.immatriculation as vehicule_immatriculation
    FROM demandes_vehicules d
    LEFT JOIN employees e ON d.employe_id = e.id
    LEFT JOIN vehicules v ON d.vehicule_id = v.id
    WHERE d.employe_id = ?
    ORDER BY d.date_creation DESC
");
$mes_demandes->execute([$employe_id]);
$mes_demandes = $mes_demandes->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande de Véhicule | LOGEC</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
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

        .demande-form-container {
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

        .form-title i {
            font-size: 1.2rem;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
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

        .demande-list-container {
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

        .statut-badge {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: 500;
            display: inline-block;
        }

        .statut-en_attente {
            background: var(--warning);
            color: var(--text-primary);
        }

        .statut-approuve {
            background: var(--success);
            color: var(--white);
        }

        .statut-refuse {
            background: var(--danger);
            color: var(--white);
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

        .destinations-section {
            background: var(--background-light);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
            border: 1px solid var(--primary-light);
        }

        .destinations-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-light);
        }

        .select2-container {
            width: 100% !important;
        }

        .select2-container--default .select2-selection--multiple {
            border: 2px solid var(--divider-color);
            border-radius: 8px;
        }

        .select2-container--default.select2-container--focus .select2-selection--multiple {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .suggestions-container {
            background: var(--primary-light);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1.5rem 0;
        }

        .suggestion-item {
            background: var(--white);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Styles pour les boutons d'action */
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .edit-btn, .delete-btn, .cancel-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
        }

        .edit-btn {
            background-color: var(--primary-light);
            color: var(--primary-dark);
        }

        .edit-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .delete-btn {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .delete-btn:hover {
            background-color: var(--danger);
            color: white;
        }

        .cancel-btn {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .cancel-btn:hover {
            background-color: var(--warning);
            color: white;
        }

        .action-buttons i {
            font-size: 14px;
        }

        /* Styles pour la colonne Destination */
        .destination-cell {
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
            line-height: 1.2;
            font-size: 0.9em;
        }

        .destination-cell .destination-item {
            display: block;
            margin-bottom: 2px;
        }

        .destination-cell .destination-item:last-child {
            margin-bottom: 0;
        }

        /* Styles pour les badges de statut */
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-badge.en_attente {
            color: #ff9800;
            background-color: rgba(255, 152, 0, 0.1);
        }

        .status-badge.approuvee {
            color: #4caf50;
            background-color: rgba(76, 175, 80, 0.1);
        }

        .status-badge.refusee {
            color: #f44336;
            background-color: rgba(244, 67, 54, 0.1);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="page-header">
            <h2><i class="fas fa-car"></i> Demande de Véhicule</h2>
            <?php include('menu.php'); ?>
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

        <div class="demande-form-container">
            <h3 class="form-title">
                <i class="fas fa-plus-circle"></i> Nouvelle demande
            </h3>
            <form method="POST" id="demandeForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="demandeur">Demandeur</label>
                        <select id="demandeur" name="demandeur" class="select2" required>
                            <option value="">Sélectionnez le demandeur</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>">
                                    <?php echo htmlspecialchars($emp['nom'] . ' ' . $emp['prenoms']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_depart">Date de départ</label>
                        <input type="date" id="date_depart" name="date_depart" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="heure_depart">Heure de départ</label>
                        <input type="time" id="heure_depart" name="heure_depart" required>
                    </div>
                    <div class="form-group">
                        <label for="date_retour">Date de retour</label>
                        <input type="date" id="date_retour" name="date_retour" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="heure_retour">Heure de retour</label>
                        <input type="time" id="heure_retour" name="heure_retour" required>
                    </div>
                </div>

                <div class="destinations-section">
                    <h4 class="destinations-title">
                        <i class="fas fa-map-marker-alt"></i> Destinations
                    </h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="communes">Communes</label>
                            <select id="communes" name="communes[]" multiple class="select2" data-placeholder="Sélectionnez les communes">
                                <?php foreach ($communes as $commune): ?>
                                    <option value="<?php echo htmlspecialchars($commune); ?>">
                                        <?php echo htmlspecialchars($commune); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="districts">Districts</label>
                            <select id="districts" name="districts[]" multiple class="select2" data-placeholder="Sélectionnez les districts">
                                <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo htmlspecialchars($district); ?>">
                                        <?php echo htmlspecialchars($district); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sites">Sites (séparés par des virgules)</label>
                            <textarea id="sites" name="sites" placeholder="Entrez les sites à visiter"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="autres_endroits">Autres endroits (banque, CNPS, etc.)</label>
                            <textarea id="autres_endroits" name="autres_endroits" placeholder="Précisez les autres endroits à visiter"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="passagers">Passagers</label>
                            <select id="passagers" name="passagers[]" multiple class="select2" data-placeholder="Sélectionnez les passagers">
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>">
                                        <?php echo htmlspecialchars($emp['nom'] . ' ' . $emp['prenoms']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="motif">Motif du déplacement</label>
                        <textarea id="motif" name="motif" required placeholder="Décrivez le motif de votre déplacement"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="nombre_passagers">Nombre de passagers</label>
                        <input type="number" id="nombre_passagers" name="nombre_passagers" min="1" required>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Soumettre la demande
                </button>
            </form>
        </div>

        <?php if (isset($_SESSION['suggestions'])): ?>
            <div class="suggestions-container">
                <h4 class="form-title">
                    <i class="fas fa-lightbulb"></i> Demandes similaires trouvées
                </h4>
                <?php foreach ($_SESSION['suggestions'] as $suggestion): ?>
                    <div class="suggestion-item">
                        <div>
                            <strong><?php echo htmlspecialchars($suggestion['employe_nom'] . ' ' . $suggestion['employe_prenoms']); ?></strong>
                            <p>Date: <?php echo date('d/m/Y', strtotime($suggestion['date_depart'])); ?></p>
                            <p>Destinations: 
                                <?php
                                $destinations = [];
                                if ($suggestion['communes']) $destinations[] = "Communes: " . $suggestion['communes'];
                                if ($suggestion['districts']) $destinations[] = "Districts: " . $suggestion['districts'];
                                if ($suggestion['sites']) $destinations[] = "Sites: " . $suggestion['sites'];
                                echo implode(' | ', $destinations);
                                ?>
                            </p>
                        </div>
                        <span class="status-badge statut-<?php echo $suggestion['statut']; ?>">
                            <?php echo ucfirst($suggestion['statut']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php unset($_SESSION['suggestions']); ?>
        <?php endif; ?>

        <div class="demande-list-container">
            <div class="list-header">
                <h3 class="list-title"><i class="fas fa-history"></i> Mes demandes</h3>
            </div>
            <table id="demandesTable" class="display">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Destination</th>
                        <th>Passagers</th>
                        <th>Véhicule</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mes_demandes as $demande): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($demande['date_depart'])) ?></td>
                            <td><?= $demande['heure_depart'] ?> - <?= $demande['heure_retour'] ?></td>
                            <td class="destination-cell">
                                <?php
                                $destinations = [];
                                if (!empty($demande['districts'])) $destinations[] = $demande['districts'];
                                if (!empty($demande['communes'])) $destinations[] = $demande['communes'];
                                if (!empty($demande['sites'])) $destinations[] = $demande['sites'];
                                if (!empty($demande['autres_endroits'])) $destinations[] = $demande['autres_endroits'];
                                foreach (array_filter($destinations) as $destination) {
                                    echo '<span class="destination-item">' . htmlspecialchars($destination) . '</span>';
                                }
                                ?>
                            </td>
                            <td><?= $demande['nombre_passagers'] ?></td>
                            <td>
                                <?php if ($demande['vehicule_id']): ?>
                                    <?= htmlspecialchars($demande['vehicule_marque'] . ' ' . $demande['vehicule_modele']) ?>
                                    (<?= htmlspecialchars($demande['vehicule_immatriculation']) ?>)
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= strtolower($demande['statut']) ?>">
                                    <?= ucfirst(str_replace('_', ' ', $demande['statut'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($demande['statut'] === 'en_attente'): ?>
                                        <button onclick="editDemande(<?= htmlspecialchars(json_encode($demande)) ?>)" class="edit-btn" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteDemande(<?= $demande['id'] ?>)" class="delete-btn" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php elseif ($demande['statut'] === 'validee'): ?>
                                        <button onclick="annulerDemande(<?= $demande['id'] ?>)" class="cancel-btn" title="Annuler">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de Select2 pour les sélections multiples
            $('.select2').select2({
                width: '100%',
                tags: true,
                tokenSeparators: [',', ' ']
            });

            // Initialisation de DataTable
            $('#demandesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                },
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tous"]]
            });

            // Validation des dates
            $('#demandeForm').on('submit', function(e) {
                const dateDepart = new Date($('#date_depart').val() + ' ' + $('#heure_depart').val());
                const dateRetour = new Date($('#date_retour').val() + ' ' + $('#heure_retour').val());
                const maintenant = new Date();

                if (dateDepart < maintenant) {
                    alert('La date de départ ne peut pas être dans le passé');
                    e.preventDefault();
                    return false;
                }

                if (dateRetour < dateDepart) {
                    alert('La date de retour doit être après la date de départ');
                    e.preventDefault();
                    return false;
                }

                // Vérifier qu'au moins une destination est spécifiée
                const communes = $('#communes').val();
                const districts = $('#districts').val();
                const sites = $('#sites').val().trim();
                const autresEndroits = $('#autres_endroits').val().trim();

                if (!communes.length && !districts.length && !sites && !autresEndroits) {
                    alert('Veuillez spécifier au moins une destination');
                    e.preventDefault();
                    return false;
                }

                // Vérifier que le motif est rempli
                const motif = $('#motif').val().trim();
                if (!motif) {
                    alert('Veuillez spécifier un motif pour la demande');
                    e.preventDefault();
                    return false;
                }
            });

            // Calculer automatiquement le nombre de passagers
            $('#passagers').on('change', function() {
                var selectedPassagers = $(this).val();
                var nombrePassagers = selectedPassagers ? selectedPassagers.length : 0;
                $('#nombre_passagers').val(nombrePassagers);
            });
        });

        function editDemande(demande) {
            // Remplir le formulaire avec les données de la demande
            document.getElementById('id').value = demande.id;
            document.getElementById('date_depart').value = demande.date_depart;
            document.getElementById('heure_depart').value = demande.heure_depart;
            document.getElementById('date_retour').value = demande.date_retour;
            document.getElementById('heure_retour').value = demande.heure_retour;
            
            // Gérer les districts et communes
            if (demande.districts) {
                const districts = demande.districts.split(',');
                $('#districts').val(districts).trigger('change');
            }
            if (demande.communes) {
                const communes = demande.communes.split(',');
                $('#communes').val(communes).trigger('change');
            }
            
            document.getElementById('sites').value = demande.sites;
            document.getElementById('autres_endroits').value = demande.autres_endroits;
            document.getElementById('motif').value = demande.motif;
            document.getElementById('nombre_passagers').value = demande.nombre_passagers;
            
            // Faire défiler jusqu'au formulaire
            document.querySelector('.demande-form-container').scrollIntoView({ behavior: 'smooth' });
        }

        function deleteDemande(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette demande ?')) {
                window.location.href = 'gerer_demandes.php?delete=' + id;
            }
        }

        function annulerDemande(id) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette demande ?')) {
                window.location.href = 'gerer_demandes.php?cancel=' + id;
            }
        }
    </script>
</body>
</html>