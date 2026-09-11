<?php meta(['titre' => (string) $offre['titre']]); ?>
<?= fil([[t('rec_carrieres'), '/carrieres'], [(string) $offre['titre']]]) ?>
<article class="article">
  <h1><?= h((string) $offre['titre']) ?></h1>
  <p class="gris">
    <span class="mono"><?= h((string) $offre['reference']) ?></span>
    <?php if ($offre['loc_nom']): ?> · <?= h((string) $offre['loc_nom']) ?><?php endif; ?>
    <?php if ($offre['type_contrat']): ?> · <?= h(t('contrat_' . $offre['type_contrat'])) ?><?php endif; ?>
    <?php if ($offre['teletravail']): ?> · <?= h(t('teletravail_' . $offre['teletravail'])) ?><?php endif; ?>
  </p>

  <div class="corps"><?= $offre['rendu_desc'] ?: '<p>' . h((string) $offre['description']) . '</p>' ?></div>

  <?php if ($offre['profil']): ?>
    <h2><?= h(t('rec_profil')) ?></h2>
    <div class="corps"><?= $offre['rendu_profil'] ?: '<p>' . h((string) $offre['profil']) . '</p>' ?></div>
  <?php endif; ?>

  <h2><?= h(t('rec_remuneration')) ?></h2>
  <?php /* Aucune fourchette n'est ecrite par le portail. Si le champ est
           vide, la page le DIT — elle n'affiche pas un chiffre plausible. */ ?>
  <p><?= $offre['salaire_texte'] ? h((string) $offre['salaire_texte']) : sans_valeur(t('rec_remuneration_absente')) ?></p>
</article>

<section class="carte" id="postuler">
  <h2><?= h(t('rec_postuler')) ?></h2>
  <?php if ($offre['examen_id']): ?>
    <p class="encart encart--info">
      <?= h((int) $offre['examen_obligatoire'] === 1 ? t('rec_examen_obl_texte') : t('rec_examen_fac_texte')) ?>
    </p>
  <?php endif; ?>

  <form method="post" action="<?= h(lien('/carrieres/' . $offre['slug'] . '/postuler')) ?>"
        class="formulaire grille-2" enctype="multipart/form-data">
    <?= champ_csrf() ?>
    <div class="champ"><label for="pr"><?= h(t('emp_prenom')) ?></label>
      <input id="pr" name="prenom" required value="<?= h((string) ($saisie['prenom'] ?? '')) ?>">
      <?php if (!empty($erreurs['prenom'])): ?><span class="erreur"><?= h($erreurs['prenom']) ?></span><?php endif; ?></div>
    <div class="champ"><label for="no"><?= h(t('emp_nom')) ?></label>
      <input id="no" name="nom" required value="<?= h((string) ($saisie['nom'] ?? '')) ?>">
      <?php if (!empty($erreurs['nom'])): ?><span class="erreur"><?= h($erreurs['nom']) ?></span><?php endif; ?></div>
    <div class="champ"><label for="em"><?= h(t('cnx_email')) ?></label>
      <input id="em" name="email" type="email" required value="<?= h((string) ($saisie['email'] ?? '')) ?>">
      <?php if (!empty($erreurs['email'])): ?><span class="erreur"><?= h($erreurs['email']) ?></span><?php endif; ?></div>
    <div class="champ"><label for="te"><?= h(t('emp_telephone')) ?></label>
      <input id="te" name="telephone" type="tel" value="<?= h((string) ($saisie['telephone'] ?? '')) ?>"></div>
    <div class="champ"><label for="vi"><?= h(t('rec_ville')) ?></label>
      <input id="vi" name="ville" value="<?= h((string) ($saisie['ville'] ?? '')) ?>"></div>
    <div class="champ"><label for="pa"><?= h(t('emp_pays')) ?></label>
      <input id="pa" name="pays" maxlength="8" value="<?= h((string) ($saisie['pays'] ?? '')) ?>"></div>
    <div class="champ champ--large"><label for="lp"><?= h(t('rec_lien_pro')) ?></label>
      <input id="lp" name="lien_pro" type="url" value="<?= h((string) ($saisie['lien_pro'] ?? '')) ?>"></div>
    <div class="champ"><label for="cv"><?= h(t('rec_cv')) ?></label>
      <input id="cv" name="cv" type="file" accept=".pdf,.doc,.docx">
      <?php if (!empty($erreurs['cv'])): ?><span class="erreur"><?= h($erreurs['cv']) ?></span><?php endif; ?></div>
    <div class="champ"><label for="le"><?= h(t('rec_lettre')) ?></label>
      <input id="le" name="lettre" type="file" accept=".pdf,.doc,.docx"></div>
    <div class="champ champ--large"><label for="me"><?= h(t('rec_message')) ?></label>
      <textarea id="me" name="message" rows="6"><?= h((string) ($saisie['message'] ?? '')) ?></textarea></div>
    <div class="champ champ--large">
      <label class="case"><input type="checkbox" name="consentement" value="1" required>
        <?= h(t('rec_consentement', ['mois' => duree_conservation_mois()])) ?></label>
      <?php if (!empty($erreurs['consentement'])): ?><span class="erreur"><?= h($erreurs['consentement']) ?></span><?php endif; ?>
    </div>
    <div class="champ champ--large">
      <button class="btn btn--plein" type="submit"><?= h(t('rec_envoyer')) ?></button>
    </div>
  </form>
</section>
