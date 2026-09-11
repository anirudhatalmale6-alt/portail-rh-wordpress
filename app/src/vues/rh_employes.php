<?php meta(['titre' => t('rh_lien_employes')]); ?>
<h1><?= h(t('rh_lien_employes')) ?></h1>
<form method="get" action="<?= h(lien('/rh/employes')) ?>" class="formulaire formulaire--ligne">
  <div class="champ"><input type="search" name="q" value="<?= h($q) ?>" placeholder="<?= h(t('ann_placeholder')) ?>"></div>
  <div class="champ"><select name="statut">
    <?php foreach (['actif', 'suspendu', 'parti', 'tous'] as $s): ?>
      <option value="<?= h($s) ?>"<?= $statut === $s ? ' selected' : '' ?>><?= h($s === 'tous' ? t('doc_toutes') : t('statut_' . $s)) ?></option>
    <?php endforeach; ?>
  </select></div>
  <button class="btn btn--plein" type="submit"><?= h(t('filtrer')) ?></button>
  <a class="btn" href="<?= h(lien('/rh/employes/nouveau')) ?>"><?= h(t('emp_nouveau')) ?></a>
</form>

<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('emp_matricule')) ?></th><th><?= h(t('eq_salarie')) ?></th>
    <th><?= h(t('emp_poste')) ?></th><th><?= h(t('emp_departement')) ?></th>
    <th><?= h(t('emp_role')) ?></th><th><?= h(t('emp_statut')) ?></th><th><?= h(t('ar_titre')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $e): $m = champs_manquants($e); ?>
    <tr>
      <td class="mono"><?= h((string) $e['matricule']) ?></td>
      <td><a href="<?= h(lien('/rh/employes/' . (int) $e['id'])) ?>"><?= h(nom_complet($e)) ?></a>
        <?php if ((int) $e['decentralise'] === 1): ?><span class="etiquette etiquette--dec"><?= h(t('acc_decentralise')) ?></span><?php endif; ?></td>
      <td><?= $e['poste'] ? h((string) $e['poste']) : sans_valeur() ?></td>
      <td><?= h(col_langue(['nom_fr' => $e['dep_fr'] ?? '', 'nom_en' => $e['dep_en'] ?? ''], 'nom')) ?></td>
      <td><?= badge_role((string) ($e['role_cle'] ?? 'employe')) ?></td>
      <td><?= h(t('statut_' . $e['statut'])) ?></td>
      <td><?php if ($m): ?><span class="vide"><?= h(nombre(count($m))) ?></span><?php else: ?><span class="ok-texte">✓</span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
