# ProxiServices

Plateforme de mise en relation de clients et prestataires à Abidjan. Application Laravel 13, PHP 8.3+, Inertia, React, TypeScript et PostgreSQL en production.

## Développement

```sh
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan migrate
php artisan db:seed
npm run dev
```

Les comptes et avis de démonstration utilisent des adresses `example.test`, des mots de passe réservés à la démo et des mentions fictives visibles. Le seeder est refusé en environnement `production`. Ne réutilisez jamais ces données comme données commerciales.

## Préparation PostgreSQL

Créer une base et un utilisateur dédiés, avec le privilège de connexion et les droits de création/modification de schéma nécessaires aux migrations. Ne réutilisez pas un compte superutilisateur PostgreSQL pour l'application. Renseignez les valeurs dans l'environnement du serveur (jamais dans Git):

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=proxiservices
DB_USERNAME=proxiservices_app
DB_PASSWORD=<secret fourni par le gestionnaire de secrets>
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
LOG_CHANNEL=stack
```

Conservez `APP_KEY` stable et privé. Activez HTTPS au niveau du proxy, configurez `TRUSTED_PROXIES` avec les seules adresses du proxy, vérifiez l'envoi de courrier, les paramètres GeniusPay live et l'URL canonique avant la mise en service. Le support applicatif PostgreSQL doit aussi être vérifié par les extensions PHP `pdo_pgsql` et `pgsql`.

## Déploiement sans perte

1. Vérifier l'état Git, la version sauvegardée et l'accès à la restauration avant tout changement. Essayer la procédure sur une copie restaurée en préproduction.
2. Construire et livrer l'application; activer le mode maintenance si la migration exige une fenêtre.
3. Exécuter `php artisan migrate --force` uniquement après revue du SQL généré/du plan de migration et validation d'une sauvegarde récente. Les migrations de cette application doivent rester additives et compatibles avec le code déjà en service; éviter les renommages/suppressions de colonnes en une seule livraison.
4. Exécuter `php artisan optimize` puis vérifier l'état de santé, les journaux et les parcours critiques. `php artisan migrate:rollback` ne remplace pas une stratégie de restauration des données.

Ne lancez jamais les commandes de migration contre une base réelle depuis une session de développement ou de revue. Ce guide ne signifie pas qu'un déploiement ou une migration a été réalisé.

## Queues, cache et tâches planifiées

Le backend doit partager le même PostgreSQL persistant pour les files, le cache et les sessions si ces pilotes restent configurés sur `database`. Créez les tables avec les migrations existantes avant le démarrage des processus. Un superviseur (systemd, Supervisor ou plateforme équivalente) doit maintenir un worker:

```sh
php artisan queue:work --sleep=1 --tries=3 --timeout=90
```

Redémarrer les workers à chaque livraison (`php artisan queue:restart`) et surveiller `failed_jobs`; la commande `php artisan schedule:run` doit être appelée chaque minute, notamment pour l'expiration et l'élargissement des demandes urgentes. Définissez des politiques documentées de rétention/nettoyage des tâches échouées et des sessions.

## Sauvegardes, restauration et incidents

- Sauvegarder PostgreSQL automatiquement, chiffrer les sauvegardes, les copier hors du serveur et définir rétention/RPO/RTO avec l'exploitant.
- Tester périodiquement une restauration complète dans un environnement isolé; vérifier contraintes, volumes, connexion applicative et médias privés avant de considérer la sauvegarde exploitable.
- Centraliser les journaux Laravel et du worker, limiter l'accès, protéger les données personnelles et définir rétention/rotation. Configurer un suivi d'erreurs (par exemple Sentry) uniquement après validation des données envoyées, de la région de traitement et de la politique de confidentialité.
- Alerter sur erreurs 5xx, jobs échoués, espace disque, indisponibilité DB, échecs de sauvegarde et anomalies webhook. Ne journaliser ni secrets, ni codes OTP, ni coordonnées GPS précises.

Les remboursements, acomptes espèces, séquestres et reversements restent bloqués jusqu'à confirmation contractuelle et technique des capacités du fournisseur; aucun de ces flux n'est couvert par cette préparation.

## Vérifications locales

```sh
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
npm run types:check
npm run check -- resources/js
composer audit --locked
npm audit
```

Les tests configurés dans `phpunit.xml` utilisent SQLite en mémoire. La CI doit aussi vérifier PostgreSQL avant qu'une compatibilité de production puisse être considérée comme démontrée. Aucun score de performance ou validation visuelle n'est revendiqué sans mesure réelle.
