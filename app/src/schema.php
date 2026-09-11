<?php
/**
 * Le schema, decrit UNE FOIS, emis en SQLite ou en MySQL.
 *
 * Pourquoi pas deux fichiers .sql : parce que deux fichiers derivent. On
 * corrige une colonne dans l'un, on oublie l'autre, et la difference ne se
 * voit qu'en production sur l'autre moteur. Ici il n'y a qu'une source.
 *
 * Le modele couvre les sections 2 a 33 du cahier des charges. Les tables
 * de la phase 2 (evaluations, avantages, integration) sont CREEES des
 * maintenant et vides : le schema ne bougera pas quand on les remplira,
 * donc aucune migration destructive plus tard.
 *
 * DEUX DECISIONS DE MODELE QUI MERITENT D'ETRE LUES.
 *
 * 1. Le numero de compte bancaire n'a pas de colonne en clair. Il vit dans
 *    `banque_chiffre` (AES-256-GCM, cle hors depot) et dans
 *    `banque_masque`, qui ne contient que les 4 derniers caracteres. Aucune
 *    requete de l'application ne peut donc « rendre par accident » le
 *    numero complet dans une liste, un export ou un journal.
 *
 * 2. Le journal d'audit a des colonnes `ancienne` et `nouvelle`, et elles
 *    restent NULL pour les champs sensibles. Un journal qui garde
 *    « ancienne valeur : compte 00123456 » est une deuxieme copie du
 *    numero, dans une table que l'administrateur systeme peut lire alors
 *    qu'il n'a aucune raison de connaitre le compte de qui que ce soit.
 */

/* Types portables :
 *   pk      identifiant auto-incremente
 *   int     entier          bool    0/1
 *   str:N   VARCHAR(N)      text    texte long
 *   ts      date + heure    float   nombre a virgule
 *   blob    binaire
 * Les VARCHAR indexes en UNIQUE restent <= 190 : au-dela, MySQL en utf8mb4
 * depasse la longueur de cle maximale.
 */
function schema_tables(): array
{
    return [

        /* ============ Identite, roles, permissions ==================== */

        'roles' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:60', 'rang' => 'int',
                       'nom_fr' => 'str:120', 'nom_en' => 'str:120'],
            'uniques' => [['cle']],
        ],
        'permissions' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:80', 'description' => 'str:255'],
            'uniques' => [['cle']],
        ],
        'role_permissions' => [
            'cols' => ['id' => 'pk', 'role_id' => 'int', 'permission_id' => 'int'],
            'uniques' => [['role_id', 'permission_id']],
        ],

        'utilisateurs' => [
            'cols' => [
                'id' => 'pk',
                'matricule'    => 'str:40',    // numero d'employe, unique
                'email'        => 'str:190',   // e-mail professionnel = identifiant
                'mot_de_passe' => 'str:255',
                'doit_changer' => 'bool',      // mot de passe provisoire
                'role_id'      => 'int',
                'actif'        => 'bool',

                // --- section 4.1 : informations personnelles ------------
                'prenom' => 'str:120', 'nom' => 'str:120',
                'date_naissance' => 'str:10',
                'adresse' => 'str:255', 'telephone' => 'str:60',
                'email_perso' => 'str:190',
                'urgence_nom' => 'str:190', 'urgence_lien' => 'str:80',
                'urgence_tel' => 'str:60',
                'photo_id' => 'int',

                // --- section 4.2 : informations professionnelles -------
                'poste' => 'str:190',
                'departement_id' => 'int',
                'manager_id' => 'int',
                'localisation_id' => 'int',
                'date_embauche' => 'str:10',
                'type_contrat' => 'str:40',    // cdi | cdd | stage | prestation
                'statut' => 'str:20',          // actif | suspendu | parti
                'temps_travail' => 'str:20',   // plein | partiel
                'taux_partiel' => 'int',       // % si partiel, sinon NULL
                'date_depart' => 'str:10',

                // --- section 15/16 : annuaire et organigramme -----------
                // Chaque champ visible dans l'annuaire a son interrupteur.
                // Par defaut le telephone personnel n'y est PAS.
                'annuaire_visible' => 'bool',
                'annuaire_tel' => 'bool',

                // --- 15/16/17 : travail decentralise --------------------
                // Un salarie decentralise gere son temps lui-meme : il n'a
                // pas d'horaire impose, il declare ses journees. Son fuseau
                // est le SIEN, pas celui du siege — sinon sa journee du
                // lundi tombe le dimanche dans la base.
                'decentralise' => 'bool',
                'fuseau' => 'str:64',
                'pays_travail' => 'str:8',
                'langue' => 'str:5',

                // --- section 4.3 : informations bancaires ---------------
                // Le numero complet n'existe qu'ici, chiffre.
                'banque_institution' => 'str:190',
                'banque_chiffre' => 'text',    // base64(nonce|tag|chiffre)
                'banque_masque' => 'str:40',   // « •••• 4321 »
                'banque_maj_le' => 'ts',
                'mode_paiement' => 'str:40',   // virement | cheque | autre

                // --- paie : porte par le CONTRAT, pas par le pays -------
                'frequence_paie' => 'str:20',  // bimensuelle | mensuelle
                'devise' => 'str:8',

                'cree_le' => 'ts', 'maj_le' => 'ts', 'vu_le' => 'ts',
                'demo' => 'bool',
            ],
            'uniques' => [['matricule'], ['email']],
            'index'   => [['role_id'], ['manager_id'], ['departement_id'],
                          ['statut'], ['decentralise']],
        ],

        'sessions' => [
            'cols' => ['id' => 'pk', 'jeton' => 'str:190', 'utilisateur_id' => 'int',
                       'cree_le' => 'ts', 'expire_le' => 'ts',
                       'reauth_le' => 'ts',      // derniere reauthentification
                       'ip' => 'str:64', 'agent' => 'str:255'],
            'uniques' => [['jeton']],
            'index'   => [['utilisateur_id']],
        ],

        /* ============ Structure de l'organisation ===================== */

        'departements' => [
            'cols' => ['id' => 'pk', 'code' => 'str:40', 'nom_fr' => 'str:150',
                       'nom_en' => 'str:150', 'parent_id' => 'int', 'rang' => 'int'],
            'uniques' => [['code']],
        ],
        'localisations' => [
            'cols' => ['id' => 'pk', 'code' => 'str:40', 'nom' => 'str:150',
                       'ville' => 'str:120', 'pays' => 'str:8', 'fuseau' => 'str:64',
                       'adresse' => 'str:255'],
            'uniques' => [['code']],
        ],
        'postes' => [
            'cols' => ['id' => 'pk', 'code' => 'str:60', 'nom_fr' => 'str:190',
                       'nom_en' => 'str:190', 'departement_id' => 'int'],
            'uniques' => [['code']],
        ],
        'jours_feries' => [
            // Aucune date n'est pre-remplie. Un jour ferie est un FAIT
            // juridique, different par pays et par annee : la RH les saisit
            // ou les importe, le portail ne les devine pas.
            'cols' => ['id' => 'pk', 'pays' => 'str:8', 'date' => 'str:10',
                       'nom_fr' => 'str:150', 'nom_en' => 'str:150', 'chome' => 'bool'],
            'uniques' => [['pays', 'date']],
        ],

        /* ============ Conges (section 5) ============================== */

        'types_conges' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:60', 'nom_fr' => 'str:150',
                       'nom_en' => 'str:150', 'paye' => 'bool',
                       'justificatif' => 'bool', 'solde_defaut' => 'float',
                       'pays' => 'str:8',      // NULL = tous pays
                       'actif' => 'bool', 'rang' => 'int'],
            'uniques' => [['cle']],
        ],
        'soldes_conges' => [
            // acquis / pris / ajuste : trois nombres saisis ou calcules a
            // partir des demandes approuvees. Le solde affiche est une
            // soustraction, jamais une valeur ecrite a la main quelque part.
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'type_id' => 'int',
                       'annee' => 'int', 'acquis' => 'float', 'ajuste' => 'float',
                       'note' => 'str:255', 'maj_le' => 'ts'],
            'uniques' => [['utilisateur_id', 'type_id', 'annee']],
        ],
        'demandes_conges' => [
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int', 'type_id' => 'int',
                'debut' => 'str:10', 'fin' => 'str:10',
                'demi_debut' => 'bool', 'demi_fin' => 'bool',
                'nb_jours' => 'float',
                'motif' => 'text',
                'statut' => 'str:30',      // en_attente | approuve | refuse
                                           // | modification | annule
                'decideur_id' => 'int', 'decide_le' => 'ts',
                'commentaire' => 'text',
                'cree_le' => 'ts', 'maj_le' => 'ts', 'demo' => 'bool',
            ],
            'index' => [['utilisateur_id'], ['statut'], ['debut'], ['type_id']],
        ],

        /* ============ Absences (section 6) ============================ */

        'absences' => [
            // Le justificatif est un document a part, et il n'est PAS
            // visible du manager : un arret de travail dit pourquoi
            // quelqu'un est malade. Le manager voit les dates et le fait
            // qu'un justificatif a ete depose ; la RH voit le document.
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int',
                'type' => 'str:40',        // maladie | personnelle | parentale
                                           // | sans_solde | accident | autre
                'debut' => 'str:10', 'fin' => 'str:10', 'nb_jours' => 'float',
                'note' => 'text',
                'justificatif_id' => 'int',
                'statut' => 'str:30',      // declaree | validee | refusee
                'valide_par' => 'int', 'valide_le' => 'ts',
                'commentaire_rh' => 'text',
                'cree_le' => 'ts', 'demo' => 'bool',
            ],
            'index' => [['utilisateur_id'], ['statut'], ['debut']],
        ],

        /* ============ Documents (section 7) =========================== */

        'fichiers' => [
            // Le fichier n'est jamais dans la racine web. `chemin` est
            // relatif au repertoire hors racine, et il est tire au hasard :
            // le nom d'origine ne se retrouve pas dans l'URL.
            'cols' => ['id' => 'pk', 'chemin' => 'str:190', 'nom_origine' => 'str:255',
                       'mime' => 'str:120', 'taille' => 'int', 'sha256' => 'str:64',
                       'depose_par' => 'int', 'cree_le' => 'ts'],
            'uniques' => [['chemin']],
        ],
        'documents' => [
            // utilisateur_id NULL = document d'entreprise (politique,
            // convention) visible de tous.
            'cols' => [
                'id' => 'pk', 'utilisateur_id' => 'int', 'fichier_id' => 'int',
                'categorie' => 'str:60',   // contrat | avenant | bulletin | fiscal
                                           // | attestation | certificat | politique
                                           // | assurance | administratif | justificatif
                'titre' => 'str:255', 'description' => 'text',
                'confidentiel' => 'bool',  // 1 = RH seulement, pas le manager
                'periode' => 'str:20',
                'ajoute_par' => 'int', 'cree_le' => 'ts', 'demo' => 'bool',
            ],
            'index' => [['utilisateur_id'], ['categorie'], ['cree_le']],
        ],

        /* ============ Paie (section 8) ================================ */

        'periodes_paie' => [
            'cols' => ['id' => 'pk', 'code' => 'str:60', 'pays' => 'str:8',
                       'frequence' => 'str:20',
                       'debut' => 'str:10', 'fin' => 'str:10', 'paiement_le' => 'str:10',
                       'statut' => 'str:20',    // prevue | fermee
                       'demo' => 'bool'],
            'uniques' => [['code']],
            'index'   => [['paiement_le']],
        ],
        'bulletins' => [
            // brut / net sont SAISIS ou IMPORTES. Aucune colonne calculee.
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'periode_id' => 'int',
                       'devise' => 'str:8', 'brut' => 'float', 'net' => 'float',
                       'document_id' => 'int', 'note' => 'text',
                       'publie_le' => 'ts', 'cree_le' => 'ts', 'demo' => 'bool'],
            'uniques' => [['utilisateur_id', 'periode_id']],
            'index'   => [['utilisateur_id']],
        ],
        'lignes_bulletin' => [
            'cols' => ['id' => 'pk', 'bulletin_id' => 'int', 'rang' => 'int',
                       'type' => 'str:30',     // salaire | prime | deduction | retenue
                       'libelle' => 'str:190', 'montant' => 'float'],
            'index' => [['bulletin_id']],
        ],
        'primes' => [
            // Une prime accordee mais pas encore versee. Quand elle l'est,
            // elle devient une ligne du bulletin et pointe dessus.
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'libelle' => 'str:190',
                       'montant' => 'float', 'devise' => 'str:8', 'motif' => 'text',
                       'accordee_le' => 'str:10', 'versee_bulletin_id' => 'int',
                       'accordee_par' => 'int', 'cree_le' => 'ts', 'demo' => 'bool'],
            'index' => [['utilisateur_id']],
        ],

        /* ============ Demandes RH (section 9) ========================= */

        'demandes_rh' => [
            'cols' => [
                'id' => 'pk', 'numero' => 'str:40', 'utilisateur_id' => 'int',
                'categorie' => 'str:60',
                'objet' => 'str:255', 'corps' => 'text',
                'statut' => 'str:30',   // nouveau | en_traitement | en_attente
                                        // | resolu | ferme
                'responsable_id' => 'int',
                'cree_le' => 'ts', 'maj_le' => 'ts', 'ferme_le' => 'ts',
                'demo' => 'bool',
            ],
            'uniques' => [['numero']],
            'index'   => [['utilisateur_id'], ['statut'], ['responsable_id']],
        ],
        'messages_demande' => [
            'cols' => ['id' => 'pk', 'demande_id' => 'int', 'auteur_id' => 'int',
                       'corps' => 'text', 'rendu' => 'text', 'fichier_id' => 'int',
                       'interne' => 'bool',   // note RH, invisible du salarie
                       'cree_le' => 'ts'],
            'index' => [['demande_id']],
        ],

        /* ============ Notifications (section 10) ====================== */

        'notifications' => [
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'type' => 'str:60',
                       'titre' => 'str:255', 'corps' => 'text', 'lien' => 'str:255',
                       'lu_le' => 'ts', 'cree_le' => 'ts'],
            'index' => [['utilisateur_id', 'lu_le']],
        ],
        'preferences_notif' => [
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'type' => 'str:60',
                       'canal' => 'str:20', 'actif' => 'bool'],
            'uniques' => [['utilisateur_id', 'type', 'canal']],
        ],

        /* ============ Communications RH (section 11) ================== */

        'actualites' => [
            // Une actualite est ecrite dans UNE langue. `groupe` relie les
            // versions d'un meme sujet, `langue` dit laquelle. Pas de
            // traduction automatique : un texte RH mal traduit engage
            // l'employeur.
            'cols' => ['id' => 'pk', 'groupe' => 'str:40', 'langue' => 'str:5',
                       'slug' => 'str:190', 'titre' => 'str:255', 'chapeau' => 'text',
                       'corps' => 'text', 'rendu' => 'text',
                       'auteur_id' => 'int', 'fichier_id' => 'int',
                       'epingle' => 'bool', 'statut' => 'str:20',
                       'publie_le' => 'ts', 'cree_le' => 'ts', 'demo' => 'bool'],
            'uniques' => [['slug']],
            'index'   => [['statut', 'publie_le'], ['groupe']],
        ],

        /* ============ Formations (section 12) ========================= */

        'formations' => [
            'cols' => ['id' => 'pk', 'code' => 'str:60', 'titre_fr' => 'str:255',
                       'titre_en' => 'str:255', 'description' => 'text',
                       'obligatoire' => 'bool', 'duree_h' => 'float',
                       'validite_mois' => 'int', 'actif' => 'bool'],
            'uniques' => [['code']],
        ],
        'inscriptions_formation' => [
            'cols' => ['id' => 'pk', 'formation_id' => 'int', 'utilisateur_id' => 'int',
                       'statut' => 'str:30',   // a_venir | en_cours | complete | expiree
                       'date_prevue' => 'str:10', 'date_fin' => 'str:10',
                       'certificat_id' => 'int', 'cree_le' => 'ts', 'demo' => 'bool'],
            'uniques' => [['formation_id', 'utilisateur_id']],
            'index'   => [['utilisateur_id'], ['statut']],
        ],

        /* ============ Evaluations (section 13, phase 2) =============== */

        'cycles_evaluation' => [
            'cols' => ['id' => 'pk', 'nom' => 'str:190', 'debut' => 'str:10',
                       'fin' => 'str:10', 'statut' => 'str:20'],
        ],
        'evaluations' => [
            'cols' => ['id' => 'pk', 'cycle_id' => 'int', 'utilisateur_id' => 'int',
                       'evaluateur_id' => 'int', 'statut' => 'str:20',
                       'synthese' => 'text', 'cree_le' => 'ts', 'cloture_le' => 'ts'],
            'index' => [['utilisateur_id']],
        ],
        'objectifs' => [
            'cols' => ['id' => 'pk', 'evaluation_id' => 'int', 'utilisateur_id' => 'int',
                       'libelle' => 'str:255', 'description' => 'text',
                       'echeance' => 'str:10', 'avancement' => 'int',
                       'statut' => 'str:20', 'cree_le' => 'ts'],
            'index' => [['utilisateur_id']],
        ],

        /* ============ Avantages sociaux (section 14) ================== */

        'avantages' => [
            // Aucune couverture n'est pre-remplie : « 80 % des soins
            // dentaires » est une clause de contrat d'assurance, pas une
            // valeur par defaut raisonnable.
            'cols' => ['id' => 'pk', 'code' => 'str:60', 'titre_fr' => 'str:190',
                       'titre_en' => 'str:190', 'description' => 'text',
                       'eligibilite' => 'text', 'couverture' => 'text',
                       'contact' => 'str:255', 'document_id' => 'int',
                       'expire_le' => 'str:10', 'pays' => 'str:8', 'actif' => 'bool'],
            'uniques' => [['code']],
        ],

        /* ============ Temps de travail (employe decentralise) ========= */

        'feuilles_temps' => [
            // `date` est la date LOCALE du salarie. `minutes` est la duree
            // declaree. On ne stocke pas un couple debut/fin en UTC puis on
            // fait la soustraction a l'affichage : deux salaries dans deux
            // fuseaux liraient deux durees differentes pour la meme journee.
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'date' => 'str:10',
                       'debut' => 'str:5', 'fin' => 'str:5', 'pause_min' => 'int',
                       'minutes' => 'int', 'projet' => 'str:190', 'note' => 'text',
                       'statut' => 'str:20',   // brouillon | soumis | valide | refuse
                       'valide_par' => 'int', 'valide_le' => 'ts',
                       'commentaire' => 'text',
                       'cree_le' => 'ts', 'maj_le' => 'ts', 'demo' => 'bool'],
            'uniques' => [['utilisateur_id', 'date']],
            'index'   => [['utilisateur_id'], ['statut'], ['date']],
        ],

        /* ============ Integration / depart (sections 18-19) =========== */

        'parcours' => [
            'cols' => ['id' => 'pk', 'code' => 'str:60', 'type' => 'str:20', // arrivee | depart
                       'nom_fr' => 'str:190', 'nom_en' => 'str:190', 'actif' => 'bool'],
            'uniques' => [['code']],
        ],
        'etapes_parcours' => [
            'cols' => ['id' => 'pk', 'parcours_id' => 'int', 'rang' => 'int',
                       'libelle_fr' => 'str:255', 'libelle_en' => 'str:255',
                       'responsable' => 'str:40',   // employe | manager | rh | si
                       'jour_relatif' => 'int'],
            'index' => [['parcours_id']],
        ],
        'taches_parcours' => [
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'etape_id' => 'int',
                       'statut' => 'str:20', 'echeance' => 'str:10',
                       'fait_le' => 'ts', 'fait_par' => 'int', 'demo' => 'bool'],
            'index' => [['utilisateur_id'], ['statut']],
        ],

        /* ============ Recrutement (portail public ExportRev) ========== */

        'offres' => [
            'cols' => [
                'id' => 'pk', 'reference' => 'str:60', 'slug' => 'str:190',
                'langue' => 'str:5',
                'titre' => 'str:255', 'departement_id' => 'int',
                'localisation_id' => 'int', 'pays' => 'str:8',
                'type_contrat' => 'str:40', 'teletravail' => 'str:30', // sur_site | hybride | distanciel
                'description' => 'text', 'profil' => 'text', 'rendu_desc' => 'text',
                'rendu_profil' => 'text',
                // Aucune fourchette de salaire n'est ecrite par moi. Si elle
                // est vide, la page dit qu'elle n'est pas communiquee — elle
                // n'affiche pas un chiffre plausible.
                'salaire_texte' => 'str:190',
                'examen_id' => 'int', 'examen_obligatoire' => 'bool',
                'statut' => 'str:20',    // brouillon | publie | ferme
                'publie_le' => 'ts', 'ferme_le' => 'ts',
                'cree_par' => 'int', 'cree_le' => 'ts', 'demo' => 'bool',
            ],
            'uniques' => [['reference'], ['slug']],
            'index'   => [['statut', 'publie_le']],
        ],
        'candidatures' => [
            'cols' => [
                'id' => 'pk', 'numero' => 'str:40', 'offre_id' => 'int',
                'prenom' => 'str:120', 'nom' => 'str:120', 'email' => 'str:190',
                'telephone' => 'str:60', 'pays' => 'str:8', 'ville' => 'str:120',
                'lien_pro' => 'str:255', 'message' => 'text',
                'cv_id' => 'int', 'lettre_id' => 'int',
                'statut' => 'str:30',    // recue | en_examen | entretien | offre
                                         // | retenue | refusee | retiree
                'note_rh' => 'text',
                'examen_statut' => 'str:20',  // non_requis | a_faire | en_cours | termine
                'examen_score' => 'float', 'examen_sur' => 'float',
                'examen_fini_le' => 'ts', 'jeton_examen' => 'str:64',
                'consentement_le' => 'ts',    // horodatage du consentement
                'conservation_jusqu' => 'str:10',
                'cree_le' => 'ts', 'maj_le' => 'ts', 'demo' => 'bool',
            ],
            'uniques' => [['numero']],
            'index'   => [['offre_id'], ['statut'], ['email']],
        ],
        'examens' => [
            'cols' => ['id' => 'pk', 'code' => 'str:60', 'titre' => 'str:255',
                       'langue' => 'str:5', 'consigne' => 'text',
                       'duree_min' => 'int', 'seuil' => 'float', 'actif' => 'bool'],
            'uniques' => [['code']],
        ],
        'questions_examen' => [
            'cols' => ['id' => 'pk', 'examen_id' => 'int', 'rang' => 'int',
                       'type' => 'str:20',    // qcm | multiple | texte
                       'enonce' => 'text', 'choix' => 'text',    // JSON
                       'bonne' => 'str:120',  // index(es) attendus, jamais rendu au client
                       'points' => 'float'],
            'index' => [['examen_id']],
        ],
        'reponses_examen' => [
            'cols' => ['id' => 'pk', 'candidature_id' => 'int', 'question_id' => 'int',
                       'reponse' => 'text', 'points' => 'float', 'cree_le' => 'ts'],
            'uniques' => [['candidature_id', 'question_id']],
        ],

        /* ============ Audit, reglages, limites ======================== */

        'journal_audit' => [
            // `ancienne` et `nouvelle` restent NULL sur les champs
            // sensibles : voir l'entete de ce fichier.
            'cols' => ['id' => 'pk', 'utilisateur_id' => 'int', 'action' => 'str:80',
                       'objet' => 'str:60', 'objet_id' => 'int', 'champ' => 'str:80',
                       'ancienne' => 'text', 'nouvelle' => 'text',
                       'detail' => 'text', 'ip' => 'str:64', 'cree_le' => 'ts'],
            'index' => [['utilisateur_id'], ['objet', 'objet_id'], ['cree_le']],
        ],
        'reglages' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:120', 'valeur' => 'text'],
            'uniques' => [['cle']],
        ],
        'limites_taux' => [
            'cols' => ['id' => 'pk', 'cle' => 'str:190', 'compte' => 'int', 'debut' => 'ts'],
            'uniques' => [['cle']],
        ],
    ];
}

function schema_type(string $t, string $pilote): string
{
    if ($t === 'pk') {
        return $pilote === 'mysql'
            ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    }
    if (str_starts_with($t, 'str:')) {
        return 'VARCHAR(' . (int) substr($t, 4) . ') NULL';
    }
    return match ($t) {
        'int'   => 'INTEGER NULL',
        'bool'  => $pilote === 'mysql' ? 'TINYINT(1) NOT NULL DEFAULT 0' : 'INTEGER NOT NULL DEFAULT 0',
        'float' => 'DOUBLE NULL',
        'ts'    => 'DATETIME NULL',
        'blob'  => $pilote === 'mysql' ? 'BLOB NULL' : 'BLOB NULL',
        default => 'TEXT NULL',
    };
}

function schema_ddl(string $pilote): array
{
    $out = [];
    foreach (schema_tables() as $table => $def) {
        $lignes = [];
        foreach ($def['cols'] as $col => $type) {
            $lignes[] = "  `$col` " . schema_type($type, $pilote);
        }
        foreach ($def['uniques'] ?? [] as $u) {
            $lignes[] = '  UNIQUE (`' . implode('`, `', $u) . '`)';
        }
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (\n" . implode(",\n", $lignes) . "\n)";
        if ($pilote === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }
        $out[] = $sql;

        foreach ($def['index'] ?? [] as $ix) {
            // Nom global unique : SQLite partage l'espace de noms des index.
            $nom = 'ix_' . $table . '_' . implode('_', $ix);
            // MySQL n'accepte PAS « CREATE INDEX IF NOT EXISTS » — c'est une
            // erreur de syntaxe, pas un avertissement. L'installeur rejoue
            // le schema a chaque lancement, donc sur MySQL il attrape et
            // ignore l'erreur 1061. Sur SQLite la clause existe.
            $si = $pilote === 'mysql' ? '' : 'IF NOT EXISTS ';
            $out[] = "CREATE INDEX $si`$nom` ON `$table` (`" . implode('`, `', $ix) . "`)";
        }
    }
    return $out;
}
