<?php meta(['titre' => t('adm_journal')]); ?>
<h1><?= h(t('adm_journal')) ?></h1>
<p class="lead"><?= h(t('adm_journal_intro')) ?></p>
<?php /* Les colonnes « ancienne » et « nouvelle » sont VIDES sur les champs
         sensibles, et ce vide est ecrit : un journal qui garde
         « ancienne : compte 00123456 » est une deuxieme copie du numero,
         dans une table que l'administrateur systeme peut lire alors qu'il
         n'a aucune raison de connaitre le compte de qui que ce soit. */ ?>
<p class="note"><?= h(t('adm_journal_opaque')) ?></p>

<form method="get" action="<?= h(lien('/admin/journal')) ?>" class="formulaire formulaire--ligne">
  <div class="champ"><label for="ja"><?= h(t('adm_action')) ?></label>
    <select id="ja" name="action"><option value=""><?= h(t('doc_toutes')) ?></option>
      <?php foreach ($actions as $a): ?><option value="<?= h((string) $a['action']) ?>"<?= $filtres['action'] === $a['action'] ? ' selected' : '' ?>><?= h((string) $a['action']) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn btn--plein" type="submit"><?= h(t('filtrer')) ?></button>
</form>

<p class="note"><?= h(tn('adm_journal_nb', (int) $total)) ?></p>
<div class="tableau-defile">
<table class="tableau tableau--compact">
  <thead><tr><th><?= h(t('adm_quand')) ?></th><th><?= h(t('adm_qui')) ?></th>
    <th><?= h(t('adm_action')) ?></th><th><?= h(t('adm_objet')) ?></th>
    <th><?= h(t('adm_champ')) ?></th><th><?= h(t('adm_avant')) ?></th><th><?= h(t('adm_apres')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $l): ?>
    <tr>
      <td class="mono"><?= h(date_locale((string) $l['cree_le'])) ?></td>
      <td><?= $l['utilisateur_id'] ? h(nom_complet($l)) : '<span class="gris">' . h(t('adm_systeme')) . '</span>' ?></td>
      <td><code><?= h((string) $l['action']) ?></code></td>
      <td><?= h((string) $l['objet']) ?><?= $l['objet_id'] ? ' #' . (int) $l['objet_id'] : '' ?></td>
      <td><?= $l['champ'] ? h((string) $l['champ']) : '' ?></td>
      <td><?= $l['ancienne'] !== null ? h(mb_substr((string) $l['ancienne'], 0, 60)) : ($l['detail'] ? '<span class="gris">' . h((string) $l['detail']) . '</span>' : '') ?></td>
      <td><?= $l['nouvelle'] !== null ? h(mb_substr((string) $l['nouvelle'], 0, 60)) : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?= pagination((int) $page, (int) $total, (int) $par_page, '/admin/journal') ?>
