<?php meta(['titre' => t('nav_profil')]); ?>
<h1><?= h(t('prof_titre')) ?></h1>

<div class="deux-cols">
<section class="carte">
  <h2><?= h(t('prof_pro')) ?></h2>
  <p class="note"><?= h(t('prof_pro_note')) ?></p>
  <dl class="fiche">
    <dt><?= h(t('emp_matricule')) ?></dt><dd><?= h((string) $u['matricule']) ?></dd>
    <dt><?= h(t('emp_poste')) ?></dt><dd><?= valeur($u['poste'], 'emp_poste') ?></dd>
    <dt><?= h(t('emp_departement')) ?></dt><dd><?= $u['departement'] ? h(col_langue($u['departement'], 'nom')) : valeur('', 'emp_departement') ?></dd>
    <dt><?= h(t('emp_manager')) ?></dt><dd><?= $u['manager'] ? h(nom_complet($u['manager'])) : valeur('', 'emp_manager') ?></dd>
    <dt><?= h(t('emp_localisation')) ?></dt><dd><?= $u['localisation'] ? h((string) $u['localisation']['nom']) : valeur('', 'emp_localisation') ?></dd>
    <dt><?= h(t('emp_date_embauche')) ?></dt><dd><?= valeur($u['date_embauche'], 'emp_date_embauche') ?></dd>
    <dt><?= h(t('emp_anciennete')) ?></dt><dd><?= $u['date_embauche'] ? h(anciennete_texte((string) $u['date_embauche'])) : sans_valeur() ?></dd>
    <dt><?= h(t('emp_type_contrat')) ?></dt><dd><?= valeur($u['type_contrat'], 'emp_type_contrat') ?></dd>
    <dt><?= h(t('emp_statut')) ?></dt><dd><?= h(t('statut_' . $u['statut'])) ?></dd>
    <dt><?= h(t('emp_temps_travail')) ?></dt><dd><?= h(t('tt_' . ($u['temps_travail'] ?: 'plein'))) ?></dd>
    <dt><?= h(t('emp_role')) ?></dt><dd><?= badge_role((string) $u['role']['cle']) ?></dd>
  </dl>
</section>

<section class="carte">
  <h2><?= h(t('prof_bancaire')) ?></h2>
  <p><?= h(t('emp_banque')) ?> :
    <?php if ($u['banque_masque']): ?>
      <strong class="mono"><?= h((string) $u['banque_masque']) ?></strong>
      <span class="gris"><?= h((string) $u['banque_institution']) ?></span>
    <?php else: ?><?= valeur('', 'emp_banque') ?><?php endif; ?>
  </p>
  <?php if ($u['banque_maj_le']): ?>
    <p class="note"><?= h(t('banque_maj')) ?> <?= h(date_locale((string) $u['banque_maj_le'])) ?></p>
  <?php endif; ?>
  <p class="note"><?= h(t('banque_explication')) ?></p>
  <p><a class="btn" href="<?= h(lien('/profil/bancaire')) ?>"><?= h(t('banque_modifier')) ?></a></p>
</section>
</div>

<section class="carte">
  <h2><?= h(t('prof_perso')) ?></h2>
  <form method="post" action="<?= h(lien('/profil')) ?>" class="formulaire grille-2">
    <?= champ_csrf() ?>
    <div class="champ">
      <label for="tel"><?= h(t('emp_telephone')) ?></label>
      <input id="tel" name="telephone" type="tel" value="<?= h((string) $u['telephone']) ?>">
    </div>
    <div class="champ">
      <label for="ep"><?= h(t('emp_email_perso')) ?></label>
      <input id="ep" name="email_perso" type="email" value="<?= h((string) $u['email_perso']) ?>">
      <?php if (!empty($erreurs['email_perso'])): ?><span class="erreur"><?= h($erreurs['email_perso']) ?></span><?php endif; ?>
    </div>
    <div class="champ champ--large">
      <label for="ad"><?= h(t('emp_adresse')) ?></label>
      <input id="ad" name="adresse" type="text" value="<?= h((string) $u['adresse']) ?>">
    </div>
    <div class="champ">
      <label for="un"><?= h(t('emp_urgence')) ?></label>
      <input id="un" name="urgence_nom" type="text" value="<?= h((string) $u['urgence_nom']) ?>">
    </div>
    <div class="champ">
      <label for="ul"><?= h(t('emp_urgence_lien')) ?></label>
      <input id="ul" name="urgence_lien" type="text" value="<?= h((string) $u['urgence_lien']) ?>">
    </div>
    <div class="champ">
      <label for="ut"><?= h(t('emp_urgence_tel')) ?></label>
      <input id="ut" name="urgence_tel" type="tel" value="<?= h((string) $u['urgence_tel']) ?>">
    </div>
    <div class="champ">
      <label for="lg"><?= h(t('emp_langue')) ?></label>
      <select id="lg" name="langue">
        <?php foreach (cfg('langues') as $l): ?>
          <option value="<?= h($l) ?>"<?= $l === $u['langue'] ? ' selected' : '' ?>><?= h(t('langue_' . $l)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="champ">
      <label for="fz"><?= h(t('emp_fuseau')) ?></label>
      <input id="fz" name="fuseau" type="text" list="fuseaux" value="<?= h((string) $u['fuseau']) ?>">
      <datalist id="fuseaux">
        <?php foreach (timezone_identifiers_list() as $f): ?><option value="<?= h($f) ?>"><?php endforeach; ?>
      </datalist>
      <span class="aide"><?= h(t('emp_fuseau_aide')) ?></span>
    </div>
    <div class="champ champ--large">
      <label class="case"><input type="checkbox" name="annuaire_visible" value="1"<?= (int) $u['annuaire_visible'] === 1 ? ' checked' : '' ?>>
        <?= h(t('emp_annuaire_visible')) ?></label>
      <label class="case"><input type="checkbox" name="annuaire_tel" value="1"<?= (int) $u['annuaire_tel'] === 1 ? ' checked' : '' ?>>
        <?= h(t('emp_annuaire_tel')) ?></label>
      <span class="aide"><?= h(t('emp_annuaire_aide')) ?></span>
    </div>
    <div class="champ champ--large">
      <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
      <a class="btn btn--plat" href="<?= h(lien('/mot-de-passe')) ?>"><?= h(t('mdp_titre')) ?></a>
    </div>
  </form>
</section>
