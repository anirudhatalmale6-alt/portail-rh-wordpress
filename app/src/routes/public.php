<?php
/**
 * Routes publiques : connexion, reauthentification, carrieres, examen.
 * Ce sont les seules portes ouvertes du portail.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ */
/* Connexion                                                           */
/* ------------------------------------------------------------------ */

function page_connexion(): void
{
    if (connecte()) redirige('/');
    rendre('connexion', ['retour' => (string) ($_GET['retour'] ?? '/'), 'erreur' => null]);
}

function post_connexion(): void
{
    $email = (string) ($_POST['email'] ?? '');
    $mdp   = (string) ($_POST['mot_de_passe'] ?? '');
    $retour = (string) ($_POST['retour'] ?? '/');

    // DEUX COMPTEURS, ET ILS NE COMPTENT QUE LES ECHECS.
    //
    // Par adresse visee : cinq essais rates, c'est la protection du compte.
    // Par IP : un budget beaucoup plus large, pour ralentir quelqu'un qui
    // essaie un mot de passe sur toute l'entreprise.
    //
    // Compter aussi les REUSSITES sur l'IP bloquerait tout un bureau
    // derriere une seule sortie des la cinquieme personne qui arrive le
    // matin. La premiere version faisait exactement cela, et c'est la suite
    // de tests qui l'a montre : elle n'arrivait plus a connecter le
    // cinquieme compte de demonstration.
    $cle_email = 'e:' . mb_strtolower($email);
    if (limite_atteinte('connexion', $cle_email) || limite_atteinte('connexion_ip', ip_client())) {
        journal('alerte', 'connexion limitee', ['ip' => ip_client()]);
        rendre('connexion', ['retour' => $retour, 'erreur' => t('cnx_trop_essais')]);
        return;
    }

    $u = verifier_identifiants($email, $mdp);
    if (!$u) {
        limite_incremente('connexion', $cle_email);
        limite_incremente('connexion_ip', ip_client());
        // Un seul message pour « compte inconnu » et « mot de passe faux ».
        // Deux messages distincts sont un annuaire des salaries offert a
        // qui teste des adresses.
        audit('connexion.echec', 'utilisateur', null, [], ['email' => mb_substr($email, 0, 190)]);
        rendre('connexion', ['retour' => $retour, 'erreur' => t('cnx_echec')]);
        return;
    }

    // Une connexion reussie remet le compteur du compte a zero : le
    // proprietaire legitime ne doit pas garder un demi-budget d'essais
    // parce qu'il s'est trompe deux fois hier.
    limite_efface('connexion', $cle_email);
    ouvrir_session((int) $u['id']);
    audit('connexion.reussie', 'utilisateur', (int) $u['id']);

    // Un mot de passe provisoire doit etre change avant tout le reste.
    if ((int) ($u['doit_changer'] ?? 0) === 1) redirige('/mot-de-passe');

    // On ne suit une destination que si elle est INTERNE. Sans ce filtre,
    // ?retour=https://ailleurs.example transforme la page de connexion en
    // tremplin de redirection, et le lien porte le nom de l'employeur.
    $dest = str_starts_with($retour, '/') && !str_starts_with($retour, '//') ? $retour : '/';
    redirige($dest);
}

function post_deconnexion(): void
{
    $u = utilisateur();
    if ($u) audit('deconnexion', 'utilisateur', (int) $u['id']);
    fermer_session();
    $_SESSION = [];
    redirige('/connexion');
}

/* ------------------------------------------------------------------ */
/* Reauthentification et mot de passe                                  */
/* ------------------------------------------------------------------ */

function page_reauth(): void
{
    rendre('reauth', ['retour' => (string) ($_GET['retour'] ?? '/profil'), 'erreur' => null]);
}

function post_reauth(): void
{
    $retour = (string) ($_POST['retour'] ?? '/profil');
    $u = utilisateur();
    // Meme regle que pour la connexion : on ne compte que les echecs.
    // Sinon quelqu'un qui modifie ses coordonnees bancaires puis, une heure
    // plus tard, consulte a nouveau la page, se retrouve bloque par ses
    // propres reussites.
    $cle = 'u' . $u['id'];
    if (limite_atteinte('reauth', $cle)) {
        rendre('reauth', ['retour' => $retour, 'erreur' => t('cnx_trop_essais')]);
        return;
    }
    if (!password_verify((string) ($_POST['mot_de_passe'] ?? ''), (string) $u['mot_de_passe'])) {
        limite_incremente('reauth', $cle);
        audit('reauth.echec', 'utilisateur', (int) $u['id']);
        rendre('reauth', ['retour' => $retour, 'erreur' => t('cnx_echec')]);
        return;
    }
    limite_efface('reauth', $cle);
    reauth_marque();
    audit('reauth.reussie', 'utilisateur', (int) $u['id']);
    $dest = str_starts_with($retour, '/') && !str_starts_with($retour, '//') ? $retour : '/profil';
    redirige($dest);
}

function page_mot_de_passe(): void
{
    rendre('mot_de_passe', ['erreurs' => []]);
}

function post_mot_de_passe(): void
{
    $u = utilisateur();
    $actuel = (string) ($_POST['actuel'] ?? '');
    $nouveau = (string) ($_POST['nouveau'] ?? '');
    $confirme = (string) ($_POST['confirme'] ?? '');
    $err = [];

    // Meme avec un mot de passe provisoire, on demande l'ancien : c'est lui
    // qui prouve que la personne devant l'ecran est bien celle a qui la RH
    // l'a remis.
    if (!password_verify($actuel, (string) $u['mot_de_passe'])) $err['actuel'] = t('mdp_err_actuel');
    if (!mdp_acceptable($nouveau)) $err['nouveau'] = t('mdp_err_court');
    if ($nouveau !== $confirme) $err['confirme'] = t('mdp_err_confirme');
    if ($nouveau !== '' && $nouveau === $actuel) $err['nouveau'] = t('mdp_err_identique');

    if ($err) { rendre('mot_de_passe', ['erreurs' => $err]); return; }

    maj('utilisateurs', (int) $u['id'], [
        'mot_de_passe' => hache_mdp($nouveau), 'doit_changer' => 0, 'maj_le' => maintenant(),
    ]);
    // Toutes les AUTRES sessions tombent. Si le mot de passe a ete change
    // parce qu'on le soupconnait connu, laisser les autres sessions ouvertes
    // annule tout le benefice du changement.
    $s = $GLOBALS['rh_session'] ?? null;
    q('DELETE FROM sessions WHERE utilisateur_id = ? AND id <> ?',
      [(int) $u['id'], (int) ($s['id'] ?? 0)]);
    audit('mot_de_passe.change', 'utilisateur', (int) $u['id'], ['mot_de_passe' => ['', '']]);
    flash('ok', t('mdp_change'));
    redirige('/');
}

/* ------------------------------------------------------------------ */
/* Carrieres : la partie publique du portail de recrutement            */
/* ------------------------------------------------------------------ */

function page_carrieres(): void
{
    $filtres = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'pays' => (string) ($_GET['pays'] ?? ''),
    ];
    rendre('carrieres', [
        'offres' => offres_publiees($filtres),
        'filtres' => $filtres,
        'departements' => qtous('SELECT * FROM departements ORDER BY rang, nom_fr'),
    ]);
}

function page_offre(string $slug): void
{
    $o = offre_par_slug($slug);
    if (!$o) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('rec_offre_absente')]); return; }
    rendre('offre', ['offre' => $o, 'erreurs' => [], 'saisie' => []]);
}

function post_candidature(string $slug): void
{
    $o = offre_par_slug($slug);
    if (!$o) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('rec_offre_absente')]); return; }

    $r = deposer_candidature((int) $o['id'], $_POST, [
        'cv' => $_FILES['cv'] ?? null, 'lettre' => $_FILES['lettre'] ?? null,
    ]);
    if (isset($r['erreurs'])) {
        rendre('offre', ['offre' => $o, 'erreurs' => $r['erreurs'], 'saisie' => $_POST]);
        return;
    }
    redirige('/candidature/' . $r['numero']
           . ($r['jeton_examen'] ? '?examen=' . $r['jeton_examen'] : ''));
}

function page_candidature_recue(string $numero): void
{
    // On n'affiche PAS la candidature : le numero seul ne prouve rien, et
    // une page qui rendrait le dossier a qui connait le numero rendrait le
    // dossier a qui essaie des numeros. On confirme la reception, c'est tout.
    $jeton = (string) ($_GET['examen'] ?? '');
    $cand = $jeton !== '' ? candidature_par_jeton($jeton) : null;
    rendre('candidature_recue', [
        'numero' => preg_replace('/[^A-Z0-9\-]/', '', strtoupper($numero)),
        'jeton' => $cand ? $jeton : '',
        'obligatoire' => $cand ? (int) $cand['examen_obligatoire'] === 1 : false,
    ]);
}

/* ------------------------------------------------------------------ */
/* Examen facultatif                                                   */
/* ------------------------------------------------------------------ */

function page_examen(string $jeton): void
{
    $cand = candidature_par_jeton($jeton);
    if (!$cand) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('rec_examen_lien_invalide')]); return; }
    $ex = examen((int) $cand['examen_id']);
    if (!$ex) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('rec_err_pas_examen')]); return; }
    rendre('examen', [
        'candidature' => $cand, 'examen' => $ex,
        'questions' => questions_publiques((int) $ex['id']),
        'jeton' => $jeton, 'erreur' => null,
    ]);
}

function post_examen(string $jeton): void
{
    $cand = candidature_par_jeton($jeton);
    if (!$cand) { http_response_code(404); rendre('erreur', ['code' => 404, 'message' => t('rec_examen_lien_invalide')]); return; }

    $reponses = [];
    foreach (($_POST['q'] ?? []) as $qid => $v) $reponses[(int) $qid] = $v;

    $r = corriger_examen($cand, $reponses);
    if (isset($r['erreur'])) {
        $ex = examen((int) $cand['examen_id']);
        rendre('examen', ['candidature' => $cand, 'examen' => $ex,
                          'questions' => $ex ? questions_publiques((int) $ex['id']) : [],
                          'jeton' => $jeton, 'erreur' => $r['erreur']]);
        return;
    }
    // Le score n'est PAS montre au candidat. Il n'a pas la grille, une
    // partie est relue a la main, et un chiffre affiche devient une
    // promesse. On confirme l'enregistrement.
    rendre('examen_fini', ['a_relire' => (int) $r['a_relire']]);
}
