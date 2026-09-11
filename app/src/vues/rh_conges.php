<?php meta(['titre' => t('nav_conges')]); ?>
<h1><?= h(t('rh_conges_titre')) ?></h1>

<section>
  <h2><?= h(t('eq_conges_valider')) ?></h2>
  <?php if (!$demandes): ?><p class="vide-liste"><?= h(t('eq_rien_a_valider')) ?></p><?php else: ?>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('eq_salarie')) ?></th><th><?= h(t('cg_type')) ?></th><th><?= h(t('cg_periode')) ?></th>
      <th><?= h(t('cg_jours')) ?></th><th><?= h(t('eq_decision')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($demandes as $d): ?>
      <tr>
        <td><?= h(nom_complet($d)) ?></td>
        <td><?= h(col_langue(['nom_fr' => $d['type_fr'], 'nom_en' => $d['type_en']], 'nom')) ?></td>
        <td><?= h((string) $d['debut']) ?> → <?= h((string) $d['fin']) ?></td>
        <td class="num"><?= h(nombre((float) $d['nb_jours'], 1)) ?></td>
        <td><form method="post" action="<?= h(lien('/equipe/conges/' . (int) $d['id'])) ?>" class="formulaire--compact">
          <?= champ_csrf() ?><input type="hidden" name="retour" value="/rh/conges">
          <button class="btn btn--oui" name="decision" value="approuve" type="submit"><?= h(t('approuver')) ?></button>
          <button class="btn btn--non" name="decision" value="refuse" type="submit"><?= h(t('refuser')) ?></button>
        </form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<section>
  <h2><?= h(t('rh_types_conges')) ?></h2>
  <table class="tableau tableau--compact">
    <thead><tr><th><?= h(t('cg_type')) ?></th><th><?= h(t('cg_paye')) ?></th>
      <th><?= h(t('cg_justificatif_requis')) ?></th><th><?= h(t('cg_solde_defaut')) ?></th><th><?= h(t('emp_pays')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($types as $ty): ?>
      <tr><td><?= h(col_langue($ty, 'nom')) ?></td>
        <td><?= (int) $ty['paye'] === 1 ? '✓' : '—' ?></td>
        <td><?= (int) $ty['justificatif'] === 1 ? '✓' : '—' ?></td>
        <td class="num"><?= $ty['solde_defaut'] !== null ? h(nombre((float) $ty['solde_defaut'], 1)) : sans_valeur() ?></td>
        <td><?= $ty['pays'] ? h(strtoupper((string) $ty['pays'])) : h(t('cg_tous_pays')) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="note"><a href="<?= h(lien('/admin/parametres')) ?>"><?= h(t('rh_configurer_types')) ?></a></p>
</section>

<section>
  <h2><?= h(t('rh_conges_recents')) ?></h2>
  <div class="tableau-defile">
  <table class="tableau tableau--compact">
    <tbody><?php foreach ($recentes as $d): ?>
      <tr><td><?= h(nom_complet($d)) ?></td>
          <td><?= h(col_langue(['nom_fr' => $d['type_fr'], 'nom_en' => $d['type_en']], 'nom')) ?></td>
          <td><?= h((string) $d['debut']) ?> → <?= h((string) $d['fin']) ?></td>
          <td><?= badge_statut((string) $d['statut'], 'conge') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  </div>
</section>
