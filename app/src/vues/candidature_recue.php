<?php meta(['titre' => t('rec_recue_titre')]); ?>
<div class="carte carte--etroite">
  <h1><?= h(t('rec_recue_titre')) ?></h1>
  <p class="lead"><?= h(t('rec_recue_texte')) ?></p>
  <p class="numero"><?= h(t('rec_recue_numero')) ?> <strong><?= h($numero) ?></strong></p>

  <?php if ($jeton !== ''): ?>
    <div class="encart">
      <h2><?= h($obligatoire ? t('rec_examen_obligatoire') : t('rec_examen_facultatif')) ?></h2>
      <p><?= h($obligatoire ? t('rec_examen_obl_texte') : t('rec_examen_fac_texte')) ?></p>
      <p><a class="btn btn--plein" href="<?= h(lien('/examen/' . $jeton)) ?>"><?= h(t('rec_examen_commencer')) ?></a></p>
      <p class="note"><?= h(t('rec_examen_lien_unique')) ?></p>
    </div>
  <?php endif; ?>

  <p><a href="<?= h(lien('/carrieres')) ?>"><?= h(t('rec_autres_offres')) ?></a></p>
</div>
