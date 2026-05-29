# FacturSaaS

Application de facturation pour auto-entrepreneurs, construite avec Symfony 8 et Tailwind CSS.

## Ce que fait l'application
- Créer un compte et gérer son profil (raison sociale, IBAN)
- Créer / modifier / supprimer des clients
- Créer / modifier / supprimer des produits et services
- Créer des factures avec des lignes de produits
- Suivre le statut des factures (brouillon, en attente, payée)
- Générer des factures en PDF
- Envoyer les factures par mail et relancer les clients
- Dashboard avec résumé du chiffre d'affaires et graphique mensuel

## Programme
Symfony (Bonus : Gotenberg & Mailpit)

## Prérequis
- Git (pour cloner le dépôt)
- PHP 8.5+, Composer et Symfony CLI (pour la version locale)
- Docker et Docker Compose (pour la version conteneurisée)

## Lancement de l'application

### En local

**1. Cloner le repo :**
```bash
git clone <url-du-repo>
cd phase3-symfony-facturation
```

**2. Installer les dépendances :**
```bash
composer install
```

**3. Configurer le fichier `.env` :**
Copier `.env` en `.env.local` et vérifier que les lignes suivantes sont présentes :
```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
GOTENBERG_DSN=http://localhost:32768
MAILER_DSN=smtp://localhost:32769
```

**4. Créer la base de données et exécuter les migrations :**
```bash
symfony console doctrine:migrations:migrate
```

**5. Lancer Gotenberg (génération PDF) :**
```bash
docker compose up -d gotenberg
```

**6. Lancer Mailpit (envoi de mails) :**
```bash
docker compose up -d mailer
```

**7. Build Tailwind :**
```bash
symfony console tailwind:build
```

**8. Lancer le serveur :**
```bash
symfony server:start
```

**9.** Se rendre sur `http://127.0.0.1:8000` et créer un compte !

### Avec Docker

```bash
docker compose up --build -d
docker exec phase3-symfony-facturation-php-1 php bin/console doctrine:migrations:migrate --no-interaction
```
Ouvrir http://localhost:8090

L'interface Mailpit est accessible sur http://localhost:8025

## Variables d'environnement
| Variable | Valeur par défaut |
|---|---|
| POSTGRES_DB | app |
| POSTGRES_USER | app |
| POSTGRES_PASSWORD | app |

## Ports
| Service | Hôte | Conteneur |
|---------|------|-----------|
| App | 8090 | 80 |
| Mailer (Mailpit) | 8025 | 8025 |