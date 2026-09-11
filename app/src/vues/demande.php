<?php meta(['titre' => (string) $d['objet']]); ?>
<?= fil([[t('nav_demandes'), '/demandes'], [(string) $d['numero']]]) ?>
<h1><?= h((string) $d['objet']) ?></h1>
<p class="sous-titre">
  <span class="mono"><?= h((string) $d['numero']) ?></span> ·
  <?= h(t('dem_cat_' . $d['categorie'])) ?> ·
  <?= badge_statut((string) $d['statut'], 'demande') ?> ·
  <?= h(date_locale((string) $d['cree_le'])) ?>
  <?php if ($traite): ?> · <?= h(nom_complet($d)) ?> (<?= h((string) $d['matricule']) ?>)<?php endif; ?>
</p>

<ol class="fil-discussion">
  <?php foreach ($d['messages'] as $m): ?>
    <li class="<?= (int) $m['interne'] === 1 ? 'interne' : '' ?>">
      <div class="msg__tete">
        <?= avatar($m, 32) ?>
        <strong><?= h(nom_complet($m)) ?></strong>
        <time datetime="<?= h((string) $m['cree_le']) ?>"><?= h(date_locale((string) $m['cree_le'])) ?></time>
        <?php if ((int) $m['interne'] === 1): ?><span class="etiquette etiquette--conf"><?= h(t('dem_note_interne')) ?></span><?php endif; ?>
      </div>
      <?php /* $m['rendu'] est du HTML que NOUS avons fabrique a l'ecriture, a
               partir d'un texte deja echappe. C'est la seule raison pour
               laquelle il n'y a pas de h() autour de cette ligne. */ ?>
      <div class="msg__corps"><?= $m['rendu'] ?: '<p>' . h((string) $m['corps']) . '</p>' ?></div>
      <?php if ($m['fichier_id']): ?>
        <p class="msg__piece">📎 <?= h((string) $m['nom_origine']) ?></p>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>

<form method="post" action="<?= h(lien('/demandes/' . (int) $d['id'] . '/message')) ?>" class="formulaire" enctype="multipart/form-data">
  <?= champ_csrf() ?>
  <div class="champ">
    <label for="rc"><?= h(t('dem_repondre')) ?></label>
    <textarea id="rc" name="corps" rows="5" required></textarea>
  </div>
  <div class="champ">
    <input name="piece" type="file">
  </div>
  <?php if ($traite): ?>
    <label class="case"><input type="checkbox" name="interne" value="1"> <?= h(t('dem_note_interne_aide')) ?></label>
  <?php endif; ?>
  <button class="btn btn--plein" type="submit"><?= h(t('envoyer')) ?></button>
</form>

<?php if ($traite): ?>
<form method="post" action="<?= h(lien('/demandes/' . (int) $d['id'] . '/statut')) ?>" class="formulaire formulaire--ligne">
  <?= champ_csrf() ?>
  <div class="champ">
    <label for="st"><?= h(t('dem_changer_statut')) ?></label>
    <select id="st" name="statut">
      <?php foreach (STATUTS_DEMANDE as $s): ?>
        <option value="<?= h($s) ?>"<?= $d['statut'] === $s ? ' selected' : '' ?>><?= h(t('statut_' . $s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn" type="submit"><?= h(t('appliquer')) ?></button>
</form>
<?php endif; ?>
