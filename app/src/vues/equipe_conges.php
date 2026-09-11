<?php meta(['titre' => t('eq_conges_valider')]); ?>
<h1><?= h(t('eq_conges_valider')) ?></h1>
<p class="note"><?= h($tous ? t('eq_portee_rh') : t('eq_portee_manager')) ?></p>

<?php if (!$demandes): ?>
  <p class="vide-liste"><?= h(t('eq_rien_a_valider')) ?></p>
<?php else: ?>
<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('eq_salarie')) ?></th><th><?= h(t('cg_type')) ?></th>
    <th><?= h(t('cg_periode')) ?></th><th><?= h(t('cg_jours')) ?></th>
    <th><?= h(t('cg_motif')) ?></th><th><?= h(t('eq_decision')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($demandes as $d): ?>
    <tr>
      <td><?= h(nom_complet($d)) ?></td>
      <td><?= h(col_langue(['nom_fr' => $d['type_fr'], 'nom_en' => $d['type_en']], 'nom')) ?></td>
      <td><?= h((string) $d['debut']) ?> → <?= h((string) $d['fin']) ?></td>
      <td class="num"><?= h(nombre((float) $d['nb_jours'], 1)) ?></td>
      <td><?= $d['motif'] ? h((string) $d['motif']) : sans_valeur() ?></td>
      <td>
        <form method="post" action="<?= h(lien('/equipe/conges/' . (int) $d['id'])) ?>" class="formulaire--compact">
          <?= champ_csrf() ?>
          <input type="hidden" name="retour" value="/equipe/conges">
          <input name="commentaire" type="text" placeholder="<?= h(t('eq_commentaire')) ?>">
          <button class="btn btn--oui" name="decision" value="approuve" type="submit"><?= h(t('approuver')) ?></button>
          <button class="btn btn--non" name="decision" value="refuse" type="submit"><?= h(t('refuser')) ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
