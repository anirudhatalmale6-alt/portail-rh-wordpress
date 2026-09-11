<?php meta(['titre' => t('nav_absences')]); ?>
<h1><?= h(t('rh_absences_titre')) ?></h1>
<p class="note"><?= h(t('rh_absences_note')) ?></p>

<?php if (!$absences): ?><p class="vide-liste"><?= h(t('abs_aucune')) ?></p><?php else: ?>
<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('eq_salarie')) ?></th><th><?= h(t('abs_type')) ?></th>
    <th><?= h(t('cg_periode')) ?></th><th><?= h(t('cg_jours')) ?></th>
    <th><?= h(t('abs_justificatif')) ?></th><th><?= h(t('statut')) ?></th><th><?= h(t('eq_decision')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($absences as $a): ?>
    <tr>
      <td><?= h(nom_complet($a)) ?><br><span class="gris mono"><?= h((string) $a['matricule']) ?></span></td>
      <td><?= h(t('abs_type_' . $a['type'])) ?></td>
      <td><?= h((string) $a['debut']) ?> → <?= h((string) $a['fin']) ?></td>
      <td class="num"><?= h(nombre((float) $a['nb_jours'], 1)) ?></td>
      <td><?php if ($a['justificatif_id']): ?>
            <a href="<?= h(lien('/document/' . (int) $a['justificatif_id'])) ?>"><?= h(t('abs_voir_justificatif')) ?></a>
          <?php else: ?><?= sans_valeur(t('abs_sans_justificatif')) ?><?php endif; ?></td>
      <td><?= badge_statut((string) $a['statut'], 'absence') ?></td>
      <td><?php if ($a['statut'] === 'declaree'): ?>
        <form method="post" action="<?= h(lien('/rh/absences/' . (int) $a['id'])) ?>" class="formulaire--compact">
          <?= champ_csrf() ?>
          <button class="btn btn--oui" name="decision" value="validee" type="submit"><?= h(t('valider')) ?></button>
          <button class="btn btn--non" name="decision" value="refusee" type="submit"><?= h(t('refuser')) ?></button>
        </form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
