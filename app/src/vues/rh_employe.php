<?php meta(['titre' => nom_complet($c)]); ?>
<?= fil([[t('rh_lien_employes'), '/rh/employes'], [nom_complet($c)]]) ?>
<section class="identite">
  <?= avatar($c, 64) ?>
  <div>
    <h1><?= h(nom_complet($c)) ?></h1>
    <p class="identite__meta"><span class="mono"><?= h((string) $c['matricule']) ?></span> ·
      <?= badge_role((string) $c['role']['cle']) ?> · <?= h(t('statut_' . $c['statut'])) ?></p>
  </div>
</section>

<?php if ($manquants): ?>
  <p class="encart encart--attention"><?= h(tn('rappel_champs', count($manquants))) ?> :
    <?php foreach ($manquants as $i => $m): ?><?= $i ? ', ' : '' ?><?= h(t($m)) ?><?php endforeach; ?></p>
<?php endif; ?>

<div class="onglets">
  <a href="#dossier"><?= h(t('fiche_dossier')) ?></a>
  <a href="#conges"><?= h(t('nav_conges')) ?></a>
  <a href="#documents"><?= h(t('nav_documents')) ?></a>
  <?php if (peut('paie.gerer')): ?><a href="#paie"><?= h(t('nav_paie')) ?></a><?php endif; ?>
</div>

<section id="dossier" class="carte">
  <h2><?= h(t('fiche_dossier')) ?></h2>
  <form method="post" action="<?= h(lien('/rh/employes/' . (int) $c['id'])) ?>" class="formulaire grille-2">
    <?= champ_csrf() ?>
    <div class="champ"><label for="pr"><?= h(t('emp_prenom')) ?></label><input id="pr" name="prenom" value="<?= h((string) $c['prenom']) ?>"></div>
    <div class="champ"><label for="nm"><?= h(t('emp_nom')) ?></label><input id="nm" name="nom" value="<?= h((string) $c['nom']) ?>"></div>
    <div class="champ"><label for="po"><?= h(t('emp_poste')) ?></label><input id="po" name="poste" value="<?= h((string) $c['poste']) ?>"></div>
    <div class="champ"><label for="de"><?= h(t('emp_departement')) ?></label>
      <select id="de" name="departement_id"><option value=""></option>
        <?php foreach ($departements as $d): ?><option value="<?= (int) $d['id'] ?>"<?= (int) $c['departement_id'] === (int) $d['id'] ? ' selected' : '' ?>><?= h(col_langue($d, 'nom')) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="mg"><?= h(t('emp_manager')) ?></label>
      <select id="mg" name="manager_id"><option value=""></option>
        <?php foreach ($managers as $m): if ((int) $m['id'] === (int) $c['id']) continue; ?>
          <option value="<?= (int) $m['id'] ?>"<?= (int) $c['manager_id'] === (int) $m['id'] ? ' selected' : '' ?>><?= h(nom_complet($m)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="lo"><?= h(t('emp_localisation')) ?></label>
      <select id="lo" name="localisation_id"><option value=""></option>
        <?php foreach ($localisations as $l): ?><option value="<?= (int) $l['id'] ?>"<?= (int) $c['localisation_id'] === (int) $l['id'] ? ' selected' : '' ?>><?= h((string) $l['nom']) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="ro"><?= h(t('emp_role')) ?></label>
      <select id="ro" name="role_id">
        <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"<?= (int) $c['role_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= h(col_langue($r, 'nom')) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="st"><?= h(t('emp_statut')) ?></label>
      <select id="st" name="statut">
        <?php foreach (['actif', 'suspendu', 'parti'] as $s): ?><option value="<?= h($s) ?>"<?= $c['statut'] === $s ? ' selected' : '' ?>><?= h(t('statut_' . $s)) ?></option><?php endforeach; ?>
      </select>
      <span class="aide"><?= h(t('emp_statut_aide')) ?></span></div>
    <div class="champ"><label for="dh"><?= h(t('emp_date_embauche')) ?></label><input id="dh" name="date_embauche" type="date" value="<?= h((string) $c['date_embauche']) ?>"></div>
    <div class="champ"><label for="dn"><?= h(t('emp_date_naissance')) ?></label><input id="dn" name="date_naissance" type="date" value="<?= h((string) $c['date_naissance']) ?>"></div>
    <div class="champ"><label for="dd"><?= h(t('emp_date_depart')) ?></label><input id="dd" name="date_depart" type="date" value="<?= h((string) $c['date_depart']) ?>"></div>
    <div class="champ"><label for="tc"><?= h(t('emp_type_contrat')) ?></label>
      <select id="tc" name="type_contrat"><option value=""></option>
        <?php foreach (['cdi', 'cdd', 'stage', 'prestation'] as $tc): ?><option value="<?= h($tc) ?>"<?= $c['type_contrat'] === $tc ? ' selected' : '' ?>><?= h(t('contrat_' . $tc)) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="pa"><?= h(t('emp_pays')) ?></label>
      <select id="pa" name="pays_travail">
        <?php foreach (cfg('pays') as $code => $p): ?><option value="<?= h($code) ?>"<?= $c['pays_travail'] === $code ? ' selected' : '' ?>><?= h(col_langue($p, 'nom')) ?></option><?php endforeach; ?>
      </select></div>
    <div class="champ"><label for="fp"><?= h(t('emp_frequence_paie')) ?></label>
      <select id="fp" name="frequence_paie">
        <?php foreach (['bimensuelle', 'mensuelle'] as $f): ?><option value="<?= h($f) ?>"<?= $c['frequence_paie'] === $f ? ' selected' : '' ?>><?= h(t('paie_frequence_' . $f)) ?></option><?php endforeach; ?>
      </select>
      <span class="aide"><?= h(t('emp_frequence_aide')) ?></span></div>
    <div class="champ"><label for="dv"><?= h(t('emp_devise')) ?></label><input id="dv" name="devise" value="<?= h((string) $c['devise']) ?>" maxlength="8"></div>
    <div class="champ"><label for="fz"><?= h(t('emp_fuseau')) ?></label><input id="fz" name="fuseau" value="<?= h((string) $c['fuseau']) ?>" list="fz2">
      <datalist id="fz2"><?php foreach (timezone_identifiers_list() as $f): ?><option value="<?= h($f) ?>"><?php endforeach; ?></datalist></div>
    <div class="champ champ--large">
      <label class="case"><input type="checkbox" name="decentralise" value="1"<?= (int) $c['decentralise'] === 1 ? ' checked' : '' ?>> <?= h(t('emp_decentralise')) ?></label>
    </div>
    <div class="champ champ--large"><button class="btn btn--plein" type="submit"><?= h(t('enregistrer')) ?></button></div>
  </form>

  <h3><?= h(t('prof_bancaire')) ?></h3>
  <p><?= $c['banque_masque'] ? '<strong class="mono">' . h((string) $c['banque_masque']) . '</strong> <span class="gris">' . h((string) $c['banque_institution']) . '</span>' : valeur('', 'emp_banque') ?></p>
  <p class="note"><?= h(t('banque_rh_note')) ?></p>
</section>

<section id="conges" class="carte">
  <h2><?= h(t('nav_conges')) ?></h2>
  <div class="soldes">
    <?php foreach ($soldes as $s): ?>
      <article class="widget widget--solde">
        <h3><?= h(col_langue($s['type'], 'nom')) ?></h3>
        <p class="widget__valeur"><?= $s['configure'] ? h(nombre($s['disponible'], 1)) : sans_valeur(t('cg_solde_non_configure')) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if ($conges): ?>
    <table class="tableau tableau--compact">
      <tbody><?php foreach ($conges as $d): ?>
        <tr><td><?= h((string) $d['debut']) ?> → <?= h((string) $d['fin']) ?></td>
            <td class="num"><?= h(nombre((float) $d['nb_jours'], 1)) ?></td>
            <td><?= badge_statut((string) $d['statut'], 'conge') ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
  <?php endif; ?>
  <h3><?= h(t('nav_absences')) ?></h3>
  <?php if (!$absences): ?><p class="gris"><?= h(t('abs_aucune')) ?></p><?php else: ?>
    <table class="tableau tableau--compact">
      <tbody><?php foreach ($absences as $a): ?>
        <tr><td><?= h(t('abs_type_' . $a['type'])) ?></td>
            <td><?= h((string) $a['debut']) ?> → <?= h((string) $a['fin']) ?></td>
            <td><?= badge_statut((string) $a['statut'], 'absence') ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
  <?php endif; ?>
</section>

<section id="documents" class="carte">
  <h2><?= h(t('nav_documents')) ?></h2>
  <?php if (!$documents): ?><p class="gris"><?= h(t('doc_aucun')) ?></p><?php else: ?>
    <ul class="liste-docs">
      <?php foreach ($documents as $d): ?>
        <li><span class="etiquette"><?= h(t('doc_cat_' . $d['categorie'])) ?></span>
          <a href="<?= h(lien('/document/' . (int) $d['id'])) ?>"><?= h((string) $d['titre']) ?></a>
          <span class="gris"><?= h(date_locale((string) $d['cree_le'], false)) ?></span></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p><a class="btn" href="<?= h(lien('/rh/documents')) ?>"><?= h(t('doc_deposer')) ?></a></p>
</section>

<?php if (peut('paie.gerer')): ?>
<section id="paie" class="carte">
  <h2><?= h(t('nav_paie')) ?></h2>
  <p class="note"><?= h(t('paie_affiche_pas_calcule')) ?></p>
  <?php if (!$bulletins): ?><p class="gris"><?= h(t('paie_aucun_bulletin')) ?></p><?php else: ?>
    <table class="tableau tableau--compact">
      <tbody><?php foreach ($bulletins as $b): ?>
        <tr><td><?= h((string) $b['periode_code']) ?></td>
            <td class="num"><?= h(argent((float) $b['net'], (string) $b['devise'])) ?></td>
            <td><?= $b['publie_le'] ? h(t('paie_publie')) : '<span class="etiquette etiquette--attente">' . h(t('paie_brouillon')) . '</span>' ?></td>
            <td><a href="<?= h(lien('/paie/' . (int) $b['id'])) ?>"><?= h(t('paie_detail')) ?></a></td></tr>
      <?php endforeach; ?></tbody>
    </table>
  <?php endif; ?>

  <h3><?= h(t('paie_primes')) ?></h3>
  <?php if ($primes): ?>
    <ul class="liste-docs"><?php foreach ($primes as $p): ?>
      <li><?= h((string) $p['libelle']) ?> <span class="mono"><?= h(argent((float) $p['montant'], (string) $p['devise'])) ?></span>
        <span class="gris"><?= h((string) $p['accordee_le']) ?></span></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>
  <form method="post" action="<?= h(lien('/rh/primes')) ?>" class="formulaire formulaire--ligne">
    <?= champ_csrf() ?>
    <input type="hidden" name="utilisateur_id" value="<?= (int) $c['id'] ?>">
    <input type="hidden" name="retour" value="/rh/employes/<?= (int) $c['id'] ?>">
    <div class="champ"><label for="pl"><?= h(t('paie_prime_libelle')) ?></label><input id="pl" name="libelle" required></div>
    <div class="champ"><label for="pm"><?= h(t('paie_montant')) ?></label><input id="pm" name="montant" inputmode="decimal" required></div>
    <div class="champ"><label for="pd"><?= h(t('emp_devise')) ?></label><input id="pd" name="devise" value="<?= h((string) $c['devise']) ?>" size="5"></div>
    <div class="champ"><label for="pa2"><?= h(t('paie_accordee_le')) ?></label><input id="pa2" name="accordee_le" type="date" value="<?= h(aujourdhui()) ?>"></div>
    <button class="btn" type="submit"><?= h(t('paie_accorder_prime')) ?></button>
  </form>
</section>
<?php endif; ?>
