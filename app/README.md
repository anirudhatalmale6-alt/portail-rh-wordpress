# Portail RH + Portail Recrutement — version 1

PHP 8 · MySQL ou SQLite · aucune dependance, aucun composer, aucun build.
On televerse le dossier, on ouvre l'installeur, c'est en ligne.

---

## 1. Pourquoi PHP et pas NestJS

Ton cahier des charges (section 30) propose React/Next + NestJS + PostgreSQL.
C'est un bon choix sur un serveur dedie. Ce n'est pas ce que tu as :
l'hebergement Hostinger sert du PHP et du MySQL, et l'acces SSH est ferme —
on livre par zip que tu televerses. Un serveur Node ne demarre pas la-dessus.

Ce portail est donc ecrit en PHP 8 avec la meme architecture que le forum
livre precedemment : un point d'entree unique, une table de routage, des
requetes toutes preparees, un schema decrit une seule fois et emis en SQLite
ou en MySQL.

Si tu ouvres un VPS un jour, l'API REST est deja la (`/api/v1/…`) et le
modele de donnees ne bouge pas.

---

## 2. Installation

```bash
php outils/installer.php          # tables, roles, permissions, admin, secrets
php outils/semer.php              # jeu de demonstration (facultatif)
php -S localhost:8000 -t public   # serveur de developpement
```

L'installeur affiche **une seule fois** le mot de passe administrateur. Il
n'est ecrit dans aucun fichier du depot : un mot de passe par defaut livre
dans une archive est un compte ouvert.

Il ecrit aussi `src/config.local.php`, qui n'est pas dans le depot et porte
deux secrets : le sel de session et **la cle de chiffrement des donnees
bancaires**. Sauvegarde ce fichier **separement de la base** — les garder
ensemble annule tout l'interet du chiffrement.

### Passage en production (Hostinger)

1. Televerser le dossier. La racine web pointe sur `public/` ; `src/`,
   `outils/` et `donnees/` restent **au-dessus** de la racine.
   Si l'hebergement impose que tout soit dans `public_html`, le `.htaccess`
   livre refuse deja de servir `donnees/` et `config.local.php`.
2. Renseigner MySQL dans `src/config.local.php` :
   ```php
   'bd' => ['pilote' => 'mysql', 'hote' => 'localhost',
            'base' => '…', 'user' => '…', 'passe' => '…'],
   'cookie_secure' => true,
   ```
3. Relancer `php outils/installer.php` (rejouable sans risque).
4. `php outils/purge-demo.php --executer`, puis `'mode_demo' => false`.

**Les 151 controles ont tourne sur SQLite.** Je n'ai pas d'instance MySQL
sur ma machine. Donne-m'en une et je relance la suite dessus avant de
declarer quoi que ce soit sur ce chemin.

---

## 3. Les trois regles qui tiennent tout le projet

### 3.1 Le portail AFFICHE la paie, il ne la CALCULE pas

Il n'y a dans `src/domaine/paie.php` aucune formule de retenue sociale ou
fiscale, et il n'y en aura pas. Les regles different entre l'Algerie et le
Canada, elles different entre provinces canadiennes, et elles changent
chaque annee. Une formule ecrite la serait fausse un jour sans prevenir,
elle serait fausse sur le bulletin d'un salarie, et c'est l'employeur qui
repondrait de l'ecart.

Ce que le portail fait :

- la RH **saisit ou importe** un bulletin, ligne par ligne, telles qu'elles
  figurent sur le bulletin officiel ;
- le portail **additionne ces lignes** et les compare au net saisi. S'il y a
  un ecart, il l'affiche en rouge et **refuse la publication** — il ne
  corrige ni l'un ni l'autre, parce que c'est peut-etre le net qui est juste
  et une ligne qui manque ;
- tant qu'un bulletin n'est pas publie, le salarie ne le voit pas et la RH
  peut corriger sans que personne ait lu un chiffre faux ;
- la prochaine date de paie vient du **calendrier saisi**, pas d'une
  extrapolation « dans 14 jours » : un jour ferie decale un versement.

La frequence de paie et la devise sont portees par **le contrat**, pas par
le pays : Canada aux deux semaines en CAD, Algerie au mois en DZD par
defaut, modifiables fiche par fiche.

### 3.2 Le numero de compte bancaire n'existe qu'une fois, chiffre

- Stocke en **AES-256-GCM**, cle hors du depot. GCM et pas CBC : GCM
  authentifie, donc un octet modifie en base leve une erreur au lieu de
  rendre un numero silencieusement faux sur un virement.
- L'interface, l'API et les listes n'affichent que `•••• 4321`. Aucune
  requete de l'application ne peut ramener le numero complet par accident :
  il n'a pas de colonne en clair.
- Le modifier **redemande le mot de passe** (5 minutes de validite). Une
  session ouverte ce matin ne suffit pas : l'ecran a pu rester deverrouille.
- Le journal d'audit enregistre **qui** a change le compte et **quand**,
  jamais l'ancienne ni la nouvelle valeur. Un journal qui garderait
  « ancien : compte 00123456 » en serait une deuxieme copie, dans une table
  que l'administrateur systeme peut lire alors qu'il n'a aucune raison de
  connaitre le compte de qui que ce soit.
- Ce que le chiffrement protege : une base qui sort de l'entreprise (dump
  telecharge, export a un prestataire, phpMyAdmin laisse ouvert). Ce qu'il
  ne protege pas : quelqu'un qui a pris le serveur entier lit la cle. C'est
  une limite reelle, elle est ecrite dans `src/coffre.php` pour qu'elle ne
  soit pas decouverte le mauvais jour.

### 3.3 Le rang n'est pas l'autorisation

Un manager est « au-dessus » d'un salarie dans l'organigramme. Il n'a pour
autant **aucun acces** a son bulletin de paie, a son dossier bancaire ni au
justificatif medical d'une de ses absences. Il voit les dates et le fait
qu'un justificatif a ete depose ; la RH voit le document. Un arret de
travail dit pourquoi quelqu'un est malade.

`peut()` interroge la table `role_permissions` et ne compare jamais deux
rangs. La page « Roles et permissions » affiche ce que **le serveur
applique**, pas la declaration du code — sinon l'ecran serait un deuxieme
mensonge d'accord avec le premier. La section 23 de la suite le prouve en
revoquant une permission **en base** et en verifiant que le serveur refuse.

---

## 4. Ce qui est livre

| Section du cahier des charges | Etat |
|---|---|
| 2. Profils (employe, manager, RH, admin) | livre, + **employe decentralise** |
| 3. Tableau de bord employe | livre |
| 4. Profil employe (perso, pro, bancaire) | livre |
| 5. Conges | livre |
| 6. Absences | livre |
| 7. Documents RH | livre |
| 8. Paie (affichage) | livre |
| 9. Demandes RH | livre |
| 10. Notifications | livre (in-app ; e-mail non configure) |
| 11. Communications RH | livre |
| 12. Formations | livre |
| 13. Evaluations | **tables creees, ecrans en phase 2** |
| 14. Avantages sociaux | livre |
| 15. Annuaire | livre |
| 16. Organigramme | livre |
| 17. Calendrier | livre (integration M365/Google : phase 2) |
| 18. Onboarding | **tables creees, ecrans en phase 2** |
| 19. Offboarding | depart : sessions fermees, acces coupe |
| 20. Portail manager | livre |
| 21. Tableau de bord RH | livre |
| 22. Recherche globale | livre |
| 23. Authentification et securite | livre (MFA/SSO : phase 2) |
| 24. Gestion des roles | livre |
| 25-26. Interface, mobile | livre |
| 27. Administration | livre |
| 28. Workflows | circuits conges / absences / temps / demandes livres |
| 29. Integrations | API REST livree, connecteurs a brancher |
| 30. Architecture | **PHP/MySQL, voir section 1** |
| 31. Multilingue | FR + EN complets, architecture ouverte |
| 32. Audit et tracabilite | livre |
| 33. MVP | livre en entier |

**Le portail recrutement** (pour ExportRev) : offres publiques, formulaire
de candidature avec CV et lettre, consentement horodate, date de fin de
conservation ecrite des la creation, examen facultatif ou obligatoire, suivi
des candidatures cote RH, purge des dossiers echus.

**L'employe decentralise** : feuille de temps a la journee, dans **son**
fuseau horaire, en brouillon tant qu'il ne l'a pas soumise, soumission de la
semaine d'un bloc, validation par le manager.

---

## 5. Ce que le portail refuse d'inventer

Chaque valeur laissee vide s'affiche comme une pastille ambre
« a renseigner », visible, et se compte sur `/admin/a-renseigner`.

- **La raison sociale, l'adresse, le numero d'entreprise.** Une attestation
  d'emploi qui porte une raison sociale inventee est un faux document, et
  c'est le salarie qui la presentera a sa banque.
- **Les jours feries.** Fait juridique, different par pays et par annee. La
  RH les saisit ; le tableau de bord affiche « aucun jour ferie enregistre ».
- **Les soldes de conges par defaut.** Le nombre de jours annuels vient de
  la loi et du contrat. Ecrire « 20 » en ferait un droit acquis a l'ecran.
- **Les couvertures d'assurance.** « 80 % des soins dentaires » est une
  clause de contrat, pas une valeur par defaut raisonnable.
- **Les fourchettes de salaire des offres.** Si le champ est vide, la page
  ecrit « non communiquee ».
- **Le taux de rotation**, tant qu'il n'y a pas une annee complete
  d'historique. Sur une base installee la semaine derniere, le denominateur
  est l'effectif d'aujourd'hui et le resultat ressemble a un taux sans en
  etre un.

---

## 6. Securite

- Point d'entree unique : **aucun autre `.php` n'est accessible** depuis le
  web, donc pas de page oubliee qui n'aurait pas verifie les droits.
- Permission ecrite **a cote de la route** dans `src/routes/table.php` : une
  route sans droit se voit d'un coup d'oeil.
- Toutes les requetes preparees. Pas une seule concatenation de valeur dans
  une chaine SQL.
- **Aucun HTML utilisateur n'est accepte** : le texte est echappe en entier,
  puis on re-injecte les quelques balises qu'on ecrit soi-meme. On ne
  nettoie donc jamais un HTML hostile — on n'en recoit pas.
- Jeton anti-CSRF sur **tous** les POST, verifie dans le routeur.
- Documents **hors racine web**, noms de fichiers tires au hasard, droit
  verifie avant d'ouvrir le fichier. `arret-travail-…pdf` n'existe pas comme
  nom sur le disque.
- Politique de securite du contenu stricte, `noindex` sur tout le portail.
- Limitation de debit qui **ne compte que les echecs** : deux compteurs, un
  par compte vise, un par IP avec un budget large. Compter aussi les
  reussites bloquerait tout un bureau derriere une seule sortie des la
  cinquieme personne qui arrive le matin (voir section 8).
- Un depart ou une suspension **ferme les sessions ouvertes** immediatement.
- Changer son mot de passe ferme toutes les **autres** sessions.

---

## 7. Recette

```bash
python3 tests/tests-rh.py        # 151 controles
python3 tests/captures.py        # 47 captures + console + debordement
php tests/rh.php compte          # etat de la base
php tests/rh.php slugs           # les adresses respectent [a-z0-9-]
```

La suite n'essaie pas de prouver que le code est bon. Elle essaie de le
mettre en defaut : elle demande un bulletin qui n'est pas le sien, elle
forge un `nb_jours` que le formulaire n'envoie pas, elle publie un bulletin
incoherent en forcant l'adresse parce que le bouton est cache, elle rejoue
un lien d'examen deja utilise, elle revoque une permission en base.

**Elle peut echouer, et je l'ai verifie.** Deux mutations volontaires :

| Mutation | Resultat |
|---|---|
| Retirer la garde de coherence dans `publier_bulletin()` | 2 controles rouges |
| Cesser de relire `publie_le <= maintenant` sur les actualites | 3 controles rouges |
| Ne plus renseigner l'acteur de l'audit a la connexion | 1 controle rouge |

Les deux ont ete restaurees et le vert reconfirme. La deuxieme a au passage
montre que `recherche_globale()` porte sa **propre** verification de date :
la recherche est restee correcte pendant que les listes fuyaient. C'est de
la defense en profondeur, et elle est reelle.

Mesures, pas impressions : **0 erreur de console**, **0 debordement
horizontal** a 1280 px et a 390 px, sur 47 pages. Le portail fonctionne
**entierement sans JavaScript** — le script n'ajoute qu'un total calcule a
l'ecran.

---

## 8. Deux defauts trouves pendant le developpement

Ils sont ecrits ici parce qu'un defaut corrige sans etre nomme se refait.

**La limite de connexion bloquait les gens honnetes.** Elle comptait *tous*
les essais, reussites comprises, sur l'adresse IP. Cinq connexions depuis un
bureau derriere une seule sortie et la sixieme personne etait bloquee — sans
avoir rien fait. C'est la suite de tests qui l'a montre : elle n'arrivait
plus a connecter le cinquieme compte de demonstration, et le symptome
ressemblait a un bug de mot de passe. Corrige : on ne compte que les
**echecs**, avec deux compteurs distincts, et une connexion reussie remet le
compteur du compte a zero.

**Le journal d'audit ne savait pas qui s'etait connecte.** La colonne
« qui » disait « systeme » sur toutes les connexions reussies : a la
connexion, le cookie de session vient d'etre pose et `$_COOKIE` n'est pas
repeuple dans la meme requete, donc `utilisateur()` rendait null. La ligne
la plus utile d'un journal de connexions ne servait a rien. Vu sur une
**capture d'ecran** du journal, pas dans le code — la lecture du code
n'aurait rien montre d'anormal. Un controle a ete ajoute, et il passe au
rouge quand on retire le correctif.

**L'installeur annoncait un coffre indisponible qu'il venait de creer.** La
configuration est mise en cache au premier appel ; l'installeur la lit pour
connaitre le pilote de base **avant** d'ecrire `config.local.php`, donc le
cache gardait la version sans la cle. L'installeur ne se contente pas de
constater que la cle existe : il chiffre puis dechiffre une valeur temoin.
C'est ce controle-la qui a leve le probleme, pas une relecture du code.

---

## 9. Ce qu'il me faut de ta part

1. **Les identifiants MySQL de production.** Les 151 controles ont tourne
   sur SQLite ; je relance la suite sur MySQL avant de declarer ce chemin.
2. **Le nom de domaine du portail** et celui des offres d'emploi
   (`exportrev.com` ou `carrieres.exportrev.com`).
3. **La province canadienne.** Elle change la langue par defaut et les
   regles d'affichage.
4. **La raison sociale complete, l'adresse, le numero d'entreprise** et la
   personne qui repond d'une demande d'acces au dossier. Ce sont les quatre
   valeurs qui apparaissent sur une attestation d'emploi.
5. **Les jours feries** des deux pays pour l'annee en cours, ou la
   confirmation que la RH les saisira.
6. **Un expediteur de courriel**, si tu veux que les notifications partent.
   Sans lui rien n'est envoye, et le portail le dit plutot que de laisser
   croire qu'un salarie a ete prevenu.

---

## 10. Phase 2, dans l'ordre ou je le recommande

1. Evaluations et objectifs (tables deja creees).
2. Parcours d'integration et de depart (tables deja creees).
3. MFA, puis SSO Microsoft Entra ID / Google Workspace.
4. Import de paie depuis un vrai logiciel, a la place de la saisie.
5. Synchronisation de calendrier M365 / Google.
6. Application mobile — le portail est deja utilisable au telephone.
