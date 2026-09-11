<?php meta(['titre' => t('nav_absences')]); ?>
<h1><?= h(t('eq_absences_titre')) ?></h1>
<?php if (!$voit_motif): ?>
  <?php /* Le manager voit les dates et le fait qu'un justificatif existe. Il
           ne voit ni le motif detaille ni le document : un arret de travail
           dit pourquoi quelqu'un est malade. */ ?>
  <p class="encart encart--info"><?= h(t('eq_absences_motif_masque')) ?></p>
<?php endif; ?>

<?php if (!$absences): ?>
  <p class="vide-liste"><?= h(t('abs_aucune')) ?></p>
<?php else: ?>
<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('eq_salarie')) ?></th>
    <?php if ($voit_motif): ?><th><?= h(t('abs_type')) ?></th><?php endif; ?>
    <th><?= h(t('cg_periode')) ?></th><th><?= h(t('cg_jours')) ?></th>
    <th><?= h(t('abs_justificatif')) ?></th><th><?= h(t('statut')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($absences as $a): ?>
    <tr>
      <td><?= h(nom_complet($a)) ?></td>
      <?php if ($voit_motif): ?><td><?= h(t('abs_type_' . $a['type'])) ?></td><?php endif; ?>
      <td><?= h((string) $a['debut']) ?> → <?= h((string) $a['fin']) ?></td>
      <td class="num"><?= h(nombre((float) $a['nb_jours'], 1)) ?></td>
      <td><?php if ($a['justificatif_id']): ?>
            <?php if ($voit_motif): ?>
              <a href="<?= h(lien('/document/' . (int) $a['justificatif_id'])) ?>"><?= h(t('abs_voir_justificatif')) ?></a>
            <?php else: ?><span class="etiquette"><?= h(t('abs_justificatif_depose')) ?></span><?php endif; ?>
          <?php else: ?><?= sans_valeur(t('abs_sans_justificatif')) ?><?php endif; ?></td>
      <td><?= badge_statut((string) $a['statut'], 'absence') ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
