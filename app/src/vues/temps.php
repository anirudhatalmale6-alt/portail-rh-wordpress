<?php
meta(['titre' => t('nav_temps')]);
$lundi = $semaine['lundi'];
$prec = (new DateTimeImmutable($lundi))->modify('-7 days')->format('Y-m-d');
$suiv = (new DateTimeImmutable($lundi))->modify('+7 days')->format('Y-m-d');
$jours_noms = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
?>
<h1><?= h(t('tps_titre')) ?></h1>
<p class="lead"><?= h(t('tps_intro', ['fuseau' => (string) ($u['fuseau'] ?: 'UTC')])) ?></p>

<nav class="nav-semaine">
  <a class="btn btn--plat" href="<?= h(lien('/temps?semaine=' . $prec)) ?>">← <?= h(t('tps_semaine_prec')) ?></a>
  <strong><?= h(t('tps_semaine_du', ['date' => $lundi])) ?></strong>
  <a class="btn btn--plat" href="<?= h(lien('/temps?semaine=' . $suiv)) ?>"><?= h(t('tps_semaine_suiv')) ?> →</a>
</nav>

<section class="widgets widgets--3">
  <article class="widget">
    <h2><?= h(t('tps_total_semaine')) ?></h2>
    <p class="widget__valeur"><?= h(duree_lisible((int) $semaine['minutes'])) ?></p>
  </article>
  <article class="widget">
    <h2><?= h(t('tps_total_mois')) ?></h2>
    <p class="widget__valeur"><?= h(duree_lisible((int) $mois['minutes'])) ?></p>
    <p class="widget__note"><?= h(tn('tps_jours_declares', (int) $mois['jours'])) ?></p>
  </article>
  <article class="widget">
    <h2><?= h(t('tps_a_valider')) ?></h2>
    <p class="widget__valeur"><?= h(nombre((int) $semaine['nb_soumis'])) ?></p>
    <p class="widget__note"><?= h(tn('tps_valides_n', (int) $semaine['nb_valides'])) ?></p>
  </article>
</section>

<div class="tableau-defile">
<table class="tableau tableau--temps">
  <thead><tr>
    <th><?= h(t('tps_jour')) ?></th><th><?= h(t('tps_debut')) ?></th><th><?= h(t('tps_fin')) ?></th>
    <th><?= h(t('tps_pause')) ?></th><th><?= h(t('tps_duree')) ?></th>
    <th><?= h(t('tps_projet')) ?></th><th><?= h(t('statut')) ?></th><th></th>
  </tr></thead>
  <tbody>
  <?php $i = 0; foreach ($semaine['jours'] as $date => $l): $nom = $jours_noms[$i++]; ?>
    <tr class="<?= $date === $aujourdhui ? 'aujourdhui' : '' ?>">
      <form method="post" action="<?= h(lien('/temps/journee')) ?>">
      <td>
        <?= champ_csrf() ?>
        <input type="hidden" name="date" value="<?= h($date) ?>">
        <strong><?= h(t('jour_' . $nom)) ?></strong><br><span class="gris"><?= h($date) ?></span>
      </td>
      <?php $fige = $l && $l['statut'] === 'valide'; ?>
      <td><input name="debut" type="time" value="<?= h((string) ($l['debut'] ?? '')) ?>"<?= $fige ? ' disabled' : '' ?>></td>
      <td><input name="fin" type="time" value="<?= h((string) ($l['fin'] ?? '')) ?>"<?= $fige ? ' disabled' : '' ?>></td>
      <td><input name="pause_min" type="number" min="0" max="600" step="5" class="etroit"
                 value="<?= h((string) ($l['pause_min'] ?? 0)) ?>"<?= $fige ? ' disabled' : '' ?>></td>
      <td class="num"><?= $l ? h(duree_lisible((int) $l['minutes'])) : '—' ?></td>
      <td><input name="projet" type="text" value="<?= h((string) ($l['projet'] ?? '')) ?>"<?= $fige ? ' disabled' : '' ?>></td>
      <td><?= $l ? badge_statut((string) $l['statut'], 'temps') : sans_valeur(t('tps_non_saisi')) ?>
        <?php if ($l && $l['commentaire']): ?><p class="gris"><?= h((string) $l['commentaire']) ?></p><?php endif; ?>
      </td>
      <td><?php if (!$fige): ?><button class="btn btn--plat" type="submit"><?= h(t('enregistrer')) ?></button><?php endif; ?></td>
      </form>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php if (!empty($erreurs)): ?>
  <p class="erreur" role="alert"><?= h(implode(' · ', $erreurs)) ?></p>
<?php endif; ?>

<form method="post" action="<?= h(lien('/temps/soumettre')) ?>" class="bloc-action">
  <?= champ_csrf() ?>
  <input type="hidden" name="lundi" value="<?= h($lundi) ?>">
  <button class="btn btn--plein" type="submit"><?= h(t('tps_soumettre')) ?></button>
  <span class="aide"><?= h(t('tps_soumettre_aide')) ?></span>
</form>
