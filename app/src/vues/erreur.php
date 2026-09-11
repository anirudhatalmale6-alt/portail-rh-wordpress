<?php meta(['titre' => (string) $code]); ?>
<div class="carte carte--centre">
  <h1><?= h((string) $code) ?></h1>
  <p class="lead"><?= h($message) ?></p>
  <p><a class="btn" href="<?= h(lien(connecte() ? '/' : '/connexion')) ?>"><?= h(t('retour_accueil')) ?></a></p>
</div>
