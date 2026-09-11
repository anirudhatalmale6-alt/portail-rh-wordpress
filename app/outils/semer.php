<?php
/**
 * Jeu de demonstration.
 *
 * ------------------------------------------------------------------------
 * CE QUE CE FICHIER N'ECRIT PAS, ET POURQUOI.
 *
 * - Aucun salarie reel. Les noms sont inventes et n'appartiennent a
 *   personne. Rien ici ne dit quoi que ce soit d'ExportRev ni de ses
 *   equipes.
 * - Aucun salaire « realiste ». Les montants sont ronds et evidemment
 *   fictifs : un chiffre plausible finit par etre cite en reunion.
 * - Aucun jour ferie. Un jour ferie est un fait juridique, different par
 *   pays et par annee ; la RH les saisit. Le tableau de bord affiche donc
 *   « aucun jour ferie enregistre », et c'est exactement ce qu'on veut voir
 *   sur une installation neuve.
 * - Aucun solde de conges par defaut sur les types. Le nombre de jours
 *   annuels vient de la loi et du contrat. En revanche ce fichier OUVRE des
 *   soldes nominatifs pour les salaries de demonstration, sans quoi la page
 *   des conges ne montrerait rien du tout.
 *
 * Tout ce qui est cree porte demo = 1 et disparait avec
 * outils/purge-demo.php, qui conserve les roles, les permissions, les
 * departements, les localisations et les types de conges.
 * ------------------------------------------------------------------------
 *
 *   php outils/semer.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("A lancer en ligne de commande.\n"); }

$racine = dirname(__DIR__);
require $racine . '/src/noyau.php';
require $racine . '/src/i18n.php';
require $racine . '/src/vue.php';
require $racine . '/src/coffre.php';
require $racine . '/src/auth.php';
require $racine . '/src/balisage.php';
require $racine . '/src/schema.php';
foreach (glob($racine . '/src/domaine/*.php') as $f) require $f;

if ((int) qval('SELECT COUNT(*) FROM utilisateurs WHERE demo = 1') > 0) {
    exit("Le jeu de demonstration est deja en place. Lance d'abord outils/purge-demo.php.\n");
}

$auj = new DateTimeImmutable(aujourdhui());
$role = fn(string $c) => (int) qval('SELECT id FROM roles WHERE cle = ?', [$c]);
$dep  = fn(string $c) => (int) qval('SELECT id FROM departements WHERE code = ?', [$c]);
$loc  = fn(string $c) => (int) qval('SELECT id FROM localisations WHERE code = ?', [$c]);
$mdp_demo = hache_mdp('demonstration-2026');

/* ---------- 1. Salaries ------------------------------------------------
 * Un seul mot de passe partage pour la demonstration, ecrit en clair ICI et
 * nulle part ailleurs. Il n'existe que dans le jeu de demo et il disparait
 * avec lui : purge-demo supprime ces comptes.                            */

$gens = [
    // matricule, prenom, nom, poste, dep, loc, role, manager(cle), pays,
    // decentralise, fuseau, embauche, contrat, naissance
    ['E2020-0001', 'Nadia',  'Belkacem', 'Directrice generale', 'direction', 'ca', 'rh',      null,          'ca', 0, 'America/Toronto', '2020-03-02', 'cdi', '1984-06-12'],
    ['E2021-0002', 'Marc',   'Tremblay', 'Responsable des ventes', 'ventes', 'ca', 'manager', 'E2020-0001',  'ca', 0, 'America/Toronto', '2021-09-13', 'cdi', '1989-11-04'],
    ['E2022-0003', 'Leila',  'Hamdani',  'Chargee de recrutement', 'rh',     'dz', 'rh',      'E2020-0001',  'dz', 0, 'Africa/Algiers',  '2022-01-10', 'cdi', '1992-02-27'],
    ['E2023-0004', 'Youcef', 'Berrada',  'Analyste export', 'operations',    'dz', 'employe', 'E2021-0002',  'dz', 0, 'Africa/Algiers',  '2023-04-03', 'cdi', '1995-08-19'],
    ['E2024-0005', 'Sofia',  'Marchand', 'Developpeuse', 'technique',        'ca', 'employe_decentralise', 'E2021-0002', 'ca', 1, 'Europe/Lisbon', '2024-02-05', 'cdi', '1993-05-30'],
    ['E2024-0006', 'Karim',  'Ould',     'Consultant terrain', 'operations', 'dz', 'employe_decentralise', 'E2021-0002', 'dz', 1, 'Asia/Dubai',   '2024-06-17', 'prestation', '1990-12-08'],
    ['E2025-0007', 'Amelie', 'Rousseau', 'Assistante administrative', 'administration', 'ca', 'employe', 'E2020-0001', 'ca', 0, 'America/Toronto', '2025-01-06', 'cdd', '1998-03-22'],
    ['E2026-0008', 'Ibrahim','Saidi',    'Commercial junior', 'ventes',      'dz', 'employe', 'E2021-0002',  'dz', 0, 'Africa/Algiers',  '2026-05-04', 'cdi', '2000-09-15'],
];

$ids = [];
foreach ($gens as $g) {
    [$mat, $pre, $nom, $poste, $dc, $lc, $rc, $mgr, $pays, $dec, $fz, $emb, $ctr, $nais] = $g;
    $p = cfg('pays')[$pays];
    $ids[$mat] = insere('utilisateurs', [
        'matricule' => $mat,
        'email' => strtolower($pre . '.' . preg_replace('/[^a-z]/i', '', $nom)) . '@exemple.test',
        'mot_de_passe' => $mdp_demo, 'doit_changer' => 0, 'actif' => 1,
        'role_id' => $role($rc),
        'prenom' => $pre, 'nom' => $nom, 'poste' => $poste,
        'departement_id' => $dep($dc), 'localisation_id' => $loc($lc),
        'manager_id' => $mgr ? ($ids[$mgr] ?? null) : null,
        'date_embauche' => $emb, 'date_naissance' => $nais,
        'type_contrat' => $ctr, 'statut' => 'actif', 'temps_travail' => 'plein',
        'decentralise' => $dec, 'fuseau' => $fz, 'pays_travail' => $pays,
        'langue' => $pays === 'ca' ? 'fr' : 'fr',
        'devise' => $p['devise'], 'frequence_paie' => $p['frequence_paie'],
        'telephone' => '', 'annuaire_visible' => 1, 'annuaire_tel' => 0,
        // Le telephone et le contact d'urgence restent VIDES : ils font
        // apparaitre les pastilles « a renseigner » sur la demonstration,
        // ce qui est precisement ce que le mecanisme doit montrer.
        'cree_le' => maintenant(), 'maj_le' => maintenant(), 'demo' => 1,
    ]);
}
echo 'Salaries de demonstration : ' . count($ids) . "\n";

/* ---------- 2. Soldes de conges --------------------------------------- */
$annee = (int) gmdate('Y');
$type_annuel = (int) qval("SELECT id FROM types_conges WHERE cle = 'annuel'");
$type_maladie = (int) qval("SELECT id FROM types_conges WHERE cle = 'maladie'");
$n = 0;
foreach ($ids as $mat => $uid) {
    // Des soldes ronds et differents, pour que la page ne montre pas huit
    // fois le meme nombre.
    $acquis = 15 + (crc32($mat) % 4) * 2;
    insere('soldes_conges', ['utilisateur_id' => $uid, 'type_id' => $type_annuel,
        'annee' => $annee, 'acquis' => (float) $acquis, 'ajuste' => 0,
        'note' => 'Solde de demonstration', 'maj_le' => maintenant()]);
    insere('soldes_conges', ['utilisateur_id' => $uid, 'type_id' => $type_maladie,
        'annee' => $annee, 'acquis' => 6.0, 'ajuste' => 0,
        'note' => 'Solde de demonstration', 'maj_le' => maintenant()]);
    $n += 2;
}
echo "Soldes ouverts : $n\n";

/* ---------- 3. Demandes de conges -------------------------------------- */
$conges = [
    // matricule, debut(+j), duree(j), statut
    ['E2023-0004', 12, 4, 'en_attente'],
    ['E2024-0005', 20, 9, 'en_attente'],
    ['E2026-0008',  5, 2, 'en_attente'],
    ['E2022-0003', -30, 5, 'approuve'],
    ['E2024-0006', -12, 3, 'approuve'],
    ['E2025-0007', -60, 2, 'refuse'],
];
foreach ($conges as [$mat, $decal, $duree, $statut]) {
    $d = $auj->modify(($decal >= 0 ? '+' : '') . $decal . ' days');
    $f = $d->modify('+' . max(0, $duree - 1) . ' days');
    $u = employe($ids[$mat]);
    insere('demandes_conges', [
        'utilisateur_id' => $ids[$mat], 'type_id' => $type_annuel,
        'debut' => $d->format('Y-m-d'), 'fin' => $f->format('Y-m-d'),
        'demi_debut' => 0, 'demi_fin' => 0,
        'nb_jours' => jours_ouvres($d->format('Y-m-d'), $f->format('Y-m-d'),
                                   (string) $u['pays_travail']),
        'motif' => '', 'statut' => $statut,
        'decideur_id' => $statut === 'en_attente' ? null : ($u['manager_id'] ?? null),
        'decide_le' => $statut === 'en_attente' ? null : maintenant(),
        'commentaire' => $statut === 'refuse' ? 'Periode deja couverte par deux absences dans l\'equipe.' : '',
        'cree_le' => maintenant(), 'maj_le' => maintenant(), 'demo' => 1,
    ]);
}
echo 'Demandes de conges : ' . count($conges) . "\n";

/* ---------- 4. Absences ------------------------------------------------
 * Aucun justificatif n'est joint : la demonstration doit montrer l'etat
 * « aucun justificatif » et l'etiquette « depose » cote manager sans qu'un
 * faux certificat medical circule dans une archive livree.               */
foreach ([['E2026-0008', -3, 1, 'maladie', 'declaree'],
          ['E2023-0004', -18, 2, 'personnelle', 'validee'],
          ['E2025-0007', -45, 3, 'maladie', 'validee']] as [$mat, $decal, $duree, $type, $statut]) {
    $d = $auj->modify($decal . ' days');
    $f = $d->modify('+' . max(0, $duree - 1) . ' days');
    insere('absences', [
        'utilisateur_id' => $ids[$mat], 'type' => $type,
        'debut' => $d->format('Y-m-d'), 'fin' => $f->format('Y-m-d'),
        'nb_jours' => (float) $duree, 'note' => '',
        'statut' => $statut,
        'valide_par' => $statut === 'validee' ? $ids['E2022-0003'] : null,
        'valide_le' => $statut === 'validee' ? maintenant() : null,
        'cree_le' => maintenant(), 'demo' => 1,
    ]);
}
echo "Absences : 3\n";

/* ---------- 5. Calendrier de paie et bulletins ------------------------- */
generer_periodes('ca', 'bimensuelle', $auj->modify('-90 days')->format('Y-m-d'), 10, 5);
generer_periodes('dz', 'mensuelle',   $auj->modify('first day of -3 months')->format('Y-m-d'), 6, 3);
q('UPDATE periodes_paie SET demo = 1');

$periodes_passees = qtous('SELECT * FROM periodes_paie WHERE paiement_le < ? ORDER BY paiement_le',
                          [aujourdhui()]);
$nb_bulletins = 0;
foreach ($ids as $mat => $uid) {
    $u = employe($uid);
    foreach ($periodes_passees as $p) {
        if ($p['pays'] !== $u['pays_travail']) continue;
        // Montants ronds et manifestement fictifs. Le portail n'invente
        // aucune retenue : les lignes ci-dessous sont saisies, exactement
        // comme la RH le ferait.
        $brut = $u['frequence_paie'] === 'bimensuelle' ? 2000.00 : 4000.00;
        $cot  = round($brut * 0.10, 2);
        $imp  = round($brut * 0.15, 2);
        $net  = round($brut - $cot - $imp, 2);
        $bid = insere('bulletins', [
            'utilisateur_id' => $uid, 'periode_id' => (int) $p['id'],
            'devise' => (string) $u['devise'], 'brut' => $brut, 'net' => $net,
            'note' => 'Bulletin de demonstration — montants fictifs.',
            'publie_le' => maintenant(), 'cree_le' => maintenant(), 'demo' => 1,
        ]);
        insere('lignes_bulletin', ['bulletin_id' => $bid, 'rang' => 1, 'type' => 'salaire',
            'libelle' => 'Salaire de base', 'montant' => $brut]);
        insere('lignes_bulletin', ['bulletin_id' => $bid, 'rang' => 2, 'type' => 'deduction',
            'libelle' => 'Cotisations sociales', 'montant' => $cot]);
        insere('lignes_bulletin', ['bulletin_id' => $bid, 'rang' => 3, 'type' => 'retenue',
            'libelle' => 'Retenue a la source', 'montant' => $imp]);
        $nb_bulletins++;
    }
}
echo "Bulletins publies : $nb_bulletins\n";

insere('primes', ['utilisateur_id' => $ids['E2023-0004'], 'libelle' => 'Prime de recrutement',
    'montant' => 500.00, 'devise' => 'DZD', 'motif' => 'Douze embauches menees a terme.',
    'accordee_le' => $auj->modify('-20 days')->format('Y-m-d'),
    'accordee_par' => $ids['E2020-0001'], 'cree_le' => maintenant(), 'demo' => 1]);
insere('primes', ['utilisateur_id' => $ids['E2024-0005'], 'libelle' => 'Prime de projet',
    'montant' => 800.00, 'devise' => 'CAD', 'motif' => 'Livraison du portail interne.',
    'accordee_le' => $auj->modify('-8 days')->format('Y-m-d'),
    'accordee_par' => $ids['E2020-0001'], 'cree_le' => maintenant(), 'demo' => 1]);

/* ---------- 6. Feuilles de temps (salaries decentralises) -------------- */
$nb_temps = 0;
foreach (['E2024-0005' => 'Portail interne', 'E2024-0006' => 'Mission terrain'] as $mat => $projet) {
    for ($i = 1; $i <= 12; $i++) {
        $d = $auj->modify('-' . $i . ' days');
        if ((int) $d->format('N') >= 6) continue;
        $statut = $i <= 4 ? 'soumis' : 'valide';
        insere('feuilles_temps', [
            'utilisateur_id' => $ids[$mat], 'date' => $d->format('Y-m-d'),
            'debut' => '09:00', 'fin' => '17:30', 'pause_min' => 45, 'minutes' => 465,
            'projet' => $projet, 'note' => '', 'statut' => $statut,
            'valide_par' => $statut === 'valide' ? $ids['E2021-0002'] : null,
            'valide_le' => $statut === 'valide' ? maintenant() : null,
            'cree_le' => maintenant(), 'maj_le' => maintenant(), 'demo' => 1,
        ]);
        $nb_temps++;
    }
}
echo "Journees de temps : $nb_temps\n";

/* ---------- 7. Demandes RH --------------------------------------------- */
$demandes = [
    ['E2023-0004', 'attestation_emploi', 'Attestation d\'emploi pour ma banque',
     "Bonjour,\n\nJ'ai besoin d'une attestation d'emploi mentionnant ma date d'embauche et mon poste, pour un dossier bancaire.\n\nMerci.", 'en_traitement'],
    ['E2025-0007', 'question_paie', 'Ecart sur le dernier bulletin',
     "Le net du dernier bulletin ne correspond pas a celui du precedent alors que rien n'a change de mon cote. Pouvez-vous verifier ?", 'nouveau'],
    ['E2026-0008', 'question_conges', 'Report des jours non pris',
     "Est-ce que les jours non pris cette annee se reportent sur l'an prochain ?", 'resolu'],
];
foreach ($demandes as $i => [$mat, $cat, $objet, $corps, $statut]) {
    $id = insere('demandes_rh', [
        'numero' => sprintf('DEM-%d-%04d', $annee, $i + 1),
        'utilisateur_id' => $ids[$mat], 'categorie' => $cat,
        'objet' => $objet, 'corps' => $corps, 'statut' => $statut,
        'responsable_id' => $statut === 'nouveau' ? null : $ids['E2022-0003'],
        'cree_le' => maintenant(), 'maj_le' => maintenant(),
        'ferme_le' => $statut === 'resolu' ? maintenant() : null, 'demo' => 1,
    ]);
    insere('messages_demande', ['demande_id' => $id, 'auteur_id' => $ids[$mat],
        'corps' => $corps, 'rendu' => rendre_texte($corps), 'interne' => 0,
        'cree_le' => maintenant()]);
    if ($statut === 'resolu') {
        $rep = "Les regles de report figurent dans la politique de conges, section 4. Je te l'ai deposee dans tes documents.";
        insere('messages_demande', ['demande_id' => $id, 'auteur_id' => $ids['E2022-0003'],
            'corps' => $rep, 'rendu' => rendre_texte($rep), 'interne' => 0,
            'cree_le' => maintenant()]);
    }
}
echo 'Demandes RH : ' . count($demandes) . "\n";

/* ---------- 8. Actualites ----------------------------------------------
 * Trois actualites partagent un groupe pour montrer le mecanisme de
 * langue : le lecteur anglophone recoit la version anglaise quand elle
 * existe, et le portail SIGNALE la langue quand elle n'existe pas. Une
 * quatrieme est PROGRAMMEE dans le futur : elle ne doit apparaitre nulle
 * part avant sa date, y compris en tapant son adresse.                   */
$actus = [
    ['ouverture-portail', 'g-ouverture', 'fr', 'Le portail RH ouvre',
     'Vos documents, vos conges et vos bulletins au meme endroit.',
     "Le portail RH remplace les echanges par courriel pour les demandes courantes.\n\n- Les demandes de conges partent au manager et laissent une trace.\n- Les bulletins de paie restent disponibles dans **Documents**.\n- Les coordonnees bancaires se modifient soi-meme, et sont chiffrees.\n\nUne question ? Passe par *Demandes RH*.",
     'publie', -6, 1],
    ['hr-portal-opens', 'g-ouverture', 'en', 'The HR portal is live',
     'Your documents, time off and payslips in one place.',
     "The HR portal replaces email for everyday requests.\n\n- Time-off requests go to your manager and leave a trail.\n- Payslips stay available under **Documents**.\n- You update your own bank details, and they are encrypted.\n\nAny question? Use *HR requests*.",
     'publie', -6, 1],
    ['teletravail-cadre', '', 'fr', 'Travail a distance : le cadre',
     'Ce qui change pour les salaries decentralises.',
     "Les salaries decentralises declarent leurs journees dans **Mon temps**, dans leur propre fuseau horaire.\n\n> Une semaine se soumet d'un bloc, le manager la valide.\n\nLe detail figure dans la politique interne.",
     'publie', -2, 0],
    ['assemblee-annuelle', '', 'fr', 'Assemblee annuelle',
     'La date et le programme.',
     "Le programme detaille sera publie ici.",
     'publie', 7, 0],     // date FUTURE : programmee
    ['brouillon-mutuelle', '', 'fr', 'Nouvelle mutuelle (brouillon)',
     'En cours de redaction.',
     "Ce texte n'est pas termine et ne doit pas etre visible.",
     'brouillon', 0, 0],
];
foreach ($actus as [$slug, $groupe, $lg, $titre, $chapeau, $corps, $statut, $decal, $epingle]) {
    insere('actualites', [
        'groupe' => $groupe, 'langue' => $lg, 'slug' => $slug,
        'titre' => $titre, 'chapeau' => $chapeau,
        'corps' => $corps, 'rendu' => rendre_texte($corps),
        'auteur_id' => $ids['E2022-0003'], 'epingle' => $epingle,
        'statut' => $statut,
        'publie_le' => $statut === 'publie'
            ? gmdate('Y-m-d H:i:s', strtotime($decal . ' days')) : null,
        'cree_le' => maintenant(), 'demo' => 1,
    ]);
}
echo 'Actualites : ' . count($actus) . " (dont une programmee et un brouillon)\n";

/* ---------- 9. Formations et avantages ---------------------------------
 * Les COUVERTURES des avantages restent vides : « 80 % des soins
 * dentaires » est une clause de contrat d'assurance, pas une valeur par
 * defaut raisonnable. La page affiche donc « a renseigner ».             */
foreach ([['securite', 'Securite au travail', 'Workplace safety', 1, 2.0],
          ['cyber', 'Cybersecurite', 'Cybersecurity', 1, 1.5],
          ['export', 'Reglementation export', 'Export regulations', 0, 6.0],
          ['leadership', 'Leadership', 'Leadership', 0, 12.0]] as $i => [$c, $fr, $en, $obl, $h]) {
    insere('formations', ['code' => $c, 'titre_fr' => $fr, 'titre_en' => $en,
        'description' => '', 'obligatoire' => $obl, 'duree_h' => $h,
        'validite_mois' => $obl ? 24 : null, 'actif' => 1]);
}
$f_secu = (int) qval("SELECT id FROM formations WHERE code = 'securite'");
$f_cyber = (int) qval("SELECT id FROM formations WHERE code = 'cyber'");
foreach ($ids as $mat => $uid) {
    insere('inscriptions_formation', ['formation_id' => $f_secu, 'utilisateur_id' => $uid,
        'statut' => 'complete', 'date_fin' => $auj->modify('-120 days')->format('Y-m-d'),
        'cree_le' => maintenant(), 'demo' => 1]);
}
insere('inscriptions_formation', ['formation_id' => $f_cyber, 'utilisateur_id' => $ids['E2024-0005'],
    'statut' => 'en_cours', 'date_prevue' => $auj->modify('+14 days')->format('Y-m-d'),
    'cree_le' => maintenant(), 'demo' => 1]);

foreach ([['sante', 'Assurance sante', 'Health insurance', 'ca'],
          ['dentaire', 'Assurance dentaire', 'Dental insurance', 'ca'],
          ['retraite', 'Regime de retraite', 'Pension plan', ''],
          ['transport', 'Aide au transport', 'Transport allowance', 'dz']] as [$c, $fr, $en, $p]) {
    insere('avantages', ['code' => $c, 'titre_fr' => $fr, 'titre_en' => $en,
        'description' => '', 'eligibilite' => '', 'couverture' => '', 'contact' => '',
        'pays' => $p, 'actif' => 1]);
}
echo "Formations : 4 — avantages : 4 (couvertures volontairement vides)\n";

/* ---------- 10. Recrutement -------------------------------------------- */
$ex = insere('examens', [
    'code' => 'export-base', 'titre' => 'Connaissances export — questionnaire court',
    'langue' => 'fr',
    'consigne' => 'Cinq questions. Il n\'y a pas de piege : reponds ce que tu sais. Les deux dernieres sont libres et relues par une personne.',
    'duree_min' => 20, 'seuil' => 3.0, 'actif' => 1,
]);
$questions = [
    ['qcm', 'Un incoterm definit avant tout :', ['La devise de la transaction', 'Le partage des frais et des risques entre vendeur et acheteur', 'Le delai de paiement', 'Le taux de douane'], '1', 1.0],
    ['qcm', 'Un certificat d\'origine sert a :', ['Prouver le pays de fabrication de la marchandise', 'Assurer le transport', 'Fixer le prix de vente', 'Reserver un conteneur'], '0', 1.0],
    ['multiple', 'Parmi ces documents, lesquels accompagnent couramment une expedition ? (plusieurs reponses)', ['Facture commerciale', 'Liste de colisage', 'Contrat de travail', 'Connaissement'], '0,1,3', 2.0],
    ['texte', 'Decris en quelques lignes comment tu verifierais la solvabilite d\'un nouvel acheteur a l\'etranger.', [], '', 0.0],
    ['texte', 'Raconte une negociation que tu as menee et ce que tu ferais differemment.', [], '', 0.0],
];
foreach ($questions as $i => [$type, $enonce, $choix, $bonne, $pts]) {
    insere('questions_examen', [
        'examen_id' => $ex, 'rang' => $i + 1, 'type' => $type, 'enonce' => $enonce,
        'choix' => $choix ? json_encode($choix, JSON_UNESCAPED_UNICODE) : null,
        'bonne' => $bonne, 'points' => $pts,
    ]);
}

$offres = [
    ['Charge(e) d\'affaires export — Alger', 'ventes', 'dz', 'dz', 'cdi', 'hybride',
     "Nous cherchons quelqu'un pour developper le portefeuille d'acheteurs sur la zone.\n\n- Prospection et suivi des acheteurs\n- Montage des dossiers d'expedition\n- Coordination avec les transitaires",
     "- Experience du commerce international\n- Francais et anglais courants\n- A l'aise avec les documents douaniers",
     'publie', $ex, 0],
    ['Developpeur / developpeuse — a distance', 'technique', 'ca', 'ca', 'cdi', 'distanciel',
     "Poste entierement a distance, sur nos outils internes.\n\n- Applications web\n- Integrations avec les systemes des partenaires",
     "- Solide en PHP ou en JavaScript\n- Autonome sur l'organisation de ses journees",
     'publie', null, 0],
    ['Stage — administration des ventes', 'administration', 'ca', 'ca', 'stage', 'sur_site',
     "Stage de six mois au sein de l'equipe administration des ventes.",
     "- En cours de formation en commerce ou en administration",
     'publie', $ex, 1],
    ['Responsable logistique (brouillon)', 'operations', 'dz', 'dz', 'cdi', 'sur_site',
     "Cette offre n'est pas encore ouverte.", "", 'brouillon', null, 0],
];
$offre_ids = [];
foreach ($offres as $i => [$titre, $dc, $lc, $pays, $ctr, $tw, $desc, $profil, $statut, $exid, $oblig]) {
    $offre_ids[] = insere('offres', [
        'reference' => sprintf('OFF-%d-%04d', $annee, $i + 1),
        'slug' => slug_unique('offres', slug($titre)),
        'langue' => 'fr', 'titre' => $titre,
        'departement_id' => $dep($dc), 'localisation_id' => $loc($lc), 'pays' => $pays,
        'type_contrat' => $ctr, 'teletravail' => $tw,
        'description' => $desc, 'rendu_desc' => rendre_texte($desc),
        'profil' => $profil, 'rendu_profil' => rendre_texte($profil),
        // La remuneration reste VIDE : la page affichera « non communiquee »
        // plutot qu'une fourchette que personne n'a validee.
        'salaire_texte' => '',
        'examen_id' => $exid, 'examen_obligatoire' => $oblig,
        'statut' => $statut, 'publie_le' => $statut === 'publie' ? maintenant() : null,
        'cree_par' => $ids['E2022-0003'], 'cree_le' => maintenant(), 'demo' => 1,
    ]);
}

$cands = [
    ['Yasmine', 'Cherif',  'yasmine.cherif@exemple.test', 0, 'recue',     'a_faire'],
    ['Thomas',  'Girard',  'thomas.girard@exemple.test',  1, 'en_examen', 'non_requis'],
    ['Ines',    'Bouzid',  'ines.bouzid@exemple.test',    0, 'entretien', 'termine'],
    ['Paul',    'Lemieux', 'paul.lemieux@exemple.test',   2, 'recue',     'a_faire'],
];
foreach ($cands as $i => [$pre, $nom, $mail, $oi, $statut, $exs]) {
    $cid = insere('candidatures', [
        'numero' => sprintf('CAND-%d-%04d', $annee, $i + 1),
        'offre_id' => $offre_ids[$oi],
        'prenom' => $pre, 'nom' => $nom, 'email' => $mail,
        'telephone' => '', 'pays' => $oi === 1 ? 'ca' : 'dz', 'ville' => '',
        'lien_pro' => '', 'message' => 'Candidature de demonstration.',
        'statut' => $statut, 'examen_statut' => $exs,
        'examen_score' => $exs === 'termine' ? 3.0 : null,
        'examen_sur' => $exs === 'termine' ? 4.0 : null,
        'examen_fini_le' => $exs === 'termine' ? maintenant() : null,
        'jeton_examen' => $exs === 'a_faire' ? bin2hex(random_bytes(24)) : null,
        'consentement_le' => maintenant(),
        'conservation_jusqu' => $auj->modify('+' . duree_conservation_mois() . ' months')->format('Y-m-d'),
        'cree_le' => maintenant(), 'maj_le' => maintenant(), 'demo' => 1,
    ]);
    if ($exs === 'termine') {
        $qs = qtous('SELECT * FROM questions_examen WHERE examen_id = ? ORDER BY rang', [$ex]);
        foreach ($qs as $qq) {
            $rep = $qq['type'] === 'texte'
                ? 'Reponse libre de demonstration.'
                : (string) $qq['bonne'];
            insere('reponses_examen', ['candidature_id' => $cid, 'question_id' => (int) $qq['id'],
                'reponse' => $rep, 'points' => $qq['type'] === 'texte' ? 0.0 : (float) $qq['points'],
                'cree_le' => maintenant()]);
        }
    }
}
echo 'Offres : ' . count($offres) . ' — candidatures : ' . count($cands) . "\n";

/* ---------- 11. Notifications ------------------------------------------ */
notifier($ids['E2021-0002'], 'conge.a_valider', 'Demandes de conges en attente',
         'Trois demandes attendent une decision.', '/equipe/conges');
notifier($ids['E2022-0003'], 'candidature.nouvelle', 'Nouvelles candidatures',
         'Quatre candidatures a examiner.', '/rh/candidatures');
foreach ($ids as $uid) {
    notifier($uid, 'bulletin.publie', 'Nouveau bulletin de paie', '', '/paie');
}

echo "\nJeu de demonstration en place.\n";
echo "Comptes (mot de passe commun : demonstration-2026)\n";
foreach ($gens as $g) {
    $u = employe($ids[$g[0]]);
    printf("  %-28s %-22s %s\n", $u['email'], role_de($u)['cle'],
           (int) $u['decentralise'] === 1 ? 'decentralise' : '');
}
echo "\nMode demo actif : bandeau visible sur toutes les pages.\n";
echo "Pour effacer : php outils/purge-demo.php\n";
