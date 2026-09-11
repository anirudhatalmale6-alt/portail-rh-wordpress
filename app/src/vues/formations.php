<?php meta(['titre' => t('nav_formations')]); ?>
<h1><?= h(t('form_titre')) ?></h1>

<section>
  <h2><?= h(t('form_miennes')) ?></h2>
  <?php if (!$miennes): ?>
    <p class="vide-liste"><?= h(t('form_aucune')) ?></p>
  <?php else: ?>
  <div class="tableau-defile">
  <table class="tableau">
    <thead><tr><th><?= h(t('form_intitule')) ?></th><th><?= h(t('statut')) ?></th>
      <th><?= h(t('form_date')) ?></th><th><?= h(t('form_duree')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($miennes as $f): ?>
      <tr>
        <td><?= h(col_langue($f, 'titre')) ?>
          <?php if ((int) $f['obligatoire'] === 1): ?><span class="etiquette etiquette--obl"><?= h(t('form_obligatoire')) ?></span><?php endif; ?></td>
        <td><?= badge_statut((string) $f['statut'], 'formation') ?></td>
        <td><?= $f['date_fin'] ?: ($f['date_prevue'] ?: sans_valeur()) ?></td>
        <td class="num"><?= $f['duree_h'] ? h(nombre((float) $f['duree_h'], 1)) . ' h' : sans_valeur() ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<section>
  <h2><?= h(t('form_catalogue')) ?></h2>
  <?php if (!$catalogue): ?>
    <p class="vide-liste"><?= h(t('form_catalogue_vide')) ?></p>
  <?php else: ?>
    <ul class="liste-docs">
      <?php foreach ($catalogue as $f): ?>
        <li><strong><?= h(col_langue($f, 'titre')) ?></strong>
          <?php if ((int) $f['obligatoire'] === 1): ?><span class="etiquette etiquette--obl"><?= h(t('form_obligatoire')) ?></span><?php endif; ?>
          <?php if ($f['description']): ?><p class="gris"><?= h((string) $f['description']) ?></p><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
