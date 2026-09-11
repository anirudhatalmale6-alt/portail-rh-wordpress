<?php
/**
 * Le gabarit. Une seule page HTML pour tout le portail.
 *
 * L'en-tete change selon le role : un salarie decentralise voit « Mon
 * temps », un manager voit « Mon equipe », la RH voit « RH ». Les entrees
 * sont filtrees par PERMISSION et pas par nom de role — sinon un role
 * ajoute plus tard n'a plus de menu.
 */
$u_courant = utilisateur();
$non_lues = $u_courant ? compte_non_lues((int) $u_courant['id']) : 0;
$meta = $GLOBALS['rh_meta'];
$titre = ($meta['titre'] ?? t('portail_rh')) . ' — ' . cfg('nom_org');
$manquants_moi = $u_courant ? champs_manquants($u_courant) : [];
?><!doctype html>
<html lang="<?= h(langue()) ?>"<?= langue_rtl() ? ' dir="rtl"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titre) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= h(actif('/assets/style.css')) ?>?v=<?= h(VERSION_ACTIFS) ?>">
<link rel="icon" href="<?= h(actif('/assets/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="v-<?= h($vue ?? 'page') ?><?= $u_courant ? '' : ' hors-session' ?>">

<a class="saut" href="#principal"><?= h(t('aller_contenu')) ?></a>

<?php if (cfg('mode_demo')): ?>
  <?php /* Le bandeau est sur TOUTES les pages, y compris publiques. Un jeu
           de demonstration qu'on prend pour de vraies donnees RH, c'est une
           decision prise sur des chiffres faux. */ ?>
  <div class="bandeau-demo" role="status">
    <strong><?= h(t('demo_titre')) ?></strong> <?= h(t('demo_texte')) ?>
  </div>
<?php endif; ?>

<header class="entete">
  <div class="entete__barre">
    <a class="marque" href="<?= h(lien($u_courant ? '/' : '/carrieres')) ?>">
      <span class="marque__logo" aria-hidden="true">RH</span>
      <span class="marque__nom"><?= h(cfg('nom_org')) ?></span>
      <?php if (cfg('nom_provisoire')): ?>
        <span class="pastille-prov"><?= h(t('nom_provisoire')) ?></span>
      <?php endif; ?>
    </a>

    <?php if ($u_courant): ?>
    <nav class="nav" aria-label="<?= h(t('nav_principale')) ?>">
      <a href="<?= h(lien('/')) ?>"><?= h(t('nav_tableau')) ?></a>
      <?php if (peut('temps.saisir') && (int) $u_courant['decentralise'] === 1): ?>
        <a href="<?= h(lien('/temps')) ?>"><?= h(t('nav_temps')) ?></a>
      <?php endif; ?>
      <a href="<?= h(lien('/conges')) ?>"><?= h(t('nav_conges')) ?></a>
      <a href="<?= h(lien('/documents')) ?>"><?= h(t('nav_documents')) ?></a>
      <a href="<?= h(lien('/paie')) ?>"><?= h(t('nav_paie')) ?></a>
      <a href="<?= h(lien('/demandes')) ?>"><?= h(t('nav_demandes')) ?></a>
      <a href="<?= h(lien('/annuaire')) ?>"><?= h(t('nav_annuaire')) ?></a>
      <?php if (peut('equipe.voir')): ?>
        <a href="<?= h(lien('/equipe')) ?>"><?= h(t('nav_equipe')) ?></a>
      <?php endif; ?>
      <?php if (peut('rh.tableau')): ?>
        <a href="<?= h(lien('/rh')) ?>"><?= h(t('nav_rh')) ?></a>
      <?php endif; ?>
      <?php if (peut('admin.parametres')): ?>
        <a href="<?= h(lien('/admin/parametres')) ?>"><?= h(t('nav_admin')) ?></a>
      <?php endif; ?>
    </nav>

    <?php /* Les outils sont regroupes et pousses a droite. Sur un ecran
             etroit — ou avec la navigation RH, qui est la plus longue — la
             barre passe a deux lignes : ce bloc garde alors la deuxieme
             ligne alignee a droite au lieu de la laisser retomber a gauche,
             ce qui donnait l'impression d'un gabarit casse. */ ?>
    <div class="entete__outils">
    <form class="rech-rapide" action="<?= h(lien('/recherche')) ?>" method="get" role="search">
      <label class="hors-ecran" for="q"><?= h(t('rechercher')) ?></label>
      <input id="q" type="search" name="q" placeholder="<?= h(t('rechercher')) ?>"
             value="<?= h((string) ($_GET['q'] ?? '')) ?>">
      <button type="submit" aria-label="<?= h(t('rechercher')) ?>">
        <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <circle cx="7" cy="7" r="5" fill="none" stroke="currentColor" stroke-width="2"/>
          <path d="M11 11l4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="hors-ecran"><?= h(t('rechercher')) ?></span>
      </button>
    </form>

    <div class="compte">
      <a class="cloche" href="<?= h(lien('/notifications')) ?>">
        <?= h(t('nav_notifications')) ?>
        <?php if ($non_lues > 0): ?><span class="pastille"><?= h(nombre($non_lues)) ?></span><?php endif; ?>
      </a>
      <a class="moi" href="<?= h(lien('/profil')) ?>">
        <?= avatar($u_courant, 32) ?><span class="moi__nom"><?= h(nom_complet($u_courant)) ?></span>
      </a>
      <form method="post" action="<?= h(lien('/deconnexion')) ?>" class="en-ligne">
        <?= champ_csrf() ?>
        <button class="btn btn--plat" type="submit"><?= h(t('deconnexion')) ?></button>
      </form>
    </div>
    <?php else: ?>
      <nav class="nav" aria-label="<?= h(t('nav_principale')) ?>">
        <a href="<?= h(lien('/carrieres')) ?>"><?= h(t('nav_carrieres')) ?></a>
        <a href="<?= h(lien('/connexion')) ?>"><?= h(t('connexion')) ?></a>
      </nav>
    <?php endif; ?>

    <div class="langues">
      <?php foreach (cfg('langues') as $l): ?>
        <a class="<?= $l === langue() ? 'actif' : '' ?>"
           href="?lang=<?= h($l) ?>" hreflang="<?= h($l) ?>"><?= h(strtoupper($l)) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($u_courant): ?></div><?php endif; ?>
  </div>
</header>

<?php foreach (flashs() as $f): ?>
  <div class="flash flash--<?= h($f['type']) ?>" role="status"><?= h($f['texte']) ?></div>
<?php endforeach; ?>

<?php if ($u_courant && $manquants_moi): ?>
  <div class="rappel" role="status">
    <?= h(tn('rappel_champs', count($manquants_moi))) ?>
    <a href="<?= h(lien('/profil')) ?>"><?= h(t('rappel_completer')) ?></a>
  </div>
<?php endif; ?>

<main id="principal" class="page">
<?= $corps_page ?>
</main>

<footer class="pied">
  <div class="pied__cols">
    <div>
      <strong><?= h(cfg('nom_org')) ?></strong>
      <p><?= valeur(cfg('entite_juridique'), 'entite_juridique') ?></p>
      <p><?= valeur(cfg('adresse_postale'), 'adresse_postale') ?></p>
    </div>
    <div>
      <strong><?= h(t('pied_donnees')) ?></strong>
      <p><?= h(t('pied_donnees_texte')) ?></p>
      <p><?= h(t('pied_responsable')) ?> <?= valeur(cfg('responsable_donnees'), 'responsable_donnees') ?></p>
    </div>
    <div>
      <strong><?= h(t('pied_aide')) ?></strong>
      <p><?= h(t('pied_contact_rh')) ?> <?= valeur(cfg('contact_rh'), 'contact_rh') ?></p>
      <?php if ($u_courant): ?>
        <p><a href="<?= h(lien('/admin/a-renseigner')) ?>"><?= h(t('pied_a_renseigner')) ?></a></p>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!email_actif()): ?>
    <p class="pied__note"><?= h(t('pied_pas_email')) ?></p>
  <?php endif; ?>
</footer>

<script src="<?= h(actif('/assets/app.js')) ?>?v=<?= h(VERSION_ACTIFS) ?>" defer></script>
</body>
</html>
