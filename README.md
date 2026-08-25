# Qribly

Qribly est une marketplace locale marocaine permettant de découvrir, publier et réserver des produits ou des services. L’application combine un catalogue national, une recherche géographique, une carte interactive, des créneaux de réservation, des favoris, des avis et une messagerie en temps réel.

> **État du projet** — développement actif sur la branche `develop`. Le projet est utilisable en local avec un jeu de démonstration complet, mais certaines fonctions décrites dans la section [Limites connues](#limites-connues) restent à finaliser avant une mise en production.

## Sommaire

- [Comprendre le produit](#comprendre-le-produit)
- [Architecture technique](#architecture-technique)
- [Démarrage rapide avec Docker](#démarrage-rapide-avec-docker)
- [Données et comptes de démonstration](#données-et-comptes-de-démonstration)
- [Guide détaillé des scénarios de test](#guide-détaillé-des-scénarios-de-test)
- [Tests automatisés et qualité](#tests-automatisés-et-qualité)
- [Installation manuelle](#installation-manuelle)
- [API et structure du dépôt](#api-et-structure-du-dépôt)
- [Workflow de contribution](#workflow-de-contribution)
- [Dépannage](#dépannage)
- [Limites connues](#limites-connues)

## Comprendre le produit

### Problème traité

Qribly facilite les échanges de proximité au Maroc. Un utilisateur peut rechercher une annonce partout au Maroc, dans une ville précise ou autour de sa position, puis contacter le propriétaire ou réserver un créneau lorsqu’il s’agit d’un service.

### Profils fonctionnels

Qribly ne sépare pas techniquement les utilisateurs en rôles permanents. Un même compte peut agir comme client ou comme prestataire selon le contexte.

| Profil fonctionnel | Actions principales                                                                                                             |
| ------------------ | ------------------------------------------------------------------------------------------------------------------------------- |
| Visiteur           | Parcourir les annonces, rechercher, filtrer, consulter une offre et ses avis                                                    |
| Client             | Ajouter aux favoris, contacter un prestataire, réserver un service, annuler sa réservation et laisser un avis après réalisation |
| Prestataire        | Publier une annonce, définir ses disponibilités, confirmer ou annuler une demande et terminer une prestation réalisée           |

### Concepts importants

- une **annonce** est un produit ou un service publié par un utilisateur ;
- un **produit** peut être actif, réservé, vendu ou inactif ;
- un **service** possède une durée et peut proposer des créneaux selon les disponibilités du prestataire ;
- une **réservation** bloque toute plage qui chevauche sa durée, pas uniquement son heure de début ;
- les heures de réservation sont présentées selon l’heure du Maroc ;
- la distance est calculée avec PostGIS à partir des coordonnées géographiques de l’annonce.

### Cycle de vie d’une réservation

```text
Demande créée (pending)
    ├── confirmée par le prestataire (confirmed)
    │       ├── terminée après la fin du rendez-vous (completed)
    │       └── annulée (cancelled)
    └── annulée (cancelled)

completed → le client peut laisser un avis
```

Une réservation confirmée ne peut pas être terminée avant `heure de début + durée`. Cette règle est appliquée dans l’interface et contrôlée de nouveau par l’API.

### Fonctionnalités disponibles

- inscription, connexion et authentification API avec Laravel Sanctum ;
- création et modification d’annonces produit ou service ;
- ajout de cinq photos maximum aux formats JPG, PNG, WEBP ou AVIF ;
- sélection recherchable d’une ville et positionnement précis sur une carte ;
- consultation des annonces partout au Maroc ;
- recherche dans une ville, autour d’une ville ou autour de la position actuelle ;
- filtres par catégorie, type, statut et prix ;
- vues liste, liste avec carte et carte immersive ;
- disponibilités hebdomadaires des prestataires ;
- réservation avec gestion de la durée et des conflits ;
- favoris, avis et notifications ;
- conversations et messages en temps réel avec Laravel Reverb ;
- classement sémantique optionnel des annonces avec un service Python ;
- données de démonstration françaises avec photos locales.

## Architecture technique

```mermaid
flowchart LR
    U[Utilisateur] --> V[Vue 3 + TypeScript]
    V -->|API REST| L[Laravel 13]
    V <-->|WebSocket| R[Laravel Reverb]
    L --> P[(PostgreSQL + PostGIS)]
    L --> Q[Queue Laravel]
    Q --> R
    L -->|Classement optionnel| A[FastAPI + MiniLM]
    L --> S[Stockage local des photos]
```

| Couche               | Technologies principales                                                       | Responsabilité                                                |
| -------------------- | ------------------------------------------------------------------------------ | ------------------------------------------------------------- |
| Frontend             | Vue 3, TypeScript, Vite, Tailwind CSS, Pinia, Vue Router, Headless UI, Leaflet | Interface, navigation, cartes et état client                  |
| Backend              | PHP 8.4, Laravel 13, Sanctum, Reverb                                           | API, autorisations, réservations, notifications et temps réel |
| Données              | PostgreSQL 16, PostGIS                                                         | Données relationnelles, coordonnées et calcul des distances   |
| Recherche sémantique | FastAPI, Sentence Transformers, MiniLM multilingue                             | Classement optionnel des résultats textuels                   |
| Infrastructure       | Docker Compose, Nginx en production                                            | Environnement local et préparation du déploiement             |
| Qualité              | PHPUnit, Laravel Pint, Vue TSC, Vite, GitHub Actions                           | Tests, formatage, compilation et intégration continue         |

## Démarrage rapide avec Docker

Docker est la méthode recommandée pour les membres de l’équipe comme pour les contributeurs externes.

### Prérequis

- Git ;
- Docker Desktop avec Docker Compose ;
- ports `5173`, `8000`, `8001`, `8080` et `5432` disponibles.

### Installation

```bash
git clone https://github.com/anass-elouali/qribly.git
cd qribly
git switch develop
docker compose up --build -d
```

Le premier démarrage peut prendre plusieurs minutes. Le backend installe Composer, crée son fichier `.env`, génère la clé Laravel, exécute les migrations et charge les seeders. Le service IA télécharge son modèle lors de sa première utilisation.

Créer ensuite le lien public utilisé par les photos :

```bash
docker compose exec backend php artisan storage:link
```

### Vérifier l’installation

```bash
docker compose ps
docker compose logs --tail=50 backend
```

Les conteneurs `postgres` et `backend` doivent être sains. Les autres services doivent être démarrés sans boucle d’erreur.

| Service            | Adresse locale                         | Vérification rapide                      |
| ------------------ | -------------------------------------- | ---------------------------------------- |
| Application Vue    | <http://localhost:5173>                | La page d’accueil s’affiche              |
| API Laravel        | <http://localhost:8000/api/categories> | Une réponse JSON est retournée           |
| Laravel Reverb     | `ws://localhost:8080`                  | Les messages arrivent sans actualisation |
| Service IA         | <http://localhost:8001/health>         | Le service FastAPI retourne `status: ok` |
| PostgreSQL/PostGIS | `localhost:5432`                       | Le conteneur est marqué sain             |

### Commandes courantes

```bash
# Démarrer ou reconstruire l’environnement
docker compose up --build -d

# Suivre les journaux
docker compose logs -f backend frontend queue reverb

# Arrêter les services sans supprimer la base
docker compose down

# Redémarrer un service
docker compose restart backend
```

## Données et comptes de démonstration

### Réinitialiser l’environnement de test

Pour repartir du même état avant une campagne de test :

```bash
docker compose exec backend php artisan migrate:fresh --seed --force
docker compose exec backend php artisan storage:link
```

> **Attention :** `migrate:fresh` supprime définitivement toutes les données de la base locale `qribly`. Ne jamais exécuter cette commande sur une base partagée ou de production.

Après une réinitialisation, les anciens jetons d’authentification ne sont plus valides. Se déconnecter puis se reconnecter dans le navigateur.

Le jeu de démonstration contient :

- 7 utilisateurs ;
- 8 catégories ;
- 12 annonces et 60 photos locales ;
- 26 disponibilités hebdomadaires ;
- 11 réservations couvrant tous les statuts ;
- 5 avis, 8 favoris, 3 conversations et 10 messages ;
- 6 notifications.

Les dates de réservation sont calculées relativement au jour d’exécution du seeder. Il faut identifier les scénarios par le titre de l’offre et le statut, pas par une date fixe ou un identifiant numérique.

### Comptes de test

Tous les comptes utilisent le mot de passe `password`.

| Nom               | E-mail                    | Données utiles pour les tests                                        |
| ----------------- | ------------------------- | -------------------------------------------------------------------- |
| Nadia El Mansouri | `prestataire@qribly.test` | Prestataire principal, 3 services, demandes en attente et confirmées |
| Mehdi Berrada     | `client@qribly.test`      | Client principal, réservations, favoris, messages et notifications   |
| Lina Tahiri       | `lina@qribly.test`        | Second client, réservations réparties chez plusieurs prestataires    |
| Youssef Amrani    | `youssef@qribly.test`     | Services de transport à Marrakech et Ourika                          |
| Sara Benali       | `sara@qribly.test`        | Service et produit électronique à Casablanca                         |
| Omar Alaoui       | `omar@qribly.test`        | Cours de mathématiques à Rabat                                       |
| Salma Idrissi     | `salma@qribly.test`       | Coiffure, caftan et pâtisseries                                      |

Ces identifiants sont exclusivement réservés au développement et à la démonstration.

### Catalogue seedé

| Annonce                                    | Propriétaire | Type    | Ville      | Statut  | Durée  |
| ------------------------------------------ | ------------ | ------- | ---------- | ------- | ------ |
| Grand ménage d’un appartement              | Nadia        | Service | Marrakech  | Actif   | 2 h    |
| Entretien et nettoyage de climatisation    | Nadia        | Service | Marrakech  | Actif   | 1 h 30 |
| Chef à domicile — menu marocain            | Nadia        | Service | Agadir     | Actif   | 3 h    |
| Réparation de smartphone                   | Sara         | Service | Casablanca | Actif   | 1 h    |
| PC portable reconditionné                  | Sara         | Produit | Casablanca | Réservé | —      |
| Cours de soutien en mathématiques          | Omar         | Service | Rabat      | Actif   | 1 h 30 |
| Coiffure et brushing à domicile            | Salma        | Service | Rabat      | Actif   | 1 h    |
| Livraison express de petits colis          | Youssef      | Service | Marrakech  | Actif   | 1 h    |
| Location de VTT dans la vallée de l’Ourika | Youssef      | Service | Ourika     | Actif   | 4 h    |
| Panier de légumes bio de saison            | Youssef      | Produit | Marrakech  | Actif   | —      |
| Caftan marocain brodé à la main            | Salma        | Produit | Tanger     | Vendu   | —      |
| Assortiment de pâtisseries marocaines      | Salma        | Produit | Essaouira  | Inactif | —      |

### Réservations seedées

| Client | Offre                         | Prestataire | Statut                     | Créneau relatif                         |
| ------ | ----------------------------- | ----------- | -------------------------- | --------------------------------------- |
| Mehdi  | Grand ménage d’un appartement | Nadia       | En attente                 | Prochain lundi à 09:00                  |
| Lina   | Réparation de smartphone      | Sara        | En attente                 | Prochain mardi à 10:00                  |
| Mehdi  | Entretien de climatisation    | Nadia       | Confirmée                  | Prochain mercredi à 13:00               |
| Lina   | Location de VTT               | Youssef     | Confirmée                  | Prochain vendredi à 09:00               |
| Mehdi  | Cours de mathématiques        | Omar        | Terminée                   | Mercredi précédent à 15:00              |
| Lina   | Coiffure et brushing          | Salma       | Terminée                   | Samedi précédent à 11:00                |
| Lina   | Grand ménage d’un appartement | Nadia       | Terminée                   | Lundi d’une semaine précédente à 13:00  |
| Mehdi  | Livraison de petits colis     | Youssef     | Terminée                   | Jeudi précédent à 10:00                 |
| Lina   | Chef à domicile               | Nadia       | Terminée                   | Samedi d’une semaine précédente à 12:00 |
| Mehdi  | Livraison de petits colis     | Youssef     | Annulée par le client      | Prochain jeudi à 14:00                  |
| Lina   | Chef à domicile               | Nadia       | Annulée par le prestataire | Prochain samedi à 12:00                 |

## Guide détaillé des scénarios de test

### Règles de campagne

1. Réinitialiser la base avant une campagne complète.
2. Noter le navigateur, la taille d’écran et le commit testé.
3. Ne pas utiliser les identifiants numériques des annonces ou réservations comme référence stable.
4. Utiliser deux fenêtres ou une fenêtre privée lorsqu’un scénario implique un client et un prestataire.
5. Après un scénario qui modifie les données, continuer avec l’état obtenu ou réinitialiser la base avant un scénario indépendant.

Modèle de résultat conseillé :

| Champ               | Valeur                                     |
| ------------------- | ------------------------------------------ |
| Scénario            | `S-XX — Nom du scénario`                   |
| Commit              | Résultat de `git rev-parse --short HEAD`   |
| Environnement       | Docker local / autre                       |
| Navigateur et écran | Exemple : Chrome, 1440 × 900               |
| Résultat            | Réussi / Échoué / Bloqué                   |
| Preuve              | Capture, réponse API ou message d’erreur   |
| Remarque            | Étapes exactes pour reproduire un problème |

### S-00 — Démarrage et santé des services

**Objectif :** vérifier que l’environnement est prêt avant les tests fonctionnels.

**Prérequis :** dépôt cloné et Docker Desktop démarré.

**Étapes :**

1. Exécuter `docker compose up --build -d`.
2. Exécuter `docker compose ps`.
3. Ouvrir <http://localhost:8000/api/categories>.
4. Ouvrir <http://localhost:5173>.
5. Vérifier qu’au moins une carte d’annonce contient une photo, une ville et un prix.

**Résultat attendu :** l’API retourne les catégories, l’application s’affiche sans erreur bloquante et les photos sont chargées.

### S-01 — Découverte publique et filtres

**Objectif :** vérifier le catalogue sans authentification.

**Prérequis :** être déconnecté.

**Étapes :**

1. Ouvrir la page **Près de moi**.
2. Vérifier que le mode initial est **Partout au Maroc**.
3. Rechercher `mathématiques`.
4. Vérifier que l’offre **Cours de soutien en mathématiques** apparaît.
5. Effacer la recherche puis ouvrir **Filtres**.
6. Choisir `Services`, la catégorie `Éducation`, puis appliquer.
7. Retirer les filtres.

**Résultat attendu :** les résultats et leur compteur se mettent à jour, les filtres actifs restent visibles et leur suppression restaure le catalogue.

### S-02 — Recherche par ville, alentours et distance

**Objectif :** vérifier la logique géographique et la carte.

**Étapes :**

1. Dans **Près de moi**, sélectionner la recherche par ville.
2. Rechercher puis choisir `Rabat` dans la liste recherchable.
3. Choisir **Dans Rabat** et vérifier que les offres de mathématiques et de coiffure apparaissent.
4. Choisir **Rabat et alentours**.
5. Tester successivement plusieurs rayons parmi `5`, `10`, `25`, `50` et `100 km`.
6. Vérifier que chaque carte affiche une distance et que la carte se recentre sur la zone choisie.
7. Passer entre les modes **Distances**, **Liste + carte** et **Carte** lorsqu’ils sont disponibles.

**Résultat attendu :** le sélecteur de ville reste cohérent avec le mode choisi, les annonces respectent le rayon et les distances correspondent aux marqueurs de la carte.

**Variante géolocalisation :** choisir **Ma position**, autoriser la géolocalisation et vérifier que le filtre de ville disparaît. En cas de refus de permission, un message explicite doit être affiché sans casser le catalogue.

### S-03 — Authentification et protection des pages privées

**Objectif :** vérifier la session et les redirections.

**Étapes :**

1. Déconnecté, ouvrir directement `/profile` puis `/messages`.
2. Vérifier la redirection vers la connexion.
3. Se connecter avec `client@qribly.test` / `password`.
4. Vérifier le retour à une navigation authentifiée et l’accès au profil.
5. Se déconnecter.
6. Tenter une connexion avec un mot de passe incorrect.

**Résultat attendu :** les pages privées sont protégées, la bonne connexion réussit, la déconnexion invalide la session et l’échec de connexion affiche un message compréhensible.

### S-04 — Détail d’un produit et détail d’un service

**Objectif :** vérifier que l’interface distingue correctement les deux types d’annonces.

**Étapes :**

1. Ouvrir **PC portable reconditionné**.
2. Vérifier la galerie, le statut réservé, la ville, le vendeur, la description, le prix et la carte.
3. Vérifier qu’aucun sélecteur de créneau n’est proposé pour ce produit.
4. Ouvrir **Cours de soutien en mathématiques**.
5. Vérifier la galerie, la durée `1 h 30`, les disponibilités, les avis, le prestataire et la localisation.

**Résultat attendu :** les informations communes sont cohérentes et seul le service présente le parcours de réservation.

### S-05 — Favoris

**Objectif :** vérifier l’ajout et la suppression d’un favori.

**Prérequis :** être connecté avec `client@qribly.test`.

**Étapes :**

1. Ouvrir une annonce qui n’est pas déjà dans les favoris.
2. Cliquer sur le bouton favori.
3. Ouvrir **Profil → Mes favoris**.
4. Vérifier que l’annonce est présente.
5. Retirer le favori et actualiser la page.

**Résultat attendu :** l’état du bouton et la liste du profil restent synchronisés après actualisation.

### S-06 — Publication d’une annonce

**Objectif :** vérifier le formulaire en trois étapes.

**Prérequis :** être connecté avec un compte de démonstration.

**Étapes :**

1. Cliquer sur **Publier une annonce**.
2. Créer une annonce de test avec un titre facilement identifiable, par exemple `TEST QA — Nettoyage canapé`.
3. Compléter le type, la catégorie, la description, le prix et la durée si le type est `Service`.
4. Ajouter une à cinq images valides.
5. Vérifier l’aperçu et la suppression des images.
6. Rechercher une ville dans la liste puis placer le marqueur sur la carte.
7. Publier l’annonce.

**Résultat attendu :** chaque étape conserve ses données, les erreurs sont affichées près des champs concernés et l’annonce publiée possède ses photos, sa ville et son emplacement.

**Nettoyage :** supprimer l’annonce depuis **Mes annonces** ou réinitialiser la base.

### S-07 — Création d’une réservation et chevauchement des créneaux

**Objectif :** vérifier la durée réelle d’une prestation et le blocage des créneaux qui se chevauchent.

**Prérequis :** être connecté avec `client@qribly.test` et choisir un service qui ne lui appartient pas.

**Étapes :**

1. Ouvrir **Cours de soutien en mathématiques**, durée `1 h 30`.
2. Choisir un jour disponible et noter tous les créneaux affichés autour de l’heure choisie.
3. Sélectionner un créneau, saisir une note puis confirmer la demande.
4. Ouvrir **Profil → Mes réservations** et vérifier le statut **En attente**.
5. Revenir sur l’offre et le même jour.

**Résultat attendu :** la réservation apparaît avec sa date, son heure et sa durée. Tous les départs qui chevaucheraient la plage réservée disparaissent. Par exemple, une réservation `18:30–20:00` rend indisponible un départ à `18:00` si celui-ci durerait jusqu’à `19:30`.

### S-08 — Confirmation d’une demande par le prestataire

**Objectif :** vérifier le passage de `pending` à `confirmed`.

**Prérequis :** base seedée.

**Étapes prestataire :**

1. Se connecter avec `prestataire@qribly.test`.
2. Ouvrir **Profil → Réservations reçues**.
3. Filtrer sur **En attente**.
4. Ouvrir la demande **Grand ménage d’un appartement** de Mehdi.
5. Cliquer sur **Confirmer** puis valider la fenêtre de confirmation.

**Étapes client :**

6. Se connecter avec `client@qribly.test` dans une autre fenêtre.
7. Ouvrir **Mes réservations** et les notifications.

**Résultat attendu :** le prestataire voit le statut **Confirmée**, le client voit le même statut et reçoit une notification. Le créneau reste indisponible.

### S-09 — Protection contre une fin de service anticipée

**Objectif :** vérifier la règle métier empêchant de terminer une prestation trop tôt.

**Prérequis :** base seedée et compte `prestataire@qribly.test`.

**Étapes :**

1. Ouvrir **Réservations reçues → Confirmées**.
2. Repérer **Entretien et nettoyage de climatisation**.
3. Vérifier le rendez-vous et sa durée `1 h 30`.
4. Observer le bouton **Marquer comme terminée** sur la carte.
5. Ouvrir **Voir les détails** et observer le bouton **Terminer**.

**Résultat attendu :** les deux boutons sont désactivés avant la fin du rendez-vous. Un message indique l’heure exacte à partir de laquelle l’action sera disponible.

**Test automatisé associé :**

```bash
docker compose exec backend php artisan test --filter=provider_cannot_complete_a_reservation_before_the_appointment_ends
```

### S-10 — Annulation et libération du créneau

**Objectif :** vérifier qu’une annulation libère la plage réservée.

**Prérequis :** créer une nouvelle réservation en attente et noter précisément son offre, sa date et son heure.

**Étapes :**

1. Avec le client, ouvrir **Mes réservations**.
2. Annuler la réservation test et confirmer la fenêtre.
3. Vérifier le statut **Annulée**.
4. Revenir sur l’offre et le jour initialement choisi.

**Résultat attendu :** la réservation est annulée, le prestataire ne peut plus la confirmer et le créneau redevient réservable.

### S-11 — Messagerie en temps réel

**Objectif :** vérifier la conversation, Reverb et la lecture des messages.

**Prérequis :** ouvrir deux fenêtres : Mehdi dans la première, Nadia dans la seconde.

**Étapes :**

1. Avec Mehdi, ouvrir la conversation existante avec Nadia.
2. Envoyer un message identifiable, par exemple `TEST TEMPS RÉEL — Bonjour`.
3. Observer la fenêtre de Nadia sans l’actualiser.
4. Répondre depuis Nadia.
5. Revenir à la fenêtre de Mehdi sans actualiser.

**Résultat attendu :** les deux messages apparaissent dans l’ordre, sans rechargement, et l’état de lecture se met à jour.

Si le test échoue :

```bash
docker compose logs --tail=100 backend queue reverb
```

### S-12 — Réservations terminées et avis

**Objectif :** vérifier l’affichage des prestations terminées et des avis existants.

**Étapes :**

1. Se connecter avec `client@qribly.test`.
2. Ouvrir **Mes réservations** et repérer **Cours de soutien en mathématiques** terminé.
3. Ouvrir l’offre correspondante.
4. Vérifier que l’avis seedé est affiché avec sa note et son commentaire.
5. Répéter avec un autre compte ou une autre offre terminée.

**Résultat attendu :** seuls les clients d’une réservation terminée peuvent être auteurs d’un avis et chaque avis apparaît sur l’offre concernée.

> **Couverture actuelle :** les cinq réservations terminées seedées possèdent déjà un avis. Le jeu de démonstration ne fournit pas encore de réservation terminée sans avis pour tester directement le formulaire de création. Ce cas doit être ajouté aux seeders avant de considérer la validation manuelle des avis comme complète.

### S-13 — Autorisations entre utilisateurs

**Objectif :** vérifier qu’un utilisateur ne peut pas administrer les données d’un autre.

**Étapes :**

1. Se connecter avec `sara@qribly.test`.
2. Ouvrir une annonce appartenant à Nadia.
3. Vérifier que les actions de modification et suppression ne sont pas proposées.
4. Ouvrir **Réservations reçues** et vérifier que seules les réservations des offres de Sara sont affichées.
5. Vérifier avec les tests API qu’une réservation reçue par un autre prestataire ne peut pas être confirmée.

**Résultat attendu :** l’interface masque les actions non autorisées et l’API retourne `403` si une requête est forcée.

### S-14 — Vérification responsive et accessibilité minimale

**Objectif :** détecter les régressions évidentes sur les écrans principaux.

**Tailles minimales à tester :** `375 × 812`, `768 × 1024` et `1440 × 900`.

**Pages :** accueil, Près de moi, détail d’offre, publication, profil, réservations et messagerie.

**Vérifications :**

- aucun défilement horizontal involontaire ;
- photos non déformées ;
- actions principales visibles et cliquables ;
- navigation réalisable au clavier ;
- focus visible sur les boutons et champs ;
- fenêtres fermables avec `Échap` et focus conservé à l’intérieur ;
- libellés compréhensibles sans dépendre uniquement de la couleur ;
- messages d’erreur associés à l’action concernée.

## Tests automatisés et qualité

### Préparer la base de test Docker

Les tests Laravel utilisent la base séparée `qribly_test`. La créer une seule fois :

```bash
docker compose exec postgres createdb -U postgres qribly_test
```

Si PostgreSQL indique que la base existe déjà, aucune action supplémentaire n’est nécessaire.

### Backend

```bash
# Tous les tests
docker compose exec backend php artisan test

# Un fichier précis
docker compose exec backend php artisan test tests/Feature/ReservationTest.php

# Formatage PHP sans modification
docker compose exec backend ./vendor/bin/pint --test
```

Les suites principales couvrent les annonces, disponibilités, réservations et seeders :

- `backend/tests/Feature/OfferTest.php` ;
- `backend/tests/Feature/ProviderAvailabilityTest.php` ;
- `backend/tests/Feature/ReservationTest.php` ;
- `backend/tests/Feature/DemoSeedersTest.php`.

### Frontend

```bash
docker compose exec frontend npm run type-check
docker compose exec frontend npm run build
```

### Contrôles avant livraison

```bash
git status --short
git diff --check
docker compose exec backend php artisan test
docker compose exec frontend npm run build
```

La CI GitHub exécute les migrations, les tests Laravel, la vérification TypeScript et le build Vite pour les pushs et pull requests vers `develop` et `main`.

## Installation manuelle

Utiliser cette méthode seulement lorsque Docker n’est pas disponible.

### Prérequis

- PHP 8.4 et Composer 2 ;
- extensions PHP `mbstring`, `bcmath`, `exif`, `gd`, `pcntl`, `pdo_pgsql` et `zip` ;
- PostgreSQL 16 avec PostGIS ;
- Node.js `^22.18.0` ou `>=24.12.0` et npm ;
- Python 3.12 pour la recherche sémantique locale.

### Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Lorsque PostgreSQL tourne sur la machine, remplacer `DB_HOST=postgres` par `DB_HOST=127.0.0.1` dans `backend/.env`.

Dans deux terminaux supplémentaires :

```bash
cd backend
php artisan queue:listen --tries=1 --timeout=0
```

```bash
cd backend
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Configuration locale utile :

```dotenv
AI_SERVICE_URL=http://localhost:8001
REVERB_BROADCAST_HOST=localhost
REVERB_BROADCAST_PORT=8080
```

### Frontend

```bash
cd frontend
cp .env.example .env
npm ci
npm run dev
```

`frontend/.env` :

```dotenv
VITE_API_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=qribly-local-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

### Service de recherche sémantique

```bash
cd ai-service
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --host=0.0.0.0 --port=8001 --reload
```

Sous Windows PowerShell :

```powershell
.\.venv\Scripts\Activate.ps1
```

Le modèle `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2` est téléchargé lors de la première utilisation.

### Interprétation intelligente avec Groq

Sans fournisseur distant, l’assistant utilise automatiquement l’interpréteur local. Pour activer Groq, créer un fichier `.env` à la racine du dépôt avec :

```dotenv
AI_PROVIDER=groq
GROQ_API_KEY=gsk_remplacer_par_la_cle_du_projet
GROQ_MODEL=openai/gpt-oss-20b
GROQ_BASE_URL=https://api.groq.com/openai/v1
AI_TIMEOUT_SECONDS=20
```

Ne jamais ajouter la clé réelle à Git. Après modification, redémarrer le service :

```bash
docker compose up -d --force-recreate ai-service
```

Si Groq est indisponible ou si son quota est dépassé, le service revient automatiquement à l’interpréteur local. La configuration OpenAI historique reste supportée avec `AI_PROVIDER=openai`, `OPENAI_API_KEY` et `OPENAI_MODEL`.

## API et structure du dépôt

### Structure

```text
qribly/
├── backend/                 API Laravel, migrations, seeders et tests
├── frontend/                application Vue et composants UI
├── ai-service/              service FastAPI de classement sémantique
├── docker/                  configuration Nginx
├── .github/workflows/       intégration continue
├── compose.yaml             environnement Docker local
└── docker-compose.prod.yml  environnement Docker de production
```

### Domaines de l’API

L’API est exposée sous `/api`.

| Domaine          | Exemples de responsabilités                                                   |
| ---------------- | ----------------------------------------------------------------------------- |
| Authentification | inscription, connexion, utilisateur courant et déconnexion                    |
| Annonces         | liste, détail, création, modification, suppression et photos                  |
| Recherche        | texte, filtres, ville, proximité et classement sémantique                     |
| Réservations     | création, listes client/prestataire, confirmation, annulation et finalisation |
| Disponibilités   | planning hebdomadaire et créneaux libres d’une offre                          |
| Communauté       | favoris, avis et notifications                                                |
| Communication    | conversations, messages et authentification Reverb                            |

Les routes sont définies dans [`backend/routes/api.php`](backend/routes/api.php). Les routes privées attendent un jeton Sanctum dans l’en-tête `Authorization: Bearer <token>`.

Les principaux points d’entrée frontend sont définis dans [`frontend/src/router/index.ts`](frontend/src/router/index.ts).

## Workflow de contribution

### Branches

- `develop` : intégration et développement courant ;
- `main` : versions stabilisées ;
- branches de travail recommandées : `feat/...`, `fix/...`, `docs/...` ou `test/...`.

### Commits

Utiliser Conventional Commits :

```text
feat(offers): add city search
fix(reservations): prevent early service completion
docs(readme): add onboarding and test scenarios
```

### Checklist d’une pull request

- le changement répond à un besoin clairement décrit ;
- les parcours existants ne sont pas cassés ;
- les tests automatisés concernés passent ;
- les nouveaux cas métier possèdent un test ;
- le frontend compile ;
- les migrations et seeders restent rejouables ;
- les captures ou étapes de validation manuelle sont jointes si l’interface change ;
- aucun secret, fichier `.env` ou identifiant de production n’est ajouté ;
- la documentation est mise à jour si le comportement change.

## Dépannage

### Le backend reste `unhealthy`

Le premier démarrage peut être long pendant `composer install` et les migrations.

```bash
docker compose logs --tail=150 backend postgres
docker compose restart backend
```

### Les photos ne s’affichent pas

```bash
docker compose exec backend php artisan storage:link
docker compose exec backend php artisan db:seed --force
```

Vérifier ensuite qu’une URL sous `http://localhost:8000/storage/` est accessible pour un fichier existant.

### Les annonces ou réservations ne chargent plus après un reset

Les jetons Sanctum ont été supprimés. Se déconnecter, effacer la session de l’application si nécessaire, puis se reconnecter avec un compte de démonstration.

### La géolocalisation échoue

- autoriser la localisation pour `localhost` dans le navigateur ;
- vérifier qu’aucun autre onglet ne bloque la demande de permission ;
- utiliser la recherche par ville pour continuer les tests sans position réelle.

### Les messages n’arrivent pas en temps réel

```bash
docker compose ps
docker compose logs --tail=100 backend queue reverb
```

Vérifier également que le port `8080` est disponible et que `VITE_REVERB_APP_KEY` vaut `qribly-local-key`.

### Un port est déjà utilisé

Arrêter le service local utilisant le port ou adapter le port publié dans `compose.yaml` et les variables correspondantes du frontend.

### Les tests Laravel ne trouvent pas la base

```bash
docker compose exec postgres createdb -U postgres qribly_test
docker compose exec backend php artisan test
```

## Limites connues

- aucun paiement en ligne n’est intégré ;
- le report vers un autre créneau et le motif structuré de refus/annulation restent à développer ;
- les absences exceptionnelles et plusieurs plages dans une même journée ne sont pas encore gérées ;
- le client ne possède pas encore une étape distincte de confirmation après réalisation du service ;
- le jeu seedé ne contient pas encore de réservation terminée sans avis ;
- la couverture automatisée du frontend reste limitée au type-check et au build ;
- la validation mobile, clavier et lecteur d’écran doit encore être renforcée ;
- le déploiement de production n’est pas considéré comme finalisé.

## Licence

Le backend Laravel déclare une licence MIT. Vérifier les droits sur la marque, les contenus, les photos et les autres composants du produit avant toute diffusion publique de Qribly.
