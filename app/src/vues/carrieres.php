<?php meta(['titre' => t('rec_carrieres')]); ?>
<h1><?= h(t('rec_carrieres_titre', ['org' => cfg('nom_org')])) ?></h1>
<p class="lead"><?= h(t('rec_carrieres_intro')) ?></p>

<form method="get" action="<?= h(lien('/carrieres')) ?>" class="formulaire formulaire--ligne">
  <div class="champ"><label class="hors-ecran" for="cq"><?= h(t('rechercher')) ?></label>
    <input id="cq" type="search" name="q" value="<?= h((string) $filtres['q']) ?>" placeholder="<?= h(t('rec_placeholder')) ?>"></div>
  <div class="champ"><label class="hors-ecran" for="cp"><?= h(t('emp_pays')) ?></label>
    <select id="cp" name="pays"><option value=""><?= h(t('rec_tous_pays')) ?></option>
      <?php foreach (cfg('pays') as $code => $p): ?>
        <option value="<?= h($code) ?>"<?= $filtres['pays'] === $code ? ' selected' : '' ?>><?= h(col_langue($p, 'nom')) ?></option><?php endforeach; ?>
    </select></div>
  <button class="btn btn--plein" type="submit"><?= h(t('filtrer')) ?></button>
</form>

<?php if (!$offres): ?>
  <p class="vide-liste"><?= h(t('rec_aucune_offre')) ?></p>
<?php else: ?>
<ul class="liste-offres">
  <?php foreach ($offres as $o): ?>
    <li>
      <h2><a href="<?= h(lien('/carrieres/' . $o['slug'])) ?>"><?= h((string) $o['titre']) ?></a></h2>
      <p class="gris">
        <span class="mono"><?= h((string) $o['reference']) ?></span>
        <?php if ($o['dep_fr'] || $o['dep_en']): ?> · <?= h(col_langue(['nom_fr' => $o['dep_fr'], 'nom_en' => $o['dep_en']], 'nom')) ?><?php endif; ?>
        <?php if ($o['loc_nom']): ?> · <?= h((string) $o['loc_nom']) ?><?php endif; ?>
        <?php if ($o['type_contrat']): ?> · <?= h(t('contrat_' . $o['type_contrat'])) ?><?php endif; ?>
        <?php if ($o['teletravail']): ?> · <?= h(t('teletravail_' . $o['teletravail'])) ?><?php endif; ?>
      </p>
      <p><?= h(extrait((string) $o['description'], 240)) ?></p>
      <?php if ($o['examen_id']): ?>
        <span class="etiquette"><?= h((int) $o['examen_obligatoire'] === 1 ? t('rec_examen_obligatoire') : t('rec_examen_facultatif')) ?></span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
