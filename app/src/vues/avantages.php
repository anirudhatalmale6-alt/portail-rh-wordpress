<?php meta(['titre' => t('nav_avantages')]); ?>
<h1><?= h(t('av_titre')) ?></h1>
<p class="lead"><?= h(t('av_intro')) ?></p>

<?php if (!$avantages): ?>
  <p class="vide-liste"><?= h(t('av_aucun')) ?></p>
<?php else: ?>
<div class="grille-cartes">
  <?php foreach ($avantages as $a): ?>
    <article class="carte">
      <h2><?= h(col_langue($a, 'titre')) ?></h2>
      <?php if ($a['description']): ?><p><?= h((string) $a['description']) ?></p><?php endif; ?>
      <dl class="fiche fiche--serree">
        <dt><?= h(t('av_eligibilite')) ?></dt><dd><?= valeur($a['eligibilite'], 'av_eligibilite') ?></dd>
        <dt><?= h(t('av_couverture')) ?></dt><dd><?= valeur($a['couverture'], 'av_couverture') ?></dd>
        <dt><?= h(t('av_contact')) ?></dt><dd><?= valeur($a['contact'], 'av_contact') ?></dd>
        <dt><?= h(t('av_expire')) ?></dt><dd><?= $a['expire_le'] ? h((string) $a['expire_le']) : sans_valeur() ?></dd>
      </dl>
      <?php if ($a['document_id']): ?>
        <p><a href="<?= h(lien('/document/' . (int) $a['document_id'])) ?>"><?= h(t('av_document')) ?></a></p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>
