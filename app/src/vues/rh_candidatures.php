<?php meta(['titre' => t('rec_candidatures')]); ?>
<h1><?= h(t('rec_candidatures')) ?></h1>
<form method="get" action="<?= h(lien('/rh/candidatures')) ?>" class="formulaire formulaire--ligne">
  <div class="champ"><input type="search" name="q" value="<?= h((string) $filtres['q']) ?>" placeholder="<?= h(t('rec_recherche')) ?>"></div>
  <div class="champ"><select name="offre_id"><option value=""><?= h(t('rec_toutes_offres')) ?></option>
    <?php foreach ($offres as $o): ?><option value="<?= (int) $o['id'] ?>"<?= (int) $filtres['offre_id'] === (int) $o['id'] ? ' selected' : '' ?>><?= h((string) $o['titre']) ?></option><?php endforeach; ?>
  </select></div>
  <div class="champ"><select name="statut"><option value=""><?= h(t('doc_toutes')) ?></option>
    <?php foreach (STATUTS_CANDIDATURE as $s): ?><option value="<?= h($s) ?>"<?= $filtres['statut'] === $s ? ' selected' : '' ?>><?= h(t('statut_' . $s)) ?></option><?php endforeach; ?>
  </select></div>
  <button class="btn btn--plein" type="submit"><?= h(t('filtrer')) ?></button>
</form>

<p class="note"><?= h(tn('rec_nb_candidatures', (int) $resultat['total'])) ?></p>
<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('dem_numero')) ?></th><th><?= h(t('rec_candidat')) ?></th>
    <th><?= h(t('rec_offre')) ?></th><th><?= h(t('rec_examen')) ?></th>
    <th><?= h(t('statut')) ?></th><th><?= h(t('dem_date')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($resultat['lignes'] as $c): ?>
    <tr>
      <td class="mono"><?= h((string) $c['numero']) ?></td>
      <td><a href="<?= h(lien('/rh/candidatures/' . (int) $c['id'])) ?>"><?= h(trim($c['prenom'] . ' ' . $c['nom'])) ?></a></td>
      <td><?= h((string) $c['offre_titre']) ?></td>
      <td><?php if ($c['examen_statut'] === 'termine'): ?>
            <?= h(nombre((float) $c['examen_score'], 1)) ?> / <?= h(nombre((float) $c['examen_sur'], 1)) ?>
          <?php else: ?><?= h(t('rec_examen_statut_' . $c['examen_statut'])) ?><?php endif; ?></td>
      <td><?= badge_statut((string) $c['statut'], 'candidature') ?></td>
      <td><?= h(date_locale((string) $c['cree_le'], false)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= pagination((int) $resultat['page'], (int) $resultat['total'], (int) $resultat['par_page'], '/rh/candidatures') ?>
