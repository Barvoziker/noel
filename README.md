# 🧱 Collection LEGO

Application Symfony pour gérer ma collection LEGO et éviter qu'on m'offre des sets en double.

- **Moi** : je renseigne les sets que j'ai et ceux que je veux.
- **Mes proches** : ils vérifient si j'ai déjà un set et le réservent, pour qu'aucun autre ne l'achète.
- **La surprise est préservée** : je ne vois jamais ce qui a été réservé.

## 🎯 Fonctionnalités

### Pour les proches (`/gift/`)
- **Vérification instantanée** par numéro (`42143`, `#42143`, `42143-1`, `Set 42143`) ou par nom.
- **Set absent de la liste** : la réponse est claire (« Mathis ne l'a pas, tu peux l'offrir »). Le proche peut le réserver *hors liste* : les autres le verront réservé, le propriétaire ne verra rien.
- **Liste en grille avec images** : filtres (à offrir, souhaits, possédés), thème, tri par envie ou par prix.
- **Fiche détaillée** : pièces, prix indicatif, niveau d'envie, note du propriétaire, liens LEGO.com et Rebrickable.
- **Réservation en un clic** avec un **code d'annulation** (ex. `K7P-2QX`).
- **« Mes réservations »** : retrouve et annule ses réservations depuis ce navigateur, ou depuis un autre appareil avec le code.
- **Alerte** si le propriétaire a obtenu le set entre-temps.
- **Avertissement** si le set vérifié n'est pas un véhicule : le propriétaire ne collectionne que ça.

### Pour le propriétaire (`/admin/`)
- **Tableau de bord** : nombre de sets possédés et souhaités, pièces, valeur estimée, thèmes préférés, derniers ajouts, lien de partage à copier.
- **Ajout rapide** : on tape un numéro, puis « Je l'ai » ou « Je le veux ».
- **Catalogue véhicules** : les ~2 000 sets voitures et véhicules motorisés existants (sur ~28 500), filtrables par thème, année et nom, à ajouter en un clic (« Je l'ai » ou « Je le veux »).
- **Dernières sorties que tu n'as pas** sur le tableau de bord.
- **Remplissage automatique** (nom, thème, année, pièces, image) depuis le catalogue local, sans clé API.
- **Niveau d'envie** (❤ très envie, envie, bonus), prix indicatif et note pour les proches.
- **« 🎉 Je l'ai ! »** en un clic pour passer un souhait en possédé.
- **Import** en masse (texte ou CSV) et **export CSV**, réimportable tel quel.
- **Zéro spoiler** : aucune réservation n'est visible, ni ici, ni sur les pages publiques.

### Comment la surprise est protégée
| Visiteur | Comment il est reconnu | Voit les réservations ? |
|---|---|---|
| Propriétaire | Cookie posé automatiquement dès qu'il passe par `/admin` | ❌ Jamais, même sur les pages publiques |
| Proche | Clique « Je veux offrir un cadeau » à la première visite | ✅ |

Un set ajouté hors liste par un proche reste invisible pour le propriétaire tant qu'il ne le possède pas. Si le propriétaire ajoute plus tard ce même numéro, la fiche est reprise sans rien révéler.

## 🚀 Installation

Prérequis : PHP 8.2+, PostgreSQL et Composer.

```bash
composer install

# Configuration locale (non commitée)
cp .env .env.local   # puis adapter DATABASE_URL, OWNER_NAME, etc.

php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate

# Catalogue Rebrickable (~5 s, à relancer chaque semaine, ou bouton « Mettre à jour » dans l'admin)
php bin/console app:catalog:sync

# Données d'exemple (optionnel, efface la base !)
php bin/console doctrine:fixtures:load

symfony serve        # ou : php -S localhost:8000 -t public/
```

### Variables d'environnement (`.env.local`)
| Variable | Rôle |
|---|---|
| `DATABASE_URL` | Connexion PostgreSQL |
| `OWNER_NAME` | Prénom affiché aux proches (« Mathis a déjà ce set ») |
| `ADMIN_PASSWORD_HASH` | Hash du mot de passe admin, généré avec `php bin/console security:hash-password`. **Entre guillemets simples.** |
| `COLLECTION_FOCUS` | Ce que tu collectionnes, affiché aux proches (défaut : « les voitures et véhicules motorisés ») |
| `REBRICKABLE_API_KEY` | Facultatif. Clé gratuite sur [rebrickable.com/api](https://rebrickable.com/api/), utilisée seulement pour un set sorti depuis la dernière synchro du catalogue |

Identifiant admin : `admin`. Sans `ADMIN_PASSWORD_HASH` dans `.env.local`, le mot de passe est `admin123` : **change-le** avant de mettre l'appli en ligne, et sers-la en HTTPS, car l'authentification HTTP Basic envoie le mot de passe en clair sur HTTP.

## 🏎 Catalogue et détection des véhicules

Rebrickable publie chaque jour son catalogue complet en CSV, accessible librement ([rebrickable.com/downloads](https://rebrickable.com/downloads/)). `app:catalog:sync` le recopie dans la table `catalog_sets`. Contrairement à l'API (limitée à environ 1 requête/s, avec un risque de bannissement), il n'y a aucune limite.

`VehicleClassifier` décide si un set est un véhicule terrestre motorisé :
- **Thèmes 100 % véhicules** (Speed Champions, Racers, City > Traffic…) : tout est retenu.
- **Ailleurs** (Technic, Icons, licences…) : le nom doit citer un type de véhicule (truck, excavator, motorcycle…) ou une marque (Ferrari, Porsche, Batmobile…).
- **Toujours exclus** : avions, hélicoptères, bateaux, vaisseaux, vélos, trains, ainsi que les produits dérivés (livres, porte-clés, Duplo…).

Pour ajuster le tri, il suffit de modifier les listes de mots dans `src/Service/VehicleClassifier.php`, puis de relancer la synchro.

## 📥 Format d'import

Un set par ligne, séparateur `;` (ou tabulation) :

```
numéro;nom;thème;année;possédé
42143
75192;Millennium Falcon;Star Wars;2017;oui
10497;Galaxy Explorer;Icons;2022;non
```

Seul le numéro est obligatoire. Si le nom manque et qu'une clé Rebrickable est configurée, les infos sont récupérées automatiquement.

## 📊 Données

**`sets`** : `numero_set` (unique, normalisé), `nom`, `theme`, `annee`, `pieces`, `prix`, `priorite` (1 à 3), `notes`, `image_url` (facultatif : l'image Rebrickable sert de repli), `owned`, `owned_at`, `added_by_giver`, `created_at`.

**`reservations`** : `set_id` (unique, une réservation par set), `reserved_at`, `anonymous_id`, `cancel_code_hash` (HMAC du code d'annulation), `reserved_by_hash` (HMAC du prénom, jamais affiché).

## 🛠️ Développement

```bash
php bin/phpunit                               # tests
php bin/console make:migration                # après une modif d'entité
php bin/console doctrine:migrations:migrate
php bin/console lint:twig templates
```

```
src/
├── Controller/
│   ├── AdminController.php        # /admin : tableau de bord, CRUD, import/export, lookup
│   └── GiftController.php         # /gift : recherche, liste, réservation, annulation
├── Command/CatalogSyncCommand.php # app:catalog:sync
├── Entity/                        # Set, Reservation, CatalogSet
├── EventSubscriber/
│   └── OwnerCookieSubscriber.php  # marque le navigateur du propriétaire
├── Repository/SetRepository.php   # recherche, filtres, statistiques
└── Service/
    ├── CatalogSync.php            # téléchargement du catalogue Rebrickable
    ├── LegoCatalog.php            # infos d'un set : catalogue local, puis API
    ├── VehicleClassifier.php      # véhicule ou pas ?
    ├── ReservationHashService.php # codes d'annulation, hash
    ├── SetNumber.php              # normalisation des numéros
    └── Viewer.php                 # qui regarde ? (propriétaire / proche)
templates/                         # Twig ; styles dans assets/styles/app.css
```
