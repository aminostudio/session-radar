# Amino Orbit · Préparation à une première soumission WordPress.org

**État au 14 novembre 2026 :** dépôt privé, version publiée sur GitHub `0.3.7`, correctif `0.3.8` en PR #5, **aucune soumission WordPress.org effectuée**. Ce document est une vérification préalable, pas une autorisation de publication.

## Processus officiel (sources WordPress.org)

- [Soumettre un plugin](https://wordpress.org/plugins/developers/add/) après connexion à un compte WordPress.org.
- [FAQ développeurs, mise à jour novembre 2026](https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/) : ZIP installable de **moins de 10 Mo**, fini et sans outils de développement superflus ; revue manuelle ; une seule soumission active à la fois pour un développeur ordinaire.
- [Conditions de publication](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) : licence GPL-compatible, respect de la vie privée, pas de suivi sans consentement applicable, version complète, pas de série de micro-versions publiques pour manipuler « Recently Updated ».
- [Plugin Check et 2FA](https://make.wordpress.org/plugins/2024/10/01/plugin-check-and-2fa-now-mandatory-for-new-plugin-submissions/) : contrôle Plugin Check et **2FA** requis pour les propriétaires et contributeurs de plugins.
- Après acceptation, WordPress.org attribue un **dépôt SVN** ; le plugin devient accessible dans son répertoire après publication des fichiers dans SVN, et non du seul fait de déposer le ZIP dans GitHub.

## Vérification de notre projet

| Point | État | Preuve/mesure à prendre |
| --- | --- | --- |
| Licence | Préliminairement conforme | `GPL-2.0-or-later` dans le fichier principal, le readme et le texte LICENSE |
| ZIP | Conforme en taille et installation privée | v0.3.7 : 13 fichiers, environ 106 Ko ; un seul répertoire `amino-orbit/` ; installable sur #LAB |
| Test PHP | Confirmé | PHP 8.0, 8.2 et 8.4 dans GitHub Actions |
| Compatibilité WordPress | Confirmée sur environnements testés | WordPress 6.4/PHP 8.0 et WordPress récent via CI ; langues FR/ES/EN |
| Plugin Check | Aucun échec bloquant de l'outil | 0 erreur, 3 avertissements connus, en modes statique et runtime ; à revoir lors de la soumission |
| Vie privée | Améliorée, revue finale nécessaire | Activité désactivée par défaut, suivi activable, arrêt + purge, export/effacement via outils natifs de WordPress ; consentement et information des utilisateurs restent du ressort de l'exploitant |
| Migration depuis versions anciennes | En cours de finalisation | PR #5 : purge unique des traces historiques avant la première activation explicite du suivi |
| Accessibilité | Partiellement contrôlée | Automatisation axe-core sans violations dans les éléments testés ; **test humain de lecteur d'écran et zoom réel non réalisé** |
| Compte propriétaire WordPress.org | **À confirmer par le propriétaire** | Le `readme.txt` indique `Contributors: aminostudio`, mais une [fiche publique « Amino Studio » portant le compte `@aminogfx`](https://profiles.wordpress.org/aminogfx/) existe. Ne pas supposer qu'un identifiant équivaut à l'autre sans confirmation. Modifier le readme si nécessaire. |
| Vérification du slug `amino-orbit` | **Non vérifiée par la plateforme de dépôt** | Une recherche publique ne constitue pas une réservation. Le formulaire WordPress.org et sa revue déterminent la disponibilité finale. |
| 2FA WordPress.org | **À confirmer sur le compte** | Vérifier la double authentification sur le compte utilisé pour soumettre et committer |
| Maintenance et assistance | À organiser | Adresse de contact fiable, calendrier de mises à jour, réponses dans le forum de support WordPress.org |
| Soumission WordPress.org | Non commencée | Décision explicite du propriétaire nécessaire avant envoi |

## Séquence courte, sans surproduction de versions publiques

1. Finaliser la PR #5, vérifier le correctif de migration sur WordPress, et intégrer sur `main`.
2. Vérifier le bon **identifiant WordPress.org** à inscrire dans `Contributors` et l'activation de **2FA**. Ne jamais publier de secrets ni de données de connexion sur GitHub.
3. Faire un test manuel rapide : clavier complet, zoom navigateur à 200 % et 400 %, et un lecteur d'écran ; documenter les trois observations axe-core à vérifier.
4. Examiner les mentions sur le suivi des collaborateurs dans le contexte prévu et retenir la base légale applicable avant utilisation dans un environnement professionnel.
5. Préparer un unique ZIP destiné à WordPress.org, valider le `readme.txt`, et soumettre **un seul plugin** sur [wordpress.org/plugins/developers/add/](https://wordpress.org/plugins/developers/add/).
6. Après approbation, publier le `trunk/` et un tag de version dans le dépôt SVN attribué, puis gérer les changements publics en versions cohérentes.

**L'état actuel est « proche de la soumission », pas « approuvé » et pas « prêt pour tous les contextes ».** Les versions privées sur GitHub peuvent continuer d'évoluer plus vite que les releases publiques sur WordPress.org.
