<?php meta(['titre' => t('nav_equipe')]); ?>
<h1><?= h(t('eq_titre')) ?></h1>
<p class="note"><?= h(t('eq_perimetre')) ?></p>

<section class="widgets widgets--4">
  <article class="widget"><h2><?= h(t('eq_effectif')) ?></h2><p class="widget__valeur"><?= h(nombre($effectif)) ?></p></article>
  <article class="widget"><h2><?= h(t('eq_absents')) ?></h2><p class="widget__valeur"><?= h(nombre(count($absents_aujourdhui))) ?></p>
    <p class="widget__note"><?= h(t('eq_absents_note')) ?></p></article>
  <article class="widget"><h2><?= h(t('eq_conges_valider')) ?></h2><p class="widget__valeur"><?= h(nombre(count($conges_a_valider))) ?></p>
    <a href="<?= h(lien('/equipe/conges')) ?>"><?= h(t('eq_traiter')) ?></a></article>
  <article class="widget"><h2><?= h(t('eq_temps_valider')) ?></h2><p class="widget__valeur"><?= h(nombre(count($temps_a_valider))) ?></p>
    <a href="<?= h(lien('/equipe/temps')) ?>"><?= h(t('eq_traiter')) ?></a></article>
</section>

<section class="deux-cols">
  <div>
    <h2><?= h(t('eq_membres')) ?></h2>
    <?php if (!$equipe): ?><p class="vide-liste"><?= h(t('eq_vide')) ?></p><?php else: ?>
    <ul class="grille-cartes">
      <?php foreach ($equipe as $e): ?>
        <li class="carte-personne">
          <?= avatar($e, 40) ?>
          <div>
            <a href="<?= h(lien('/annuaire/' . (int) $e['id'])) ?>"><?= h(nom_complet($e)) ?></a>
            <p class="gris"><?= $e['poste'] ? h((string) $e['poste']) : sans_valeur() ?></p>
            <?php if ((int) $e['decentralise'] === 1): ?>
              <span class="etiquette etiquette--dec"><?= h((string) $e['fuseau']) ?></span>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <div>
    <h2><?= h(t('eq_ce_mois')) ?></h2>
    <h3><?= h(t('eq_anniversaires')) ?></h3>
    <?php if (!$anniversaires): ?><p class="gris"><?= h(t('eq_aucun')) ?></p><?php else: ?>
      <ul class="liste-simple"><?php foreach ($anniversaires as $e): ?>
        <li><?= h(nom_complet($e)) ?> <span class="gris"><?= h(substr((string) $e['date_naissance'], 5)) ?></span></li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
    <h3><?= h(t('eq_embauches')) ?></h3>
    <?php if (!$embauches): ?><p class="gris"><?= h(t('eq_aucun')) ?></p><?php else: ?>
      <ul class="liste-simple"><?php foreach ($embauches as $e): ?>
        <li><?= h(nom_complet($e)) ?> <span class="gris"><?= h(anciennete_texte((string) $e['date_embauche'])) ?></span></li>
      <?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</section>
