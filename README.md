# Bubo Server

API Laravel 13 pour l'application [Bubo_Spents](https://github.com/desmond8500/Bubo_Spents).

Ce dépôt contient pour l'instant la couche métier (migrations, modèles, API de synchronisation).
Le squelette Laravel (artisan, bootstrap, config, composer.json…) doit être généré une fois en local :

```bash
# depuis un dossier temporaire
composer create-project laravel/laravel tmp-bubo

# copier le squelette dans ce dépôt sans écraser les fichiers existants
cp -rn tmp-bubo/. /chemin/vers/Bubo_Server/
cd /chemin/vers/Bubo_Server

cp .env.example .env && php artisan key:generate
php artisan install:api --without-migration-prompt   # active routes/api.php
php artisan migrate
php artisan serve
```

Pour que l'app web (Vite, port 5173) puisse appeler l'API :

```bash
php artisan config:publish cors   # puis autoriser l'origine http://localhost:5173
```

## Endpoints

| Méthode | URL | Rôle |
| --- | --- | --- |
| GET | `/api/marques` | Liste des marques |
| GET | `/api/fournisseurs` | Liste des fournisseurs |
| GET | `/api/articles` | Liste des articles |
| POST | `/api/sync` | Upsert par `uuid` des lignes locales (`"deleted": true` = suppression douce), renvoie l'état complet du serveur |

Exemple de corps pour `POST /api/sync` :

```json
{
  "marques": [{ "uuid": "…", "nom": "Nike" }],
  "fournisseurs": [{ "uuid": "…", "nom": "Marché Sandaga", "contact": null }],
  "articles": [
    { "uuid": "…", "nom": "Chaussures", "prix": 25000, "depense_le": "2026-11-12", "marque_uuid": "…", "fournisseur_uuid": "…" }
  ]
}
```
