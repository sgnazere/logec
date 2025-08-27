<?php
require_once 'config.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo '<div class="alert alert-danger">Assurance non spécifiée.</div>';
    exit;
}
$stmt = $db->prepare("SELECT a.*, v.immatriculation, v.marque, v.modele FROM assurances a JOIN vehicules v ON a.vehicule_id = v.id WHERE a.id = ?");
$stmt->execute([$id]);
$assurance = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$assurance) {
    echo '<div class="alert alert-danger">Assurance introuvable.</div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail de l'assurance</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .details-container {
            background: #fff;
            max-width: 600px;
            margin: 3rem auto;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 2.5rem 2rem;
        }
        h2 { color: #4CAF50; margin-bottom: 2rem; }
        .details-list { list-style: none; padding: 0; margin: 0 0 2rem 0; }
        .details-list li { margin-bottom: 1.2rem; font-size: 1.1rem; }
        .details-list strong { color: #388E3C; min-width: 140px; display: inline-block; }
        .btn-retour {
            display: inline-block;
            background: #4CAF50;
            color: #fff;
            padding: 0.7rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-retour:hover { background: #388E3C; }
    </style>
</head>
<body>
<div class="details-container">
    <a href="gerer_assurances.php" class="btn-retour"><i class="fas fa-arrow-left"></i> Retour</a>
    <h2><i class="fas fa-shield-alt"></i> Détail de l'assurance</h2>
    <ul class="details-list">
        <li><strong>Véhicule :</strong> <?= htmlspecialchars($assurance['immatriculation']) ?> (<?= htmlspecialchars($assurance['marque']) ?> <?= htmlspecialchars($assurance['modele']) ?>)</li>
        <li><strong>Compagnie :</strong> <?= htmlspecialchars($assurance['compagnie']) ?></li>
        <li><strong>N° police :</strong> <?= htmlspecialchars($assurance['numero_police']) ?></li>
        <li><strong>Date début :</strong> <?= htmlspecialchars($assurance['date_debut']) ?></li>
        <li><strong>Date expiration :</strong> <?= htmlspecialchars($assurance['date_expiration']) ?></li>
        <li><strong>Montant :</strong> <?= number_format($assurance['montant'], 2, ',', ' ') ?> F CFA</li>
        <li><strong>Créée le :</strong> <?= htmlspecialchars($assurance['created_at']) ?></li>
    </ul>
</div>
</body>
</html> 