

web: php artisan serve --host=0.0.0.0 --port=\$PORT > Procfile
worker: php artisan queue:work database --sleep=3 --tries=3 >> Procfile
