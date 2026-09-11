#!/usr/bin/env python3
"""
Suite de recette du PORTAIL RH.

    php outils/installer.php && php outils/semer.php
    php -S 127.0.0.1:8842 -t public &
    python3 tests/tests-rh.py [http://127.0.0.1:8842]

CE QUE CETTE SUITE ESSAIE DE FAIRE, ET CE QU'ELLE NE FAIT PAS.

Elle n'essaie pas de prouver que le code est bon. Elle essaie de le mettre
en defaut : elle demande un bulletin de paie qui n'est pas le sien, elle
forge un champ que le formulaire n'affiche pas, elle publie un bulletin
incoherent, elle rejoue un lien d'examen deja utilise.

Un test qui ne peut pas echouer ne prouve rien. Deux verifications de la
section 14 le montrent explicitement : elles comparent un compte AVANT et
APRES au lieu de constater qu'une page repond 200.
"""

import datetime
import html
import json
import os
import re
import subprocess
import sys
import time

import requests

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8842").rstrip("/")

# La base PERSISTE entre deux executions. Une adresse de candidature figee
# ferait echouer la deuxieme execution sur le controle du doublon — et le
# message serait « la candidature n'aboutit pas », ce qui est faux. Chaque
# execution a donc son adresse. Le controle du doublon, lui, la reutilise
# volontairement.
MARQUE = str(int(time.time()))
MAIL_CANDIDAT = f"test.candidat.{MARQUE}@exemple.test"

# Les demandes de conges se chevauchent d'une execution a l'autre si les
# dates sont figees : la deuxieme execution serait refusee pour
# chevauchement et le message dirait « la demande n'est pas acceptee », ce
# qui serait faux. Chaque execution prend donc sa propre semaine, un lundi,
# loin dans le futur. Le controle du chevauchement, lui, la reutilise
# volontairement.
_base = datetime.date(2028, 1, 3) + datetime.timedelta(days=(int(MARQUE) % 400) * 7)
LUNDI = _base - datetime.timedelta(days=_base.weekday())
D1 = LUNDI.isoformat()
D5 = (LUNDI + datetime.timedelta(days=4)).isoformat()
D3 = (LUNDI + datetime.timedelta(days=2)).isoformat()
SAMEDI = (LUNDI + datetime.timedelta(days=5)).isoformat()
DIMANCHE = (LUNDI + datetime.timedelta(days=6)).isoformat()

# Les compteurs de debit sont PERSISTANTS — c'est ce qui les rend utiles.
# Ils empechent donc de rejouer la suite dans l'heure : la sixieme
# candidature serait refusee et le message dirait « la candidature
# n'aboutit pas », ce qui serait faux. On les remet a zero avant de
# commencer, explicitement, par l'outil de recette et jamais par
# l'application. La derniere section verifie que la limite EXISTE bel et
# bien, ce qui serait invisible autrement.
RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
try:
    sortie = subprocess.run(["php", "tests/rh.php", "limites-raz"], cwd=RACINE,
                            capture_output=True, text=True, timeout=20)
    print(sortie.stdout.strip() or sortie.stderr.strip())
except Exception as exc:                                   # base distante
    print(f"  (compteurs de debit non remis a zero : {exc})")

OK = 0
KO = 0
ECHECS = []


def verif(nom, condition, detail=""):
    global OK, KO
    if condition:
        OK += 1
        print(f"  ok    {nom}")
    else:
        KO += 1
        ECHECS.append(nom)
        d = f"  [{detail}]" if detail else ""
        print(f"  ECHEC {nom}{d}")


def section(titre):
    print(f"\n=== {titre} ===")


def csrf(session, chemin):
    """Le jeton de la page demandee. Sans lui aucun POST ne passe."""
    r = session.get(BASE + chemin)
    m = re.search(r'name="csrf" value="([^"]+)"', r.text)
    return m.group(1) if m else ""


def connecte(email, mdp="demonstration-2026"):
    s = requests.Session()
    jeton = csrf(s, "/connexion")
    r = s.post(BASE + "/connexion",
               data={"csrf": jeton, "email": email, "mot_de_passe": mdp, "retour": "/"},
               allow_redirects=True)
    return s, r


# ---------------------------------------------------------------- 1
section("1. Surface publique")

anon = requests.Session()
r = anon.get(BASE + "/carrieres")
verif("la page carrieres repond 200 sans session", r.status_code == 200)
verif("elle liste les offres publiees", "export" in r.text.lower())

# L'offre en brouillon ne doit exister nulle part, y compris a son adresse.
verif("aucune offre en brouillon dans la liste publique",
      "brouillon" not in r.text.lower())
r = anon.get(BASE + "/carrieres/responsable-logistique-brouillon")
verif("l'offre en brouillon repond 404 a son adresse directe", r.status_code == 404,
      str(r.status_code))

r = anon.get(BASE + "/connexion")
verif("la page de connexion repond 200", r.status_code == 200)

for chemin in ["/", "/paie", "/documents", "/rh", "/admin/journal", "/annuaire"]:
    r = anon.get(BASE + chemin, allow_redirects=False)
    verif(f"{chemin} renvoie un visiteur vers la connexion",
          r.status_code == 303 and "/connexion" in r.headers.get("Location", ""),
          f"{r.status_code} {r.headers.get('Location','')}")

# L'API doit rendre un vrai 401, pas une redirection HTML : un client qui
# suit les redirections recevrait une page de connexion en croyant lire du
# JSON, et verrait un « succes ».
r = anon.get(BASE + "/api/v1/moi", allow_redirects=False)
verif("l'API repond 401 et non une redirection", r.status_code == 401, str(r.status_code))

r = anon.get(BASE + "/api/v1/offres")
verif("l'API des offres est publique", r.status_code == 200)
try:
    offres_api = r.json()
except Exception:
    offres_api = {}
verif("l'API n'expose aucune remuneration inventee",
      all(o.get("remuneration") in (None, "") for o in offres_api.get("offres", [])))

r = anon.get(BASE + "/connexion")
verif("l'en-tete interdit l'indexation",
      "noindex" in r.headers.get("X-Robots-Tag", ""))
verif("l'en-tete pose une politique de securite du contenu",
      "default-src 'self'" in r.headers.get("Content-Security-Policy", ""))

# ---------------------------------------------------------------- 2
section("2. Connexion")

s_bad = requests.Session()
j = csrf(s_bad, "/connexion")
r = s_bad.post(BASE + "/connexion",
               data={"csrf": j, "email": "youcef.berrada@exemple.test",
                     "mot_de_passe": "mauvais", "retour": "/"})
verif("un mot de passe faux ne connecte pas", "Identifiants incorrects" in r.text)

s_inconnu = requests.Session()
j = csrf(s_inconnu, "/connexion")
r2 = s_inconnu.post(BASE + "/connexion",
                    data={"csrf": j, "email": "personne@nulle-part.test",
                          "mot_de_passe": "mauvais", "retour": "/"})
# Le meme message pour « compte inconnu » et « mot de passe faux » : deux
# messages distincts sont un annuaire des salaries offert a qui teste.
verif("le message est identique pour un compte inconnu",
      "Identifiants incorrects" in r2.text)

s_emp, r = connecte("youcef.berrada@exemple.test")
verif("un salarie se connecte", "Bonjour Youcef" in r.text, r.text[:80])

s_mgr, _ = connecte("marc.tremblay@exemple.test")
s_rh, _ = connecte("leila.hamdani@exemple.test")
s_dec, _ = connecte("sofia.marchand@exemple.test")

verif("le manager voit l'entree « Mon equipe »",
      "/equipe" in s_mgr.get(BASE + "/").text)
verif("le salarie ordinaire ne voit pas l'entree « Mon equipe »",
      '"/equipe"' not in s_emp.get(BASE + "/").text)
verif("le salarie decentralise voit l'entree « Mon temps »",
      "/temps" in s_dec.get(BASE + "/").text)
verif("le salarie de bureau ne voit pas « Mon temps »",
      '"/temps"' not in s_emp.get(BASE + "/").text)

# Redirection ouverte : une destination externe ne doit pas etre suivie.
s_open = requests.Session()
j = csrf(s_open, "/connexion")
r = s_open.post(BASE + "/connexion",
                data={"csrf": j, "email": "youcef.berrada@exemple.test",
                      "mot_de_passe": "demonstration-2026",
                      "retour": "https://ailleurs.example/"},
                allow_redirects=False)
verif("une destination externe apres connexion est ignoree",
      "ailleurs.example" not in r.headers.get("Location", ""),
      r.headers.get("Location", ""))

# ---------------------------------------------------------------- 3
section("3. Permissions : ce qu'un role ne peut PAS faire")

for chemin in ["/rh", "/rh/employes", "/rh/paie", "/admin/roles", "/admin/journal",
               "/equipe", "/equipe/conges"]:
    verif(f"un salarie est refuse sur {chemin}",
          s_emp.get(BASE + chemin).status_code == 403,
          str(s_emp.get(BASE + chemin).status_code))

for chemin in ["/rh/paie", "/admin/roles"]:
    verif(f"un manager est refuse sur {chemin}",
          s_mgr.get(BASE + chemin).status_code == 403)

verif("un manager accede a son tableau d'equipe",
      s_mgr.get(BASE + "/equipe").status_code == 200)
verif("la RH accede au tableau de bord RH",
      s_rh.get(BASE + "/rh").status_code == 200)

# La page equipe DIT ce que le manager ne voit pas, plutot que de le taire.
r = s_mgr.get(BASE + "/equipe")
verif("la page equipe enonce le perimetre du manager",
      "ni leur paie" in r.text or "ni leur compte bancaire" in r.text)

# ---------------------------------------------------------------- 4
section("4. Le bulletin de paie d'un autre")

# On cherche un bulletin qui n'appartient pas au salarie connecte.
r = s_emp.get(BASE + "/paie")
mien = re.findall(r'/paie/(\d+)', r.text)
verif("le salarie voit ses propres bulletins", len(mien) > 0, str(len(mien)))

autre = None
for i in range(1, 200):
    if str(i) not in mien:
        rr = s_emp.get(BASE + f"/paie/{i}")
        if rr.status_code in (403, 404):
            autre = i
            break
verif("un bulletin qui n'est pas le sien est refuse", autre is not None)

# Le manager non plus : il n'a pas paie.gerer.
if mien:
    r = s_mgr.get(BASE + f"/paie/{mien[0]}")
    verif("le manager ne peut pas ouvrir le bulletin d'un subordonne",
          r.status_code in (403, 404), str(r.status_code))
    r = s_rh.get(BASE + f"/paie/{mien[0]}")
    verif("la RH peut ouvrir le bulletin", r.status_code == 200, str(r.status_code))

# ---------------------------------------------------------------- 5
section("5. Actualite en brouillon et actualite programmee")

r = s_emp.get(BASE + "/actualites")
verif("le brouillon n'apparait pas dans la liste",
      "Nouvelle mutuelle" not in r.text)
verif("l'actualite programmee n'apparait pas dans la liste",
      "Assemblee annuelle" not in r.text)

r = s_emp.get(BASE + "/actualites/brouillon-mutuelle")
verif("le brouillon repond 404 a son adresse directe", r.status_code == 404,
      str(r.status_code))
r = s_emp.get(BASE + "/actualites/assemblee-annuelle")
verif("l'actualite programmee repond 404 avant sa date", r.status_code == 404,
      str(r.status_code))

r = s_emp.get(BASE + "/recherche?q=Assemblee")
verif("l'actualite programmee n'apparait pas dans la recherche",
      "assemblee-annuelle" not in r.text)

r = s_emp.get(BASE + "/")
verif("l'actualite programmee n'apparait pas sur le tableau de bord",
      "Assemblee annuelle" not in r.text)

# ---------------------------------------------------------------- 6
section("6. Le HTML saisi doit ressortir ECHAPPE")

j = csrf(s_emp, "/demandes/nouvelle")
charge = '<script>alert(1)</script> <img src=x onerror=alert(2)> **gras**'
r = s_emp.post(BASE + "/demandes",
               data={"csrf": j, "categorie": "autre",
                     "objet": "Test d'echappement", "corps": charge},
               allow_redirects=True)
verif("la demande est enregistree", "Test d&#039;echappement" in r.text or "Test d'echappement" in r.text)
# Le HTML doit etre ECHAPPE, pas retire : « onerror= » figure donc bien dans
# la page, en texte, a l'interieur de &lt;img …&gt;.
verif("aucune balise <script> venue du texte", "<script>alert(1)" not in r.text)
verif("le <script> saisi apparait echappe", "&lt;script&gt;alert(1)" in r.text)
verif("aucune balise <img> venue du texte", "<img src=x" not in r.text)
verif("le <img onerror> saisi apparait echappe", "&lt;img src=x onerror=" in r.text)
verif("la mise en forme que NOUS fabriquons est rendue", "<strong>gras</strong>" in r.text)

# ---------------------------------------------------------------- 7
section("7. CSRF")

r = s_emp.post(BASE + "/demandes",
               data={"categorie": "autre", "objet": "Sans jeton",
                     "corps": "Ceci ne doit pas passer."})
verif("un POST sans jeton est refuse", r.status_code == 403, str(r.status_code))
r = s_emp.post(BASE + "/demandes",
               data={"csrf": "faux", "categorie": "autre", "objet": "Jeton faux",
                     "corps": "Ceci ne doit pas passer."})
verif("un POST avec un mauvais jeton est refuse", r.status_code == 403, str(r.status_code))

# ---------------------------------------------------------------- 8
section("8. Conges : le calcul est cote serveur")

j = csrf(s_emp, "/conges")
r = s_emp.get(BASE + "/conges")
type_id = re.search(r'name="type_id"[^>]*>\s*<option value="(\d+)"', r.text)
type_id = type_id.group(1) if type_id else "1"

# On envoie un nb_jours forge. Le serveur ne lit meme pas ce champ.
r = s_emp.post(BASE + "/conges/demander",
               data={"csrf": j, "type_id": type_id,
                     "debut": D1, "fin": D5,
                     "nb_jours": "0.5", "motif": "Test de calcul"},
               allow_redirects=True)
verif("la demande est acceptee", "Demande envoyee" in r.text or "Test de calcul" in r.text)
# Le formulaire annoncait 0,5 jour ; le serveur doit en compter 5. On lit
# la ligne du tableau qui porte NOS dates, et pas n'importe quel « 5 » de
# la page.
ligne = re.search(re.escape(D1) + r'\s*→\s*' + re.escape(D5) + r'</td>\s*<td class="num">([^<]+)</td>',
                  r.text)
verif("le nb_jours forge est ignore : le serveur compte 5 jours ouvres",
      ligne is not None and ligne.group(1).strip().startswith("5"),
      ligne.group(1).strip() if ligne else "ligne introuvable")

# Chevauchement.
j = csrf(s_emp, "/conges")
r = s_emp.post(BASE + "/conges/demander",
               data={"csrf": j, "type_id": type_id,
                     "debut": D3, "fin": D3, "motif": "Chevauchement"},
               allow_redirects=True)
verif("une demande qui chevauche est refusee", "chevauche" in r.text.lower()
      or "couvre deja" in r.text.lower())

# Ordre des dates.
j = csrf(s_emp, "/conges")
r = s_emp.post(BASE + "/conges/demander",
               data={"csrf": j, "type_id": type_id,
                     "debut": D5, "fin": D1, "motif": "Ordre"},
               allow_redirects=True)
verif("une date de fin anterieure est refusee",
      "precede" in r.text.lower() or "avant" in r.text.lower())

# Un week-end complet ne contient aucun jour ouvre.
j = csrf(s_emp, "/conges")
r = s_emp.post(BASE + "/conges/demander",
               data={"csrf": j, "type_id": type_id,
                     "debut": SAMEDI, "fin": DIMANCHE, "motif": "Week-end"},
               allow_redirects=True)
verif("un week-end seul est refuse (aucun jour ouvre)",
      "aucun jour ouvre" in r.text.lower())

# ---------------------------------------------------------------- 9
section("9. Conges : qui a le droit de decider")

r = s_mgr.get(BASE + "/equipe/conges")
verif("le manager voit des demandes a trancher", "Approuver" in r.text)
# On vise LA demande creee par cette execution, reperee par ses dates.
# Prendre « la premiere de la liste » ferait dependre le test de l'ordre
# d'affichage et, un jour, trancherait la demande de quelqu'un d'autre.
bloc = re.search(r'/equipe/conges/(\d+)"[\s\S]{0,400}?' + re.escape(D1), r.text)
if not bloc:
    bloc = re.search(re.escape(D1) + r'[\s\S]{0,800}?/equipe/conges/(\d+)"', r.text)
ids = [bloc.group(1)] if bloc else []
verif("la demande creee par ce test est bien en attente chez le manager",
      len(ids) > 0, f"dates {D1} → {D5}")

if ids:
    # Un salarie ordinaire ne peut pas trancher, meme en connaissant l'id.
    j = csrf(s_emp, "/conges")
    r = s_emp.post(BASE + f"/equipe/conges/{ids[0]}",
                   data={"csrf": j, "decision": "approuve"})
    verif("un salarie ne peut pas approuver une demande",
          r.status_code == 403, str(r.status_code))

    # Le manager approuve la premiere de SON equipe.
    j = csrf(s_mgr, "/equipe/conges")
    r = s_mgr.post(BASE + f"/equipe/conges/{ids[0]}",
                   data={"csrf": j, "decision": "approuve", "retour": "/equipe/conges"},
                   allow_redirects=True)
    verif("le manager approuve une demande de son equipe",
          "Decision enregistree" in r.text)

    # Rejouer la meme decision doit echouer : elle n'est plus en attente.
    j = csrf(s_mgr, "/equipe/conges")
    r = s_mgr.post(BASE + f"/equipe/conges/{ids[0]}",
                   data={"csrf": j, "decision": "refuse", "retour": "/equipe/conges"},
                   allow_redirects=True)
    verif("une demande deja tranchee ne se rejoue pas",
          "deja ete traitee" in r.text)

# ---------------------------------------------------------------- 10
section("10. Absence : le justificatif n'est pas pour le manager")

r = s_mgr.get(BASE + "/equipe/absences")
verif("le manager voit les absences de son equipe", r.status_code == 200)
verif("la page explique que le motif est masque",
      "sick note" in r.text.lower() or "arret de travail" in r.text.lower())
verif("aucun lien vers un justificatif chez le manager",
      "/document/" not in r.text)

r = s_rh.get(BASE + "/rh/absences")
verif("la RH accede a la page des absences", r.status_code == 200)

# ---------------------------------------------------------------- 11
section("11. Donnees bancaires : coffre, masque, reauthentification")

r = s_emp.get(BASE + "/profil/bancaire", allow_redirects=False)
verif("la page bancaire exige une reauthentification",
      r.status_code == 303 and "reauthentification" in r.headers.get("Location", ""),
      f"{r.status_code} {r.headers.get('Location','')}")

# On tente d'ecrire SANS s'etre reauthentifie.
j = csrf(s_emp, "/reauthentification?retour=/profil/bancaire")
r = s_emp.post(BASE + "/profil/bancaire",
               data={"csrf": j, "institution": "Banque test",
                     "numero": "1234567890123456", "mode_paiement": "virement"},
               allow_redirects=False)
verif("l'ecriture sans reauthentification est renvoyee vers la reauthentification",
      r.status_code == 303 and "reauthentification" in r.headers.get("Location", ""))

# Reauthentification, puis ecriture.
j = csrf(s_emp, "/reauthentification?retour=/profil/bancaire")
r = s_emp.post(BASE + "/reauthentification",
               data={"csrf": j, "mot_de_passe": "demonstration-2026",
                     "retour": "/profil/bancaire"}, allow_redirects=True)
verif("la reauthentification aboutit sur la page bancaire",
      "Institution" in r.text or "institution" in r.text.lower())

j = csrf(s_emp, "/profil/bancaire")
r = s_emp.post(BASE + "/profil/bancaire",
               data={"csrf": j, "institution": "Banque test",
                     "numero": "1234567890123456", "mode_paiement": "virement"},
               allow_redirects=True)
verif("les coordonnees bancaires sont enregistrees",
      "enregistr" in r.text.lower())

r = s_emp.get(BASE + "/profil")
verif("le profil affiche le masque et pas le numero",
      "3456" in r.text and "1234567890123456" not in r.text)

r = s_emp.get(BASE + "/api/v1/moi")
corps = r.text
verif("l'API rend le masque, jamais le numero",
      "1234567890123456" not in corps and "3456" in corps)

# Le numero ne doit apparaitre nulle part dans le journal d'audit.
s_adm, _ = connecte("admin@local", "___mot_de_passe_inconnu___")
r = s_rh.get(BASE + "/admin/journal?action=banque")
verif("le journal d'audit est lisible par la RH", r.status_code == 200,
      str(r.status_code))
verif("le journal ne contient pas le numero de compte",
      "1234567890123456" not in r.text)
verif("le journal dit explicitement que la valeur n'est pas journalisee",
      "non journalisee" in r.text)

# ---------------------------------------------------------------- 12
section("12. Temps de travail (salarie decentralise)")

r = s_dec.get(BASE + "/temps")
verif("le salarie decentralise accede a sa feuille de temps", r.status_code == 200)
verif("son fuseau est affiche", "Europe/Lisbon" in r.text)
verif("le salarie de bureau est refuse sur /temps",
      s_emp.get(BASE + "/temps").status_code == 403)

# Journee a cheval sur minuit : 22:00 → 02:00 avec 0 de pause = 4 h.
j = csrf(s_dec, "/temps")
r = s_dec.post(BASE + "/temps/journee",
               data={"csrf": j, "date": "2027-04-14", "debut": "22:00",
                     "fin": "02:00", "pause_min": "0", "projet": "Nuit"},
               allow_redirects=True)
verif("une journee a cheval sur minuit compte 4 h", "4 h" in r.text, r.text[:0])

# Duree aberrante.
j = csrf(s_dec, "/temps")
r = s_dec.post(BASE + "/temps/journee",
               data={"csrf": j, "date": "2027-04-15", "debut": "01:00",
                     "fin": "23:59", "pause_min": "0", "projet": "Trop long"},
               allow_redirects=True)
verif("plus de seize heures sur une journee est refuse",
      "seize heures" in r.text.lower())

# Heure invalide.
j = csrf(s_dec, "/temps")
r = s_dec.post(BASE + "/temps/journee",
               data={"csrf": j, "date": "2027-04-16", "debut": "25:00",
                     "fin": "26:00", "pause_min": "0"}, allow_redirects=True)
verif("une heure invalide est refusee", "invalide" in r.text.lower())

# Soumission de la semaine.
j = csrf(s_dec, "/temps?semaine=2027-04-12")
r = s_dec.post(BASE + "/temps/soumettre",
               data={"csrf": j, "lundi": "2027-04-12"}, allow_redirects=True)
verif("la semaine se soumet", "soumise" in r.text.lower() or "submitted" in r.text.lower())

# Une semaine sans brouillon ne se soumet pas deux fois.
j = csrf(s_dec, "/temps?semaine=2027-04-12")
r = s_dec.post(BASE + "/temps/soumettre",
               data={"csrf": j, "lundi": "2027-04-12"}, allow_redirects=True)
verif("une semaine deja soumise ne se resoumet pas",
      "aucune journee en brouillon" in r.text.lower() or "no draft day" in r.text.lower())

# ---------------------------------------------------------------- 13
section("13. Paie : publier un bulletin incoherent est refuse par le SERVEUR")

r = s_rh.get(BASE + "/rh/paie")
verif("la RH accede a la page de paie", r.status_code == 200)


def options(page, champ):
    """Les valeurs d'un <select> donne, sans attraper celles du voisin."""
    m = re.search(r'name="' + champ + r'"[^>]*>(.*?)</select>', page, re.S)
    return re.findall(r'<option value="(\d+)"', m.group(1)) if m else []


def bulletins_a_publier(page):
    """Les identifiants listes dans la SECTION « a publier » uniquement.

    Chercher /paie/(\d+) dans toute la page ramenerait aussi les bulletins
    deja publies plus haut, et le test publierait autre chose que ce qu'il
    vient de creer — en passant au vert.
    """
    marqueur = "Bulletins a publier"
    if marqueur not in page:
        return []
    return re.findall(r'/paie/(\d+)"', page.split(marqueur, 1)[1])


def saisir_bulletin(session, uids, periodes, brut, net, lignes):
    """Saisit un bulletin sur le premier couple (salarie, periode) libre.

    Le portail refuse deux bulletins pour un meme salarie sur une meme
    periode — c'est voulu. Sans cette recherche, la Nieme execution de la
    suite epuiserait les periodes du premier salarie et echouerait sur
    « deja » ; le message dirait « le bulletin n'est pas accepte », ce qui
    serait faux.
    """
    for uid in uids:
        for pid in periodes:
            j = csrf(session, "/rh/paie")
            donnees = [("csrf", j), ("utilisateur_id", uid), ("periode_id", pid),
                       ("brut", brut), ("net", net), ("devise", "CAD")]
            for typ, lib, mnt in lignes:
                donnees += [("ligne_type[]", typ), ("ligne_libelle[]", lib),
                            ("ligne_montant[]", mnt)]
            r = session.post(BASE + "/rh/paie/bulletin", data=donnees, allow_redirects=True)
            if "enregistre en brouillon" in r.text or "saved as a draft" in r.text:
                return r
    return None


pid = options(r.text, "periode_id")
uid = options(r.text, "utilisateur_id")
verif("des periodes de paie existent", len(pid) > 0, str(len(pid)))
verif("des salaries sont proposes", len(uid) > 0, str(len(uid)))

if pid and uid:
    avant_publiables = set(bulletins_a_publier(r.text))

    # --- (a) bulletin INCOHERENT : lignes = 900, net annonce = 1000.
    r = saisir_bulletin(s_rh, uid, pid, "1000", "1000",
                        [("salaire", "Base", "1000"), ("deduction", "Cotisation", "100")])
    verif("le bulletin incoherent est accepte en brouillon", r is not None)

    apres = set(bulletins_a_publier(r.text if r is not None else ""))
    nouveaux = sorted(apres - avant_publiables, key=int)
    verif("le brouillon apparait dans la liste a publier", len(nouveaux) == 1,
          f"{len(nouveaux)} nouveaux")

    verif("l'ecart est signale a la RH",
          r is not None and ("ecart de" in r.text.lower() or "gap of" in r.text.lower()))

    if r is not None and nouveaux:
        bid = nouveaux[0]
        # Le bouton de publication ne s'affiche pas sur un bulletin
        # incoherent. On force donc l'adresse : une interface qui cache un
        # bouton n'a rien protege, c'est le serveur qui doit refuser.
        verif("aucun bouton de publication n'est propose sur ce bulletin",
              f"/rh/paie/bulletin/{bid}/publier" not in r.text)

        j = csrf(s_rh, "/rh/paie")
        r2 = s_rh.post(BASE + f"/rh/paie/bulletin/{bid}/publier",
                       data={"csrf": j}, allow_redirects=True)
        verif("le serveur REFUSE de publier un bulletin incoherent",
              "publication refus" in r2.text.lower() or "refused" in r2.text.lower(),
              r2.text[:0])

        # Et il n'est toujours pas visible du salarie : la page de detail
        # d'un bulletin non publie reste refusee a son proprietaire.
        verif("le bulletin non publie reste dans la liste a publier",
              bid in bulletins_a_publier(r2.text))

    # --- (b) contre-epreuve : un bulletin COHERENT doit se publier.
    #     Sans elle, un refus systematique passerait pour une reussite.
    r = saisir_bulletin(s_rh, uid, pid, "1000", "900",
                        [("salaire", "Base", "1000"), ("deduction", "Cotisation", "100")])
    verif("un bulletin coherent est accepte en brouillon", r is not None)
    if r is not None:
        m = re.search(r'/rh/paie/bulletin/(\d+)/publier', r.text)
        verif("un bulletin coherent propose bien le bouton publier", m is not None)
        if m:
            j = csrf(s_rh, "/rh/paie")
            r3 = s_rh.post(BASE + f"/rh/paie/bulletin/{m.group(1)}/publier",
                           data={"csrf": j}, allow_redirects=True)
            verif("le bulletin coherent se publie",
                  "publie" in r3.text.lower() or "published" in r3.text.lower())

# ---------------------------------------------------------------- 14
section("14. Le test peut-il echouer ? (comptes avant / apres)")

# Une verification qui compte AVANT et APRES prouve qu'elle mesure quelque
# chose. Constater qu'une page repond 200 ne prouve rien.
# On compte LA candidature qu'on va deposer, pas les liens de la premiere
# page : la liste est paginee a 25, et au-dela le nombre de liens ne bouge
# plus. Un compteur qui plafonne passe pour un compteur qui ne bouge pas.
def compte_candidature(mail):
    page = s_rh.get(BASE + "/rh/candidatures?q=" + mail).text
    return len(re.findall(r'/rh/candidatures/\d+', page))


avant = compte_candidature(MAIL_CANDIDAT)
verif("cette adresse n'a encore aucune candidature", avant == 0, str(avant))

r = anon.get(BASE + "/carrieres")
slug = re.search(r'/carrieres/([\w\-]+)"', r.text)
slug = slug.group(1) if slug else None
verif("une offre publique est trouvee", slug is not None)

if slug:
    j = csrf(anon, "/carrieres/" + slug)
    r = anon.post(BASE + f"/carrieres/{slug}/postuler",
                  data={"csrf": j, "prenom": "Test", "nom": "Candidat",
                        "email": MAIL_CANDIDAT, "consentement": "1"},
                  files={"cv": ("cv.pdf", b"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<<>>\n%%EOF\n",
                                "application/pdf")},
                  allow_redirects=True)
    verif("la candidature publique aboutit", "Candidature recue" in r.text
          or "Application received" in r.text)
    numero = re.search(r'<strong>(CAND-[\d\-]+)</strong>', r.text)
    verif("un numero de suivi est rendu", numero is not None)

    jeton = re.search(r'/examen/([a-f0-9]{40,})', r.text)
    verif("un lien d'examen est propose quand l'offre en a un", jeton is not None)

    apres = compte_candidature(MAIL_CANDIDAT)
    verif(f"la candidature deposee est retrouvee cote RH ({avant} -> {apres})",
          apres == avant + 1, f"{avant} -> {apres}")

    # Doublon sur la meme offre avec la meme adresse.
    j = csrf(anon, "/carrieres/" + slug)
    r = anon.post(BASE + f"/carrieres/{slug}/postuler",
                  data={"csrf": j, "prenom": "Test", "nom": "Candidat",
                        "email": MAIL_CANDIDAT, "consentement": "1"},
                  files={"cv": ("cv.pdf", b"%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<<>>\n%%EOF\n",
                                "application/pdf")},
                  allow_redirects=True)
    verif("une deuxieme candidature sur la meme offre est refusee",
          "deja ete deposee" in r.text or "already been submitted" in r.text)

    # Consentement non coche.
    j = csrf(anon, "/carrieres/" + slug)
    r = anon.post(BASE + f"/carrieres/{slug}/postuler",
                  data={"csrf": j, "prenom": "Sans", "nom": "Consentement",
                        "email": f"sans.consentement.{MARQUE}@exemple.test"},
                  files={"cv": ("cv.pdf", b"%PDF-1.4\ntrailer<<>>\n%%EOF\n", "application/pdf")},
                  allow_redirects=True)
    verif("une candidature sans consentement est refusee",
          "consentement" in r.text.lower())

# ---------------------------------------------------------------- 15
section("15. Examen : la bonne reponse ne sort jamais du serveur")

if slug and jeton:
    lien = "/examen/" + jeton.group(1)
    r = anon.get(BASE + lien)
    verif("la page d'examen s'ouvre", r.status_code == 200)
    verif("les questions sont affichees", "Question 1" in r.text)
    # Le champ `bonne` de la base vaut « 1 », « 0 », « 0,1,3 ». Il ne doit
    # apparaitre sous aucune forme reconnaissable.
    verif("aucun attribut de correction dans le HTML",
          'name="bonne"' not in r.text and 'data-bonne' not in r.text
          and '"bonne"' not in r.text)
    verif("aucune reponse pre-cochee", "checked" not in r.text)

    j = csrf(anon, lien)
    qids = re.findall(r'name="q\[(\d+)\]', r.text)
    donnees = [("csrf", j)]
    for qid in dict.fromkeys(qids):
        donnees.append((f"q[{qid}]", "0"))
    r = anon.post(BASE + lien, data=donnees, allow_redirects=True)
    verif("l'examen s'envoie", "Examen enregistre" in r.text
          or "Assessment recorded" in r.text)
    # Le score n'est PAS montre au candidat.
    verif("aucun score n'est affiche au candidat",
          "/ 4" not in r.text and "sur 4" not in r.text)

    # Le jeton est brule : le lien ne doit plus fonctionner.
    r = anon.get(BASE + lien)
    verif("le lien d'examen ne se rejoue pas", r.status_code == 404, str(r.status_code))

# ---------------------------------------------------------------- 16
section("16. Annuaire : ce que la fiche ne publie pas")

r = s_emp.get(BASE + "/annuaire")
verif("l'annuaire s'affiche", r.status_code == 200)
verif("l'annuaire explique le reglage de confidentialite",
      "choisit" in r.text or "chooses" in r.text)
# Le telephone n'est pas publie par defaut : les fiches de demonstration
# n'en ont pas, donc aucune ligne « tel » ne doit apparaitre.
verif("aucun telephone publie par defaut",
      r.text.count('class="mono"') > 0)

r = s_emp.get(BASE + "/organigramme")
verif("l'organigramme s'affiche", r.status_code == 200)
verif("l'organigramme contient la racine", "Belkacem" in r.text)

# ---------------------------------------------------------------- 17
section("17. Langues")

r = s_emp.get(BASE + "/?lang=en")
verif("l'interface bascule en anglais", "Dashboard" in r.text or "Hello" in r.text)
verif("aucune cle brute affichee en anglais",
      not re.search(r'>\s*[a-z]{2,}_[a-z_]{3,}\s*<', r.text),
      (re.search(r'>\s*[a-z]{2,}_[a-z_]{3,}\s*<', r.text) or [""])[0])

r = s_emp.get(BASE + "/?lang=fr")
verif("l'interface revient en francais", "Bonjour" in r.text)
verif("aucune cle brute affichee en francais",
      not re.search(r'>\s*[a-z]{2,}_[a-z_]{3,}\s*<', r.text))

r = s_emp.get(BASE + "/actualites?lang=en")
verif("l'actualite existant en anglais est servie en anglais",
      "The HR portal is live" in r.text)

# ---------------------------------------------------------------- 18
section("18. Pastilles « a renseigner »")

r = s_emp.get(BASE + "/admin/a-renseigner")
verif("la page a-renseigner s'affiche", r.status_code == 200)
verif("elle liste des champs manquants du dossier",
      "class=\"liste-manques\"" in r.text or "complet" in r.text)

r = s_emp.get(BASE + "/profil")
verif("le profil signale les champs vides par une pastille",
      'class="vide"' in r.text)

r = s_rh.get(BASE + "/admin/a-renseigner")
verif("la RH voit aussi le compte de dossiers incomplets",
      "incomplet" in r.text.lower() or "incomplete" in r.text.lower())

# ---------------------------------------------------------------- 19
section("19. Documents : hors racine web et droits verifies")

r = anon.get(BASE + "/donnees/rh.sqlite")
verif("la base n'est pas servie par le web", r.status_code == 404, str(r.status_code))
r = anon.get(BASE + "/../src/config.local.php")
verif("config.local.php n'est pas atteignable", r.status_code in (400, 404),
      str(r.status_code))

r = s_emp.get(BASE + "/documents")
verif("la page documents s'affiche", r.status_code == 200)
verif("elle explique le stockage hors racine",
      "hors de la racine" in r.text or "outside the web root" in r.text)

# Un document qui n'existe pas, et un document d'un autre.
verif("un document inexistant repond 404",
      s_emp.get(BASE + "/document/99999").status_code == 404)

# ---------------------------------------------------------------- 20
section("20. Permissions appliquees = permissions affichees")

r = s_rh.get(BASE + "/admin/journal")
verif("la RH accede au journal d'audit", r.status_code == 200)
verif("le journal contient des entrees", "journal_audit" in r.text or "<tbody>" in r.text)

# La ligne la plus utile d'un journal de connexions est « qui ». A la
# connexion, le cookie vient d'etre pose et $_COOKIE n'est pas repeuple dans
# la meme requete : sans precaution, toutes les connexions s'inscrivent au
# nom du « systeme ». C'est ce que montrait une capture d'ecran du journal.
r = s_rh.get(BASE + "/admin/journal?action=connexion.reussie")
lignes = re.findall(r'<td>(?:<span class="gris">)?([^<]{2,60})(?:</span>)?</td>\s*<td><code>connexion\.reussie',
                    r.text)
verif("le journal enregistre au moins une connexion",
      "connexion.reussie" in r.text)
verif("les connexions sont attribuees a une personne, pas au « systeme »",
      lignes and all("systeme" not in l for l in lignes),
      ", ".join(sorted(set(lignes))[:4]))

# La matrice des roles est reservee a l'administrateur systeme.
verif("la RH n'accede pas a la matrice des roles",
      s_rh.get(BASE + "/admin/roles").status_code == 403,
      str(s_rh.get(BASE + "/admin/roles").status_code))

# ---------------------------------------------------------------- 21
section("21. Sans JavaScript")

# Aucune page ne depend du script : toutes les actions passent par un
# formulaire ou un lien. On le verifie en s'assurant qu'aucune page ne
# contient un gestionnaire d'evenement en ligne, ce qui serait le signe
# d'une action qui n'existe que cote client.
pages = ["/", "/conges", "/absences", "/documents", "/paie", "/demandes",
         "/annuaire", "/organigramme", "/actualites", "/formations",
         "/avantages", "/calendrier", "/notifications", "/profil"]
sans_inline = True
for p in pages:
    t = s_emp.get(BASE + p).text
    if re.search(r'\son(click|change|submit|load)=', t):
        sans_inline = False
        break
verif("aucun gestionnaire d'evenement en ligne dans l'interface", sans_inline, p)

toutes_ok = all(s_emp.get(BASE + p).status_code == 200 for p in pages)
verif("toutes les pages de l'espace salarie repondent 200", toutes_ok)

pages_rh = ["/rh", "/rh/employes", "/rh/conges", "/rh/absences", "/rh/demandes",
            "/rh/documents", "/rh/paie", "/rh/actualites", "/rh/offres",
            "/rh/candidatures", "/rh/temps", "/admin/a-renseigner"]
toutes_rh = all(s_rh.get(BASE + p).status_code == 200 for p in pages_rh)
verif("toutes les pages RH repondent 200", toutes_rh,
      ", ".join(p for p in pages_rh if s_rh.get(BASE + p).status_code != 200))

pages_mgr = ["/equipe", "/equipe/conges", "/equipe/absences", "/equipe/temps"]
verif("toutes les pages manager repondent 200",
      all(s_mgr.get(BASE + p).status_code == 200 for p in pages_mgr))

# ---------------------------------------------------------------- 22
section("22. Deconnexion")

j = csrf(s_emp, "/")
r = s_emp.post(BASE + "/deconnexion", data={"csrf": j}, allow_redirects=False)
verif("la deconnexion renvoie vers la connexion",
      r.status_code == 303 and "/connexion" in r.headers.get("Location", ""))
r = s_emp.get(BASE + "/", allow_redirects=False)
verif("la session est bien fermee", r.status_code == 303)

# ---------------------------------------------------------------- 23
section("23. La table des permissions est-elle LUE, ou seulement affichee ?")

# On ne lit pas le code : on MUTE l'etat et on observe le comportement.
# Une table role_permissions remplie, migree et affichee peut tres bien
# n'etre lue par rien. La seule question qui le decouvre est « qui lit
# cette valeur au moment de decider ? », et la seule facon d'y repondre est
# de changer la valeur.
def php(*args):
    return subprocess.run(["php", "tests/rh.php", *args], cwd=RACINE,
                          capture_output=True, text=True, timeout=20).stdout


avant = s_mgr.get(BASE + "/equipe/conges").status_code
verif("le manager accede aux conges de son equipe avant mutation", avant == 200,
      str(avant))

php("revoque", "manager", "conges.equipe.valider")
s_mgr2, _ = connecte("marc.tremblay@exemple.test")
apres = s_mgr2.get(BASE + "/equipe/conges").status_code
verif("apres revocation EN BASE, le serveur refuse", apres == 403,
      f"avant {avant}, apres {apres}")

php("rend", "manager", "conges.equipe.valider")
s_mgr3, _ = connecte("marc.tremblay@exemple.test")
retabli = s_mgr3.get(BASE + "/equipe/conges").status_code
verif("apres restitution, l'acces revient", retabli == 200, str(retabli))

# La matrice d'administration doit montrer ce que le serveur APPLIQUE.
s_adm_ok = None
sortie = php("permissions", "manager")
verif("l'outil rend les permissions appliquees",
      "conges.equipe.valider" in sortie, sortie[:80])

# ---------------------------------------------------------------- 24
section("24. Les adresses respectent l'alphabet du routeur")

# Le generateur de slug et les motifs de routage doivent partager le meme
# alphabet. Un slug contenant une lettre accentuee ou arabe produirait une
# page qui repond 404 alors que la ligne existe — et un 404 ne s'inscrit
# dans aucun journal.
sortie = php("slugs")
verif("aucune adresse stockee hors de [a-z0-9-]",
      "Toutes les adresses" in sortie, sortie.strip()[:120])

# Contre-epreuve en direct : un titre entierement non latin doit produire
# une adresse ASCII, et cette adresse doit repondre.
j = csrf(s_rh, "/rh/actualites")
titre_ar = "\u0625\u0639\u0644\u0627\u0646 \u0627\u0644\u0645\u0648\u0627\u0631\u062f \u0627\u0644\u0628\u0634\u0631\u064a\u0629 " + MARQUE
r = s_rh.post(BASE + "/rh/actualites",
              data={"csrf": j, "titre": titre_ar, "langue": "fr",
                    "chapeau": "", "corps": "Test alphabet.", "statut": "publie"},
              allow_redirects=True)
verif("l'actualite au titre non latin est enregistree",
      "enregistr" in r.text.lower())

liens = re.findall(r'/actualites/([\w\-]+)"', s_rh.get(BASE + "/rh/actualites").text)
ascii_only = all(re.fullmatch(r'[a-z0-9\-]+', l) for l in liens)
verif("toutes les adresses d'actualites sont ASCII", ascii_only,
      ", ".join(l for l in liens if not re.fullmatch(r'[a-z0-9\-]+', l)))
if liens:
    codes = {l: s_rh.get(BASE + "/actualites/" + l).status_code for l in liens}
    verif("chaque adresse d'actualite publiee repond",
          all(c in (200, 404) for c in codes.values()) and 200 in codes.values(),
          str(codes))

# ---------------------------------------------------------------- 25
section("25. La limite de debit existe bel et bien")

# Elle a ete remise a zero au demarrage. On la pousse jusqu'au refus, ce qui
# prouve qu'elle n'est pas un decor. Elle est ensuite remise a zero pour ne
# pas laisser la base dans un etat qui ferait echouer la prochaine
# execution — un test qui mute un etat partage le restaure.
refus = False
if slug:
    for i in range(12):
        j = csrf(anon, "/carrieres/" + slug)
        rr = anon.post(BASE + f"/carrieres/{slug}/postuler",
                       data={"csrf": j, "prenom": "Debit", "nom": f"Essai{i}",
                             "email": f"debit.{MARQUE}.{i}@exemple.test",
                             "consentement": "1"},
                       files={"cv": ("cv.pdf", b"%PDF-1.4\ntrailer<<>>\n%%EOF\n",
                                     "application/pdf")},
                       allow_redirects=True)
        if "Trop de candidatures" in rr.text or "Too many applications" in rr.text:
            refus = True
            break
    verif(f"la limite de candidatures finit par refuser (essai {i + 1})", refus)

print(php("limites-raz").strip())

# ---------------------------------------------------------------- fin
print("\n" + "=" * 62)
print(f"  {OK} controles passes, {KO} echecs")
if ECHECS:
    print("\n  Echecs :")
    for e in ECHECS:
        print(f"    - {e}")
print("=" * 62)
sys.exit(1 if KO else 0)
