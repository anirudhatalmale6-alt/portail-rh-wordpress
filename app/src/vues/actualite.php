<?php meta(['titre' => (string) $a['titre']]);
$autre = $a['langue'] !== langue();
$dir = langue_rtl((string) $a['langue']) ? 'rtl' : 'ltr'; ?>
<?= fil([[t('nav_actualites'), '/actualites'], [(string) $a['titre']]]) ?>
<article class="article">
  <h1<?= $autre ? ' lang="' . h((string) $a['langue']) . '" dir="' . $dir . '"' : '' ?>><?= h((string) $a['titre']) ?></h1>
  <p class="gris"><?= h(t('act_par')) ?> <?= h(nom_complet($a)) ?> ·
     <?= h(date_locale((string) $a['publie_le'], false)) ?></p>
  <?php if ($a['chapeau']): ?>
    <p class="chapeau"<?= $autre ? ' lang="' . h((string) $a['langue']) . '" dir="' . $dir . '"' : '' ?>><?= h((string) $a['chapeau']) ?></p>
  <?php endif; ?>
  <div class="corps"<?= $autre ? ' lang="' . h((string) $a['langue']) . '" dir="' . $dir . '"' : '' ?>>
    <?= $a['rendu'] ?: '<p>' . h((string) $a['corps']) . '</p>' ?>
  </div>
</article>
