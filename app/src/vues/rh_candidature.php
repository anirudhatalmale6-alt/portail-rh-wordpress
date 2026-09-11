<?php meta(['titre' => trim($c['prenom'] . ' ' . $c['nom'])]); ?>
<?= fil([[t('rec_candidatures'), '/rh/candidatures'], [(string) $c['numero']]]) ?>
<h1><?= h(trim($c['prenom'] . ' ' . $c['nom'])) ?></h1>
<p class="sous-titre"><span class="mono"><?= h((string) $c['numero']) ?></span> ·
  <?= h((string) $c['offre_titre']) ?> · <?= badge_statut((string) $c['statut'], 'candidature') ?></p>

<dl class="fiche">
  <dt><?= h(t('cnx_email')) ?></dt><dd class="mono"><?= h((string) $c['email']) ?></dd>
  <dt><?= h(t('emp_telephone')) ?></dt><dd class="mono"><?= valeur($c['telephone'], 'emp_telephone') ?></dd>
  <dt><?= h(t('rec_ville')) ?></dt><dd><?= valeur($c['ville'], 'rec_ville') ?></dd>
  <dt><?= h(t('rec_lien_pro')) ?></dt><dd><?= $c['lien_pro'] ? '<a href="' . h((string) $c['lien_pro']) . '" rel="nofollow noopener" target="_blank">' . h((string) $c['lien_pro']) . '</a>' : sans_valeur() ?></dd>
  <dt><?= h(t('rec_consentement_le')) ?></dt><dd><?= h(date_locale((string) $c['consentement_le'])) ?></dd>
  <dt><?= h(t('rec_conservation')) ?></dt><dd><?= h((string) $c['conservation_jusqu']) ?></dd>
</dl>

<?php if ($c['message']): ?>
  <h2><?= h(t('rec_message')) ?></h2>
  <p class="corps"><?= nl2br(h((string) $c['message'])) ?></p>
<?php endif; ?>

<p>
  <?php if ($c['cv_id']): ?><a class="btn" href="<?= h(lien('/document/' . (int) $c['cv_id'])) ?>"><?= h(t('rec_cv')) ?></a><?php else: ?><?= sans_valeur(t('rec_sans_cv')) ?><?php endif; ?>
</p>

<?php if ($reponses): ?>
<section class="carte">
  <h2><?= h(t('rec_examen')) ?> — <?= h(nombre((float) $c['examen_score'], 1)) ?> / <?= h(nombre((float) $c['examen_sur'], 1)) ?></h2>
  <p class="note"><?= h(t('rec_examen_bareme_note')) ?></p>
  <ol class="liste-reponses">
    <?php foreach ($reponses as $r): $libre = $r['type'] === 'texte'; ?>
      <li>
        <p class="question__enonce"><?= h((string) $r['enonce']) ?></p>
        <?php if ($libre): ?>
          <p class="corps"><?= nl2br(h((string) $r['reponse'])) ?></p>
          <p class="note"><?= h(t('rec_a_relire')) ?></p>
        <?php else: ?>
          <?php $choix = json_decode((string) $r['choix'], true) ?: [];
                $donnees = array_filter(explode(',', (string) $r['reponse']), 'strlen');
                $textes = array_map(fn($i) => $choix[(int) $i] ?? $i, $donnees); ?>
          <p><?= h(implode(' · ', $textes) ?: t('rec_sans_reponse')) ?></p>
          <p class="note"><?= h(t('rec_points', ['p' => nombre((float) $r['points'], 1), 'b' => nombre((float) $r['bareme'], 1)])) ?></p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</section>
<?php endif; ?>

<form method="post" action="<?= h(lien('/rh/candidatures/' . (int) $c['id'])) ?>" class="formulaire">
  <?= champ_csrf() ?>
  <div class="champ"><label for="st"><?= h(t('dem_changer_statut')) ?></label>
    <select id="st" name="statut">
      <?php foreach (STATUTS_CANDIDATURE as $s): ?><option value="<?= h($s) ?>"<?= $c['statut'] === $s ? ' selected' : '' ?>><?= h(t('statut_' . $s)) ?></option><?php endforeach; ?>
    </select></div>
  <div class="champ"><label for="nr"><?= h(t('rec_note_rh')) ?></label>
    <textarea id="nr" name="note_rh" rows="4"><?= h((string) $c['note_rh']) ?></textarea>
    <span class="aide"><?= h(t('rec_note_rh_aide')) ?></span></div>
  <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
</form>
