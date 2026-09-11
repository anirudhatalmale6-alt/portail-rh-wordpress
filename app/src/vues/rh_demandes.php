<?php meta(['titre' => t('nav_demandes')]); ?>
<h1><?= h(t('rh_demandes_titre')) ?></h1>
<form method="get" action="<?= h(lien('/rh/demandes')) ?>" class="formulaire formulaire--ligne">
  <div class="champ"><input type="search" name="q" value="<?= h((string) $filtres['q']) ?>" placeholder="<?= h(t('dem_recherche')) ?>"></div>
  <div class="champ"><select name="statut"><option value=""><?= h(t('doc_toutes')) ?></option>
    <?php foreach (STATUTS_DEMANDE as $s): ?><option value="<?= h($s) ?>"<?= $filtres['statut'] === $s ? ' selected' : '' ?>><?= h(t('statut_' . $s)) ?></option><?php endforeach; ?>
  </select></div>
  <div class="champ"><select name="categorie"><option value=""><?= h(t('doc_toutes')) ?></option>
    <?php foreach (CATEGORIES_DEMANDE as $c): ?><option value="<?= h($c) ?>"<?= $filtres['categorie'] === $c ? ' selected' : '' ?>><?= h(t('dem_cat_' . $c)) ?></option><?php endforeach; ?>
  </select></div>
  <button class="btn btn--plein" type="submit"><?= h(t('filtrer')) ?></button>
</form>

<p class="note"><?= h(tn('dem_nb', (int) $resultat['total'])) ?></p>
<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('dem_numero')) ?></th><th><?= h(t('eq_salarie')) ?></th>
    <th><?= h(t('dem_objet')) ?></th><th><?= h(t('dem_categorie')) ?></th>
    <th><?= h(t('statut')) ?></th><th><?= h(t('dem_date')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($resultat['lignes'] as $d): ?>
    <tr>
      <td class="mono"><?= h((string) $d['numero']) ?></td>
      <td><?= h(nom_complet($d)) ?></td>
      <td><a href="<?= h(lien('/demandes/' . (int) $d['id'])) ?>"><?= h((string) $d['objet']) ?></a></td>
      <td><?= h(t('dem_cat_' . $d['categorie'])) ?></td>
      <td><?= badge_statut((string) $d['statut'], 'demande') ?></td>
      <td><?= h(date_locale((string) $d['cree_le'], false)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= pagination((int) $resultat['page'], (int) $resultat['total'], (int) $resultat['par_page'], '/rh/demandes') ?>
