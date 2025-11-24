# 🎁 Liste de Noël LEGO

Application Symfony pour éviter les doublons de cadeaux LEGO à Noël.

## 🎯 Fonctionnalités

### Pour toi (admin)
- **Gestion des sets** : Ajouter, modifier, supprimer des sets LEGO
- **Marquer comme possédé** : Cocher les sets que tu as déjà dans ta collection
- **Vue d'ensemble** : Voir tous les sets et leur statut (possédé/réservé/disponible)
- **Confidentialité** : Tu ne vois pas qui a réservé quoi

### Pour tes proches
- **Recherche par numéro** : Saisir un numéro de set pour vérifier sa disponibilité
- **Filtres intelligents** : Voir les sets disponibles à offrir, souhaités, ou tous
- **Évite les doublons** : Les sets possédés sont clairement marqués
- **Réservation simple** : Réserver un set disponible en un clic
- **Anonymat** : Pas besoin de compte, réservation anonyme

## 🚀 Installation

### Prérequis
- PHP 8.2+
- PostgreSQL
- Composer

### Configuration
1. **Base de données** : Assure-toi que PostgreSQL est démarré et que la DB `lego_list` existe
2. **Variables d'environnement** : Le fichier `.env` est déjà configuré pour `postgresql://mathisbuchet:@127.0.0.1:5432/lego_list`

### Démarrage
```bash
# Installer les dépendances
composer install

# Créer les tables (si pas encore fait)
php bin/console doctrine:schema:update --force

# Charger des données d'exemple (optionnel)
php bin/console doctrine:fixtures:load

# Démarrer le serveur
symfony serve
# ou
php -S localhost:8000 -t public/
```

## 🔐 Accès

### Pages publiques (tes proches)
- **Accueil** : `/gift/` - Recherche et navigation
- **Tous les sets** : `/gift/list` - Liste complète (possédés + souhaités)
- **Sets souhaités** : `/gift/list?filter=wanted` - Seulement les non-possédés
- **Disponibles à offrir** : `/gift/list?filter=available` - Souhaités + non réservés

### Administration (toi)
- **URL** : `/admin/`
- **Identifiants** : `admin` / `admin123`
- **Authentification** : HTTP Basic (popup du navigateur)

## 📊 Structure des données

### Table `sets`
- `numero_set` : Numéro LEGO officiel (ex: 75302) - **unique**
- `nom` : Nom du set
- `theme` : Thème LEGO (Star Wars, City, etc.)
- `annee` : Année de sortie
- `image_url` : URL de l'image (optionnel)
- `owned` : Booléen - true si tu possèdes déjà ce set

### Table `reservations`
- `set_id` : Référence vers le set - **unique** (un set = une réservation max)
- `reserved_at` : Date/heure de réservation
- `reserved_by` : Prénom (optionnel, invisible partout)

## 🔍 Comment trouver le numéro d'un set LEGO ?

### 📦 Sur la boîte physique
- **Emplacement** : Coin supérieur droit de la boîte
- **Format** : 4-5 chiffres (ex: 75192, 42143)
- **Variantes** : Parfois précédé de "Set" ou "#"

### 🌐 En ligne
- **Site officiel** : LEGO.com → dans l'URL du produit
- **Revendeurs** : Amazon, Fnac, etc. → fiche produit
- **Communauté** : Brickset.com, Rebrickable.com

### 🎯 Exemples concrets
- **75192** - Millennium Falcon UCS
- **10497** - Galaxy Explorer  
- **21058** - Grande Pyramide de Gizeh
- **42143** - Ferrari Daytona SP3
- **60367** - Avion de passagers

### 💡 Astuces
- Le numéro est **unique** pour chaque set
- Les sets récents ont souvent 5 chiffres
- Les sets anciens peuvent avoir 4 chiffres
- Ignore les lettres ou symboles autour

## 🎮 Utilisation

### Ajouter des sets (admin)
1. Va sur `/admin/`
2. Connecte-toi avec `admin` / `admin123`
3. Clique "Ajouter un set"
4. Remplis au minimum le numéro et le nom
5. **Coche "Je possède déjà ce set"** si c'est dans ta collection

### Réserver un set (proches)
1. Va sur `/gift/`
2. Tape un numéro de set ou clique "Voir la liste complète"
3. Si le set est dispo, clique "Réserver ce set"
4. Optionnel : laisse ton prénom (invisible pour l'admin)

## 🔒 Sécurité & Confidentialité

- **Admin** : Protégé par HTTP Basic Auth
- **Public** : Accès libre, pas de compte requis
- **Mode surprise total** : L'admin ne voit AUCUNE information de réservation
- **Données hashées** : Les prénoms sont hashés en base de données
- **Identifiants anonymes** : Chaque réservation a un ID anonyme unique
- **Contraintes** : Un set ne peut être réservé qu'une seule fois

## 🛠️ Développement

### Commandes utiles
```bash
# Vider le cache
php bin/console cache:clear

# Créer une nouvelle entité
php bin/console make:entity

# Créer une migration
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Recharger les fixtures
php bin/console doctrine:fixtures:load --purge-with-truncate
```

### Structure du projet
```
src/
├── Controller/
│   ├── AdminController.php    # CRUD admin (/admin/*)
│   └── GiftController.php     # Pages publiques (/gift/*)
├── Entity/
│   ├── Set.php               # Entité Set LEGO
│   └── Reservation.php       # Entité Réservation
└── Repository/
    └── SetRepository.php     # Requêtes personnalisées

templates/
├── admin/                    # Templates admin
├── gift/                     # Templates publics
└── base.html.twig           # Template de base avec CSS
```

## 🎄 Bon Noël !

L'app est prête à l'emploi. Tes proches peuvent maintenant vérifier et réserver des sets sans risque de doublon, et tu gardes la surprise ! 🎁
