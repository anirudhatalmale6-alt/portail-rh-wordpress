<?php meta(['titre' => t('rh_temps_titre')]); ?>
<h1><?= h(t('rh_temps_titre')) ?></h1>

<section>
  <h2><?= h(t('eq_temps_valider')) ?></h2>
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
        <td class="mono"><?= h((string) $l['debut']) ?>–<?= h((string) $l['fin']) ?></td>
        <td class="num"><?= h(duree_lisible((int) $l['minutes'])) ?></td>
        <td><?= $l['projet'] ? h((string) $l['projet']) : sans_valeur() ?></td>
        <td>
          <form method="post" action="<?= h(lien('/equipe/temps/' . (int) $l['id'])) ?>" class="formulaire--compact">
            <?= champ_csrf() ?>
            <input type="hidden" name="retour" value="/rh/temps">
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
</section>

<section>
  <h2><?= h(t('rh_decentralises')) ?></h2>
  <p class="note"><?= h(t('rh_decentralises_note')) ?></p>
  <?php if (!$decentralises): ?>
    <p class="vide-liste"><?= h(t('rh_aucun_decentralise')) ?></p>
  <?php else: ?>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('eq_salarie')) ?></th><th><?= h(t('emp_fuseau')) ?></th>
      <th><?= h(t('emp_pays')) ?></th><th><?= h(t('tps_total_mois')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($decentralises as $e): $m = total_mois((int) $e['id'], gmdate('Y-m')); ?>
      <tr>
        <td><a href="<?= h(lien('/rh/employes/' . (int) $e['id'])) ?>"><?= h(nom_complet($e)) ?></a></td>
        <td class="mono"><?= h((string) $e['fuseau']) ?></td>
        <td><?= h(strtoupper((string) $e['pays_travail'])) ?></td>
        <td class="num"><?= h(duree_lisible((int) $m['minutes'])) ?>
          <span class="gris"><?= h(tn('tps_jours_declares', (int) $m['jours'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
