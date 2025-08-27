<?php
session_start();
require_once 'check_auth.php';
require_once 'config.php';

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : null;
    $nom = $_POST['nom'];
    $prenoms = $_POST['prenoms'];
    $sexe = $_POST['sexe'];
    $adresse = $_POST['adresse'];
    $numero_permis = $_POST['numero_permis'];
    $type_permis = $_POST['type_permis'];
    $date_expiration_permis = $_POST['date_expiration_permis'];
    $telephone = $_POST['telephone'];
    $email = $_POST['email'];
    $statut = $_POST['statut'];

    try {
        if ($id) {
            // Modification
            $stmt = $db->prepare("UPDATE chauffeurs SET nom = ?, prenoms = ?, sexe = ?, adresse = ?, numero_permis = ?, type_permis = ?, date_expiration_permis = ?, telephone = ?, email = ?, statut = ? WHERE id = ?");
            $stmt->execute([$nom, $prenoms, $sexe, $adresse, $numero_permis, $type_permis, $date_expiration_permis, $telephone, $email, $statut, $id]);
            $_SESSION['success'] = "Chauffeur modifié avec succès.";
        } else {
            // Ajout
            $stmt = $db->prepare("INSERT INTO chauffeurs (nom, prenoms, sexe, adresse, numero_permis, type_permis, date_expiration_permis, telephone, email, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $prenoms, $sexe, $adresse, $numero_permis, $type_permis, $date_expiration_permis, $telephone, $email, $statut]);
            $_SESSION['success'] = "Chauffeur ajouté avec succès.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de l'opération : " . $e->getMessage();
    }
    
    header('Location: gerer_chauffeurs.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Chauffeurs</title>
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

        .chauffeur-form-container {
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

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            font-family: inherit;
        }

        .form-group input:focus, .form-group textarea:focus {
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

        .chauffeur-list-container {
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

        .statut-disponible {
            color: var(--success);
            font-weight: 500;
        }

        .statut-en_mission {
            color: var(--warning);
            font-weight: 500;
        }

        .statut-indisponible {
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

        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
            transform: scale(1.02);
        }

        .form-group select option {
            padding: 0.5rem;
        }

        .form-group select option:first-child {
            color: #666;
        }
    </style>
</head>
<body>
    <div id="chauffeur-tooltip" style="display: none; position: absolute; background-color: #333; color: #fff; padding: 10px 15px; border-radius: 6px; z-index: 1000; pointer-events: none; box-shadow: 0 2px 8px rgba(0,0,0,0.25); font-size: 0.9rem; max-width: 250px; white-space: pre-wrap;"></div>
    <div class="dashboard">
        <div class="page-header">
            <h2><i class="fas fa-id-card"></i> Gestion des Chauffeurs</h2>
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

        <div class="chauffeur-form-container">
            <h3 class="form-title"><i class="fas fa-user-plus"></i> Ajouter un chauffeur</h3>
            <form id="chauffeurForm" method="POST">
                <input type="hidden" name="id" id="chauffeur_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" required>
                    </div>
                    <div class="form-group">
                        <label for="prenoms">Prénoms</label>
                        <input type="text" id="prenoms" name="prenoms" required>
                    </div>
                    <div class="form-group">
                        <label>Sexe</label>
                        <div style="display: flex; gap: 1rem; align-items: center; padding-top: 0.5rem;">
                            <input type="radio" id="sexe_homme" name="sexe" value="Homme" required style="width: auto; height: auto;">
                            <label for="sexe_homme" style="font-weight: normal; user-select: none;">Homme</label>
                            <input type="radio" id="sexe_femme" name="sexe" value="Femme" required style="width: auto; height: auto;">
                            <label for="sexe_femme" style="font-weight: normal; user-select: none;">Femme</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="adresse">Adresse</label>
                        <textarea id="adresse" name="adresse" rows="2" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="numero_permis">Numéro de permis</label>
                        <input type="text" id="numero_permis" name="numero_permis" required>
                    </div>
                    <div class="form-group">
                        <label for="type_permis">Type de permis</label>
                        <select id="type_permis" name="type_permis" required>
                            <option value="">Sélectionner un type de permis</option>
                            <option value="A">Permis A</option>
                            <option value="B">Permis B</option>
                            <option value="C">Permis C</option>
                            <option value="D">Permis D</option>
                            <option value="E">Permis E</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_expiration_permis">Date d'expiration du permis</label>
                        <input type="date" id="date_expiration_permis" name="date_expiration_permis" required>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone</label>
                        <input type="tel" id="telephone" name="telephone">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="statut">Statut</label>
                        <select id="statut" name="statut" required>
                            <option value="">Sélectionner un statut</option>
                            <option value="disponible">Disponible</option>
                            <option value="en_mission">En mission</option>
                            <option value="indisponible">Indisponible</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>

        <div class="chauffeur-list-container">
            <div class="list-header">
                <h3 class="list-title"><i class="fas fa-list"></i> Liste des chauffeurs</h3>
            </div>
            <table id="chauffeursTable" class="display">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Prénoms</th>
                        <th>Sexe</th>
                        <th>Adresse</th>
                        <th>N° Permis</th>
                        <th>Type Permis</th>
                        <th>Date Expiration</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = $db->query("SELECT * FROM chauffeurs ORDER BY id DESC");
                    while ($chauffeur = $query->fetch(PDO::FETCH_ASSOC)):
                    ?>
                    <tr>
                        <td><?php echo $chauffeur['id']; ?></td>
                        <td><?php echo htmlspecialchars($chauffeur['nom']); ?></td>
                        <td><?php echo htmlspecialchars($chauffeur['prenoms']); ?></td>
                        <td><?php echo htmlspecialchars($chauffeur['sexe'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($chauffeur['adresse'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($chauffeur['numero_permis']); ?></td>
                        <td><?php echo htmlspecialchars($chauffeur['type_permis']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($chauffeur['date_expiration_permis'])); ?></td>
                        <td><?php echo htmlspecialchars($chauffeur['telephone']); ?></td>
                        <td><?php echo htmlspecialchars($chauffeur['email']); ?></td>
                        <td><span class="statut-<?php echo $chauffeur['statut']; ?>"><?php echo ucfirst($chauffeur['statut']); ?></span></td>
                        <td>
                            <div class="action-buttons">
                                <button onclick="editChauffeur(<?php echo htmlspecialchars(json_encode($chauffeur)); ?>)" class="btn-edit" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteChauffeur(<?php echo $chauffeur['id']; ?>)" class="btn-delete" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
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
            dataTable = $('#chauffeursTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                },
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "Tous"]],
                columnDefs: [
                    { "visible": false, "targets": [3, 4, 6, 7, 9] }
                ]
            });

            // Tooltip logic
            const tooltip = $('#chauffeur-tooltip');

            $('#chauffeursTable tbody').on('mouseover', 'tr', function(e) {
                const rowData = dataTable.row(this).data();
                if (rowData) {
                    const sexe = rowData[3];
                    const adresse = rowData[4];
                    const type_permis = rowData[6];
                    const date_expiration = rowData[7];
                    const email = rowData[9];

                    let tooltipContent = '';
                    if (sexe && sexe.trim() !== '') tooltipContent += `<strong>Sexe:</strong> ${sexe}<br>`;
                    if (adresse && adresse.trim() !== '') tooltipContent += `<strong>Adresse:</strong> ${adresse}<br>`;
                    if (type_permis && type_permis.trim() !== '') tooltipContent += `<strong>Permis:</strong> ${type_permis}<br>`;
                    if (date_expiration && date_expiration.trim() !== '') tooltipContent += `<strong>Expiration:</strong> ${date_expiration}<br>`;
                    if (email && email.trim() !== '') tooltipContent += `<strong>Email:</strong> ${email}`;

                    // Nettoyer la dernière balise <br> si elle existe
                    if (tooltipContent.endsWith('<br>')) {
                        tooltipContent = tooltipContent.slice(0, -4);
                    }

                    if (tooltipContent) {
                        tooltip.html(tooltipContent);
                        tooltip.css({
                            display: 'block',
                            left: e.pageX + 15,
                            top: e.pageY + 15
                        }).stop().show();
                    }
                }
            }).on('mouseleave', 'tr', function() {
                tooltip.stop().hide();
            }).on('mousemove', 'tr', function(e) {
                tooltip.css({
                    left: e.pageX + 15,
                    top: e.pageY + 15
                });
            });
        });

        function editChauffeur(chauffeur) {
            document.getElementById('chauffeur_id').value = chauffeur.id;
            document.getElementById('nom').value = chauffeur.nom;
            document.getElementById('prenoms').value = chauffeur.prenoms;
            document.getElementById('adresse').value = chauffeur.adresse || '';

            if (chauffeur.sexe === 'Homme') {
                document.getElementById('sexe_homme').checked = true;
            } else if (chauffeur.sexe === 'Femme') {
                document.getElementById('sexe_femme').checked = true;
            } else {
                document.getElementById('sexe_homme').checked = false;
                document.getElementById('sexe_femme').checked = false;
            }

            document.getElementById('numero_permis').value = chauffeur.numero_permis;
            document.getElementById('type_permis').value = chauffeur.type_permis;
            document.getElementById('date_expiration_permis').value = chauffeur.date_expiration_permis;
            document.getElementById('telephone').value = chauffeur.telephone;
            document.getElementById('email').value = chauffeur.email;
            document.getElementById('statut').value = chauffeur.statut;
            
            document.querySelector('.form-title').innerHTML = '<i class="fas fa-user-edit"></i> Modifier un chauffeur';
            document.querySelector('.chauffeur-form-container').scrollIntoView({ behavior: 'smooth' });
        }

        function deleteChauffeur(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce chauffeur ?')) {
                fetch('delete_chauffeur.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression : ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue lors de la suppression');
                });
            }
        }

        document.getElementById('chauffeurForm').addEventListener('reset', function() {
            document.getElementById('chauffeur_id').value = '';
            document.querySelector('.form-title').innerHTML = '<i class="fas fa-user-plus"></i> Ajouter un chauffeur';
        });
    </script>
</body>
</html> 