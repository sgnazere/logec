<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

// Fonction pour générer un nouveau code de sortie
function genererCodeSortie($db) {
    $annee = date('Y');
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM sorties_vehicules 
        WHERE YEAR(date_sortie) = ?
    ");
    $stmt->execute([$annee]);
    $count = $stmt->fetch()['count'];
    return 'S' . $annee . sprintf('%04d', $count + 1);
}

// Traitement de la création d'une nouvelle sortie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'creer_sortie') {
        try {
            $db->beginTransaction();

            $code_sortie = genererCodeSortie($db);
            $vehicule_id = $_POST['vehicule_id'];
            $chauffeur_id = $_POST['chauffeur_id'];
            $demandes_ids = $_POST['demandes'];
            $date_sortie = $_POST['date_sortie'];
            $heure_depart = $_POST['heure_depart'];
            $heure_retour = $_POST['heure_retour'];
            $destination = $_POST['destination'];

            // Créer la sortie
            $stmt = $db->prepare("
                INSERT INTO sorties_vehicules 
                (code_sortie, vehicule_id, chauffeur_id, date_sortie, heure_depart, heure_retour, destination_commune)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code_sortie, $vehicule_id, $chauffeur_id, $date_sortie, $heure_depart, $heure_retour, $destination]);
            $sortie_id = $db->lastInsertId();

            // Créer le déplacement associé
            $stmt = $db->prepare("
                INSERT INTO deplacements 
                (vehicule_id, chauffeur_id, date_depart, date_retour, statut)
                VALUES (?, ?, ?, ?, 'planifie')
            ");
            $date_depart = $date_sortie . ' ' . $heure_depart;
            $date_retour = $date_sortie . ' ' . $heure_retour;
            $stmt->execute([$vehicule_id, $chauffeur_id, $date_depart, $date_retour]);
            $deplacement_id = $db->lastInsertId();

            // Lier les demandes à la sortie et au déplacement
            foreach ($demandes_ids as $demande_id) {
                // Lier à la sortie
                $stmt = $db->prepare("
                    INSERT INTO sorties_demandes (sortie_id, demande_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$sortie_id, $demande_id]);

                // Lier au déplacement avec les kilométrages
                $stmt = $db->prepare("
                    INSERT INTO demandes_deplacements 
                    (demande_id, deplacement_id, km_depart, date_km_depart)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $demande_id, 
                    $deplacement_id, 
                    $_POST['km_depart'],
                    $_POST['date_km_depart']
                ]);

                // Mettre à jour le statut de la demande
                $stmt = $db->prepare("
                    UPDATE demandes_vehicules 
                    SET statut = 'validee', vehicule_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$vehicule_id, $demande_id]);
            }

            // Mettre à jour le statut du véhicule et du chauffeur
            $stmt = $db->prepare("UPDATE vehicules SET statut = 'en_mission' WHERE id = ?");
            $stmt->execute([$vehicule_id]);

            $stmt = $db->prepare("UPDATE chauffeurs SET statut = 'en_mission' WHERE id = ?");
            $stmt->execute([$chauffeur_id]);

            $db->commit();
            $_SESSION['success'] = "Déplacement créé avec succès (Code sortie: $code_sortie)";
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Erreur lors de la création du déplacement: " . $e->getMessage();
        }
        header('Location: gerer_deplacements.php');
        exit();
    }
}

// Récupérer les demandes en attente groupées par date et destination
$demandes_groupees = $db->query("
    SELECT 
        DATE(d1.date_depart) as date_sortie,
        d1.heure_depart,
        d1.heure_retour,
        GROUP_CONCAT(DISTINCT 
            CONCAT(
                CASE WHEN d1.districts != '' THEN CONCAT(d1.districts, ' - ') ELSE '' END,
                CASE WHEN d1.communes != '' THEN d1.communes ELSE '' END
            )
            SEPARATOR ', '
        ) as destinations,
        GROUP_CONCAT(DISTINCT d1.id) as demandes_ids,
        GROUP_CONCAT(
            DISTINCT CONCAT(e.nom, ' ', e.prenoms, ' (', d1.nombre_passagers, ' passagers)')
        ) as employes,
        SUM(d1.nombre_passagers) as total_passagers,
        COUNT(DISTINCT d1.id) as nombre_demandes
    FROM demandes_vehicules d1
    JOIN employees e ON d1.employe_id = e.id
    WHERE d1.statut = 'en_attente'
    GROUP BY DATE(d1.date_depart), d1.heure_depart, d1.heure_retour
    HAVING COUNT(*) >= 1
    ORDER BY date_sortie, heure_depart
")->fetchAll();

// Récupérer les véhicules disponibles
try {
    // Ajouter le champ energie à la table vehicules s'il n'existe pas
    $db->query("
        ALTER TABLE vehicules 
        ADD COLUMN IF NOT EXISTS energie ENUM('Diesel', 'Essence') 
        DEFAULT 'Diesel'
    ");
    
    $vehicules = $db->query("
        SELECT *
        FROM vehicules
        WHERE statut = 'disponible'
        ORDER BY capacite DESC, marque, modele
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Erreur lors de la modification de la table vehicules : " . $e->getMessage());
    $_SESSION['error'] = "Une erreur est survenue lors de la mise à jour de la base de données.";
}

// Récupérer les chauffeurs disponibles
try {
    // Vérifier si la table chauffeurs existe
    $table_exists = $db->query("SHOW TABLES LIKE 'chauffeurs'")->rowCount() > 0;
    error_log("La table chauffeurs existe : " . ($table_exists ? "Oui" : "Non"));

    if ($table_exists) {
        // Vérifier la structure de la table
        $columns = $db->query("SHOW COLUMNS FROM chauffeurs")->fetchAll(PDO::FETCH_COLUMN);
        error_log("Colonnes de la table chauffeurs : " . print_r($columns, true));

        // Récupérer tous les chauffeurs sans filtre de statut
        $all_chauffeurs = $db->query("SELECT * FROM chauffeurs")->fetchAll();
        error_log("Nombre total de chauffeurs : " . count($all_chauffeurs));
        error_log("Données des chauffeurs : " . print_r($all_chauffeurs, true));

        // Récupérer les chauffeurs disponibles
        $chauffeurs = $db->query("
            SELECT *
            FROM chauffeurs
            WHERE statut = 'disponible'
            ORDER BY nom, prenoms
        ")->fetchAll();
        error_log("Nombre de chauffeurs disponibles : " . count($chauffeurs));
    } else {
        // Créer la table chauffeurs si elle n'existe pas
        $db->query("
            CREATE TABLE IF NOT EXISTS chauffeurs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nom VARCHAR(50) NOT NULL,
                prenoms VARCHAR(100) NOT NULL,
                statut ENUM('disponible', 'en_mission', 'en_conge', 'hors_service') DEFAULT 'disponible',
                date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        error_log("Table chauffeurs créée");
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la vérification/récupération des chauffeurs : " . $e->getMessage());
    $_SESSION['error'] = "Une erreur est survenue lors de la récupération des chauffeurs.";
}

// Débogage
error_log("Nombre de véhicules disponibles : " . count($vehicules));
error_log("Nombre de chauffeurs disponibles : " . count($chauffeurs));

// Vérifier si les tables existent et contiennent des données
$all_vehicules = $db->query("SELECT COUNT(*) as count FROM vehicules")->fetch();
$all_chauffeurs = $db->query("SELECT COUNT(*) as count FROM chauffeurs")->fetch();
error_log("Nombre total de véhicules : " . $all_vehicules['count']);
error_log("Nombre total de chauffeurs : " . $all_chauffeurs['count']);

// Vérifier les statuts des véhicules
$vehicule_stats = $db->query("SELECT statut, COUNT(*) as count FROM vehicules GROUP BY statut")->fetchAll();
error_log("Statuts des véhicules : " . print_r($vehicule_stats, true));

// Vérifier les statuts des chauffeurs
$chauffeur_stats = $db->query("SELECT statut, COUNT(*) as count FROM chauffeurs GROUP BY statut")->fetchAll();
error_log("Statuts des chauffeurs : " . print_r($chauffeur_stats, true));

// Récupérer les déplacements planifiés
$deplacements = $db->query("
    SELECT 
        d.*,
        v.marque, v.modele, v.immatriculation, v.capacite,
        CONCAT(c.nom, ' ', c.prenoms) as chauffeur,
        sv.code_sortie,
        GROUP_CONCAT(
            DISTINCT CONCAT(emp.nom, ' ', emp.prenoms)
            SEPARATOR ', '
        ) as employes,
        COUNT(DISTINCT dv.id) as nombre_demandes,
        GROUP_CONCAT(
            DISTINCT CONCAT(emp.nom, ' ', emp.prenoms, ' (', dv.nombre_passagers, ' passagers)')
            SEPARATOR ', '
        ) as passagers,
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
    LEFT JOIN employees emp ON dv.employe_id = emp.id
    LEFT JOIN sorties_vehicules sv ON (
        sv.vehicule_id = d.vehicule_id AND 
        sv.chauffeur_id = d.chauffeur_id AND 
        DATE(sv.date_sortie) = DATE(d.date_depart)
    )
    GROUP BY d.id
    ORDER BY d.date_depart DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Déplacements</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/select2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .card {
            background: var(--white);
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            border: 1px solid var(--divider-color);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--divider-color);
        }

        .card-header h4 {
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .demande-group {
            background: var(--background-light);
            border: 1px solid var(--divider-color);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .demande-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            background: var(--white);
            padding: 1rem;
            border-radius: 5px;
            border: 1px solid var(--divider-color);
        }

        .info-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .employes-list {
            background: var(--white);
            border: 1px solid var(--divider-color);
            border-radius: 5px;
            padding: 1rem;
        }

        .employe-item {
            padding: 0.75rem;
            border-bottom: 1px solid var(--divider-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .employe-item:last-child {
            border-bottom: none;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--divider-color);
            border-radius: 5px;
            background-color: var(--white);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
            border: none;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: var(--success);
            color: var(--white);
        }

        .alert-danger {
            background: var(--danger);
            color: var(--white);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
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

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            display: inline-block;
        }

        .status-planifie { background: var(--primary-light); color: var(--primary-dark); }
        .status-en-cours { background: var(--warning); color: var(--text-primary); }
        .status-termine { background: var(--success); color: var(--white); }

        @media (max-width: 768px) {
            .dashboard {
                padding: 1rem;
                width: 100%;
            }

            .demande-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="page-header">
                <h2><i class="fas fa-route"></i> Gestion des Déplacements</h2>
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
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <section class="section">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Demandes à Regrouper</h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($demandes_groupees as $groupe): ?>
                            <div class="demande-group">
                                <h5>Sortie du <?php echo date('d/m/Y', strtotime($groupe['date_sortie'])); ?></h5>
                                <div class="demande-info">
                                    <div class="info-item">
                                        <div class="info-label">Horaires</div>
                                        <div class="info-value">
                                            <?php echo $groupe['heure_depart'] . ' - ' . $groupe['heure_retour']; ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Destinations</div>
                                        <div class="info-value">
                                            <?php echo htmlspecialchars($groupe['destinations']); ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Nombre de demandes</div>
                                        <div class="info-value"><?php echo $groupe['nombre_demandes']; ?></div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Total passagers</div>
                                        <div class="info-value"><?php echo $groupe['total_passagers']; ?></div>
                                    </div>
                                </div>

                                <div class="employes-list mb-3">
                                    <h6><i class="fas fa-users"></i> Employés concernés</h6>
                                    <?php foreach (explode(',', $groupe['employes']) as $employe): ?>
                                        <div class="employe-item">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($employe); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <form method="POST" class="sortie-form">
                                    <input type="hidden" name="action" value="creer_sortie">
                                    <input type="hidden" name="date_sortie" value="<?php echo $groupe['date_sortie']; ?>">
                                    <input type="hidden" name="heure_depart" value="<?php echo $groupe['heure_depart']; ?>">
                                    <input type="hidden" name="heure_retour" value="<?php echo $groupe['heure_retour']; ?>">
                                    <input type="hidden" name="destination" value="<?php echo htmlspecialchars($groupe['destinations']); ?>">
                                    
                                    <?php
                                    $demandes_ids = explode(',', $groupe['demandes_ids']);
                                    foreach ($demandes_ids as $demande_id) {
                                        echo "<input type='hidden' name='demandes[]' value='" . $demande_id . "'>";
                                    }
                                    ?>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Véhicule</label>
                                                <select name="vehicule_id" class="form-select select2" required>
                                                    <option value="">Sélectionner un véhicule</option>
                                                    <?php foreach ($vehicules as $vehicule): ?>
                                                        <?php if ($vehicule['capacite'] >= $groupe['total_passagers']): ?>
                                                            <option value="<?php echo $vehicule['id']; ?>">
                                                                <?php echo htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele'] . 
                                                                    ' (' . $vehicule['immatriculation'] . ') - ' . $vehicule['capacite'] . ' places'); ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Chauffeur</label>
                                                <select name="chauffeur_id" class="form-select select2" required>
                                                    <option value="">Sélectionner un chauffeur</option>
                                                    <?php foreach ($chauffeurs as $chauffeur): ?>
                                                        <option value="<?php echo $chauffeur['id']; ?>">
                                                            <?php echo htmlspecialchars($chauffeur['nom'] . ' ' . $chauffeur['prenoms']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Kilométrage au départ</label>
                                                <input type="number" name="km_depart" class="form-control" required min="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Date et heure du relevé de départ</label>
                                                <input type="datetime-local" name="date_km_depart" class="form-control" required>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Créer la sortie
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($demandes_groupees)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Aucune demande en attente à regrouper
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Déplacements Planifiés</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="deplacements-table">
                                <thead>
                                    <tr>
                                        <th>Code Sortie</th>
                                        <th>Date et Heure</th>
                                        <th>Véhicule</th>
                                        <th>Chauffeur</th>
                                        <th>Destinations</th>
                                        <th>Passagers</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($deplacements as $deplacement): ?>
                                        <tr>
                                            <td>
                                                <?php if ($deplacement['code_sortie']): ?>
                                                    <?php echo htmlspecialchars($deplacement['code_sortie']); ?>
                                                <?php else: ?>
                                                    #<?php echo $deplacement['id']; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    echo date('d/m/Y H:i', strtotime($deplacement['date_depart'])) . ' - ' . 
                                                    date('H:i', strtotime($deplacement['date_retour']));
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($deplacement['marque'] . ' ' . $deplacement['modele'] . 
                                                    ' (' . $deplacement['immatriculation'] . ')'); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($deplacement['chauffeur']); ?></td>
                                            <td><?php echo htmlspecialchars($deplacement['destinations']); ?></td>
                                            <td><?php echo $deplacement['passagers']; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $deplacement['statut']; ?>">
                                                    <?php echo ucfirst($deplacement['statut']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script src="assets/js/jquery-3.7.1.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de DataTables
            $('#deplacements-table').DataTable({
                language: {
                    url: 'assets/js/french.json'
                },
                order: [[1, 'desc']]
            });

            // Initialisation de Select2
            $('.select2').select2({
                theme: 'bootstrap-5'
            });

            // Validation du formulaire
            $('.sortie-form').on('submit', function(e) {
                const vehicule = $(this).find('select[name="vehicule_id"]').val();
                const chauffeur = $(this).find('select[name="chauffeur_id"]').val();

                if (!vehicule || !chauffeur) {
                    alert('Veuillez sélectionner un véhicule et un chauffeur');
                    e.preventDefault();
                    return false;
                }

                return confirm('Voulez-vous créer ce déplacement ?');
            });
        });
    </script>
</body>
</html> 