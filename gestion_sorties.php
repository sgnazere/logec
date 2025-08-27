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

            // Lier les demandes à la sortie
            foreach ($demandes_ids as $demande_id) {
                $stmt = $db->prepare("
                    INSERT INTO sorties_demandes (sortie_id, demande_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$sortie_id, $demande_id]);

                // Mettre à jour le statut de la demande
                $stmt = $db->prepare("
                    UPDATE demandes_vehicules 
                    SET statut = 'approuve', vehicule_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$vehicule_id, $demande_id]);
            }

            $db->commit();
            $_SESSION['success'] = "Sortie créée avec succès (Code: $code_sortie)";
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Erreur lors de la création de la sortie: " . $e->getMessage();
        }
        header('Location: gestion_sorties.php');
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
            CASE 
                WHEN d1.communes != '' THEN d1.communes
                WHEN d1.districts != '' THEN d1.districts
                WHEN d1.sites != '' THEN d1.sites
                ELSE d1.autres_endroits
            END
        ) as destinations,
        GROUP_CONCAT(DISTINCT d1.id) as demandes_ids,
        GROUP_CONCAT(
            DISTINCT CONCAT(e.nom, ' ', e.prenoms, ' (', d1.nombre_passagers, ' passagers)')
        ) as employes,
        SUM(d1.nombre_passagers) as total_passagers
    FROM demandes_vehicules d1
    JOIN employees e ON d1.employe_id = e.id
    WHERE d1.statut = 'en_attente'
    GROUP BY DATE(d1.date_depart), d1.heure_depart, d1.heure_retour
    HAVING COUNT(*) >= 1
    ORDER BY date_sortie, heure_depart
")->fetchAll();

// Récupérer les véhicules disponibles
$vehicules = $db->query("
    SELECT *
    FROM vehicules
    WHERE statut = 1
    ORDER BY capacite DESC, marque, modele
")->fetchAll();

// Récupérer les chauffeurs
$chauffeurs = $db->query("
    SELECT *
    FROM chauffeurs
    WHERE statut = 'disponible'
    ORDER BY nom, prenoms
")->fetchAll();

// Récupérer les sorties planifiées
$sorties = $db->query("
    SELECT 
        sv.*,
        v.marque, v.modele, v.immatriculation, v.capacite,
        CONCAT(c.nom, ' ', c.prenoms) as chauffeur,
        GROUP_CONCAT(
            DISTINCT CONCAT(emp.nom, ' ', emp.prenoms)
            SEPARATOR ', '
        ) as employes,
        COUNT(DISTINCT dv.id) as nombre_demandes,
        SUM(dv.nombre_passagers) as total_passagers
    FROM sorties_vehicules sv
    JOIN vehicules v ON sv.vehicule_id = v.id
    JOIN chauffeurs c ON sv.chauffeur_id = c.id
    JOIN sorties_demandes sd ON sv.id = sd.sortie_id
    JOIN demandes_vehicules dv ON sd.demande_id = dv.id
    JOIN employees emp ON dv.employe_id = emp.id
    GROUP BY sv.id
    ORDER BY sv.date_sortie DESC, sv.heure_depart DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Sorties de Véhicules</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sorties-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .sortie-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem;
        }

        .sortie-header {
            border-bottom: 1px solid #eee;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
        }

        .sortie-code {
            font-size: 1.2rem;
            color: #4CAF50;
            font-weight: bold;
        }

        .sortie-info {
            margin: 0.5rem 0;
        }

        .sortie-employes {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .demande-group {
            background: #f9f9f9;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }

        .btn-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .employes-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .employes-list li {
            padding: 0.25rem 0;
            border-bottom: 1px solid #eee;
        }

        .employes-list li:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="page-header">
            <h2><i class="fas fa-car"></i> Gestion des Sorties de Véhicules</h2>
            <a href="gestion_demande_vehicule.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="content-section">
            <h3>Demandes à Regrouper</h3>
            <?php foreach ($demandes_groupees as $groupe): ?>
                <div class="demande-group">
                    <h4>Sortie du <?php echo date('d/m/Y', strtotime($groupe['date_sortie'])); ?></h4>
                    <p>
                        <strong>Horaires:</strong> 
                        <?php echo $groupe['heure_depart'] . ' - ' . $groupe['heure_retour']; ?>
                    </p>
                    <p>
                        <strong>Destinations:</strong> 
                        <?php echo htmlspecialchars($groupe['destinations']); ?>
                    </p>
                    <p>
                        <strong>Employés:</strong> 
                        <?php echo htmlspecialchars($groupe['employes']); ?>
                    </p>
                    <p>
                        <strong>Total passagers:</strong> 
                        <?php echo $groupe['total_passagers']; ?>
                    </p>

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

                        <div class="form-group">
                            <label for="vehicule_<?php echo $groupe['date_sortie']; ?>">Véhicule:</label>
                            <select name="vehicule_id" id="vehicule_<?php echo $groupe['date_sortie']; ?>" class="form-control" required>
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

                        <div class="form-group">
                            <label for="chauffeur_<?php echo $groupe['date_sortie']; ?>">Chauffeur:</label>
                            <select name="chauffeur_id" id="chauffeur_<?php echo $groupe['date_sortie']; ?>" class="form-control" required>
                                <option value="">Sélectionner un chauffeur</option>
                                <?php foreach ($chauffeurs as $chauffeur): ?>
                                    <option value="<?php echo $chauffeur['id']; ?>">
                                        <?php echo htmlspecialchars($chauffeur['nom'] . ' ' . $chauffeur['prenoms']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Créer la Sortie
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>

            <?php if (empty($demandes_groupees)): ?>
                <div class="alert alert-info">
                    Aucune demande en attente à regrouper
                </div>
            <?php endif; ?>
        </div>

        <div class="content-section">
            <h3>Sorties Planifiées</h3>
            <div class="sorties-container">
                <?php foreach ($sorties as $sortie): ?>
                    <div class="sortie-card">
                        <div class="sortie-header">
                            <div class="sortie-code"><?php echo htmlspecialchars($sortie['code_sortie']); ?></div>
                            <div class="sortie-date">
                                <?php echo date('d/m/Y', strtotime($sortie['date_sortie'])); ?>
                            </div>
                        </div>
                        <div class="sortie-info">
                            <p><strong>Horaires:</strong> <?php echo $sortie['heure_depart'] . ' - ' . $sortie['heure_retour']; ?></p>
                            <p><strong>Véhicule:</strong> <?php echo htmlspecialchars($sortie['marque'] . ' ' . $sortie['modele'] . ' (' . $sortie['immatriculation'] . ')'); ?></p>
                            <p><strong>Chauffeur:</strong> <?php echo htmlspecialchars($sortie['chauffeur']); ?></p>
                            <p><strong>Destination:</strong> <?php echo htmlspecialchars($sortie['destination_commune']); ?></p>
                            <p><strong>Passagers:</strong> <?php echo $sortie['total_passagers']; ?> / <?php echo $sortie['capacite']; ?></p>
                        </div>
                        <div class="sortie-employes">
                            <h4>Employés (<?php echo $sortie['nombre_demandes']; ?>)</h4>
                            <ul class="employes-list">
                                <?php foreach (explode(', ', $sortie['employes']) as $employe): ?>
                                    <li><?php echo htmlspecialchars($employe); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($sorties)): ?>
                    <div class="alert alert-info">
                        Aucune sortie planifiée
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(document).ready(function() {
            // Validation du formulaire
            $('.sortie-form').on('submit', function(e) {
                const vehicule = $(this).find('select[name="vehicule_id"]').val();
                const chauffeur = $(this).find('select[name="chauffeur_id"]').val();

                if (!vehicule || !chauffeur) {
                    alert('Veuillez sélectionner un véhicule et un chauffeur');
                    e.preventDefault();
                    return false;
                }

                return confirm('Voulez-vous créer cette sortie ?');
            });
        });
    </script>
</body>
</html> 