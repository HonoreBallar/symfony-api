# Symfony API

[![CI](https://github.com/HonoreBallar/symfony-api/actions/workflows/ci.yml/badge.svg)](https://github.com/HonoreBallar/symfony-api/actions/workflows/ci.yml)
![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony)
![PHPStan niveau 6](https://img.shields.io/badge/PHPStan-niveau%206-brightgreen)
[![Licence MIT](https://img.shields.io/badge/licence-MIT-blue)](LICENSE)

API REST JSON en Symfony : authentification par jeton, gestion des comptes utilisateurs et CRUD de catégories avec image de couverture.

## Fonctionnalités

- **Authentification par jeton Bearer** (valable 7 jours), révocable à la déconnexion
- **Inscription avec confirmation de l'adresse e-mail** (lien signé, valable 24 h)
- **Mot de passe oublié** (jeton à usage unique, valable 1 h)
- **Catégories** : liste paginée, création, modification, suppression, image de couverture (JPEG, PNG ou WebP, 5 Mo max)
- **Gestion des utilisateurs** réservée aux administrateurs
- **Réponses JSON uniformes** `{ success, message, data }`, erreurs comprises
- **Documentation OpenAPI** générée depuis le code, consultable sur `/api/doc`

## Stack

| Composant | Rôle |
|---|---|
| PHP 8.5, Symfony 8.1 | Framework |
| PostgreSQL 18, Doctrine ORM et Migrations | Persistance |
| Symfony Messenger (transport Doctrine) | Envoi des e-mails en arrière-plan |
| Symfony Mailer, Mailpit en local | E-mails |
| NelmioApiDocBundle | Documentation OpenAPI et Swagger UI |
| Docker Compose (php-fpm, nginx, worker, PostgreSQL, Mailpit) | Environnement de développement |

## Démarrage rapide

**Prérequis** : Docker et Docker Compose. PHP et Composer n'ont pas besoin d'être installés sur la machine : tout tourne dans les conteneurs.

```bash
git clone https://github.com/HonoreBallar/symfony-api.git
cd symfony-api

# 1. Démarrer les conteneurs
docker compose up -d --build

# 2. Installer les dépendances
docker compose exec php composer install

# 3. Créer le schéma de la base
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction

# 4. Créer un administrateur (le mot de passe est demandé si --password est omis)
docker compose exec php bin/console app:create-admin admin@example.com Admin --password='Admin1234!'

# 5. Redémarrer le worker, qui n'a pas pu démarrer tant que les dépendances n'étaient pas installées
docker compose restart worker
```

Une fois démarré :

| Service | URL |
|---|---|
| API | http://localhost:8080/api |
| Documentation Swagger | http://localhost:8080/api/doc |
| Mailpit (e-mails envoyés en local) | http://localhost:8025 |
| PostgreSQL | `localhost:5432`, utilisateur `app`, mot de passe `app`, base `app` |

> Sous Windows, le premier `composer install` peut prendre plusieurs minutes : les écritures dans un dossier partagé avec Docker sont lentes.

## Utiliser l'API

### Exemple complet

```bash
# Se connecter : la réponse contient data.token
curl -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"Admin1234!"}'

# Utiliser le jeton sur les autres routes
TOKEN=<valeur de data.token>

curl -X POST http://localhost:8080/api/categories \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Livres","description":"Romans et essais"}'

curl "http://localhost:8080/api/categories?page=1&pageSize=10" \
  -H "Authorization: Bearer $TOKEN"

# Ajouter une image de couverture (champ multipart "cover")
curl -X POST http://localhost:8080/api/categories/<id>/cover \
  -H "Authorization: Bearer $TOKEN" \
  -F cover=@couverture.jpg
```

### Format des réponses

Succès :

```json
{
  "success": true,
  "message": "Catégories récupérées avec succès.",
  "data": [{ "id": "01a17e65-a9b0-73cc-8bbc-e8ccbb15345b", "name": "Livres", "coverUrl": null }],
  "pagination": { "page": 1, "pageSize": 10, "totalItems": 1, "totalPages": 1 }
}
```

`pagination` n'est présent que sur les listes (paramètres `page`, défaut 1, et `pageSize`, de 1 à 100, défaut 10).

Erreur :

```json
{
  "success": false,
  "message": "Les données envoyées sont invalides.",
  "errors": [{ "field": "name", "message": "This value should not be blank." }]
}
```

`errors` détaille les champs en cause sur les erreurs de validation (422) ; il est vide dans les autres cas (401, 403, 404, 409…).

### Ressources

| Préfixe | Accès | Contenu |
|---|---|---|
| `/api/auth` | Public : `login`, `register`, `verify-email`, `forgot-password`, `reset-password`. Connecté : `me`, `logout` | Authentification et compte |
| `/api/categories` | Lecture : utilisateur connecté. Écriture et couverture : `ROLE_ADMIN` | Catégories |
| `/api/users` | `ROLE_ADMIN` | Gestion des utilisateurs (création, rôles, activation) |

Le détail de chaque route (paramètres, corps, réponses) est sur **http://localhost:8080/api/doc**. Swagger est généré depuis les attributs des contrôleurs : c'est la référence à jour.

**Règles de connexion** : un compte doit avoir confirmé son adresse e-mail et être actif pour se connecter. Sinon, la connexion renvoie 403 avec un message explicite. Les comptes créés par `app:create-admin` sont directement actifs et vérifiés.

## Configuration

Les valeurs par défaut sont dans `.env` (commité). Pour les surcharger en local, créez un `.env.local` (ignoré par Git). En production, utilisez de vraies variables d'environnement ou `bin/console secrets:set`.

| Variable | Rôle | Valeur par défaut |
|---|---|---|
| `APP_SECRET` | Signature des liens de confirmation d'e-mail | Défini dans `.env.dev` ; **à générer en production** |
| `DATABASE_URL` | Connexion PostgreSQL | Surchargée par `compose.yaml` pour pointer sur le conteneur `database` |
| `MAILER_DSN` | Serveur d'envoi des e-mails | `smtp://mailpit:1025` |
| `MAILER_FROM` | Expéditeur des e-mails | `no-reply@symfony-api.local` |
| `FRONTEND_URL` | Front-end vers lequel pointent les e-mails de bienvenue et de réinitialisation (`<FRONTEND_URL>/reset-password?token=…`) | `http://localhost:3000` |
| `MESSENGER_TRANSPORT_DSN` | File des messages asynchrones | `doctrine://default?auto_setup=0` (la table est créée par les migrations) |

## E-mails et worker Messenger

Les e-mails (confirmation, bienvenue, réinitialisation) ne partent pas pendant la requête HTTP : ils sont placés dans une file, puis envoyés par le service `worker` de `compose.yaml` (`messenger:consume async`). La requête reste rapide même si le serveur SMTP est lent ou indisponible.

- **Après une modification du code, redémarrez le worker** : il garde l'ancien code en mémoire.
  ```bash
  docker compose restart worker
  ```
- Un envoi qui échoue est retenté 3 fois (après 10 s, 20 s puis 40 s), puis rangé dans la file `failed` :
  ```bash
  docker compose exec php bin/console messenger:failed:show
  docker compose exec php bin/console messenger:failed:retry
  ```
- En local, tous les e-mails arrivent dans Mailpit : http://localhost:8025

## Tests et qualité

À la première utilisation, créez la base de test (`app_test`) :

```bash
docker compose exec php bin/console doctrine:database:create --env=test
docker compose exec php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

Puis :

| Commande | Effet |
|---|---|
| `composer ci` | Lance toutes les vérifications ci-dessous, comme la CI |
| `composer ci:lint` | Valide `composer.json`, le conteneur de services, la config YAML et les templates Twig |
| `composer ci:cs` | Vérifie le standard de code `@Symfony` (`vendor/bin/php-cs-fixer fix` pour corriger) |
| `composer ci:stan` | Analyse statique PHPStan, niveau 6 |
| `composer ci:test` | Tests PHPUnit |

À lancer dans le conteneur : `docker compose exec php composer ci`.

- Les tests fonctionnels (`tests/Controller/Api`) passent par de vraies requêtes HTTP sur l'API. `ApiTestCase` fournit les helpers de connexion.
- Chaque test tourne dans une transaction annulée à la fin (DAMA DoctrineTestBundle) : les tests ne laissent aucune donnée en base et ne dépendent pas de leur ordre d'exécution.
- Les dépréciations, notices et warnings PHP font échouer la suite (`phpunit.dist.xml`).

La CI (GitHub Actions, `.github/workflows/ci.yml`) tourne sur chaque PR et chaque push sur `main` :

1. **Lint & static analysis** : `composer audit`, puis les mêmes vérifications que `ci:lint`, `ci:cs` et `ci:stan`
2. **Tests** : PostgreSQL 18, aller-retour complet des migrations (montée, descente, remontée), cohérence entre les migrations et les entités, puis PHPUnit

## Organisation du code

```
src/
├── Command/         Commandes console (app:create-admin)
├── Controller/Api/  Contrôleurs ; ApiController produit l'enveloppe JSON
├── Dto/             Corps et paramètres des requêtes, validés (#[MapRequestPayload], #[MapQueryString])
├── Entity/          Entités Doctrine
├── EventListener/   ApiExceptionListener : toute exception sous /api devient une réponse JSON
├── Http/            ApiResponse : l'enveloppe JSON hors contrôleurs
├── OpenApi/         Attributs réutilisables pour la documentation Swagger
├── Repository/      Requêtes Doctrine
├── Routing/         Distinction entre « route inconnue » et « identifiant mal formé »
├── Security/        Jetons Bearer, vérification des comptes, réponses 401/403
├── Serializer/      Normalisation (URL de couverture des catégories)
├── Service/         Logique métier (inscription, e-mails, stockage des couvertures)
└── Validator/       Contraintes de validation personnalisées
```

Les images de couverture sont stockées dans `public/uploads/categories/` (ignoré par Git).

## Contribuer

- `main` est protégée : passez par une branche et une pull request. La CI doit être verte et la branche à jour avant de merger (squash ou rebase).
- Messages de commit au format [Conventional Commits](https://www.conventionalcommits.org/fr/) : `feat(api): …`, `fix: …`, `docs: …`
- Lancez `composer ci` avant de pousser.
- Toute modification d'entité passe par une migration :
  ```bash
  docker compose exec php bin/console make:migration
  docker compose exec php bin/console doctrine:migrations:migrate
  ```
  N'utilisez jamais `doctrine:schema:update` : la CI vérifie que les migrations correspondent aux entités et qu'elles peuvent être annulées.
- Ajoutez une dépendance avec `composer require` dans le conteneur, pour que la recipe Symfony Flex génère sa configuration.

## Dépannage

| Symptôme | Solution |
|---|---|
| Les e-mails n'arrivent pas dans Mailpit | `docker compose ps` : le `worker` doit être `Up`. Sinon, `docker compose logs worker`, puis `docker compose restart worker` |
| `composer require` échoue avec `Permission denied` sur `composer.lock` | Le conteneur tourne en `www-data`. Lancez la commande en root : `docker compose exec -u root -e COMPOSER_ALLOW_SUPERUSER=1 php composer require …` |
| Erreur 500 : consulter le détail | Les logs sont écrits sur la sortie d'erreur du conteneur : `docker compose logs php` |

## Licence

[MIT](LICENSE)
