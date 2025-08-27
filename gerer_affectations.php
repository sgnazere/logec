<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

// Vérifier si l'utilisateur est un administrateur
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: gestion_demande_vehicule.php');
    exit();
}

// Traitement de l'affectation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $demande_id = $_POST['demande_id'];
    $vehicule_id = $_POST['vehicule_id'];
    $action = $_POST['action'];

    try {
        if ($action === 'affecter') {
            // Vérifier la capacité du véhicule
            $stmt = $db->prepare("
                SELECT d.nombre_passagers, v.capacite
                FROM demandes_vehicules d
                JOIN vehicules v ON v.id = ?
                WHERE d.id = ?
            ");
            $stmt->execute([$vehicule_id, $demande_id]);
            $result = $stmt->fetch();

            if ($result && $result['nombre_passagers'] > $result['capacite']) {
                $_SESSION['error'] = "Le véhicule sélectionné n'a pas assez de places (Capacité: {$result['capacite']}, Requis: {$result['nombre_passagers']})";
            } else {
                // Vérifier les disponibilités
                $stmt = $db->prepare("
                    SELECT d2.*
                    FROM demandes_vehicules d2
                    JOIN demandes_vehicules d1 ON d1.id = ?
                    WHERE d2.vehicule_id = ?
                    AND d2.statut = 'approuve'
                    AND (
                        (d2.date_depart BETWEEN d1.date_depart AND d1.date_retour)
                        OR (d2.date_retour BETWEEN d1.date_depart AND d1.date_retour)
                        OR (d1.date_depart BETWEEN d2.date_depart AND d2.date_retour)
                    )
                ");
                $stmt->execute([$demande_id, $vehicule_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['error'] = "Le véhicule est déjà réservé sur cette période";
                } else {
                    $stmt = $db->prepare("
                        UPDATE demandes_vehicules 
                        SET vehicule_id = ?, statut = 'approuve'
                        WHERE id = ?
                    ");
                    $stmt->execute([$vehicule_id, $demande_id]);
                    $_SESSION['success'] = "Véhicule affecté avec succès";
                }
            }
        } elseif ($action === 'refuser') {
            $stmt = $db->prepare("
                UPDATE demandes_vehicules 
                SET statut = 'refuse'
                WHERE id = ?
            ");
            $stmt->execute([$demande_id]);
            $_SESSION['success'] = "Demande refusée";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de l'opération : " . $e->getMessage();
    }

    header('Location: gerer_affectations.php');
    exit();
}

// Récupérer les demandes en attente avec regroupement par destination
$demandes = $db->query("
    SELECT 
        d.*,
        e.nom as employe_nom,
        e.prenoms as employe_prenoms,
        GROUP_CONCAT(
            DISTINCT d2.id
        ) as demandes_similaires_ids,
        GROUP_CONCAT(
            DISTINCT CONCAT(e2.nom, ' ', e2.prenoms)
            SEPARATOR '|'
        ) as employes_similaires
    FROM demandes_vehicules d
    JOIN employees e ON d.employe_id = e.id
    LEFT JOIN demandes_vehicules d2 ON 
        d2.id != d.id
        AND d2.statut = 'en_attente'
        AND (
            (d2.communes = d.communes AND d.communes != '')
            OR (d2.districts = d.districts AND d.districts != '')
            OR (d2.sites LIKE CONCAT('%', d.sites, '%'))
        )
        AND ABS(DATEDIFF(d2.date_depart, d.date_depart)) <= 1
    LEFT JOIN employees e2 ON d2.employe_id = e2.id
    WHERE d.statut = 'en_attente'
    GROUP BY d.id
    ORDER BY d.date_creation DESC
")->fetchAll();

// Récupérer les véhicules disponibles
$vehicules = $db->query("
    SELECT *
    FROM vehicules
    WHERE statut = 1
    ORDER BY marque, modele
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Affectations</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .dashboard {
            padding: 2rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        .demande-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            padding: 1.5rem;
        }

        .demande-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--divider-color);
        }

        .demande-info {
            flex: 1;
        }

        .demande-actions {
            display: flex;
            gap: 0.5rem;
        }

        .demande-title {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .demande-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .demande-destinations {
            margin: 1rem 0;
            padding: 1rem;
            background: var(--background-light);
            border-radius: 4px;
        }

        .demande-similaires {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--primary-light);
            border-radius: 4px;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .vehicule-select {
            padding: 0.5rem;
            border: 1px solid var(--divider-color);
            border-radius: 4px;
            margin-right: 0.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: var(--primary-light);
            color: var(--primary-dark);
            border: 1px solid var(--primary-color);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .destination-tag {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: var(--accent-color);
            color: white;
            border-radius: 4px;
            margin: 0.25rem;
            font-size: 0.9rem;
        }

        .employe-tag {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: var(--primary-dark);
            color: white;
            border-radius: 4px;
            margin: 0.25rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="page-header">
            <h2><i class="fas fa-tasks"></i> Gestion des Affectations</h2>
            <a href="gestion_demande_vehicule.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Retour
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

        <?php foreach ($demandes as $demande): ?>
            <div class="demande-card">
                <div class="demande-header">
                    <div class="demande-info">
                        <h3 class="demande-title">
                            Demande de <?php echo htmlspecialchars($demande['employe_nom'] . ' ' . $demande['employe_prenoms']); ?>
                        </h3>
                        <div class="demande-meta">
                            <p>
                                <i class="fas fa-calendar"></i>
                                Départ: <?php echo date('d/m/Y H:i', strtotime($demande['date_depart'] . ' ' . $demande['heure_depart'])); ?>
                            </p>
                            <p>
                                <i class="fas fa-calendar-check"></i>
                                Retour: <?php echo date('d/m/Y H:i', strtotime($demande['date_retour'] . ' ' . $demande['heure_retour'])); ?>
                            </p>
                            <p>
                                <i class="fas fa-users"></i>
                                Nombre de passagers: <?php echo $demande['nombre_passagers']; ?>
                            </p>
                        </div>
                    </div>
                    <div class="demande-actions">
                        <form method="POST" class="affectation-form" onsubmit="return confirm('Confirmer cette action ?');">
                            <input type="hidden" name="demande_id" value="<?php echo $demande['id']; ?>">
                            <select name="vehicule_id" class="vehicule-select" required>
                                <option value="">Sélectionner un véhicule</option>
                                <?php foreach ($vehicules as $vehicule): ?>
                                    <option value="<?php echo $vehicule['id']; ?>">
                                        <?php echo htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele'] . 
                                            ' (' . $vehicule['immatriculation'] . ') - ' . $vehicule['capacite'] . ' places'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="action" value="affecter" class="btn btn-success">
                                <i class="fas fa-check"></i> Affecter
                            </button>
                            <button type="submit" name="action" value="refuser" class="btn btn-danger">
                                <i class="fas fa-times"></i> Refuser
                            </button>
                        </form>
                    </div>
                </div>

                <div class="demande-destinations">
                    <h4><i class="fas fa-map-marker-alt"></i> Destinations</h4>
                    <?php
                    if ($demande['communes']) {
                        $communes = explode(',', $demande['communes']);
                        foreach ($communes as $commune) {
                            echo "<span class='destination-tag'><i class='fas fa-building'></i> " . htmlspecialchars($commune) . "</span>";
                        }
                    }
                    if ($demande['districts']) {
                        $districts = explode(',', $demande['districts']);
                        foreach ($districts as $district) {
                            echo "<span class='destination-tag'><i class='fas fa-map'></i> " . htmlspecialchars($district) . "</span>";
                        }
                    }
                    if ($demande['sites']) {
                        $sites = explode(',', $demande['sites']);
                        foreach ($sites as $site) {
                            echo "<span class='destination-tag'><i class='fas fa-industry'></i> " . htmlspecialchars($site) . "</span>";
                        }
                    }
                    if ($demande['autres_endroits']) {
                        echo "<span class='destination-tag'><i class='fas fa-map-pin'></i> " . htmlspecialchars($demande['autres_endroits']) . "</span>";
                    }
                    ?>
                </div>

                <?php if ($demande['employes_similaires']): ?>
                    <div class="demande-similaires">
                        <h4><i class="fas fa-users"></i> Demandes similaires</h4>
                        <?php
                        $employes = explode('|', $demande['employes_similaires']);
                        foreach ($employes as $employe) {
                            if ($employe) {
                                echo "<span class='employe-tag'><i class='fas fa-user'></i> " . htmlspecialchars($employe) . "</span>";
                            }
                        }
                        ?>
                        <p class="demande-meta">
                            <i class="fas fa-info-circle"></i>
                            Ces employés ont des demandes similaires (même période, destinations communes)
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if (empty($demandes)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Aucune demande en attente
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Validation du formulaire d'affectation
            $('.affectation-form').on('submit', function(e) {
                const action = e.originalEvent.submitter.value;
                if (action === 'affecter') {
                    const vehiculeId = $(this).find('select[name="vehicule_id"]').val();
                    if (!vehiculeId) {
                        alert('Veuillez sélectionner un véhicule');
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html> 