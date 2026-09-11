# -*- coding: utf-8 -*-
"""
Fabrique l'extension WordPress « Portail RH » installable.

L'application n'est PAS recopiee a la main : elle est prise telle quelle dans
../rh-portail. L'extension est une enveloppe, pas une deuxieme version.

  python3 build.py   ->  portail-rh-1.0.0.zip
"""
import os
import shutil
import zipfile

RACINE = os.path.dirname(os.path.abspath(__file__))
APP = os.path.normpath(os.path.join(RACINE, "..", "rh-portail"))
VERSION = "1.0.0"
NOM = "portail-rh"

# CE QUI NE PART PAS. Chaque ligne a une raison.
EXCLUS_FICHIERS = {
    # LE FICHIER DE SECRETS. Il porte la cle qui dechiffre les coordonnees
    # bancaires et le sel des sessions. Livrer ce fichier, c'est livrer la
    # cle avec le coffre. L'extension en genere un neuf a l'activation.
    "config.local.php",
    ".gitignore",
    ".DS_Store",
}
EXCLUS_DOSSIERS = {
    "tests",      # la suite de controles n'a rien a faire en production
    "apercus",    # captures d'ecran
    "donnees",    # la base SQLite de developpement et ses donnees de demo
    "__pycache__",
    ".git",
}


def collecte():
    fichiers = []
    for dossier, sous, noms in os.walk(APP):
        sous[:] = [d for d in sous if d not in EXCLUS_DOSSIERS]
        for n in noms:
            if n in EXCLUS_FICHIERS:
                continue
            chemin = os.path.join(dossier, n)
            fichiers.append((chemin, os.path.relpath(chemin, APP)))
    return fichiers


if __name__ == "__main__":
    fichiers = collecte()

    # --- controles AVANT d'ecrire quoi que ce soit ------------------------
    noms = [rel for _, rel in fichiers]
    fuite = [n for n in noms if "config.local" in n]
    assert not fuite, f"LE FICHIER DE SECRETS PARTIRAIT DANS LE ZIP : {fuite}"
    assert any(n.endswith("public/index.php") for n in noms), \
        "le point d'entree de l'application est absent"
    assert any(n.endswith("outils/installer.php") for n in noms), \
        "l'installeur est absent"

    entree = os.path.join(RACINE, "plugin", "portail-rh.php")
    with open(entree, encoding="utf-8") as f:
        tete = f.read()
    # WordPress refuse une archive dont aucun .php de premier niveau ne porte
    # d'en-tete « Plugin Name ». C'est exactement l'erreur qu'il a eue.
    assert "Plugin Name:" in tete, "l'en-tete d'extension manque"

    cible = os.path.join(RACINE, f"{NOM}-{VERSION}.zip")
    if os.path.exists(cible):
        os.remove(cible)

    with zipfile.ZipFile(cible, "w", zipfile.ZIP_DEFLATED) as z:
        z.write(entree, f"{NOM}/portail-rh.php")
        # L'application, en entier, sous app/
        for chemin, rel in fichiers:
            z.write(chemin, f"{NOM}/app/{rel}")
        # Un index.php muet dans chaque dossier n'est pas necessaire : rien
        # sous app/ n'est atteignable, l'extension route tout par elle-meme.
        z.writestr(f"{NOM}/readme.txt",
                   "=== Portail RH ===\n"
                   f"Stable tag: {VERSION}\n\n"
                   "Le portail RH et recrutement, installable depuis WordPress.\n"
                   "Apres activation : Outils > Portail RH > Installer maintenant.\n"
                   "Le portail repond ensuite sur /rh/.\n")

    taille = os.path.getsize(cible)
    with zipfile.ZipFile(cible) as z:
        dedans = z.namelist()
    print(f"{cible}")
    print(f"  {len(dedans)} fichiers, {taille / 1024:.0f} Ko")
    print(f"  entree : {NOM}/portail-rh.php")
    print(f"  application : {sum(1 for n in dedans if n.startswith(NOM + '/app/'))} fichiers")
    assert not [n for n in dedans if "config.local" in n], "fuite de secrets"
    print("  aucun fichier de secrets : verifie")
