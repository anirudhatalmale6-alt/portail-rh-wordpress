<?php meta(['titre' => t('rec_examen_fini')]); ?>
<div class="carte carte--etroite">
  <h1><?= h(t('rec_examen_fini')) ?></h1>
  <p class="lead"><?= h(t('rec_examen_fini_texte')) ?></p>
  <?php if ($a_relire > 0): ?>
    <p class="note"><?= h(tn('rec_examen_a_relire', $a_relire)) ?></p>
  <?php endif; ?>
  <?php /* Aucun score n'est affiche : une partie est relue a la main, et un
           chiffre montre ici deviendrait une promesse. */ ?>
  <p><a href="<?= h(lien('/carrieres')) ?>"><?= h(t('rec_autres_offres')) ?></a></p>
</div>
