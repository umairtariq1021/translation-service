# Translation Management Service

A Laravel-based API for managing translations across multiple locales, with support for tags, searching, filtering, JSON export, token authentication, and OpenAPI/Swagger documentation.

## Features

* Translation CRUD APIs
* Multiple locale support (`en`, `fr`, `sv`)
* Translation tagging (`mobile`, `desktop`, `web`)
* Search translations by key or content
* Filter translations by locale and tag
* Pagination
* JSON translation export
* Token-based API authentication using Laravel Sanctum
* OpenAPI/Swagger API documentation
* Database indexing for optimized queries
* Factory/command for generating 100,000+ translation records
* PSR-12 compliant code
* Feature and unit testing

---

## Requirements

* PHP 8.2+
* Laravel 13
* MySQL 8+
* Composer
* Node.js / npm
* Git

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/umairtariq1021/translation-service.git
cd translation-service
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Configure environment

Copy the example environment file:

```bash
cp .env.example .env
```

For Windows:

```bash
copy .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure the database connection in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=translation_service
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Run migrations and seeders

```bash
php artisan migrate --seed
```

This creates the required tables and seeds the initial locales, tags, translations, and test user.

### 5. Start the application

```bash
php artisan serve
```

The API will be available at:

```text
http://127.0.0.1:8000
```

---

## Authentication

The API uses Laravel Sanctum for token-based authentication.

First, login using:

```http
POST /api/auth/login
```

Example request:

```json
{
    "email": "test@example.com",
    "password": "password"
}
```

The response contains an API token.

Use the token in subsequent protected requests:

```http
Authorization: Bearer YOUR_TOKEN
```

---

## API Endpoints

### Authentication

| Method | Endpoint          | Authentication |
| ------ | ----------------- | -------------- |
| POST   | `/api/auth/login` | No             |

### Translations

| Method | Endpoint                          | Authentication |
| ------ | --------------------------------- | -------------- |
| GET    | `/api/translations`               | Yes            |
| POST   | `/api/translations`               | Yes            |
| GET    | `/api/translations/{translation}` | Yes            |
| PUT    | `/api/translations/{translation}` | Yes            |
| DELETE | `/api/translations/{translation}` | Yes            |
| GET    | `/api/translations/export`        | Yes            |

---

## Create Translation

```http
POST /api/translations
```

Example:

```json
{
    "locale": "en",
    "key": "home.title",
    "content": "Welcome Home",
    "tags": [
        "web",
        "mobile"
    ]
}
```

---

## List Translations

```http
GET /api/translations
```

Supported query parameters:

```text
locale
key
tag
search
page
per_page
```

Example:

```text
/api/translations?locale=en&tag=web&search=welcome&page=1&per_page=20
```

The API uses pagination to prevent large datasets from being loaded into memory at once.

---

## Export Translations

```http
GET /api/translations/export?locale=en
```

The export endpoint returns a flat JSON object suitable for frontend applications such as Vue.js.

Example:

```json
{
    "home.title": "Welcome Home",
    "home.description": "Welcome to our application",
    "button.save": "Save"
}
```

The export is generated directly from the database and always retrieves the latest translation values.

---

# Swagger / OpenAPI Documentation

The API is documented using **L5-Swagger** and `swagger-php`.

The project uses PHP 8 attributes for OpenAPI definitions.

After starting the application, Swagger UI is available at:

```text
http://127.0.0.1:8000/api/documentation
```

To regenerate the OpenAPI documentation:

```bash
php artisan l5-swagger:generate
```

The Swagger documentation includes:

* Authentication
* Translation CRUD endpoints
* Translation listing and filtering
* Translation export
* Request parameters
* Request bodies
* Response definitions
* Authentication requirements
* Validation/error responses

Swagger UI also provides a **Try it out** feature for testing the APIs directly from the browser.

For protected endpoints, use the **Authorize** button and provide the Sanctum bearer token.

---

# Database Design

The database is normalized into the following main tables:

```text
locales
   |
   | 1:N
   ↓
translations
   |
   | N:M
   ↓
tags
```

### `locales`

Stores supported application locales.

```text
id
code
name
created_at
updated_at
```

The locale `code` is unique.

Example:

```text
en → English
fr → French
sv → Swedish
```

### `translations`

Stores the actual translation values.

```text
id
locale_id
translation_key
content
created_at
updated_at
```

A unique constraint is applied to:

```text
(locale_id, translation_key)
```

This ensures that the same translation key cannot be duplicated within the same locale.

### `tags`

Stores translation context tags.

```text
id
name
created_at
updated_at
```

### `tag_translation`

Pivot table for the many-to-many relationship between translations and tags.

```text
translation_id
tag_id
```

---

# Design Choices

## Service Layer

Business logic is kept inside `TranslationService` instead of placing it directly in controllers.

The request flow is:

```text
Request
   ↓
Middleware
   ↓
Controller
   ↓
Form Request
   ↓
Service
   ↓
Eloquent / Database
```

Controllers remain thin and are primarily responsible for handling HTTP concerns.

---

## Form Requests

Validation is handled using dedicated Laravel Form Request classes:

```text
StoreTranslationRequest
UpdateTranslationRequest
TranslationIndexRequest
ExportTranslationRequest
LoginRequest
```

This keeps validation separate from business logic and makes the rules reusable and easier to test.

---

## API Resources

`TranslationResource` is used to control the structure of translation API responses.

This prevents database models from being exposed directly and provides a consistent API response format.

---

## Database Indexing

Indexes were added based on the application's query patterns.

Important indexes include:

```text
translations(locale_id, translation_key)
translations(translation_key)
translations(locale_id, id)
tag_translation(tag_id, translation_id)
```

The `(locale_id, id)` index helps queries that filter translations by locale and order them by ID.

The unique `(locale_id, translation_key)` constraint also provides both data integrity and an efficient lookup path.

---

## Large Dataset Generation

The assignment requires support for 100,000+ translation records.

The project includes a dedicated data-generation command that uses bulk inserts/chunking rather than creating every record individually through Eloquent.

This avoids the overhead of:

```text
100,000 individual model creations
```

and significantly reduces memory usage and database round trips.

The generated dataset uses the same translation keys across the supported locales:

```text
en
fr
sv
```

---

## Performance Considerations

The application is designed with the assignment's performance requirements in mind.

### Pagination

Translation listing uses database pagination instead of retrieving the entire dataset.

### Eager Loading

Required relationships are eager loaded to avoid N+1 queries:

```php
with(['locale', 'tags'])
```

### Database Indexes

Indexes are used for frequently filtered and sorted columns.

### Bulk Data Generation

Large datasets are inserted in chunks rather than using individual database operations.

### Export

The export query retrieves only the required columns and converts the result directly into a key/value JSON structure.

---

# Security

The API uses Laravel Sanctum for authentication.

Protected endpoints require:

```http
Authorization: Bearer TOKEN
```

Additional security practices include:

* Request validation
* Laravel's password hashing
* Parameterized database queries through Eloquent/query builder
* Authentication middleware
* Mass-assignment protection
* No credentials committed to the repository
* Environment-based configuration

Sensitive configuration values such as database credentials and application secrets should be stored in `.env` and not committed to Git.

---

# Testing

Run the test suite with:

```bash
php artisan test
```

For code coverage, if configured:

```bash
php artisan test --coverage
```

The test suite covers API behavior, validation, authentication, translation operations, and important application logic.

---

# Code Style

The project follows **PSR-12** coding standards.

Code should be formatted consistently before committing changes.

---

# Useful Artisan Commands

Clear application caches:

```bash
php artisan optimize:clear
```

Run migrations:

```bash
php artisan migrate
```

Run migrations and seeders:

```bash
php artisan migrate --seed
```

Reset and rebuild the database:

```bash
php artisan migrate:fresh --seed
```

Generate Swagger documentation:

```bash
php artisan l5-swagger:generate
```

Run tests:

```bash
php artisan test
```

Start the development server:

```bash
php artisan serve
```

---

# API Documentation

The Postman collection included with the project can also be used to test the API.

Swagger/OpenAPI provides interactive API documentation at:

```text
http://127.0.0.1:8000/api/documentation
```

---

# Project Structure

```text
app/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Services/
└── OpenApi/

database/
├── factories/
├── migrations/
└── seeders/

routes/
└── api.php

tests/
├── Feature/
└── Unit/

config/
└── l5-swagger.php
```

---

# License

This project was created as part of a technical assessment.