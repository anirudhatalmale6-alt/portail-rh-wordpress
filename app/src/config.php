<?php
/**
 * PORTAIL RH — configuration.
 *
 * ------------------------------------------------------------------------
 * CE QUI EST VIDE ICI EST VIDE EXPRES.
 *
 * Le nom legal de l'employeur, son adresse, son numero d'entreprise, le
 * responsable de la protection des donnees, l'expediteur des e-mails : je
 * ne les connais pas et je ne les invente pas. Chaque valeur laissee vide
 * s'affiche dans l'interface comme une pastille « a renseigner », visible,
 * qui se compte sur la page /admin/a-renseigner.
 *
 * Ce n'est pas de la coquetterie sur un portail RH : une attestation
 * d'emploi qui porte une raison sociale inventee est un faux document, et
 * c'est le salarie qui la presentera a sa banque.
 * ------------------------------------------------------------------------
 */

return [

    // --- Identite de l'employeur (a renseigner) --------------------------
    'nom_org'            => 'ExportRev',
    'nom_provisoire'     => false,
    'entite_juridique'   => '',      // raison sociale complete + forme
    'numero_entreprise'  => '',      // NEQ / RC / NIF selon le pays
    'adresse_postale'    => '',
    'contact_rh'         => '',      // canal interne, pas une adresse mail perso
    'responsable_donnees' => '',     // qui repond d'une demande d'acces au dossier
    'domaine'            => '',      // https://exemple.com, sans / final

    // --- Base de donnees -------------------------------------------------
    // 'sqlite' : zero configuration, sert la demonstration.
    // 'mysql'  : la production chez Hostinger. Renseigne les 4 champs.
    // Prefixe d'URL. Vide quand l'application est seule sur son domaine.
    // L'extension WordPress y met '/rh'.
    'base_uri' => '',

    'bd' => [
        'pilote' => 'sqlite',
        'sqlite' => __DIR__ . '/../donnees/rh.sqlite',
        'hote'   => 'localhost',
        'base'   => '',
        'user'   => '',
        'passe'  => '',
        'port'   => 3306,
    ],

    // --- Chemins ---------------------------------------------------------
    // Les documents RH (contrats, bulletins, justificatifs, CV) sont HORS
    // de la racine web et servis par un script qui verifie le droit d'acces
    // AVANT d'ouvrir le fichier. Un repertoire d'upload accessible en direct
    // rend un bulletin de paie lisible par quiconque devine son adresse.
    'chemin_documents' => __DIR__ . '/../donnees/documents',
    'chemin_journal'   => __DIR__ . '/../donnees/journal',

    // --- Langues ---------------------------------------------------------
    // fr et en sont livrees completes. ar, es, de sont prevues par
    // l'architecture (section 31) : ajouter un fichier dans src/lang/ et la
    // langue apparait. Aucune traduction automatique.
    'langues'        => ['fr', 'en'],
    'langue_defaut'  => 'fr',

    // --- Pays d'exploitation (section « pays algerie canada ») -----------
    // La frequence de paie et la devise ne sont PAS globales : elles sont
    // portees par le contrat de chaque salarie. Ces valeurs ne sont que le
    // defaut propose quand la RH cree une fiche.
    'pays' => [
        'ca' => ['nom_fr' => 'Canada',  'nom_en' => 'Canada',
                 'devise' => 'CAD', 'frequence_paie' => 'bimensuelle', 'fuseau' => 'America/Toronto'],
        'dz' => ['nom_fr' => 'Algerie', 'nom_en' => 'Algeria',
                 'devise' => 'DZD', 'frequence_paie' => 'mensuelle',   'fuseau' => 'Africa/Algiers'],
    ],
    'pays_defaut' => 'ca',

    // --- Sessions et securite -------------------------------------------
    // Laisse 'sel_session' et 'cle_coffre' vides : l'installeur les tire au
    // hasard et les ecrit dans config.local.php, qui n'est pas dans le
    // depot. Un secret ecrit en dur dans une archive publique n'est pas un
    // secret.
    'sel_session'    => '',
    'cle_coffre'     => '',          // chiffrement des donnees bancaires
    'duree_session'  => 60 * 60 * 8, // 8 h : c'est un outil de travail
    'duree_reauth'   => 300,         // 5 min de validite d'une reauthentification
    'cookie_secure'  => false,       // true derriere HTTPS (donc en production)

    // --- Envoi d'e-mails -------------------------------------------------
    // Vide = AUCUN e-mail n'est envoye. Les notifications restent dans le
    // centre in-app et l'interface le dit, plutot que de faire croire a la
    // RH qu'un salarie a ete prevenu.
    'mail_expediteur' => '',
    'mail_nom'        => '',

    // --- Paie ------------------------------------------------------------
    // LE PORTAIL AFFICHE LA PAIE, IL NE LA CALCULE PAS.
    //
    // Les retenues sociales et fiscales sont reglementees, differentes en
    // Algerie et au Canada, et elles changent. Une formule ecrite ici serait
    // fausse un jour sans prevenir, et elle serait fausse sur le bulletin
    // d'un salarie. La RH saisit ou importe le bulletin ; le portail affiche
    // l'historique, les primes, la prochaine paie et le cumul.
    // 'calcul_paie' n'existe pas et n'existera pas dans ce fichier.
    'paie_source' => 'saisie',   // saisie | import | api (a brancher)

    // --- Limites ---------------------------------------------------------
    'lignes_par_page'   => 25,
    'taille_max_doc'    => 10 * 1024 * 1024,   // 10 Mo
    'types_doc'         => [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    // Ces compteurs ne comptent QUE les echecs (voir post_connexion).
    // 'connexion'    : par adresse visee — protege un compte.
    // 'connexion_ip' : par adresse IP — ralentit un balayage, sans bloquer
    //                  un bureau entier derriere une seule sortie.
    'limites' => [
        'connexion'    => ['nb' => 5,  'fenetre' => 900],
        'connexion_ip' => ['nb' => 50, 'fenetre' => 900],
        'reauth'       => ['nb' => 5,  'fenetre' => 900],
        'candidature'  => ['nb' => 5,  'fenetre' => 3600],
        'demande'      => ['nb' => 20, 'fenetre' => 3600],
        'televersement' => ['nb' => 30, 'fenetre' => 3600],
    ],

    // --- Demonstration ---------------------------------------------------
    // true  : bandeau « donnees de demonstration » sur toutes les pages.
    // Passe a false APRES avoir lance outils/purge-demo.php.
    'mode_demo' => true,
];
