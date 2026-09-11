<?php meta(['titre' => t('ar_titre')]); ?>
<h1><?= h(t('ar_titre')) ?></h1>
<p class="lead"><?= h(t('ar_intro')) ?></p>

<section class="carte">
  <h2><?= h(t('ar_mon_dossier')) ?></h2>
  <?php if (!$mes_champs): ?>
    <p class="ok-texte"><?= h(t('ar_complet')) ?></p>
  <?php else: ?>
    <ul class="liste-manques">
      <?php foreach ($mes_champs as $c): ?><li><?= h(t($c)) ?></li><?php endforeach; ?>
    </ul>
    <p><a class="btn" href="<?= h(lien('/profil')) ?>"><?= h(t('rappel_completer')) ?></a></p>
  <?php endif; ?>
</section>

<?php if ($reglages): ?>
<section class="carte">
  <h2><?= h(t('ar_organisation')) ?></h2>
  <p class="note"><?= h(t('ar_organisation_note')) ?></p>
  <ul class="liste-manques">
    <?php foreach ($reglages as $c): ?><li><code><?= h($c) ?></code> — <?= h(t('cfg_' . $c)) ?></li><?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($fiches !== null): ?>
<section class="carte">
  <h2><?= h(t('ar_fiches')) ?></h2>
  <p><?= h(tn('ar_fiches_nb', (int) $fiches)) ?>
     <a href="<?= h(lien('/rh/employes')) ?>"><?= h(t('ar_voir_dossiers')) ?></a></p>
</section>
<?php endif; ?>
