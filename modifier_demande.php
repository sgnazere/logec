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

// Récupérer les communes et districts
$query_communes = $db->query("SELECT DISTINCT nom FROM communes ORDER BY nom");
$communes = $query_communes->fetchAll(PDO::FETCH_COLUMN);

$query_districts = $db->query("SELECT DISTINCT nom FROM districts ORDER BY nom");
$districts = $query_districts->fetchAll(PDO::FETCH_COLUMN);

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

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $communes = isset($_POST['communes']) ? implode(',', $_POST['communes']) : '';
    $districts = isset($_POST['districts']) ? implode(',', $_POST['districts']) : '';
    $sites = $_POST['sites'];
    $autres_endroits = $_POST['autres_endroits'];
    $date_depart = $_POST['date_depart'];
    $heure_depart = $_POST['heure_depart'];
    $date_retour = $_POST['date_retour'];
    $heure_retour = $_POST['heure_retour'];
    $motif = $_POST['motif'];
    $nombre_passagers = (int)$_POST['nombre_passagers'];

    try {
        // Mise à jour de la demande
        $stmt = $db->prepare("
            UPDATE demandes_vehicules 
            SET communes = ?,
                districts = ?,
                sites = ?,
                autres_endroits = ?,
                date_depart = ?,
                heure_depart = ?,
                date_retour = ?,
                heure_retour = ?,
                motif = ?,
                nombre_passagers = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $communes,
            $districts,
            $sites,
            $autres_endroits,
            $date_depart,
            $heure_depart,
            $date_retour,
            $heure_retour,
            $motif,
            $nombre_passagers,
            $demande_id
        ]);

        $_SESSION['success'] = "La demande a été modifiée avec succès.";
        header('Location: gerer_demandes.php');
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "Erreur lors de la modification : " . $e->getMessage();
    }
}

// Convertir les chaînes de communes et districts en tableaux
$demande_communes = $demande['communes'] ? explode(',', $demande['communes']) : [];
$demande_districts = $demande['districts'] ? explode(',', $demande['districts']) : [];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier la Demande | LOGEC</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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

        .modification-container {
            background: var(--white);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            gap: 1.5rem;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--white);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .destinations-section {
            background: var(--background-light);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
        }

        .destinations-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-light);
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
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="modification-container">
            <h2 class="form-title">
                <i class="fas fa-edit"></i> Modifier la Demande
            </h2>

            <form method="POST" action="" id="modificationForm">
                <div class="destinations-section">
                    <h3 class="destinations-title">
                        <i class="fas fa-map-marker-alt"></i> Destinations
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="communes">Communes</label>
                            <select id="communes" name="communes[]" multiple class="select2" data-placeholder="Sélectionnez les communes">
                                <?php foreach ($communes as $commune): ?>
                                    <option value="<?php echo htmlspecialchars($commune); ?>"
                                            <?php echo in_array($commune, $demande_communes) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($commune); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="districts">Districts</label>
                            <select id="districts" name="districts[]" multiple class="select2" data-placeholder="Sélectionnez les districts">
                                <?php foreach ($districts as $district): ?>
                                    <option value="<?php echo htmlspecialchars($district); ?>"
                                            <?php echo in_array($district, $demande_districts) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($district); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="sites">Sites</label>
                            <textarea id="sites" name="sites" placeholder="Entrez les sites à visiter"><?php echo htmlspecialchars($demande['sites']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="autres_endroits">Autres endroits</label>
                            <textarea id="autres_endroits" name="autres_endroits" placeholder="Précisez les autres endroits à visiter"><?php echo htmlspecialchars($demande['autres_endroits']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="date_depart">Date de départ</label>
                        <input type="date" id="date_depart" name="date_depart" 
                               value="<?php echo htmlspecialchars($demande['date_depart']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="heure_depart">Heure de départ</label>
                        <input type="time" id="heure_depart" name="heure_depart" 
                               value="<?php echo htmlspecialchars($demande['heure_depart']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="date_retour">Date de retour</label>
                        <input type="date" id="date_retour" name="date_retour" 
                               value="<?php echo htmlspecialchars($demande['date_retour']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="heure_retour">Heure de retour</label>
                        <input type="time" id="heure_retour" name="heure_retour" 
                               value="<?php echo htmlspecialchars($demande['heure_retour']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="motif">Motif du déplacement</label>
                        <textarea id="motif" name="motif" required><?php echo htmlspecialchars($demande['motif']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="nombre_passagers">Nombre de passagers</label>
                        <input type="number" id="nombre_passagers" name="nombre_passagers" min="1" 
                               value="<?php echo htmlspecialchars($demande['nombre_passagers']); ?>" required>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <a href="gerer_demandes.php" class="btn btn-danger">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation de Select2 pour les sélections multiples
            $('.select2').select2({
                width: '100%',
                tags: true,
                tokenSeparators: [',', ' ']
            });

            // Validation du formulaire
            $('#modificationForm').on('submit', function(e) {
                const dateDepart = new Date($('#date_depart').val() + ' ' + $('#heure_depart').val());
                const dateRetour = new Date($('#date_retour').val() + ' ' + $('#heure_retour').val());

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
            });
        });
    </script>
</body>
</html> 