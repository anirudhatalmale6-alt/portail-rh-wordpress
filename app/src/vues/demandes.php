<?php meta(['titre' => t('nav_demandes')]); ?>
<h1><?= h(t('dem_titre')) ?></h1>
<p><a class="btn btn--plein" href="<?= h(lien('/demandes/nouvelle')) ?>"><?= h(t('dem_nouvelle')) ?></a></p>

<?php if (!$demandes): ?>
  <p class="vide-liste"><?= h(t('dem_aucune')) ?></p>
<?php else: ?>
<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('dem_numero')) ?></th><th><?= h(t('dem_objet')) ?></th>
    <th><?= h(t('dem_categorie')) ?></th><th><?= h(t('statut')) ?></th><th><?= h(t('dem_date')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($demandes as $d): ?>
    <tr>
      <td class="mono"><?= h((string) $d['numero']) ?></td>
      <td><a href="<?= h(lien('/demandes/' . (int) $d['id'])) ?>"><?= h((string) $d['objet']) ?></a></td>
      <td><?= h(t('dem_cat_' . $d['categorie'])) ?></td>
      <td><?= badge_statut((string) $d['statut'], 'demande') ?></td>
      <td><?= h(date_locale((string) $d['cree_le'], false)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
