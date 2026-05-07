# FacturSaaS

Application de facturation pour auto-entrepreneurs, construite avec Symfony 8 et Tailwind CSS.

## Ce que fait l'application

- Créer un compte et gérer son profil (raison sociale, IBAN)
- Créer / modifier / supprimer des clients
- Créer / modifier / supprimer des produits et services
- Créer des factures avec des lignes de produits
- Suivre le statut des factures (brouillon, en attente, payée)
- Générer des factures en PDF
- Dashboard avec résumé du chiffre d'affaires

## Prérequis

- PHP 8.5+
- Composer
- Symfony CLI
- Docker (pour la génération PDF avec Gotenberg)

## Comment lancer le projet

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

Copier le fichier `.env` en `.env.local` et vérifier que les lignes suivantes sont présentes :
```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
GOTENBERG_DSN=http://localhost:32768
```

**4. Créer la base de données et exécuter les migrations :**

> Le projet utilise SQLite, aucune installation de base de données n'est nécessaire.
```bash
symfony console doctrine:migrations:migrate
```

**5. Lancer Gotenberg (pour la génération PDF) :**
```bash
sudo docker compose up -d gotenberg
```

> Vérifier le port utilisé par Gotenberg avec `sudo docker ps` et mettre à jour `GOTENBERG_DSN` dans `.env.local` si nécessaire.

**6. Build Tailwind :**
```bash
symfony console tailwind:build
```

**7. Lancer le serveur :**
```bash
symfony server:start
```

**8.** Se rendre sur `http://127.0.0.1:8000` et créer un compte !

---

# FacturSaaS (English)

Invoicing application for freelancers, built with Symfony 8 and Tailwind CSS.

## Features

- Create an account and manage your profile (company name, IBAN)
- Create / edit / delete clients
- Create / edit / delete products and services
- Create invoices with product lines
- Track invoice status (draft, pending, paid)
- Generate PDF invoices
- Dashboard with revenue summary

## Requirements

- PHP 8.5+
- Composer
- Symfony CLI
- Docker (for PDF generation with Gotenberg)

## How to run the project

**1. Clone the repo:**
```bash
git clone <url-du-repo>
cd phase3-symfony-facturation
```

**2. Install dependencies:**
```bash
composer install
```

**3. Configure the `.env` file:**

Copy `.env` to `.env.local` and make sure the following lines are present:
```
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
GOTENBERG_DSN=http://localhost:32768
```

**4. Create the database and run migrations:**

> This project uses SQLite, no database installation required.
```bash
symfony console doctrine:migrations:migrate
```

**5. Start Gotenberg (for PDF generation):**
```bash
sudo docker compose up -d gotenberg
```

> Check the port used by Gotenberg with `sudo docker ps` and update `GOTENBERG_DSN` in your `.env.local` if needed.

**6. Build Tailwind:**
```bash
symfony console tailwind:build
```

**7. Start the server:**
```bash
symfony server:start
```

**8.** Go to `http://127.0.0.1:8000` and create an account!