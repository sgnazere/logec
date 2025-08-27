<?php
require_once 'config.php';
require_once 'check_auth.php';

// Traitement des actions CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'ajouter':
                $nom = cleanInput($_POST['nom']);
                $type = cleanInput($_POST['type']);
                $adresse = cleanInput($_POST['adresse']);
                $ville = cleanInput($_POST['ville']);
                $latitude = floatval($_POST['latitude']);
                $longitude = floatval($_POST['longitude']);
                $distance = floatval($_POST['distance']);
                $temps_trajet = cleanInput($_POST['temps_trajet']);
                $contact_nom = cleanInput($_POST['contact_nom']);
                $contact_telephone = cleanInput($_POST['contact_telephone']);

                $query = $db->prepare("
                    INSERT INTO destinations (
                        nom, type, adresse, ville, latitude, longitude,
                        distance, temps_trajet, contact_nom, contact_telephone
                    ) VALUES (
                        :nom, :type, :adresse, :ville, :latitude, :longitude,
                        :distance, :temps_trajet, :contact_nom, :contact_telephone
                    )
                ");
                
                $query->execute([
                    ':nom' => $nom,
                    ':type' => $type,
                    ':adresse' => $adresse,
                    ':ville' => $ville,
                    ':latitude' => $latitude,
                    ':longitude' => $longitude,
                    ':distance' => $distance,
                    ':temps_trajet' => $temps_trajet,
                    ':contact_nom' => $contact_nom,
                    ':contact_telephone' => $contact_telephone
                ]);
                break;

            case 'modifier':
                $id = (int)$_POST['id'];
                $nom = cleanInput($_POST['nom']);
                $type = cleanInput($_POST['type']);
                $adresse = cleanInput($_POST['adresse']);
                $ville = cleanInput($_POST['ville']);
                $latitude = floatval($_POST['latitude']);
                $longitude = floatval($_POST['longitude']);
                $distance = floatval($_POST['distance']);
                $temps_trajet = cleanInput($_POST['temps_trajet']);
                $contact_nom = cleanInput($_POST['contact_nom']);
                $contact_telephone = cleanInput($_POST['contact_telephone']);

                $query = $db->prepare("
                    UPDATE destinations 
                    SET nom = :nom,
                        type = :type,
                        adresse = :adresse,
                        ville = :ville,
                        latitude = :latitude,
                        longitude = :longitude,
                        distance = :distance,
                        temps_trajet = :temps_trajet,
                        contact_nom = :contact_nom,
                        contact_telephone = :contact_telephone
                    WHERE id = :id
                ");
                
                $query->execute([
                    ':id' => $id,
                    ':nom' => $nom,
                    ':type' => $type,
                    ':adresse' => $adresse,
                    ':ville' => $ville,
                    ':latitude' => $latitude,
                    ':longitude' => $longitude,
                    ':distance' => $distance,
                    ':temps_trajet' => $temps_trajet,
                    ':contact_nom' => $contact_nom,
                    ':contact_telephone' => $contact_telephone
                ]);
                break;

            case 'supprimer':
                $id = (int)$_POST['id'];
                $query = $db->prepare("DELETE FROM destinations WHERE id = :id");
                $query->execute([':id' => $id]);
                break;
        }
        
        // Redirection pour éviter la soumission multiple du formulaire
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Récupération des destinations
$query = $db->query("
    SELECT * 
    FROM destinations 
    ORDER BY nom, ville
");
$destinations = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Destinations - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .action-buttons {
            margin-bottom: 20px;
        }
        .btn-add {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .table-responsive {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .btn-edit {
            background-color: #ffc107;
            color: #000;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
        }
        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .type-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .type-district {
            background-color: #28a745;
            color: white;
        }
        .type-centre_sante {
            background-color: #17a2b8;
            color: white;
        }
        .type-organisation {
            background-color: #ffc107;
            color: black;
        }
        .map-container {
            margin-top: 10px;
            height: 300px;
            border-radius: 4px;
            overflow: hidden;
        }
        #map {
            height: 100%;
            width: 100%;
        }
    </style>
    <!-- Inclure l'API Google Maps -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places"></script>
</head>
<body>
    <div class="container">
        <div class="content-wrapper">
            <div class="header">
                <h1>Gestion des Destinations</h1>
                <a href="gestion_demande_vehicule.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Retour au tableau de bord
                </a>
            </div>

            <div class="action-buttons">
                <button class="btn-add" onclick="showModal('add')">
                    <i class="fas fa-plus"></i> Ajouter une destination
                </button>
                <input type="text" id="searchInput" class="search-box" placeholder="Rechercher...">
            </div>

            <div class="table-responsive">
                <table id="destinationsTable">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Ville</th>
                            <th>Distance</th>
                            <th>Temps de trajet</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($destinations as $destination): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($destination['nom']); ?></td>
                                <td>
                                    <span class="type-badge type-<?php echo $destination['type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $destination['type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($destination['ville']); ?></td>
                                <td><?php echo number_format($destination['distance'], 1); ?> km</td>
                                <td><?php echo htmlspecialchars($destination['temps_trajet']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($destination['contact_nom']); ?><br>
                                    <?php echo htmlspecialchars($destination['contact_telephone']); ?>
                                </td>
                                <td>
                                    <button class="btn-edit" onclick="showModal('edit', <?php echo htmlspecialchars(json_encode($destination)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="confirmDelete(<?php echo $destination['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Modal Formulaire -->
            <div id="destinationModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="hideModal()">&times;</span>
                    <h2 id="modalTitle">Ajouter une destination</h2>
                    <form id="destinationForm" method="POST">
                        <input type="hidden" name="action" id="formAction" value="ajouter">
                        <input type="hidden" name="id" id="destinationId">
                        
                        <div class="form-group">
                            <label for="nom">Nom de la destination</label>
                            <input type="text" id="nom" name="nom" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="type">Type</label>
                            <select id="type" name="type" required>
                                <option value="district">District</option>
                                <option value="centre_sante">Centre de santé</option>
                                <option value="organisation">Organisation</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="adresse">Adresse</label>
                            <input type="text" id="adresse" name="adresse" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="ville">Ville</label>
                            <input type="text" id="ville" name="ville" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Localisation</label>
                            <div class="map-container">
                                <div id="map"></div>
                            </div>
                            <input type="hidden" id="latitude" name="latitude" required>
                            <input type="hidden" id="longitude" name="longitude" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="distance">Distance (km)</label>
                            <input type="number" id="distance" name="distance" step="0.1" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="temps_trajet">Temps de trajet estimé</label>
                            <input type="text" id="temps_trajet" name="temps_trajet" placeholder="ex: 2h30min" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_nom">Nom du contact</label>
                            <input type="text" id="contact_nom" name="contact_nom" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_telephone">Téléphone du contact</label>
                            <input type="tel" id="contact_telephone" name="contact_telephone" required>
                        </div>
                        
                        <button type="submit" class="btn-add">Enregistrer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        let map;
        let marker;
        const defaultLocation = { lat: 5.349390, lng: -4.017050 }; // Abidjan

        // Initialiser la carte Google Maps
        function initMap() {
            map = new google.maps.Map(document.getElementById('map'), {
                center: defaultLocation,
                zoom: 12
            });

            marker = new google.maps.Marker({
                position: defaultLocation,
                map: map,
                draggable: true
            });

            // Mettre à jour les coordonnées quand le marqueur est déplacé
            google.maps.event.addListener(marker, 'dragend', function() {
                const position = marker.getPosition();
                document.getElementById('latitude').value = position.lat();
                document.getElementById('longitude').value = position.lng();
            });

            // Autocomplete pour l'adresse
            const input = document.getElementById('adresse');
            const autocomplete = new google.maps.places.Autocomplete(input);
            
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (!place.geometry) return;

                map.setCenter(place.geometry.location);
                marker.setPosition(place.geometry.location);
                
                document.getElementById('latitude').value = place.geometry.location.lat();
                document.getElementById('longitude').value = place.geometry.location.lng();
            });
        }

        // Fonction pour afficher le modal
        function showModal(type, data = null) {
            const modal = document.getElementById('destinationModal');
            const form = document.getElementById('destinationForm');
            const modalTitle = document.getElementById('modalTitle');
            
            if (type === 'add') {
                modalTitle.textContent = 'Ajouter une destination';
                form.action.value = 'ajouter';
                form.reset();
                
                // Réinitialiser la carte
                marker.setPosition(defaultLocation);
                map.setCenter(defaultLocation);
            } else {
                modalTitle.textContent = 'Modifier une destination';
                form.action.value = 'modifier';
                
                // Remplir le formulaire avec les données existantes
                form.id.value = data.id;
                form.nom.value = data.nom;
                form.type.value = data.type;
                form.adresse.value = data.adresse;
                form.ville.value = data.ville;
                form.distance.value = data.distance;
                form.temps_trajet.value = data.temps_trajet;
                form.contact_nom.value = data.contact_nom;
                form.contact_telephone.value = data.contact_telephone;
                form.latitude.value = data.latitude;
                form.longitude.value = data.longitude;

                // Mettre à jour la carte
                const position = new google.maps.LatLng(data.latitude, data.longitude);
                marker.setPosition(position);
                map.setCenter(position);
            }
            
            modal.style.display = 'block';
        }

        // Fonction pour cacher le modal
        function hideModal() {
            document.getElementById('destinationModal').style.display = 'none';
        }

        // Fonction pour confirmer la suppression
        function confirmDelete(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette destination ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fermer le modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('destinationModal');
            if (event.target === modal) {
                hideModal();
            }
        }

        // Fonction de recherche
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const table = document.getElementById('destinationsTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;

                for (let j = 0; j < cells.length; j++) {
                    const cell = cells[j];
                    if (cell.textContent.toLowerCase().indexOf(searchText) > -1) {
                        found = true;
                        break;
                    }
                }

                row.style.display = found ? '' : 'none';
            }
        });

        // Initialiser la carte au chargement de la page
        window.onload = initMap;
    </script>
</body>
</html> 