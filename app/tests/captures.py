#!/usr/bin/env python3
"""
Captures d'ecran du portail, pour relecture.

    python3 tests/captures.py [http://127.0.0.1:8842]

Toutes les images font 1280x720 (ou 390x780 pour le mobile). Aucune capture
pleine page : une page longue produit une image de plusieurs milliers de
pixels de haut, et l'API qui les transporte la refuse. On defile et on
prend plusieurs vues quand c'est necessaire.

Le script verifie aussi, page par page :
  - qu'aucune erreur ne remonte dans la console du navigateur ;
  - qu'aucune page ne deborde horizontalement, ni en 1280 ni en 390.
Ces deux controles ne se voient pas sur une capture : on les mesure.
"""

import os
import sys

from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8842").rstrip("/")
SORTIE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "apercus")
os.makedirs(SORTIE, exist_ok=True)

MDP = "demonstration-2026"
COMPTES = {
    "employe": "youcef.berrada@exemple.test",
    "decentralise": "sofia.marchand@exemple.test",
    "manager": "marc.tremblay@exemple.test",
    "rh": "leila.hamdani@exemple.test",
}

# (fichier, compte, chemin, defilement en pixels)
VUES = [
    ("rh-01-carrieres",        None,           "/carrieres", 0),
    ("rh-02-offre",            None,           None, 0),          # resolue plus bas
    ("rh-03-connexion",        None,           "/connexion", 0),
    ("rh-04-tableau-employe",  "employe",      "/", 0),
    ("rh-05-tableau-suite",    "employe",      "/", 620),
    ("rh-06-profil",           "employe",      "/profil", 0),
    ("rh-07-profil-perso",     "employe",      "/profil", 560),
    ("rh-08-bancaire-reauth",  "employe",      "/profil/bancaire", 0),
    ("rh-09-conges",           "employe",      "/conges", 0),
    ("rh-10-conges-historique","employe",      "/conges", 520),
    ("rh-11-absences",         "employe",      "/absences", 0),
    ("rh-12-documents",        "employe",      "/documents", 0),
    ("rh-13-paie",             "employe",      "/paie", 0),
    ("rh-14-demandes",         "employe",      "/demandes", 0),
    ("rh-15-demande-nouvelle", "employe",      "/demandes/nouvelle", 0),
    ("rh-16-annuaire",         "employe",      "/annuaire", 0),
    ("rh-17-organigramme",     "employe",      "/organigramme", 0),
    ("rh-18-actualites",       "employe",      "/actualites", 0),
    ("rh-19-formations",       "employe",      "/formations", 0),
    ("rh-20-avantages",        "employe",      "/avantages", 0),
    ("rh-21-calendrier",       "employe",      "/calendrier", 0),
    ("rh-22-notifications",    "employe",      "/notifications", 0),
    ("rh-23-a-renseigner",     "employe",      "/admin/a-renseigner", 0),
    ("rh-24-temps",            "decentralise", "/temps", 0),
    ("rh-25-temps-semaine",    "decentralise", "/temps", 380),
    ("rh-26-equipe",           "manager",      "/equipe", 0),
    ("rh-27-equipe-conges",    "manager",      "/equipe/conges", 0),
    ("rh-28-equipe-absences",  "manager",      "/equipe/absences", 0),
    ("rh-29-equipe-temps",     "manager",      "/equipe/temps", 0),
    ("rh-30-rh-tableau",       "rh",           "/rh", 0),
    ("rh-31-rh-tableau-suite", "rh",           "/rh", 560),
    ("rh-32-rh-employes",      "rh",           "/rh/employes", 0),
    ("rh-33-rh-employe",       "rh",           "/rh/employes/2", 0),
    ("rh-34-rh-paie",          "rh",           "/rh/paie", 0),
    ("rh-35-rh-paie-saisie",   "rh",           "/rh/paie", 900),
    ("rh-36-rh-documents",     "rh",           "/rh/documents", 0),
    ("rh-37-rh-actualites",    "rh",           "/rh/actualites", 0),
    ("rh-38-rh-offres",        "rh",           "/rh/offres", 0),
    ("rh-39-rh-candidatures",  "rh",           "/rh/candidatures", 0),
    ("rh-40-rh-absences",      "rh",           "/rh/absences", 0),
    ("rh-41-journal-audit",    "rh",           "/admin/journal", 0),
    ("rh-42-anglais",          "employe",      "/?lang=en", 0),
    ("rh-43-annuaire-anglais", "employe",      "/annuaire?lang=en", 0),
]

MOBILES = [
    ("rh-44-mobile-tableau",   "employe",      "/"),
    ("rh-45-mobile-conges",    "employe",      "/conges"),
    ("rh-46-mobile-temps",     "decentralise", "/temps"),
    ("rh-47-mobile-carrieres", None,           "/carrieres"),
]

erreurs_console = []
debordements = []


def connecte(page, compte):
    page.goto(BASE + "/connexion", wait_until="domcontentloaded")
    page.fill('input[name="email"]', COMPTES[compte])
    page.fill('input[name="mot_de_passe"]', MDP)
    page.click('button[type="submit"]')
    page.wait_for_load_state("domcontentloaded")


def controle_debordement(page, nom, largeur):
    trop = page.evaluate(
        "() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1")
    if trop:
        detail = page.evaluate("() => document.documentElement.scrollWidth")
        debordements.append(f"{nom} ({largeur} px -> {detail} px)")


with sync_playwright() as p:
    nav = p.chromium.launch()

    # Adresse d'une offre publiee, pour la capture 02.
    ctx0 = nav.new_context(viewport={"width": 1280, "height": 720})
    pg0 = ctx0.new_page()
    pg0.goto(BASE + "/carrieres", wait_until="domcontentloaded")
    lien = pg0.eval_on_selector_all(
        'a[href^="/carrieres/"]', "els => els.length ? els[0].getAttribute('href') : null")
    ctx0.close()

    sessions = {}
    for compte in COMPTES:
        ctx = nav.new_context(viewport={"width": 1280, "height": 720})
        page = ctx.new_page()
        page.on("console", lambda m, c=compte: erreurs_console.append(f"{c}: {m.text}")
                if m.type == "error" else None)
        page.on("pageerror", lambda e, c=compte: erreurs_console.append(f"{c}: {e}"))
        connecte(page, compte)
        sessions[compte] = page

    ctx_anon = nav.new_context(viewport={"width": 1280, "height": 720})
    page_anon = ctx_anon.new_page()
    page_anon.on("console",
                 lambda m: erreurs_console.append(f"anon: {m.text}") if m.type == "error" else None)
    page_anon.on("pageerror", lambda e: erreurs_console.append(f"anon: {e}"))
    sessions[None] = page_anon

    n = 0
    for nom, compte, chemin, defil in VUES:
        if chemin is None:
            chemin = lien
            if not chemin:
                continue
        page = sessions[compte]
        page.goto(BASE + chemin, wait_until="networkidle")
        controle_debordement(page, nom, 1280)
        if defil:
            page.evaluate(f"window.scrollTo(0, {defil})")
            page.wait_for_timeout(120)
        page.screenshot(path=os.path.join(SORTIE, nom + ".png"))
        n += 1
        print(f"  {nom}.png")

    # Mobile : 390x780, la taille d'un telephone courant.
    for nom, compte, chemin in MOBILES:
        ctx = nav.new_context(viewport={"width": 390, "height": 780})
        page = ctx.new_page()
        page.on("console",
                lambda m, c=nom: erreurs_console.append(f"{c}: {m.text}") if m.type == "error" else None)
        if compte:
            connecte(page, compte)
        page.goto(BASE + chemin, wait_until="networkidle")
        controle_debordement(page, nom, 390)
        page.screenshot(path=os.path.join(SORTIE, nom + ".png"))
        ctx.close()
        n += 1
        print(f"  {nom}.png")

    nav.close()

print(f"\n{n} captures dans {SORTIE}")
print("Erreurs de console :", len(erreurs_console))
for e in erreurs_console[:12]:
    print("   ", e)
print("Debordements horizontaux :", len(debordements))
for d in debordements:
    print("   ", d)

sys.exit(1 if (erreurs_console or debordements) else 0)
