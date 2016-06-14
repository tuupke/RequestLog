# Installation #

Make sure Elasticsearch is running
https://bitbucket.org/eventix/eventix-3.0/wiki/Request%20Logging%20/%20Elasticsearch

Add `Eventix\RequestLog\RequestLogServiceProvider::class` to `config/app.php` (ServiceProviders)
Add `\Eventix\RequestLog\Middleware\RequestLogMiddleware::class` to `app/http/Kernel.php` to the global middleware

Add to your .env
```
REQUEST_LOG_HOST=localhost:9200
REQUEST_LOG=true
```