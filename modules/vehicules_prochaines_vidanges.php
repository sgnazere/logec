<?php
require_once __DIR__ . '/../config.php';

// Requête pour les véhicules proches de la prochaine vidange
$sql = "SELECT v.id, v.immatriculation, v.kilometrage, vd.prochain_kilometrage,
               vd.prochain_kilometrage - v.kilometrage AS km_restants
        FROM vehicules v
        JOIN vidanges vd ON vd.vehicule_id = v.id
        WHERE vd.prochain_kilometrage - v.kilometrage <= 500
          AND vd.prochain_kilometrage IS NOT NULL
        ORDER BY km_restants ASC";
$stmt = $db->query($sql);
$prochaines_vidanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<style>
.tr-rouge { background:#e74c3c;color:white }
.tr-orange { background:#f39c12;color:white }
.tr-vert { background:#27ae60;color:white }
</style>
<div class="card" style="margin-top:2rem;">
  <div class="card-header">
    <h3><i class="fas fa-oil-can"></i> Véhicules proches de la prochaine vidange</h3>
  </div>
  <div class="card-body">
    <table id="prochainesVidangesTable" class="display" style="width:100%">
      <thead>
        <tr>
          <th>Immatriculation</th>
          <th>Kilométrage actuel</th>
          <th>Prochain km vidange</th>
          <th>Kilomètres restants</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($prochaines_vidanges as $v):
          $class = '';
          if ($v['km_restants'] <= 0) $class = 'tr-rouge';
          elseif ($v['km_restants'] <= 200) $class = 'tr-orange';
          else $class = 'tr-vert';
        ?>
        <tr class="<?= $class ?>">
          <td><?= htmlspecialchars($v['immatriculation']) ?></td>
          <td><?= number_format($v['kilometrage'], 0, ',', ' ') ?></td>
          <td><?= number_format($v['prochain_kilometrage'], 0, ',', ' ') ?></td>
          <td><?= number_format($v['km_restants'], 0, ',', ' ') ?></td>
          <td>
            <a href="vehicule_details.php?id=<?= urlencode($v['id']) ?>" class="btn btn-sm btn-info">
              <i class="fas fa-history"></i> Historique
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (empty($prochaines_vidanges)): ?>
      <div class="alert alert-success mt-3">Aucun véhicule proche de la prochaine vidange.</div>
    <?php endif; ?>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
  $('#prochainesVidangesTable').DataTable({
    "language": {
      "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json"
    },
    "order": [[3, "asc"]],
    "pageLength": 10
  });
});
</script> 