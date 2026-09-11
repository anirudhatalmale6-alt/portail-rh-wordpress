<?php meta(['titre' => t('nav_admin')]); ?>
<h1><?= h(t('adm_parametres')) ?></h1>

<?php if ($manquants): ?>
  <div class="encart encart--attention">
    <p><?= h(tn('adm_manquants', count($manquants))) ?></p>
    <ul><?php foreach ($manquants as $m): ?><li><code><?= h($m) ?></code> — <?= h(t('cfg_' . $m)) ?></li><?php endforeach; ?></ul>
    <p class="note"><?= h(t('adm_manquants_ou')) ?></p>
  </div>
<?php endif; ?>

<section class="widgets widgets--3">
  <article class="widget"><h2><?= h(t('adm_coffre')) ?></h2>
    <p class="widget__valeur"><?= $coffre ? '<span class="ok-texte">✓</span>' : sans_valeur(t('adm_non_configure')) ?></p>
    <p class="widget__note"><?= h(t('adm_coffre_note')) ?></p></article>
  <article class="widget"><h2><?= h(t('adm_email')) ?></h2>
    <p class="widget__valeur"><?= $email ? '<span class="ok-texte">✓</span>' : sans_valeur(t('adm_non_configure')) ?></p>
    <p class="widget__note"><?= h(t('adm_email_note')) ?></p></article>
  <article class="widget"><h2><?= h(t('adm_demo')) ?></h2>
    <p class="widget__valeur"><?= cfg('mode_demo') ? h(t('oui')) : h(t('non')) ?></p>
    <p class="widget__note"><?= h(t('adm_demo_note')) ?></p></article>
</section>

<section class="carte">
  <h2><?= h(t('adm_reglages')) ?></h2>
  <form method="post" action="<?= h(lien('/admin/parametres')) ?>" class="formulaire formulaire--ligne">
    <?= champ_csrf() ?><input type="hidden" name="action" value="reglage">
    <div class="champ"><label for="rc"><?= h(t('adm_cle')) ?></label><input id="rc" name="cle" required></div>
    <div class="champ"><label for="rv"><?= h(t('adm_valeur')) ?></label><input id="rv" name="valeur"></div>
    <button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button>
  </form>
  <table class="tableau tableau--compact">
    <tbody><?php foreach ($reglages as $r): ?>
      <tr><th><code><?= h((string) $r['cle']) ?></code></th><td><?= valeur($r['valeur'], (string) $r['cle']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
</section>

<section class="carte">
  <h2><?= h(t('rh_types_conges')) ?></h2>
  <form method="post" action="<?= h(lien('/admin/parametres')) ?>" class="formulaire formulaire--ligne">
    <?= champ_csrf() ?><input type="hidden" name="action" value="type_conge">
    <div class="champ"><label for="tc"><?= h(t('adm_cle')) ?></label><input id="tc" name="cle" required></div>
    <div class="champ"><label for="tf"><?= h(t('adm_nom_fr')) ?></label><input id="tf" name="nom_fr" required></div>
    <div class="champ"><label for="te"><?= h(t('adm_nom_en')) ?></label><input id="te" name="nom_en" required></div>
    <div class="champ"><label for="ts"><?= h(t('cg_solde_defaut')) ?></label><input id="ts" name="solde_defaut" inputmode="decimal" class="etroit"></div>
    <div class="champ"><label for="tp"><?= h(t('emp_pays')) ?></label><input id="tp" name="pays" maxlength="8" class="etroit"></div>
    <div class="champ"><label class="case"><input type="checkbox" name="paye" value="1" checked> <?= h(t('cg_paye')) ?></label>
      <label class="case"><input type="checkbox" name="justificatif" value="1"> <?= h(t('cg_justificatif_requis')) ?></label></div>
    <button class="btn" type="submit"><?= h(t('ajouter')) ?></button>
  </form>
  <table class="tableau tableau--compact">
    <tbody><?php foreach ($types_conges as $ty): ?>
      <tr><th><?= h(col_langue($ty, 'nom')) ?></th>
        <td><code><?= h((string) $ty['cle']) ?></code></td>
        <td><?= (int) $ty['paye'] === 1 ? h(t('cg_paye')) : h(t('cg_non_paye')) ?></td>
        <td class="num"><?= $ty['solde_defaut'] !== null ? h(nombre((float) $ty['solde_defaut'], 1)) : sans_valeur() ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
</section>

<section class="carte">
  <h2><?= h(t('adm_feries')) ?></h2>
  <?php /* Aucune date n'est pre-remplie. Un jour ferie est un fait juridique,
           different par pays et par annee : la RH les saisit, le portail ne
           les devine pas. */ ?>
  <p class="note"><?= h(t('adm_feries_note')) ?></p>
  <form method="post" action="<?= h(lien('/admin/parametres')) ?>" class="formulaire formulaire--ligne">
    <?= champ_csrf() ?><input type="hidden" name="action" value="ferie">
    <div class="champ"><label for="fp"><?= h(t('emp_pays')) ?></label>
      <select id="fp" name="pays"><?php foreach (cfg('pays') as $code => $p): ?>
        <option value="<?= h($code) ?>"><?= h(col_langue($p, 'nom')) ?></option><?php endforeach; ?></select></div>
    <div class="champ"><label for="fd"><?= h(t('adm_date')) ?></label><input id="fd" name="date" type="date" required></div>
    <div class="champ"><label for="ff"><?= h(t('adm_nom_fr')) ?></label><input id="ff" name="nom_fr" required></div>
    <div class="champ"><label for="fe"><?= h(t('adm_nom_en')) ?></label><input id="fe" name="nom_en"></div>
    <div class="champ"><label class="case"><input type="checkbox" name="chome" value="1" checked> <?= h(t('adm_chome')) ?></label></div>
    <button class="btn" type="submit"><?= h(t('ajouter')) ?></button>
  </form>
  <?php if (!$feries): ?><p class="vide-liste"><?= h(t('adm_aucun_ferie')) ?></p><?php else: ?>
  <table class="tableau tableau--compact">
    <tbody><?php foreach ($feries as $f): ?>
      <tr><td><?= h(strtoupper((string) $f['pays'])) ?></td><td><?= h((string) $f['date']) ?></td>
          <td><?= h(col_langue($f, 'nom')) ?></td><td><?= (int) $f['chome'] === 1 ? h(t('adm_chome')) : '—' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table>
  <?php endif; ?>
</section>

<section class="carte">
  <h2><?= h(t('adm_structure')) ?></h2>
  <div class="deux-cols">
    <div><h3><?= h(t('emp_departement')) ?></h3>
      <ul class="liste-simple"><?php foreach ($departements as $d): ?><li><?= h(col_langue($d, 'nom')) ?> <code><?= h((string) $d['code']) ?></code></li><?php endforeach; ?></ul></div>
    <div><h3><?= h(t('emp_localisation')) ?></h3>
      <ul class="liste-simple"><?php foreach ($localisations as $l): ?><li><?= h((string) $l['nom']) ?> <span class="gris"><?= h((string) $l['fuseau']) ?></span></li><?php endforeach; ?></ul></div>
  </div>
</section>

<p><a class="btn" href="<?= h(lien('/admin/roles')) ?>"><?= h(t('adm_roles')) ?></a>
   <a class="btn" href="<?= h(lien('/admin/journal')) ?>"><?= h(t('adm_journal')) ?></a></p>
