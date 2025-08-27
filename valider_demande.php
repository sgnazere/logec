<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

// Vérifier si une demande est spécifiée
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Aucune demande spécifiée.";
    header('Location: gerer_demandes.php');
    exit();
}

$demande_id = (int)$_GET['id'];

// Récupérer les informations de la demande
$stmt = $db->prepare("
    SELECT 
        d.*,
        e.nom as employe_nom,
        e.prenoms as employe_prenoms
    FROM demandes_vehicules d
    JOIN employees e ON d.employe_id = e.id
    WHERE d.id = ?
");
$stmt->execute([$demande_id]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    $_SESSION['error'] = "Demande non trouvée.";
    header('Location: gerer_demandes.php');
    exit();
}

// Récupérer les véhicules disponibles
$stmt = $db->query("
    SELECT * FROM vehicules 
    WHERE statut = 'disponible'
    ORDER BY marque, modele
");
$vehicules_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les chauffeurs disponibles
$stmt = $db->query("
    SELECT * FROM chauffeurs 
    WHERE statut = 'disponible'
    ORDER BY nom, prenoms
");
$chauffeurs_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire de validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicule_id = isset($_POST['vehicule_id']) ? (int)$_POST['vehicule_id'] : null;
    $chauffeur_id = isset($_POST['chauffeur_id']) ? (int)$_POST['chauffeur_id'] : null;
    $statut = $_POST['statut'];

    try {
        $db->beginTransaction();

        // Mettre à jour la demande
        $stmt = $db->prepare("
            UPDATE demandes_vehicules 
            SET vehicule_id = ?,
                chauffeur_id = ?,
                statut = ?,
                date_validation = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$vehicule_id, $chauffeur_id, $statut, $demande_id]);

        if ($statut === 'approuvee') {
            // Mettre à jour le statut du véhicule
            if ($vehicule_id) {
                $stmt = $db->prepare("UPDATE vehicules SET statut = 'en_mission' WHERE id = ?");
                $stmt->execute([$vehicule_id]);
            }

            // Mettre à jour le statut du chauffeur
            if ($chauffeur_id) {
                $stmt = $db->prepare("UPDATE chauffeurs SET statut = 'en_mission' WHERE id = ?");
                $stmt->execute([$chauffeur_id]);
            }
        }

        $db->commit();
        $_SESSION['success'] = "La demande a été validée avec succès.";
        header('Location: gerer_demandes.php');
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Erreur lors du traitement de la demande : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation de la Demande | LOGEC</title>
    <link rel="stylesheet" href="style.css">
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

        .dashboard {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .validation-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .demande-details {
            background: var(--background-light);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .demande-details h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .detail-group {
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid var(--divider-color);
        }

        .detail-group:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: bold;
            color: var(--text-secondary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--white);
        }

        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--white);
        }

        .btn-danger {
            background: var(--danger);
            color: var(--white);
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-warning {
            background-color: var(--warning);
            color: var(--text-primary);
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="validation-container">
            <h2><i class="fas fa-check-circle"></i> Validation de la Demande</h2>

            <?php if (empty($vehicules_disponibles) || empty($chauffeurs_disponibles)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php if (empty($vehicules_disponibles)): ?>
                        Aucun véhicule disponible pour le moment.
                    <?php endif; ?>
                    <?php if (empty($chauffeurs_disponibles)): ?>
                        <?php if (empty($vehicules_disponibles)) echo "<br>"; ?>
                        Aucun chauffeur disponible pour le moment.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="demande-details">
                <h3>Détails de la demande</h3>
                <div class="detail-group">
                    <span class="detail-label">Demandeur:</span>
                    <span><?php echo htmlspecialchars($demande['employe_nom'] . ' ' . $demande['employe_prenoms']); ?></span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Date de départ:</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($demande['date_depart'] . ' ' . $demande['heure_depart'])); ?></span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Date de retour:</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($demande['date_retour'] . ' ' . $demande['heure_retour'])); ?></span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Destinations:</span>
                    <span>
                        <?php
                        $destinations = [];
                        if ($demande['communes']) $destinations[] = "Communes: " . $demande['communes'];
                        if ($demande['districts']) $destinations[] = "Districts: " . $demande['districts'];
                        if ($demande['sites']) $destinations[] = "Sites: " . $demande['sites'];
                        if ($demande['autres_endroits']) $destinations[] = "Autres: " . $demande['autres_endroits'];
                        echo implode(' | ', $destinations);
                        ?>
                    </span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Motif:</span>
                    <span><?php echo htmlspecialchars($demande['motif']); ?></span>
                </div>
                <div class="detail-group">
                    <span class="detail-label">Nombre de passagers:</span>
                    <span><?php echo htmlspecialchars($demande['nombre_passagers']); ?></span>
                </div>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="vehicule_id">Véhicule</label>
                    <select name="vehicule_id" id="vehicule_id" required>
                        <option value="">Sélectionnez un véhicule</option>
                        <?php foreach ($vehicules_disponibles as $vehicule): ?>
                            <option value="<?php echo $vehicule['id']; ?>">
                                <?php echo htmlspecialchars($vehicule['marque'] . ' ' . $vehicule['modele'] . ' (' . $vehicule['immatriculation'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="chauffeur_id">Chauffeur</label>
                    <select name="chauffeur_id" id="chauffeur_id" required>
                        <option value="">Sélectionnez un chauffeur</option>
                        <?php foreach ($chauffeurs_disponibles as $chauffeur): ?>
                            <option value="<?php echo $chauffeur['id']; ?>">
                                <?php echo htmlspecialchars($chauffeur['nom'] . ' ' . $chauffeur['prenoms']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <input type="hidden" name="statut" value="approuvee">

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Valider la demande
                    </button>
                    <a href="gerer_demandes.php" class="btn btn-danger">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 