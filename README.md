# Blaze - Accelerate Laravel with Rocket-Like Speed

## Introduction
Blaze is a versatile package that revolutionizes the way you build websites in Laravel. By seamlessly combining dynamic and static content generation, Blaze empowers developers to create high-performance websites with ease. With Blaze, you can achieve an average response time of just 4 milliseconds, ensuring lightning-fast performance for your applications. This comprehensive README.md will guide you through the setup and usage of Blaze, enabling you to harness its full potential for building dynamic and static websites in Laravel.

Here's a more detailed diagram illustrating how Blaze interacts with Laravel and Nginx for dynamic and static content generation, along with cache invalidation:

```
              +-----------------+       +-----------------+       +-----------------+
              |    Laravel      |       |     Nginx       |       |     Blaze       |
              |   Application   |       |   Web Server    |       |   Integration   |
              +-----------------+       +-----------------+       +-----------------+
                      |                        |                      |
                      | HTTP Requests/Responses|                      |
                      | ---------------------->|                      |
                      |                        |                      |
                      |   Dynamic Routes       |                      |
                      | ---------------------> |                      |
                      |                        |                      |
                      |                        |                      |
                      |  Static Files Serving  |                      |
                      |  (Cache Hit)           |                      |
                      | <--------------------- |                      |
                      |                        |                      |
                      |   Cache Miss           |                      |
                      |  (Dynamic Content)     |                      |
                      | ---------------------> |                      |
                      |                        |                      |
                      |   Cache File Creation  |                      |
                      |                        |                      |
                      | <--------------------- |                      |
                      |                        |                      |
                      |                        | Cache File Storage   |
                      |                        | and Management       |
                      |                        | -------------------->|
                      |                        |                      |
                      |                        |                      |
                      | Cache File Serving     |                      |
                      | (Cache Hit)            |                      |
                      | <--------------------- |                      |
                      |                        |                      |
                      | Cache Invalidation     |                      |
                      | (Model Changes,        |                      |
                      |  Custom Events, etc.)  |                      |
                      | ---------------------> |                      |
                      |                        |                      |
                      |                        | Cache File Removal   |
                      |                        | -------------------->|
                      |                        |                      |
                      |                        |                      |
                      |                        |                      |
                      |                        |                      |
              +-----------------+       +-----------------+       +-----------------+
              |   PHP-FPM       |       |   File System   |       |   Cache Files   |
              |   Process       |       |   (Serving      |       |                 |
              |   Manager       |       |   Static Files) |       |                 |
              +-----------------+       +-----------------+       +-----------------+
```

1. HTTP requests are initially received by Nginx.
2. Nginx forwards dynamic requests to the Laravel application.
3. Laravel processes dynamic routes and generates responses for dynamic content.
4. Responses are served to clients directly or cached by Blaze as static files.
5. For cache hits, Nginx serves static files directly, bypassing Laravel for improved performance.
6. If a cache miss occurs, Blaze generates cache files for the dynamic content and stores them.
7. Cached files are served by Nginx for subsequent requests until invalidated.
8. Cache invalidation can occur due to model changes, custom events, or other triggers.
9. Upon invalidation, Blaze removes outdated cache files to ensure fresh content delivery.
10. The cycle continues, providing a seamless blend of dynamic and static content delivery for optimal performance.

Note: having nginx is not required but using the provided config can significantly improve response time.


## Features
- **Dynamic-Static Fusion:** Blaze seamlessly integrates dynamic and static content generation, offering the best of both worlds for website performance and scalability.
- **Automatic Content Rendering:** Blaze automates content rendering and caching, ensuring lightning-fast response times and optimal user experience.
- **Flexible Configuration:** Customize Blaze to fit your project's requirements with its flexible configuration options and intuitive design.

## Getting Started
### 1. Installation
- Install Blaze via Composer:
  ```bash
  composer require mvaliolahi/blaze
  ```

### 2. Configuration
- Add `blaze_id` to your `EncryptCookies` middleware like this:

```php
class EncryptCookies extends Middleware
{
    protected $except = [
        'blaze_id',
    ];
}
```

You should also add `@blaze` directive before the body tag ends for handle csrf.

### 3. Adding Blaze to Routes
- Blaze introduces two types of caching: `private` and `public`.
  - **Private Routes:** These routes are personalized and may display user-specific content. Use the `blaze:private` middleware to mark routes as private.
  - **Public Routes:** These routes are static and serve the same content to all users. Use the `blaze:public` middleware to mark routes as public.
- Example:
  ```php
  Route::get('/profile', [ProfileController::class, 'show'])->middleware('blaze:private');
  Route::get('/posts', [PostController::class, 'index'])->middleware('blaze:public');
  ```

  ### Difference between `blaze:private` and `blaze:public`
  - **Private Routes (`blaze:private`):** These routes are personalized and may display user-specific content. Blaze stores separate cache files for each user, identified by a unique `blaze_id` cookie. Changes to user-specific data invalidate the cache for that user only.
  - **Public Routes (`blaze:public`):** These routes serve static content to all users. Blaze generates a single cache file for each public route, optimizing performance by serving pre-rendered HTML files.

### 4. Config the model for invalidating and refreshing the cache
- **Model Integration:**
  - Add the `Blaze` trait to your models to enable dynamic content generation.
  - Define which routes should be invalid and whether they are public or private in the `$blaze` property of your models.
- **Cache Invalidation:**
  - Blaze observes model changes and invalidates cache files associated with affected routes, ensuring content remains up-to-date.

  ### Examples
  - Below is an example of how you can integrate Blaze into your Laravel models for dynamic-static fusion:
    ```php
    class Page extends Model
    {
        use Blaze;

        protected $blaze_refresh = true;

        protected $blaze = [
            'private' => ['profile', 'settings'],
            'public'  => ['homepage', 'posts.index'],
        ];
    }
    ```

  Define `blaze_refresh = true`, which will automatically update the cache by calling related routes regarding the $blaze array, note that enabling this feature may affect response time.

### 5. Nginx Config
- Add below map inside http {}

```nginx
  # Map to replace forward slashes with underscores
  map $uri $sanitized_uri {
    ~/(.*) _$1;
    default $uri;
  }

  # Define a variable based on the presence of the blaze_id cookie
  map $http_cookie $blaze_id {
    default "";
    "~*blaze_id=([^;]+)(?:;|$)" $1;
  }
````

After that change / location to:
```nginx
location / {
    # 1. All method except GET must be proxied to PHP-FPM (post, put, patch, delete)
    if ($request_method !~ ^(GET)$ ) {
        rewrite ^ /index.php$query_string last;
    }

    # 2. detect blaze identifier using map
    # 3. Sanitize the URI for match cache file using map.
    rewrite ^/(.*)[/?&=](.*)$ /$1_$2;

    # 4. Set the paths for private and public cache files
    set $private_cache_file "/cache/${blaze_id}_${sanitized_uri}?${query_string}.html";
    set $public_cache_file "/cache/public_${sanitized_uri}?${query_string}.html";

    # 5. Try to serve the private cached file if it exists, then the public cached file, and finally index.php
    try_files $private_cache_file $public_cache_file /index.php?$query_string;
}
```


### TODO
- [] Artisan command for cache public and private routes for add users.
- [] Minify static file.
