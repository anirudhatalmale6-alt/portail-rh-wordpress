<?php meta(['titre' => t('rechercher')]); ?>
<h1><?= h(t('rech_titre')) ?></h1>
<form method="get" action="<?= h(lien('/recherche')) ?>" class="formulaire formulaire--ligne">
  <div class="champ">
    <label class="hors-ecran" for="rq"><?= h(t('rechercher')) ?></label>
    <input id="rq" type="search" name="q" value="<?= h($q) ?>" autofocus>
  </div>
  <button class="btn btn--plein" type="submit"><?= h(t('rechercher')) ?></button>
</form>

<?php if ($q === ''): ?>
  <p class="note"><?= h(t('rech_exemple')) ?></p>
<?php elseif (!$resultats): ?>
  <p class="vide-liste"><?= h(t('rech_rien', ['q' => $q])) ?></p>
<?php else: ?>
  <p class="note"><?= h(tn('rech_nb', count($resultats))) ?></p>
  <ul class="liste-resultats">
    <?php foreach ($resultats as $r): ?>
      <li>
        <span class="etiquette"><?= h(t('rech_type_' . $r['type'])) ?></span>
        <a href="<?= h(lien($r['lien'])) ?>"><?= h($r['titre']) ?></a>
        <?php if ($r['sous_titre']): ?><span class="gris"><?= h($r['sous_titre']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
