<?php
require_once 'config.php';
// Suppression
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->prepare("DELETE FROM vidanges WHERE id = ?")->execute([$id]);
    header('Location: gerer_vidanges.php');
    exit;
}
// Ajout ou modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $vehicule_id = $_POST['vehicule_id'];
    $date_vidange = $_POST['date_vidange'];
    $kilometrage = $_POST['kilometrage'];
    $prochain_kilometrage = $_POST['prochain_kilometrage'];
    $type_huile = $_POST['type_huile'];
    $remarques = $_POST['remarques'];
    if ($id) {
        $sql = "UPDATE vidanges SET vehicule_id=?, date_vidange=?, kilometrage=?, prochain_kilometrage=?, type_huile=?, remarques=? WHERE id=?";
        $db->prepare($sql)->execute([$vehicule_id, $date_vidange, $kilometrage, $prochain_kilometrage, $type_huile, $remarques, $id]);
    } else {
        $sql = "INSERT INTO vidanges (vehicule_id, date_vidange, kilometrage, prochain_kilometrage, type_huile, remarques) VALUES (?, ?, ?, ?, ?, ?)";
        $db->prepare($sql)->execute([$vehicule_id, $date_vidange, $kilometrage, $prochain_kilometrage, $type_huile, $remarques]);
    }
    header('Location: gerer_vidanges.php');
    exit;
}
// Pour le formulaire d'édition
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM vidanges WHERE id = ?");
    $stmt->execute([intval($_GET['edit'])]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
// Liste des véhicules pour le select
$vehicules = $db->query("SELECT id, immatriculation FROM vehicules ORDER BY immatriculation")->fetchAll(PDO::FETCH_ASSOC);
// Liste des vidanges
$vidanges = $db->query("SELECT v.*, ve.immatriculation FROM vidanges v JOIN vehicules ve ON v.vehicule_id = ve.id ORDER BY v.date_vidange DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des Vidanges</title>
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
        body {
            background-color: var(--background-light);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard {
            padding: 2rem;
            max-width: 1200px;
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
        .form-section {
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 2rem;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--divider-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }
        .form-group input:focus, .form-group select:focus {
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
        .form-section a {
            color: var(--danger);
            margin-left: 1rem;
            text-decoration: underline;
        }
        .dataTables_wrapper {
            background: var(--white);
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        table.dataTable {
            width: 100% !important;
            border-collapse: collapse;
        }
        table.dataTable th, table.dataTable td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--divider-color);
        }
        table.dataTable th {
            background: var(--primary-light);
            color: var(--primary-dark);
        }
        table.dataTable tbody tr:hover {
            background: var(--background-light);
        }
        .action-links a {
            margin-right: 10px;
            color: var(--primary-color);
            font-weight: 500;
        }
        .action-links a:last-child {
            color: var(--danger);
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
    </style>
</head>
<body>
<div class="dashboard">
    <div class="page-header">
        <h2><i class="fas fa-oil-can"></i> Gestion des Vidanges</h2>
        <a href="gestion_demande_vehicule.php" class="back-btn"><i class="fas fa-arrow-left"></i> Retour au tableau de bord</a>
    </div>
    <div class="form-section">
        <form method="post">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Véhicule :</label>
                    <select name="vehicule_id" required>
                        <option value="">Sélectionner</option>
                        <?php foreach ($vehicules as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= (isset($edit['vehicule_id']) && $edit['vehicule_id'] == $v['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['immatriculation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date vidange :</label>
                    <input type="date" name="date_vidange" value="<?= $edit['date_vidange'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Kilométrage :</label>
                    <input type="number" name="kilometrage" value="<?= $edit['kilometrage'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Prochain km :</label>
                    <input type="number" name="prochain_kilometrage" value="<?= $edit['prochain_kilometrage'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Type d'huile :</label>
                    <input type="text" name="type_huile" value="<?= $edit['type_huile'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Remarques :</label>
                    <input type="text" name="remarques" value="<?= $edit['remarques'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> <?= $edit ? 'Mettre à jour' : 'Ajouter' ?>
                    </button>
                    <?php if ($edit): ?>
                        <a href="gerer_vidanges.php">Annuler</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    <table id="vidangesTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Véhicule</th>
                <th>Date vidange</th>
                <th>Kilométrage</th>
                <th>Prochain km</th>
                <th>Type huile</th>
                <th>Remarques</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($vidanges as $v): ?>
            <tr>
                <td><?= htmlspecialchars($v['immatriculation']) ?></td>
                <td><?= htmlspecialchars($v['date_vidange']) ?></td>
                <td><?= number_format($v['kilometrage'], 0, ',', ' ') ?></td>
                <td><?= number_format($v['prochain_kilometrage'], 0, ',', ' ') ?></td>
                <td><?= htmlspecialchars($v['type_huile']) ?></td>
                <td><?= htmlspecialchars($v['remarques']) ?></td>
                <td class="action-links">
                    <a href="?edit=<?= $v['id'] ?>" title="Modifier"><i class="fas fa-pencil-alt"></i></a>
                    <a href="vidange_details.php?id=<?= $v['id'] ?>" title="Voir"><i class="fas fa-eye"></i></a>
                    <a href="?delete=<?= $v['id'] ?>" onclick="return confirm('Supprimer cette vidange ?')" title="Supprimer"><i class="fas fa-trash"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    $('#vidangesTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json" }
    });
});
</script>
</body>
</html> 