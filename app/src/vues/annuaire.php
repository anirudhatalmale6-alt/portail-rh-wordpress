<?php meta(['titre' => t('nav_annuaire')]); ?>
<h1><?= h(t('ann_titre')) ?></h1>

<form method="get" action="<?= h(lien('/annuaire')) ?>" class="formulaire formulaire--ligne">
  <div class="champ"><label class="hors-ecran" for="aq"><?= h(t('rechercher')) ?></label>
    <input id="aq" type="search" name="q" value="<?= h((string) $filtres['q']) ?>" placeholder="<?= h(t('ann_placeholder')) ?>"></div>
  <div class="champ"><label class="hors-ecran" for="ad"><?= h(t('emp_departement')) ?></label>
    <select id="ad" name="departement_id">
      <option value=""><?= h(t('ann_tous_departements')) ?></option>
      <?php foreach ($departements as $d): ?>
        <option value="<?= (int) $d['id'] ?>"<?= (int) $filtres['departement_id'] === (int) $d['id'] ? ' selected' : '' ?>><?= h(col_langue($d, 'nom')) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="champ"><label class="hors-ecran" for="al"><?= h(t('emp_localisation')) ?></label>
    <select id="al" name="localisation_id">
      <option value=""><?= h(t('ann_toutes_localisations')) ?></option>
      <?php foreach ($localisations as $l): ?>
        <option value="<?= (int) $l['id'] ?>"<?= (int) $filtres['localisation_id'] === (int) $l['id'] ? ' selected' : '' ?>><?= h((string) $l['nom']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <button class="btn btn--plein" type="submit"><?= h(t('filtrer')) ?></button>
  <a class="btn btn--plat" href="<?= h(lien('/organigramme')) ?>"><?= h(t('nav_organigramme')) ?></a>
</form>

<p class="note"><?= h(tn('ann_resultats', (int) $resultat['total'])) ?> · <?= h(t('ann_confidentialite')) ?></p>

<?php if (!$cartes): ?>
  <p class="vide-liste"><?= h(t('ann_rien')) ?></p>
<?php else: ?>
<ul class="grille-cartes">
  <?php foreach ($cartes as $c): ?>
    <li class="carte-personne">
      <?= avatar(['prenom' => $c['nom'], 'nom' => ''], 48) ?>
      <div>
        <a class="carte-personne__nom" href="<?= h(lien('/annuaire/' . $c['id'])) ?>"><?= h($c['nom']) ?></a>
        <p class="carte-personne__poste"><?= $c['poste'] !== '' ? h($c['poste']) : sans_valeur() ?></p>
        <p class="gris"><?= h($c['departement']) ?><?php if ($c['localisation']): ?> · <?= h($c['localisation']) ?><?php endif; ?></p>
        <p class="mono"><?= h($c['email']) ?></p>
        <?php if ($c['telephone'] !== ''): ?><p class="mono"><?= h($c['telephone']) ?></p><?php endif; ?>
        <?php if ($c['decentralise']): ?>
          <span class="etiquette etiquette--dec"><?= h(t('acc_decentralise')) ?> <?= h($c['fuseau']) ?></span>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
<?= pagination((int) $resultat['page'], (int) $resultat['total'], (int) $resultat['par_page'], '/annuaire') ?>
<?php endif; ?>
