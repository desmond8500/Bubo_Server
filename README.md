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
php artisan storage:link   # sert les logos et images envoyés par l'app (régler APP_URL dans .env)
php artisan serve
```

Pour que l'app web (Vite, port 5173) puisse appeler l'API :

```bash
php artisan config:publish cors   # puis autoriser l'origine http://localhost:5173
```

## Endpoints

| Méthode | URL | Rôle |
| --- | --- | --- |
| POST | `/api/register` | Crée un compte (`name`, `email`, `password`), renvoie `token` et `user` |
| POST | `/api/login` | Connexion (`email`, `password`), renvoie `token` et `user` |
| POST | `/api/logout` | Révoque le token courant (authentifié) |
| GET | `/api/me` | Utilisateur courant (authentifié) |
| GET | `/api/{marques,fournisseurs,articles,depenses,revenus,emprunts}` | Liste de la ressource de l'utilisateur (authentifié) |
| POST | `/api/sync` (authentifié) | Upsert par `uuid` des lignes locales (`"deleted": true` = suppression douce), renvoie l'état complet du serveur |

Exemple de corps pour `POST /api/sync` :

```json
{
  "marques": [{ "uuid": "…", "nom": "Nike", "description": null, "logo": "data:image/jpeg;base64,…" }],
  "articles": [
    { "uuid": "…", "nom": "Chaussures", "prix": 25000, "images": [], "marque_uuid": "…", "fournisseur_uuid": null }
  ],
  "depenses": [
    { "uuid": "…", "article_uuid": "…", "quantite": 1, "montant": 25000, "date": "2026-11-12" }
  ],
  "revenus": [{ "uuid": "…", "description": "Salaire", "montant": 300000, "date": "2026-11-01" }],
  "emprunts": [{ "uuid": "…", "description": "Prêt Moussa", "montant": 50000, "date": "2026-11-05", "deleted": true }]
}
```

## Authentification

L'API utilise des tokens Laravel Sanctum (en-tête `Authorization: Bearer <token>`). Chaque ligne appartient à
un utilisateur (`user_id`) : un utilisateur ne voit et ne modifie que ses propres données.

Après `php artisan install:api` (qui installe Sanctum), ajouter le trait au modèle `User` généré par le squelette :

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    // ...
}
```

Les lignes créées avant l'ajout de `user_id` n'ont pas de propriétaire et ne sont renvoyées à personne.
L'inscription est ouverte : pour la fermer en production, retirer la route `POST /api/register`.
