#!/bin/sh
# bind mount で /app が差し替わるため、起動時に依存解決・マイグレーション・seed を行う
set -e
composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --force --class=DemoUserSeeder
exec php artisan serve --host=0.0.0.0 --port=8080
