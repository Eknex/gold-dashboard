# gold-dashboard
Inventaire Métaux Précieux ( Or et argent ) – Application d’inventaire et suivi des prix

Gérez gratuitement et facilement votre collection d’or et/ou d’argent.

Application web open source : inventaire complet, suivi du cours du jour, statistiques, interface moderne, et respect de la vie privée (tout reste chez vous).

→ Parfait pour collectionneurs, investisseurs ou passionnés !

💗 Si ce projet vous est utile, vous pouvez me soutenir ici : https://buymeacoffee.com/eknex



<img width="1550" height="555" alt="image" src="https://github.com/user-attachments/assets/4a9d714c-9a8b-416a-9d8d-9c89b1d91bd7" />




------------------------------------------------------------------------------------------------------------------------------------------------------------------


📦 Fichiers principaux
1. index.html
Interface web principale – tout se gère via cette seule page locale ou en ligne.

2. api.php
Mini API PHP à déposer sur ton serveur, qui gère:

La sauvegarde locale et sécurisée de tes données (pas de cloud)

L’authentification par code PIN

🚀 Installation rapide
Prérequis
Un hébergement web avec PHP (même une petite VM/LAMP locale ou mutualisé)

Pas besoin de base de données !

Si besoin, voir en bas pour tester sans hébergement 

Étapes
Dépose les deux fichiers index.html et api.php dans le même dossier sur ton hébergement web.

Ouvre index.html dans ton navigateur (depuis le serveur, pas seulement en local).

Choisis/configure un code PIN à la première ouverture.

Utilise, ajoute, modifie, sauvegarde… tout est enregistré côté serveur dans data.json (créé automatiquement).

🛠️ Utilisation
Toute l’interface se trouve sur index.html

Le stockage est local sur votre serveur (les fichiers data.json et pin.hash sont créés à côté de api.php)

Si tu veux remettre à zéro : supprime data.json et pin.hash côté serveur, relance la page

🔒 Sécurité & vie privée
Le PIN est stocké hashé, jamais en clair

Aucune donnée n’est envoyée à un service externe

Tu restes propriétaire de toutes tes données, rien dans le cloud (sauf ton serveur !)

🆘 Problèmes fréquents
“Erreur de connexion au serveur” → Vérifie les permissions sur le dossier, que PHP fonctionne bien et que les deux fichiers sont dans le même dossier

“Un PIN est déjà configuré” → Supprimer pin.hash



------------------------------------------------------------------------------------------------------------------------------------------------------------------
Tu ne peux pas utiliser toutes les fonctionnalités (sauvegarde, PIN, etc.) de ton projet en ouvrant simplement index.html directement depuis ton PC (par double clic ou “Ouvrir avec le navigateur”) si le fichier api.php n’est pas accessible via un serveur web.

Explications :
La page index.html doit communiquer avec api.php (en AJAX/fetch)

Depuis le protocole file://, les navigateurs bloquent la plupart des requêtes (problème de CORS, sécurité et accès réseau local)

Résultat : la connexion au serveur échoue systématiquement (“Connexion au serveur” bloquée…)

💡 Solutions
1. Installer un mini serveur Web local
Exemple : XAMPP, WampServer, MAMP, Laragon, ou Visual Studio Code + extension “Live Server”, ou simplement le serveur Python intégré

Exemple avec Python (préinstallé sur beaucoup de machines) :
Place index.html et api.php dans un dossier, par exemple : C:\\mon-inventaire

Ouvre un terminal/cmd dans ce dossier

Tape :

Pour Python 3 :

text
python -m http.server 8000
Va dans ton navigateur à : http://localhost:8000/index.html

Le serveur gère les requêtes entre index.html et api.php correctement.

2. Utiliser un hébergement web (même gratuit ou sur un NAS type Unraid)
Dépose les deux fichiers sur un espace Apache/Nginx (ou équivalent)

Accède à la page par une vraie URL HTTP (et plus par file://)



--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------



Historique des versions
Version 4.1

Ajout de l'affichage du cours du jour en direct.
Correction et fiabilisation de l'ajout automatique du cours du jour à la connexion.
Version 4.0

Déplacement de la gestion des colonnes dans les paramètres.
Version 3.9

Correction du bug d'affichage des prix et graphiques.
Menu des colonnes de l'inventaire rendu compact.
Version 3.8

Menu déroulant pour les colonnes.
Tri ajouté à l'historique des prix.
Version 3.7

Menu déroulant pour les colonnes de l'inventaire.
Ajout du tri sur l'historique des prix.
Version 3.6

Ajout de tuiles de valeur par matière sur le tableau de bord.
Version 3.5

Ajout du sélecteur de période pour le graphique d'évolution.
Ajout des colonnes de poids dans l'inventaire.
Bouton d'ajout rapide du cours du jour.



☕ Soutenir
Ce projet est open source.
Si tu l’apprécies, tu peux offrir un café ☕ ici : buymeacoffee.com/eknex
