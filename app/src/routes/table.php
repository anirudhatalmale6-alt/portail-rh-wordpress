<?php
/**
 * La table de routage.
 *
 * Un tableau, et une seule boucle qui l'applique. La permission exigee est
 * ECRITE A COTE DE LA ROUTE, pas au debut de chaque controleur : une route
 * ajoutee sans droit se voit d'un coup d'oeil dans ce fichier, alors qu'un
 * `exige()` oublie au milieu d'une fonction de trente lignes ne se voit pas.
 *
 * null = route publique. C'est deliberement rare, et la liste tient sur un
 * ecran : connexion, offres d'emploi, formulaire de candidature, examen.
 *
 * Les motifs n'utilisent que [\w\-] pour les segments variables, et slug()
 * ne produit que de l'ASCII : le generateur d'adresses et le routeur
 * partagent donc le meme alphabet.
 */

declare(strict_types=1);

function routes(): array
{
    return [
        /* ---------- Public ------------------------------------------ */
        ['#^/connexion$#',                  ['GET'],  'page_connexion',        null],
        ['#^/connexion$#',                  ['POST'], 'post_connexion',        null],
        ['#^/deconnexion$#',                ['POST'], 'post_deconnexion',      null],
        ['#^/carrieres$#',                  ['GET'],  'page_carrieres',        null],
        ['#^/carrieres/([\w\-]+)$#',        ['GET'],  'page_offre',            null],
        ['#^/carrieres/([\w\-]+)/postuler$#', ['POST'], 'post_candidature',    null],
        ['#^/candidature/([\w\-]+)$#',      ['GET'],  'page_candidature_recue', null],
        ['#^/examen/([a-f0-9]{40,64})$#',   ['GET'],  'page_examen',           null],
        ['#^/examen/([a-f0-9]{40,64})$#',   ['POST'], 'post_examen',           null],

        /* ---------- Compte ------------------------------------------ */
        ['#^/reauthentification$#',         ['GET'],  'page_reauth',           'profil.voir'],
        ['#^/reauthentification$#',         ['POST'], 'post_reauth',           'profil.voir'],
        ['#^/mot-de-passe$#',               ['GET'],  'page_mot_de_passe',     'profil.voir'],
        ['#^/mot-de-passe$#',               ['POST'], 'post_mot_de_passe',     'profil.voir'],

        /* ---------- Employe ----------------------------------------- */
        ['#^/$#',                           ['GET'],  'page_accueil',          'profil.voir'],
        ['#^/profil$#',                     ['GET'],  'page_profil',           'profil.voir'],
        ['#^/profil$#',                     ['POST'], 'post_profil',           'profil.modifier'],
        ['#^/profil/bancaire$#',            ['GET'],  'page_bancaire',         'profil.bancaire'],
        ['#^/profil/bancaire$#',            ['POST'], 'post_bancaire',         'profil.bancaire'],

        ['#^/conges$#',                     ['GET'],  'page_conges',           'conges.demander'],
        ['#^/conges/demander$#',            ['POST'], 'post_conge',            'conges.demander'],
        ['#^/conges/(\d+)/annuler$#',       ['POST'], 'post_annuler_conge',    'conges.demander'],
        ['#^/absences$#',                   ['GET'],  'page_absences',         'absences.declarer'],
        ['#^/absences/declarer$#',          ['POST'], 'post_absence',          'absences.declarer'],
        ['#^/calendrier$#',                 ['GET'],  'page_calendrier',       'profil.voir'],

        ['#^/documents$#',                  ['GET'],  'page_documents',        'documents.siens'],
        ['#^/document/(\d+)$#',             ['GET'],  'page_document',         'documents.siens'],
        ['#^/paie$#',                       ['GET'],  'page_paie',             'paie.sienne'],
        ['#^/paie/(\d+)$#',                 ['GET'],  'page_bulletin',         'paie.sienne'],

        ['#^/demandes$#',                   ['GET'],  'page_demandes',         'demandes.creer'],
        ['#^/demandes/nouvelle$#',          ['GET'],  'page_demande_nouvelle', 'demandes.creer'],
        ['#^/demandes$#',                   ['POST'], 'post_demande',          'demandes.creer'],
        ['#^/demandes/(\d+)$#',             ['GET'],  'page_demande',          'demandes.creer'],
        ['#^/demandes/(\d+)/message$#',     ['POST'], 'post_message_demande',  'demandes.creer'],
        ['#^/demandes/(\d+)/statut$#',      ['POST'], 'post_statut_demande',   'demandes.traiter'],

        ['#^/notifications$#',              ['GET'],  'page_notifications',    'profil.voir'],
        ['#^/notifications/lues$#',         ['POST'], 'post_notifications_lues', 'profil.voir'],

        ['#^/annuaire$#',                   ['GET'],  'page_annuaire',         'annuaire.voir'],
        ['#^/annuaire/(\d+)$#',             ['GET'],  'page_fiche',            'annuaire.voir'],
        ['#^/organigramme$#',               ['GET'],  'page_organigramme',     'annuaire.voir'],
        ['#^/actualites$#',                 ['GET'],  'page_actualites',       'profil.voir'],
        ['#^/actualites/([\w\-]+)$#',       ['GET'],  'page_actualite',        'profil.voir'],
        ['#^/formations$#',                 ['GET'],  'page_formations',       'profil.voir'],
        ['#^/avantages$#',                  ['GET'],  'page_avantages',        'profil.voir'],
        ['#^/recherche$#',                  ['GET'],  'page_recherche',        'profil.voir'],

        /* ---------- Temps (employe decentralise) -------------------- */
        ['#^/temps$#',                      ['GET'],  'page_temps',            'temps.saisir'],
        ['#^/temps/journee$#',              ['POST'], 'post_journee',          'temps.saisir'],
        ['#^/temps/soumettre$#',            ['POST'], 'post_soumettre_semaine', 'temps.saisir'],

        /* ---------- Manager ----------------------------------------- */
        ['#^/equipe$#',                     ['GET'],  'page_equipe',           'equipe.voir'],
        ['#^/equipe/conges$#',              ['GET'],  'page_equipe_conges',    'conges.equipe.valider'],
        ['#^/equipe/conges/(\d+)$#',        ['POST'], 'post_decision_conge',   'conges.equipe.valider'],
        ['#^/equipe/absences$#',            ['GET'],  'page_equipe_absences',  'absences.equipe.voir'],
        ['#^/equipe/temps$#',               ['GET'],  'page_equipe_temps',     'temps.equipe.valider'],
        ['#^/equipe/temps/(\d+)$#',         ['POST'], 'post_decision_temps',   'temps.equipe.valider'],

        /* ---------- RH ---------------------------------------------- */
        ['#^/rh$#',                         ['GET'],  'page_rh',               'rh.tableau'],
        ['#^/rh/employes$#',                ['GET'],  'page_rh_employes',      'profil.tous.voir'],
        ['#^/rh/employes/nouveau$#',        ['GET'],  'page_rh_employe_nouveau', 'admin.utilisateurs'],
        ['#^/rh/employes$#',                ['POST'], 'post_rh_employe',       'admin.utilisateurs'],
        ['#^/rh/employes/(\d+)$#',          ['GET'],  'page_rh_employe',       'profil.tous.voir'],
        ['#^/rh/employes/(\d+)$#',          ['POST'], 'post_rh_employe_maj',   'profil.tous.modifier'],
        ['#^/rh/conges$#',                  ['GET'],  'page_rh_conges',        'conges.administrer'],
        ['#^/rh/absences$#',                ['GET'],  'page_rh_absences',      'absences.administrer'],
        ['#^/rh/absences/(\d+)$#',          ['POST'], 'post_decision_absence', 'absences.administrer'],
        ['#^/rh/demandes$#',                ['GET'],  'page_rh_demandes',      'demandes.traiter'],
        ['#^/rh/documents$#',               ['GET'],  'page_rh_documents',     'documents.gerer'],
        ['#^/rh/documents$#',               ['POST'], 'post_rh_document',      'documents.gerer'],
        ['#^/rh/paie$#',                    ['GET'],  'page_rh_paie',          'paie.gerer'],
        ['#^/rh/paie/periodes$#',           ['POST'], 'post_rh_periodes',      'paie.gerer'],
        ['#^/rh/paie/bulletin$#',           ['POST'], 'post_rh_bulletin',      'paie.gerer'],
        ['#^/rh/paie/bulletin/(\d+)/publier$#', ['POST'], 'post_rh_publier',   'paie.gerer'],
        ['#^/rh/primes$#',                  ['POST'], 'post_rh_prime',         'paie.gerer'],
        ['#^/rh/temps$#',                   ['GET'],  'page_rh_temps',         'temps.administrer'],
        ['#^/rh/actualites$#',              ['GET'],  'page_rh_actualites',    'actualites.publier'],
        ['#^/rh/actualites$#',              ['POST'], 'post_rh_actualite',     'actualites.publier'],

        /* ---------- Recrutement (cote RH) --------------------------- */
        ['#^/rh/offres$#',                  ['GET'],  'page_rh_offres',        'recrutement.gerer'],
        ['#^/rh/offres$#',                  ['POST'], 'post_rh_offre',         'recrutement.gerer'],
        ['#^/rh/candidatures$#',            ['GET'],  'page_rh_candidatures',  'recrutement.gerer'],
        ['#^/rh/candidatures/(\d+)$#',      ['GET'],  'page_rh_candidature',   'recrutement.gerer'],
        ['#^/rh/candidatures/(\d+)$#',      ['POST'], 'post_rh_candidature',   'recrutement.gerer'],

        /* ---------- Administration ---------------------------------- */
        ['#^/admin/roles$#',                ['GET'],  'page_admin_roles',      'admin.roles'],
        ['#^/admin/parametres$#',           ['GET'],  'page_admin_parametres', 'admin.parametres'],
        ['#^/admin/parametres$#',           ['POST'], 'post_admin_parametres', 'admin.parametres'],
        ['#^/admin/journal$#',              ['GET'],  'page_admin_journal',    'admin.journal'],
        ['#^/admin/a-renseigner$#',         ['GET'],  'page_a_renseigner',     'profil.voir'],

        /* ---------- API --------------------------------------------- */
        ['#^/api/v1/moi$#',                 ['GET'],  'api_moi',               'profil.voir'],
        ['#^/api/v1/annuaire$#',            ['GET'],  'api_annuaire',          'annuaire.voir'],
        ['#^/api/v1/notifications$#',       ['GET'],  'api_notifications',     'profil.voir'],
        ['#^/api/v1/offres$#',              ['GET'],  'api_offres',            null],
    ];
}
