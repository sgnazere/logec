<?php
require_once 'config.php';
$vehicule_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$vehicule_id) {
    echo '<div class="alert alert-danger">Véhicule non spécifié.</div>';
    exit;
}
// Infos véhicule
$stmt = $db->prepare("SELECT * FROM vehicules WHERE id = ?");
$stmt->execute([$vehicule_id]);
$vehicule = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vehicule) {
    echo '<div class="alert alert-danger">Véhicule introuvable.</div>';
    exit;
}
// Historique visites techniques
$visites = $db->prepare("SELECT * FROM visites_techniques WHERE vehicule_id = ? ORDER BY date_visite DESC");
$visites->execute([$vehicule_id]);
// Historique assurances
$assurances = $db->prepare("SELECT * FROM assurances WHERE vehicule_id = ? ORDER BY date_debut DESC");
$assurances->execute([$vehicule_id]);
// Historique vidanges
$vidanges = $db->prepare("SELECT * FROM vidanges WHERE vehicule_id = ? ORDER BY date_vidange DESC");
$vidanges->execute([$vehicule_id]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détails du véhicule</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .section { margin-bottom: 2rem; }
        .section h3 { margin-bottom: 1rem; }
        .btn-retour { margin-bottom: 2rem; display: inline-block; background: #007bff; color: #fff; padding: 8px 18px; border-radius: 5px; text-decoration: none; }
        .btn-retour:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class="container">
    <a href="gestion_demande_vehicule.php" class="btn-retour"><i class="fas fa-arrow-left"></i> Retour</a>
    <h2>Détails du véhicule : <?= htmlspecialchars($vehicule['immatriculation']) ?></h2>
    <ul>
        <li><strong>Marque :</strong> <?= htmlspecialchars($vehicule['marque']) ?></li>
        <li><strong>Modèle :</strong> <?= htmlspecialchars($vehicule['modele']) ?></li>
        <li><strong>Type :</strong> <?= htmlspecialchars($vehicule['type_vehicule']) ?></li>
        <li><strong>Capacité :</strong> <?= htmlspecialchars($vehicule['capacite']) ?> places</li>
        <li><strong>Kilométrage :</strong> <?= number_format($vehicule['kilometrage'], 0, ',', ' ') ?> km</li>
        <li><strong>Statut :</strong> <?= htmlspecialchars($vehicule['statut']) ?></li>
    </ul>
    <div class="section">
        <h3><i class="fas fa-wrench"></i> Historique des vidanges</h3>
        <table id="tableVidanges" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Date</th><th>Kilométrage</th><th>Prochain km</th><th>Type huile</th><th>Remarques</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vidanges as $vd): ?>
                <tr>
                    <td><?= htmlspecialchars($vd['date_vidange']) ?></td>
                    <td><?= number_format($vd['kilometrage'], 0, ',', ' ') ?></td>
                    <td><?= number_format($vd['prochain_kilometrage'], 0, ',', ' ') ?></td>
                    <td><?= htmlspecialchars($vd['type_huile']) ?></td>
                    <td><?= htmlspecialchars($vd['remarques']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="section">
        <h3><i class="fas fa-file-medical"></i> Historique des visites techniques</h3>
        <table id="tableVisites" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Date visite</th><th>Date expiration</th><th>Résultat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($visites as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['date_visite']) ?></td>
                    <td><?= htmlspecialchars($v['date_expiration']) ?></td>
                    <td><?= htmlspecialchars($v['resultat']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="section">
        <h3><i class="fas fa-shield-alt"></i> Historique des assurances</h3>
        <table id="tableAssurances" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>Compagnie</th><th>N° police</th><th>Début</th><th>Expiration</th><th>Montant</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assurances as $a): ?>
                <tr>
                    <td><?= htmlspecialchars($a['compagnie']) ?></td>
                    <td><?= htmlspecialchars($a['numero_police']) ?></td>
                    <td><?= htmlspecialchars($a['date_debut']) ?></td>
                    <td><?= htmlspecialchars($a['date_expiration']) ?></td>
                    <td><?= number_format($a['montant'], 2, ',', ' ') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
$(document).ready(function() {
    $('#tableVidanges').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" },
        "order": [[0, "desc"]],
        "pageLength": 10
    });
    $('#tableVisites').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" },
        "order": [[0, "desc"]],
        "pageLength": 10
    });
    $('#tableAssurances').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" },
        "order": [[2, "desc"]],
        "pageLength": 10
    });
});
</script>
</body>
</html> 