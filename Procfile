web: php artisan serve --host=0.0.0.0 --port=8080 --tries=5
worker: php artisan queue:work database --sleep=3 --tries=3 --timeout=180 --memory=256
scheduler: php artisan schedule:work
