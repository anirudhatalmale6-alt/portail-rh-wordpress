<?php meta(['titre' => t('nav_actualites')]); ?>
<h1><?= h(t('act_titre')) ?></h1>
<?php if (!$actualites): ?>
  <p class="vide-liste"><?= h(t('act_aucune')) ?></p>
<?php else: ?>
<ul class="liste-actus liste-actus--pleine">
  <?php foreach ($actualites as $a): $autre = $a['langue'] !== langue(); ?>
    <li>
      <?php if ((int) $a['epingle'] === 1): ?><span class="etiquette"><?= h(t('act_epingle')) ?></span><?php endif; ?>
      <h2<?= $autre ? ' lang="' . h((string) $a['langue']) . '" dir="' . (langue_rtl((string) $a['langue']) ? 'rtl' : 'ltr') . '"' : '' ?>>
        <a href="<?= h(lien('/actualites/' . $a['slug'])) ?>"><?= h((string) $a['titre']) ?></a>
      </h2>
      <?php if ($a['chapeau']): ?>
        <p<?= $autre ? ' lang="' . h((string) $a['langue']) . '"' : '' ?>><?= h((string) $a['chapeau']) ?></p>
      <?php endif; ?>
      <?php /* La ligne « par X · date » est du mobilier d'interface : elle
               reste dans la langue de LECTURE, meme quand l'article est dans
               une autre. Mise en rtl avec le reste, elle inverserait la date
               autour de ses mots latins. */ ?>
      <p class="gris"><?= h(t('act_par')) ?> <?= h(nom_complet($a)) ?> ·
         <?= h(date_locale((string) $a['publie_le'], false)) ?>
         <?php if ($autre): ?> · <span class="etiquette"><?= h(strtoupper((string) $a['langue'])) ?></span><?php endif; ?></p>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
