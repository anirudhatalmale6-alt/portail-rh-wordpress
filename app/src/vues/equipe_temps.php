<?php meta(['titre' => t('eq_temps_valider')]); ?>
<h1><?= h(t('eq_temps_valider')) ?></h1>
<p class="note"><?= h($tous ? t('eq_portee_rh') : t('eq_portee_manager')) ?></p>

<?php if (!$lignes): ?>
  <p class="vide-liste"><?= h(t('eq_rien_a_valider')) ?></p>
<?php else: ?>
<div class="tableau-defile">
<table class="tableau">
  <thead><tr><th><?= h(t('eq_salarie')) ?></th><th><?= h(t('tps_jour')) ?></th>
    <th><?= h(t('tps_horaire')) ?></th><th><?= h(t('tps_duree')) ?></th>
    <th><?= h(t('tps_projet')) ?></th><th><?= h(t('eq_decision')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($lignes as $l): ?>
    <tr>
      <td><?= h(nom_complet($l)) ?><br><span class="gris"><?= h((string) $l['fuseau']) ?></span></td>
      <td><?= h((string) $l['date']) ?></td>
      <td class="mono"><?= h((string) $l['debut']) ?>–<?= h((string) $l['fin']) ?>
        <?php if ((int) $l['pause_min'] > 0): ?><span class="gris">(−<?= (int) $l['pause_min'] ?> min)</span><?php endif; ?></td>
      <td class="num"><?= h(duree_lisible((int) $l['minutes'])) ?></td>
      <td><?= $l['projet'] ? h((string) $l['projet']) : sans_valeur() ?></td>
      <td>
        <form method="post" action="<?= h(lien('/equipe/temps/' . (int) $l['id'])) ?>" class="formulaire--compact">
          <?= champ_csrf() ?>
          <input type="hidden" name="retour" value="<?= h($tous ? '/rh/temps' : '/equipe/temps') ?>">
          <button class="btn btn--oui" name="decision" value="valide" type="submit"><?= h(t('valider')) ?></button>
          <button class="btn btn--non" name="decision" value="refuse" type="submit"><?= h(t('refuser')) ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
