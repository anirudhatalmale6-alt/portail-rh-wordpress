<?php meta(['titre' => t('rh_titre')]); ?>
<h1><?= h(t('rh_titre')) ?></h1>

<section class="widgets widgets--4">
  <article class="widget"><h2><?= h(t('rh_effectif')) ?></h2>
    <p class="widget__valeur"><?= h(nombre($effectif)) ?></p>
    <p class="widget__note"><?= h(tn('rh_dont_decentralises', (int) $decentralises)) ?></p></article>
  <article class="widget"><h2><?= h(t('rh_entrees')) ?></h2><p class="widget__valeur"><?= h(nombre($entrees)) ?></p>
    <p class="widget__note"><?= h(t('rh_cette_annee')) ?></p></article>
  <article class="widget"><h2><?= h(t('rh_departs')) ?></h2><p class="widget__valeur"><?= h(nombre($departs)) ?></p>
    <p class="widget__note"><?= h(t('rh_cette_annee')) ?></p></article>
  <article class="widget"><h2><?= h(t('rh_turnover')) ?></h2>
    <?php if ($turnover === null): ?>
      <?php /* Un taux de rotation se calcule sur une periode. Sur une base
               installee la semaine derniere, le denominateur est l'effectif
               d'aujourd'hui et le resultat ressemble a un taux sans en etre
               un. On ecrit donc pourquoi il n'y a pas de chiffre. */ ?>
      <p class="widget__valeur"><?= sans_valeur(t('rh_turnover_indispo')) ?></p>
      <p class="widget__note"><?= h(t('rh_turnover_note')) ?></p>
    <?php else: ?>
      <p class="widget__valeur"><?= h(nombre($turnover, 1)) ?> %</p>
      <p class="widget__note"><?= h(t('rh_turnover_calcul')) ?></p>
    <?php endif; ?>
  </article>
</section>

<section class="widgets widgets--4">
  <article class="widget"><h2><?= h(t('rh_absents_auj')) ?></h2><p class="widget__valeur"><?= h(nombre($absents_aujourdhui)) ?></p></article>
  <article class="widget"><h2><?= h(t('rh_conges_auj')) ?></h2><p class="widget__valeur"><?= h(nombre($en_conge_aujourdhui)) ?></p></article>
  <article class="widget"><h2><?= h(t('rh_conges_attente')) ?></h2><p class="widget__valeur"><?= h(nombre($conges_attente)) ?></p>
    <a href="<?= h(lien('/rh/conges')) ?>"><?= h(t('eq_traiter')) ?></a></article>
  <article class="widget"><h2><?= h(t('rh_demandes_ouvertes')) ?></h2><p class="widget__valeur"><?= h(nombre($demandes_ouvertes)) ?></p>
    <a href="<?= h(lien('/rh/demandes')) ?>"><?= h(t('eq_traiter')) ?></a></article>
</section>

<section class="widgets widgets--4">
  <article class="widget"><h2><?= h(t('rh_absences_attente')) ?></h2><p class="widget__valeur"><?= h(nombre($absences_attente)) ?></p>
    <a href="<?= h(lien('/rh/absences')) ?>"><?= h(t('eq_traiter')) ?></a></article>
  <article class="widget"><h2><?= h(t('rh_temps_attente')) ?></h2><p class="widget__valeur"><?= h(nombre($temps_attente)) ?></p>
    <a href="<?= h(lien('/rh/temps')) ?>"><?= h(t('eq_traiter')) ?></a></article>
  <article class="widget"><h2><?= h(t('rh_candidatures')) ?></h2><p class="widget__valeur"><?= h(nombre($candidatures_nouvelles)) ?></p>
    <p class="widget__note"><?= h(tn('rh_offres_publiees', (int) $offres_publiees)) ?></p>
    <a href="<?= h(lien('/rh/candidatures')) ?>"><?= h(t('eq_traiter')) ?></a></article>
  <article class="widget"><h2><?= h(t('rh_fiches_incompletes')) ?></h2><p class="widget__valeur"><?= h(nombre($fiches_incompletes)) ?></p>
    <a href="<?= h(lien('/rh/employes')) ?>"><?= h(t('ar_voir_dossiers')) ?></a></article>
</section>

<?php if ($formations_obligatoires_manquantes > 0): ?>
  <p class="encart encart--attention"><?= h(tn('rh_form_obl_manquantes', (int) $formations_obligatoires_manquantes)) ?></p>
<?php endif; ?>

<section class="deux-cols">
  <div>
    <h2><?= h(t('rh_par_departement')) ?></h2>
    <table class="tableau tableau--compact">
      <tbody>
      <?php foreach ($par_departement as $d): $nom = col_langue($d, 'nom'); ?>
        <tr><th><?= $nom !== '' ? h($nom) : sans_valeur(t('rh_sans_departement')) ?></th>
            <td class="num"><?= h(nombre((int) $d['n'])) ?></td>
            <td class="jauge-cell"><?= jauge((float) $d['n'], (float) max(1, $effectif), $nom) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div>
    <h2><?= h(t('rh_par_localisation')) ?></h2>
    <table class="tableau tableau--compact">
      <tbody>
      <?php foreach ($par_localisation as $l): ?>
        <tr><th><?= $l['nom'] !== '' ? h((string) $l['nom']) : sans_valeur(t('rh_sans_localisation')) ?></th>
            <td class="num"><?= h(nombre((int) $l['n'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <h2><?= h(t('rh_par_pays')) ?></h2>
    <table class="tableau tableau--compact">
      <tbody>
      <?php foreach ($par_pays as $p): ?>
        <tr><th><?= $p['pays'] !== '' ? h(strtoupper((string) $p['pays'])) : sans_valeur() ?></th>
            <td class="num"><?= h(nombre((int) $p['n'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<nav class="liens-rh">
  <a class="btn" href="<?= h(lien('/rh/employes')) ?>"><?= h(t('rh_lien_employes')) ?></a>
  <a class="btn" href="<?= h(lien('/rh/paie')) ?>"><?= h(t('rh_lien_paie')) ?></a>
  <a class="btn" href="<?= h(lien('/rh/documents')) ?>"><?= h(t('rh_lien_documents')) ?></a>
  <a class="btn" href="<?= h(lien('/rh/actualites')) ?>"><?= h(t('rh_lien_actualites')) ?></a>
  <a class="btn" href="<?= h(lien('/rh/offres')) ?>"><?= h(t('rh_lien_offres')) ?></a>
</nav>
