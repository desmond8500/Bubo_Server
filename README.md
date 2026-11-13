# Bubo Server

API Laravel 13 pour l'application [Bubo_Spents](https://github.com/desmond8500/Bubo_Spents) : authentification
par token (Sanctum) et synchronisation des dépenses, revenus, emprunts, articles, marques et fournisseurs.

## Installation

```bash
composer install
cp .env.example .env && php artisan key:generate   # régler APP_URL (adresse publique du serveur)

php artisan install:api   # installe Sanctum et charge routes/api.php (garder le routes/api.php existant)
php artisan migrate
php artisan storage:link  # sert les logos et images envoyés par l'app
php artisan serve --host=0.0.0.0
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
| GET | `/api/{marques,fournisseurs,articles,depenses}` | Données communes : visibles par tous les utilisateurs connectés (avec leur `auteur`) |
| GET | `/api/{depenses_personnelles,revenus,emprunts}` | Données privées : uniquement celles de l'utilisateur connecté |
| GET | `/api/users` | Liste des utilisateurs et de leurs rôles (admin) |
| PATCH | `/api/users/{id}` | Change le rôle (`admin`, `membre`, `lecteur`) (admin) |
| GET | `/api/journal` | Journal d'activité paginé, filtres `action` et `user_id` (admin) |
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

## Données communes, données privées et rôles

| Données | Visibilité |
| --- | --- |
| Marques, fournisseurs, articles, **dépenses générales** (`depenses`) | communes : tous les utilisateurs connectés les voient |
| **Dépenses personnelles**, revenus, emprunts | privées : uniquement leur propriétaire |

Chaque ligne commune garde son auteur (`user_id` et `auteur.name`). Droits d'écriture sur les données communes :

| Rôle | Données communes | Données privées |
| --- | --- | --- |
| `admin` | lit, crée, corrige et supprime celles de tous ; gère les rôles et lit le journal | les siennes |
| `membre` (par défaut) | lit tout, crée, corrige et supprime les siennes | les siennes |
| `lecteur` | lit seulement | les siennes |

Le premier compte créé est administrateur ; à la migration, le compte existant le plus ancien le devient.
Il reste toujours au moins un administrateur. Les écritures refusées par `/api/sync` sont comptées dans `refuses`
(exemple : `{"articles": 1}`) ; la réponse contient aussi `moi` (utilisateur courant avec son rôle à jour).

## Journal d'activité

La table `journal` enregistre : inscriptions, connexions (réussies et échouées), déconnexions, changements de rôle,
et créations / modifications / suppressions de données. Le contenu des données privées n'est jamais journalisé
(seulement le type d'action et l'identifiant). Consultation : `GET /api/journal` (admin).
