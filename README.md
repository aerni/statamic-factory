# Factory

> The Statamic equivalent of [Laravel model factories](https://laravel.com/docs/11.x/eloquent-factories)

Factory makes it easy to generate realistic test data for your Statamic site. Create factories for entries, terms, and users with blueprint-aware scaffolding that gives you a solid starting point. Whether you're seeding your local development environment or writing tests, Factory provides an intuitive, Laravel-style API tailored specifically for Statamic's content model.

```php
use Database\Factories\Statamic\Collections\Blog\ArticleFactory;

// Create a single article
$article = ArticleFactory::new()->create();

// Create 10 articles
$articles = ArticleFactory::times(10)->create();

// Create articles across all sites
$articles = ArticleFactory::times(5)->inEachSite()->create();
```

## Installation

Install the addon using Composer:

```bash
composer require aerni/factory --dev
```

**Requirements:**
- Statamic 5.x
- Laravel 11.x
- PHP 8.2+

This addon is intended for development and testing environments and should be installed as a dev dependency.

## Getting Started

### Generating Factories

Generate a new factory using the interactive make command:

```bash
php please make:factory
```

The command guides you through an interactive process:

1. Select the content type (Collections, Taxonomies, or Users)
2. Choose which collection, taxonomy, or user blueprint to use
3. Optionally create a seeder alongside your factory

The factory is generated in `database/factories/Statamic` with a prefilled `definition()` method based on your blueprint fields.

### Factory Structure

Factories are PHP classes that extend `Aerni\Factory\Factories\Factory` and use one of three traits depending on the content type you're creating.

#### Entry Factories

Entry factories create Statamic entries (blog posts, pages, products, etc.) using the `CreatesEntry` trait:

```php
<?php

namespace Database\Factories\Statamic\Collections\Blog;

use Aerni\Factory\Factories\Factory;
use Aerni\Factory\Factories\Concerns\CreatesEntry;

class ArticleFactory extends Factory
{
    use CreatesEntry;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraphs(3, true),
            'author' => $this->faker->name,
        ];
    }
}
```

The collection and blueprint are automatically inferred from the factory's namespace and class name. For example, `Database\Factories\Statamic\Collections\Blog\ArticleFactory` resolves to collection `blog` and blueprint `article`.

**Advanced: Explicit Configuration**

You can optionally override the auto-resolution by explicitly setting the `$collection` and `$blueprint` properties:

```php
class ArticleFactory extends Factory
{
    use CreatesEntry;

    protected string $collection = 'blog';
    protected string $blueprint = 'article';

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraphs(3, true),
            'author' => $this->faker->name,
        ];
    }
}
```

This is useful when your factory class name doesn't match your blueprint handle, or when you want to be explicit about which collection and blueprint to use.

#### Term Factories

Term factories create taxonomy terms using the `CreatesTerm` trait:

```php
<?php

namespace Database\Factories\Statamic\Taxonomies\Tags;

use Aerni\Factory\Factories\Factory;
use Aerni\Factory\Factories\Concerns\CreatesTerm;

class TagFactory extends Factory
{
    use CreatesTerm;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word,
        ];
    }
}
```

The taxonomy and blueprint are automatically inferred from the factory's namespace and class name. For example, `Database\Factories\Statamic\Taxonomies\Tags\TagFactory` resolves to taxonomy `tags` and blueprint `tag`.

**Advanced: Explicit Configuration**

You can optionally override the auto-resolution by explicitly setting the `$taxonomy` and `$blueprint` properties:

```php
class TagFactory extends Factory
{
    use CreatesTerm;

    protected string $taxonomy = 'tags';
    protected string $blueprint = 'tag';

    public function definition(): array
    {
        return [
            'title' => $this->faker->word,
        ];
    }
}
```

This is useful when your factory class name doesn't match your blueprint handle, or when you want to be explicit about which taxonomy and blueprint to use.

#### User Factories

User factories create Statamic users using the `CreatesUser` trait:

```php
<?php

namespace Database\Factories\Statamic\Users;

use Aerni\Factory\Factories\Factory;
use Aerni\Factory\Factories\Concerns\CreatesUser;

class UserFactory extends Factory
{
    use CreatesUser;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'),
            'roles' => ['editor'],
        ];
    }
}
```

## Creating Content

### Creating Single Instances

Use the `create()` method to instantiate and persist content:

```php
use Database\Factories\Statamic\Collections\Blog\ArticleFactory;

// Create a single article
$article = ArticleFactory::new()->create();

// Create with specific attributes
$article = ArticleFactory::new()->create([
    'title' => 'My Custom Title',
    'content' => 'Custom content here',
]);

// Explicitly create one (useful when chaining with times())
$article = ArticleFactory::times(5)->createOne();
```

### Creating Multiple Instances

Generate multiple instances using `times()` or `count()`:

```php
// Create 10 articles
$articles = ArticleFactory::times(10)->create();

// Alternative syntax
$articles = ArticleFactory::new()->count(10)->create();

// Returns a Collection of Entry instances
$articles->each(function ($article) {
    echo $article->title;
});
```

#### Creating Many with Custom Attributes

Use `createMany()` to create multiple instances with specific attributes:

```php
// Create one instance (default)
$articles = ArticleFactory::new()->createMany();

// Create 5 instances
$articles = ArticleFactory::new()->createMany(5);

// Create with different attributes for each
$articles = ArticleFactory::new()->createMany([
    ['title' => 'First Article', 'featured' => true],
    ['title' => 'Second Article', 'featured' => false],
]);

// When using times() with createMany(), the attributes take precedence
$articles = ArticleFactory::times(10)->createMany([
    ['title' => 'First Article'],
    ['title' => 'Second Article'],
]);
// Creates 2 articles, not 10
```

### Making Without Persisting

The `make()` method creates instances without saving them to disk. This is useful for tests that don't require persistence:

```php
// Create an unpersisted entry
$article = ArticleFactory::new()->make();

// The entry exists in memory but isn't saved
$article->title; // 'Some Random Title'
$article->id(); // null until saved

// Make multiple instances
$articles = ArticleFactory::times(5)->make();

// Explicitly make one
$article = ArticleFactory::new()->makeOne();
```

> Using `make()` instead of `create()` in tests that don't require persistence can significantly improve test performance by avoiding disk I/O operations.

### Getting Raw Attributes

Use `raw()` to get the attribute array without instantiating a model:

```php
// Get attributes as array
$attributes = ArticleFactory::new()->raw();

// Returns:
// [
//     'title' => 'Some Random Title',
//     'content' => 'Lorem ipsum...',
//     'author' => 'John Doe'
// ]

// Get multiple sets of attributes
$attributes = ArticleFactory::times(3)->raw();

// Returns array of attribute arrays

// Override specific attributes
$attributes = ArticleFactory::new()->raw([
    'title' => 'Custom Title',
]);
```

> This feature works just like Laravel's [Creating Models Using Factories](https://laravel.com/docs/11.x/eloquent-factories#creating-models-using-factories). Learn more in the Laravel documentation.

## Multi-Site Support

Factory includes first-class support for Statamic's multi-site features, making it easy to generate localized content across all your configured sites.

### Creating Content in Specific Sites

Use the `inSite()` method to create content in a specific site:

```php
use Database\Factories\Statamic\Collections\Blog\ArticleFactory;

// Create article in German site
$article = ArticleFactory::new()
    ->inSite('de')
    ->create();

echo $article->locale(); // 'de'
```

If you specify a site that doesn't exist or isn't configured for the collection/taxonomy, the factory will fall back to the first available site:

```php
// If 'fr' isn't configured, falls back to first available site
$article = ArticleFactory::new()
    ->inSite('fr')
    ->create();

echo $article->locale(); // 'en' (or whatever the first site is)
```

### Creating Content in Random Sites

Use `inRandomSite()` to distribute content randomly across your configured sites:

```php
// Create 10 articles randomly distributed across sites
$articles = ArticleFactory::times(10)
    ->inRandomSite()
    ->create();

// Each article will be in a random site
$articles->pluck('locale'); // ['en', 'de', 'en', 'de', 'de', 'en', ...]
```

This is particularly useful for testing multi-site scenarios where you want varied locale distribution.

### Creating Content in All Sites

Use `inEachSite()` to create content in every configured site:

```php
// Create one article in each site
$articles = ArticleFactory::new()
    ->inEachSite()
    ->create();

// With 3 sites configured, creates 3 articles (one per site)
$articles->count(); // 3
$articles->pluck('locale'); // ['en', 'de', 'fr']

// Create 5 articles in each site
$articles = ArticleFactory::times(5)
    ->inEachSite()
    ->create();

// With 3 sites, creates 15 articles total (5 × 3)
$articles->count(); // 15
```

The optional parameter allows you to specify how many instances to create per site:

```php
// Create 3 articles in each site
$articles = ArticleFactory::new()
    ->inEachSite(3)
    ->create();

// With 2 sites configured, creates 6 articles total
$articles->count(); // 6
```

### Locale-Aware Faker

When creating content for specific sites, Faker automatically adjusts its locale to match the site's locale. This means names, sentences, and other locale-specific data will be appropriate for that site:

```php
// English site - English names
$article = ArticleFactory::new()
    ->inSite('en')
    ->create();

// $this->faker->name might generate: "John Smith"

// German site - German names
$article = ArticleFactory::new()
    ->inSite('de')
    ->create();

// $this->faker->name might generate: "Hans Müller"
```

This automatic locale detection works for all multi-site methods (`inSite()`, `inRandomSite()`, `inEachSite()`).

If you don't explicitly set a site, entries will be created in the default site of the configured sites, and Faker will use the locale of that default site for generating content.

### Multi-Site Terms

When creating terms for multiple sites, Factory handles an important Statamic requirement: terms must have data in the default locale. The factory automatically ensures this:

```php
use Database\Factories\Statamic\Taxonomies\Tags\TagFactory;

// Create term in German site
$tag = TagFactory::new()
    ->inSite('de')
    ->create();

// Factory automatically creates data in both default AND German locale
$tag->dataForLocale('en'); // Has data (default locale)
$tag->dataForLocale('de'); // Has data (specified locale)
```

This behind-the-scenes handling ensures your multi-site taxonomy terms work correctly without additional configuration.

> **Note:** For multi-site to work, your collection or taxonomy must be configured with the sites you're targeting. If a site isn't configured for that collection/taxonomy, it will be skipped.

## Factory States

States allow you to apply specific attribute modifications to your factories. Think of them as variations or presets for your content.

### Using State Methods

Apply states using the `state()` method:

```php
use Database\Factories\Statamic\Collections\Blog\ArticleFactory;

// Apply state with array
$article = ArticleFactory::new()
    ->state(['featured' => true, 'priority' => 10])
    ->create();

// Apply state with closure
$article = ArticleFactory::new()
    ->state(function (array $attributes) {
        return [
            'title' => strtoupper($attributes['title']),
            'featured' => true,
        ];
    })
    ->create();

// Chain multiple states
$article = ArticleFactory::new()
    ->state(['featured' => true])
    ->state(['archived' => false])
    ->create();
```

For setting a single attribute, use the `set()` convenience method:

```php
$article = ArticleFactory::new()
    ->set('featured', true)
    ->create();

// Equivalent to:
$article = ArticleFactory::new()
    ->state(['featured' => true])
    ->create();
```

### Published State

Entries default to published. Use the `unpublished()` method to create draft entries:

```php
// Published by default
$article = ArticleFactory::new()->create();
$article->published(); // true

// Create unpublished entry
$draft = ArticleFactory::new()
    ->unpublished()
    ->create();

$draft->published(); // false
```

### Defining Custom State Methods

Create reusable state methods for common variations:

```php
class ArticleFactory extends Factory
{
    use CreatesEntry;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraphs(3, true),
            'featured' => false,
            'status' => 'draft',
        ];
    }

    public function featured(): static
    {
        return $this->state([
            'featured' => true,
            'priority' => 10,
        ]);
    }

    public function published(): static
    {
        return $this->state([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'archived',
                'archived_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            ];
        });
    }
}
```

Use your custom states:

```php
// Create featured article
$article = ArticleFactory::new()
    ->featured()
    ->create();

// Chain multiple custom states
$article = ArticleFactory::new()
    ->published()
    ->featured()
    ->create();

// Combine with inline state
$article = ArticleFactory::new()
    ->featured()
    ->state(['author' => 'John Doe'])
    ->create();
```

> This feature works just like Laravel's [Factory States](https://laravel.com/docs/11.x/eloquent-factories#factory-states). Learn more in the Laravel documentation.

## Sequences

Sequences allow you to cycle through different values when creating multiple instances. This is perfect for generating varied but predictable test data.

### Basic Sequences

Use the `sequence()` method to alternate between different attribute values:

```php
use Database\Factories\Statamic\Collections\Blog\ArticleFactory;

// Alternate between two states
$articles = ArticleFactory::times(4)
    ->sequence(
        ['status' => 'draft'],
        ['status' => 'published'],
    )
    ->create();

// First article: status = 'draft'
// Second article: status = 'published'
// Third article: status = 'draft'
// Fourth article: status = 'published'
```

Use closures to access the sequence index and count:

```php
$articles = ArticleFactory::times(3)
    ->sequence(function ($sequence) {
        return [
            'title' => 'Article #' . $sequence->index,
            'priority' => $sequence->count,
        ];
    })
    ->create();

// First article: title = 'Article #0', priority = 3
// Second article: title = 'Article #1', priority = 3
// Third article: title = 'Article #2', priority = 3
```

### For Each Sequence

The `forEachSequence()` method automatically sets the count based on the number of sequence items:

```php
// No need to call times() - count is set automatically
$articles = ArticleFactory::new()
    ->forEachSequence(
        ['category' => 'news'],
        ['category' => 'blog'],
        ['category' => 'tutorial'],
    )
    ->create();

// Creates exactly 3 articles
$articles->count(); // 3
$articles->pluck('category'); // ['news', 'blog', 'tutorial']
```

This is particularly useful when you want one instance per sequence value:

```php
$users = UserFactory::new()
    ->forEachSequence(
        ['email' => 'admin@example.com', 'roles' => ['super']],
        ['email' => 'editor@example.com', 'roles' => ['editor']],
        ['email' => 'viewer@example.com', 'roles' => ['viewer']],
    )
    ->create();

// Creates exactly 3 users with specified roles
```

### Cross Join Sequences

The `crossJoinSequence()` method creates a cartesian product of multiple sequences, generating every possible combination:

```php
$articles = ArticleFactory::times(4)
    ->crossJoinSequence(
        [['status' => 'draft'], ['status' => 'published']],
        [['featured' => true], ['featured' => false]],
    )
    ->create();

// Creates 4 articles with these combinations:
// 1. status = 'draft', featured = true
// 2. status = 'draft', featured = false
// 3. status = 'published', featured = true
// 4. status = 'published', featured = false
```

Real-world example for testing all permission combinations:

```php
$users = UserFactory::times(9)
    ->crossJoinSequence(
        [['role' => 'admin'], ['role' => 'editor'], ['role' => 'viewer']],
        [['status' => 'active'], ['status' => 'inactive'], ['status' => 'suspended']],
    )
    ->create();

// Creates 9 users covering all role × status combinations
```

> This feature works just like Laravel's [Sequences](https://laravel.com/docs/11.x/eloquent-factories#sequences). Learn more in the Laravel documentation.

## Factory Relationships

Factories can reference other factories to create related content, mimicking Statamic's relationship fields and references.

### Defining Relationships

Reference other factories directly in your definition to create related content:

```php
use Database\Factories\Statamic\Collections\Blog\ArticleFactory;
use Database\Factories\Statamic\Taxonomies\Tags\TagFactory;
use Database\Factories\Statamic\Users\UserFactory;

class ArticleFactory extends Factory
{
    use CreatesEntry;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'author' => UserFactory::new(), // Creates user, stores ID
            'related_article' => ArticleFactory::new(), // Creates article, stores ID
            'tags' => [
                TagFactory::new(),
                TagFactory::new(),
                TagFactory::new(),
            ], // Creates 3 tags, stores IDs
        ];
    }
}
```

When you create an article, the factory automatically:
1. Creates the related content (user, tags, related article)
2. Extracts the IDs from the created content
3. Stores those IDs in the appropriate fields

```php
$article = ArticleFactory::new()->create();

$article->author; // '123e4567-e89b-12d3-a456-426614174000' (User ID)
$article->tags; // ['tag-1', 'tag-2', 'tag-3'] (Tag IDs)
$article->related_article; // 'article-id' (Article ID)
```

#### Conditional Relationships

Use closures to create conditional relationships:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence,
        'author' => function (array $attributes) {
            // Only create author for published articles
            return $attributes['status'] === 'published'
                ? UserFactory::new()
                : null;
        },
    ];
}
```

### Recycling Models

The `recycle()` method reuses existing instances instead of creating new ones. This is crucial for performance when creating many related instances:

```php
// Without recycling - creates 10 users (one per article)
$articles = ArticleFactory::times(10)->create();

// With recycling - reuses the same user for all articles
$user = UserFactory::new()->create();

$articles = ArticleFactory::times(10)
    ->recycle($user)
    ->create();

// All articles share the same author
$articles->pluck('author')->unique()->count(); // 1
```

Recycle multiple models:

```php
// Create a pool of tags
$tags = TagFactory::times(5)->create();

// Reuse these tags across articles
$articles = ArticleFactory::times(20)
    ->recycle($tags)
    ->create();

// Each article will randomly use tags from the pool
```

Recycle different model types:

```php
$user = UserFactory::new()->create();
$tags = TagFactory::times(3)->create();

$articles = ArticleFactory::times(10)
    ->recycle($user)  // Reuse this user
    ->recycle($tags)  // Reuse these tags
    ->create();
```

> **Performance Tip:** When creating many related instances, always use `recycle()`. Creating 100 articles with unique authors means 100 user saves to disk. Recycling a single user is dramatically faster.

> This feature works just like Laravel's [Factory Relationships](https://laravel.com/docs/11.x/eloquent-factories#factory-relationships) and [Recycling Models](https://laravel.com/docs/11.x/eloquent-factories#recycling-an-existing-model-for-relationships). Learn more in the Laravel documentation.

## Lifecycle Callbacks

Factories provide hooks to run code after making or creating instances. This is useful for setting up additional data or triggering side effects.

### After Making

The `afterMaking()` callback runs after the instance is made but before it's saved:

```php
use Database\Factories\Statamic\Collections\Blog\ArticleFactory;

$article = ArticleFactory::new()
    ->afterMaking(function ($article) {
        // Set computed field before saving
        $article->set('reading_time', strlen($article->content) / 200);
    })
    ->create();
```

### After Creating

The `afterCreating()` callback runs after the instance is created and saved:

```php
$article = ArticleFactory::new()
    ->afterCreating(function ($article) {
        // Instance is now persisted
        logger()->info("Created article: {$article->title}");

        // Trigger additional actions
        // event(new ArticleCreated($article));
    })
    ->create();
```

### Multiple Callbacks

Chain multiple callbacks together:

```php
$article = ArticleFactory::new()
    ->afterMaking(function ($article) {
        $article->set('reading_time', strlen($article->content) / 200);
    })
    ->afterMaking(function ($article) {
        $article->set('word_count', str_word_count($article->content));
    })
    ->afterCreating(function ($article) {
        logger()->info("Created: {$article->title}");
    })
    ->create();
```

### Callbacks in Factory Class

Define callbacks in your factory's `configure()` method for reusable behavior:

```php
class ArticleFactory extends Factory
{
    use CreatesEntry;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'content' => $this->faker->paragraphs(3, true),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function ($article) {
            $article->set('reading_time', strlen($article->content) / 200);
        })->afterCreating(function ($article) {
            logger()->info("Created article: {$article->title}");
        });
    }
}
```

Now every instance created with this factory will have these callbacks applied:

```php
// Callbacks run automatically
$article = ArticleFactory::new()->create();
```

> This feature works just like Laravel's [Factory Callbacks](https://laravel.com/docs/11.x/eloquent-factories#factory-callbacks). Learn more in the Laravel documentation.

## Working with Assets

Factory provides built-in support for generating and managing assets in your factories. This is particularly useful for image fields, galleries, and any blueprint fields that reference assets.

### Generating Assets

Use the `asset()` helper method to generate placeholder images:

```php
class ArticleFactory extends Factory
{
    use CreatesEntry;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence,
            'hero_image' => $this->asset('hero_image', 1200, 600),
            'thumbnail' => $this->asset('thumbnail', 400, 400),
        ];
    }
}
```

The `asset()` method accepts three parameters:
- `$field`: The field name (used for determining the asset container)
- `$width`: Image width in pixels
- `$height`: Image height in pixels

Assets are downloaded from [Lorem Picsum](https://picsum.photos/) as placeholder images.

### Multiple Assets

For fields that accept multiple assets (like galleries), generate an array of assets:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence,
        'gallery' => [
            $this->asset('gallery', 800, 600),
            $this->asset('gallery', 800, 600),
            $this->asset('gallery', 800, 600),
        ],
    ];
}
```

Use the `repeat()` helper for cleaner syntax:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence,
        'gallery' => $this->repeat(5, fn() => $this->asset('gallery', 800, 600)),
    ];
}
```

### Assets in Complex Fields

Assets work seamlessly in complex field types like Bard, Replicator, and Grid:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence,
        'content' => [
            [
                'type' => 'paragraph',
                'content' => $this->faker->paragraph,
            ],
            [
                'type' => 'image',
                'image' => $this->asset('content', 1200, 800),
                'caption' => $this->faker->sentence,
            ],
            [
                'type' => 'paragraph',
                'content' => $this->faker->paragraph,
            ],
        ],
    ];
}
```

### Automatic Asset Management

When you call `create()`, Factory automatically:
1. Downloads placeholder images to a temporary location
2. Creates asset records in Statamic
3. Moves assets to the correct container and folder
4. Respects your asset container's `dynamic` folder configuration

```php
$article = ArticleFactory::new()->create();

// Assets are automatically:
// - Downloaded from Lorem Picsum
// - Saved to the correct asset container
// - Referenced in the entry
```

### Important Notes

> **Note:** Assets are only generated when using `create()`. The `make()` method does not generate or download assets, as the content isn't being persisted.

> **Note:** Asset generation requires an active internet connection to download placeholder images from Lorem Picsum.

> **Performance:** Generating many assets can be slow due to network requests. If you're creating large amounts of test data and don't need realistic images, consider omitting asset fields or using `make()` instead of `create()`.

> **Cleanup:** Temporary files are stored in `storage/app/factory-tmp` during asset generation. These files are automatically cleaned up, but you may want to clear this directory periodically during development.

## Advanced Definition Techniques

### Conditional Fields

Use closures in your definitions to create conditional field values based on other attributes:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence,
        'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
        'published_at' => function (array $attributes) {
            return $attributes['status'] === 'published'
                ? $this->faker->dateTimeBetween('-1 year', 'now')
                : null;
        },
        'author' => function (array $attributes) {
            return $attributes['status'] === 'published'
                ? UserFactory::new()
                : null;
        },
    ];
}
```

When a field value is a closure, it receives the current attribute state:

```php
$article = ArticleFactory::new()->create();

// If status is 'published':
// - published_at will have a date
// - author will be created

// If status is 'draft':
// - published_at will be null
// - author will be null
```

### Conditional Factory Methods

Use `when()` and `unless()` to conditionally apply transformations:

```php
$article = ArticleFactory::new()
    ->when(
        Auth::user()->isAdmin(),
        fn($factory) => $factory->state(['priority' => 10])
    )
    ->unless(
        app()->environment('production'),
        fn($factory) => $factory->unpublished()
    )
    ->create();
```

### Complex Field Types

#### Bard Fields

Structure Bard content with paragraphs, images, and other content types:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence,
        'content' => [
            [
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => [
                    ['type' => 'text', 'text' => $this->faker->sentence],
                ],
            ],
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => $this->faker->paragraph],
                ],
            ],
            [
                'type' => 'image',
                'attrs' => [
                    'src' => $this->asset('content', 1200, 800),
                    'alt' => $this->faker->sentence,
                ],
            ],
        ],
    ];
}
```

#### Replicator Fields

Create replicator sets with varying types:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence,
        'flexible_content' => [
            [
                'type' => 'text_block',
                'enabled' => true,
                'content' => $this->faker->paragraphs(2, true),
            ],
            [
                'type' => 'image_block',
                'enabled' => true,
                'image' => $this->asset('flexible_content', 1200, 800),
                'caption' => $this->faker->sentence,
            ],
            [
                'type' => 'quote_block',
                'enabled' => true,
                'quote' => $this->faker->paragraph,
                'author' => $this->faker->name,
            ],
        ],
    ];
}
```

#### Grid Fields

Generate grid rows with multiple columns:

```php
public function definition(): array
{
    return [
        'title' => $this->faker->sentence,
        'team_members' => $this->repeat(5, function () {
            return [
                'name' => $this->faker->name,
                'role' => $this->faker->jobTitle,
                'bio' => $this->faker->paragraph,
                'photo' => $this->asset('team_members', 400, 400),
            ];
        }),
    ];
}
```

### Definition Helpers

#### The repeat() Helper

The `repeat()` method simplifies generating arrays of repeated items:

```php
// Instead of this:
'gallery' => [
    $this->asset('gallery', 800, 600),
    $this->asset('gallery', 800, 600),
    $this->asset('gallery', 800, 600),
    $this->asset('gallery', 800, 600),
    $this->asset('gallery', 800, 600),
],

// Use this:
'gallery' => $this->repeat(5, fn() => $this->asset('gallery', 800, 600)),

// Or with different values:
'features' => $this->repeat(3, fn() => [
    'title' => $this->faker->sentence,
    'description' => $this->faker->paragraph,
    'icon' => $this->faker->word,
]),
```

#### Accessing the Blueprint

Access your blueprint definition within the factory:

```php
protected function blueprint(): Blueprint
{
    return BlueprintFacade::find("collections/{$this->collectionHandle()}/{$this->blueprintHandle()}");
}

public function definition(): array
{
    $blueprint = $this->blueprint();

    // Access blueprint fields
    $fields = $blueprint->fields()->all();

    // Generate attributes based on blueprint
    // ...
}
```

> **Note:** Complex field structures must match Statamic's expected format exactly. When in doubt, create a test entry through the control panel and inspect the saved YAML to understand the expected structure.

## Seeders

Seeders provide an organized way to populate your Statamic site with test data. They're particularly useful for setting up development environments or generating demo content.

### Creating Seeders

Generate a seeder for an existing factory:

```bash
php please make:seeder
```

The command presents an interactive selection of all available factories. Select one and a seeder will be generated in `database/seeders/Statamic`.

Example generated seeder:

```php
<?php

namespace Database\Seeders\Statamic\Collections\Blog;

use Database\Factories\Statamic\Collections\Blog\ArticleFactory;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        ArticleFactory::times(10)->create();
    }
}
```

### Running Seeders

Run seeders using the interactive seed command:

```bash
php please seed
```

The command displays a multiselect list of all available seeders. Select the ones you want to run.

**Options:**

```bash
# Run all seeders without prompting
php please seed --all

# Force running in production (normally prevented)
php please seed --force
```

### Organizing Seeders

Seeders are organized by content type and mirror your factory structure:

```
database/seeders/Statamic/
├── Collections/
│   ├── Blog/
│   │   └── ArticleSeeder.php
│   └── Products/
│       └── ProductSeeder.php
├── Taxonomies/
│   └── Tags/
│       └── TagSeeder.php
└── Users/
    └── UserSeeder.php
```

### Advanced Seeder Usage

#### Multiple Factories in One Seeder

Combine related content in a single seeder:

```php
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // Create users first
        $authors = UserFactory::times(5)->create();

        // Create tags
        $tags = TagFactory::times(20)->create();

        // Create articles, recycling authors and tags
        ArticleFactory::times(50)
            ->recycle($authors)
            ->recycle($tags)
            ->create();
    }
}
```

#### Seeder Dependencies

Handle dependencies by calling other seeders:

```php
class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure users exist first
        $this->call(UserSeeder::class);

        // Ensure tags exist first
        $this->call(TagSeeder::class);

        // Now create articles that reference them
        ArticleFactory::times(50)->create();
    }
}
```

#### State-Specific Seeding

Create content in specific states:

```php
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        // 30 published articles
        ArticleFactory::times(30)
            ->published()
            ->create();

        // 10 draft articles
        ArticleFactory::times(10)
            ->unpublished()
            ->create();

        // 5 featured articles
        ArticleFactory::times(5)
            ->featured()
            ->published()
            ->create();
    }
}
```

#### Multi-Site Seeding

Distribute content across sites:

```php
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        // 20 articles in each site
        ArticleFactory::times(20)
            ->inEachSite()
            ->create();
    }
}
```

> This feature works just like Laravel's [Database Seeding](https://laravel.com/docs/11.x/seeding). Learn more in the Laravel documentation.

## Testing Integration

Factories are designed to integrate seamlessly with your PHPUnit tests, making it easy to set up test data.

### Basic Test Usage

Import and use factories in your tests:

```php
<?php

namespace Tests\Feature;

use Database\Factories\Statamic\Collections\Blog\ArticleFactory;
use Database\Factories\Statamic\Users\UserFactory;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function test_user_can_view_published_article(): void
    {
        $article = ArticleFactory::new()
            ->published()
            ->create();

        $response = $this->get($article->url());

        $response->assertOk();
        $response->assertSee($article->title);
    }

    public function test_user_cannot_view_unpublished_article(): void
    {
        $article = ArticleFactory::new()
            ->unpublished()
            ->create();

        $response = $this->get($article->url());

        $response->assertNotFound();
    }
}
```

### Test Performance

Use `make()` instead of `create()` when persistence isn't required:

```php
public function test_article_has_valid_title(): void
{
    // Faster: doesn't save to disk
    $article = ArticleFactory::new()->make([
        'title' => 'Test Title',
    ]);

    $this->assertEquals('Test Title', $article->title);
}

public function test_article_can_be_saved(): void
{
    // Use create() when you need persistence
    $article = ArticleFactory::new()->create();

    $this->assertNotNull($article->id());
}
```

### Preventing Disk Saves

Use Statamic's `PreventsSavingStacheItemsToDisk` trait to prevent saving during tests:

```php
class ArticleTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    public function test_article_factory(): void
    {
        // Creates article in memory only
        $article = ArticleFactory::new()->create();

        // No files written to content/collections/
        $this->assertFileDoesNotExist(
            base_path("content/collections/blog/{$article->slug()}.md")
        );
    }
}
```

### Recycling in Tests

Improve test performance by recycling models:

```php
public function test_can_create_many_articles_for_user(): void
{
    $author = UserFactory::new()->create();

    // Fast: reuses same author for all articles
    $articles = ArticleFactory::times(100)
        ->recycle($author)
        ->create();

    $this->assertCount(100, $articles);
    $articles->each(function ($article) use ($author) {
        $this->assertEquals($author->id(), $article->author);
    });
}
```

### Testing Multi-Site Features

Test localization and multi-site functionality:

```php
public function test_article_can_be_created_in_german_site(): void
{
    $article = ArticleFactory::new()
        ->inSite('de')
        ->create();

    $this->assertEquals('de', $article->locale());
}

public function test_article_exists_in_all_sites(): void
{
    $articles = ArticleFactory::new()
        ->inEachSite()
        ->create();

    $locales = $articles->pluck('locale')->unique();

    $this->assertContains('en', $locales);
    $this->assertContains('de', $locales);
}
```

> This feature works just like [Testing with Laravel Factories](https://laravel.com/docs/11.x/eloquent-factories#testing). Learn more in the Laravel documentation.

## Laravel Feature Compatibility

Factory is built on Laravel's factory foundation and supports many of the same features. For features not specifically documented above, refer to the Laravel factory documentation:

### Conditional Methods

Execute code conditionally using `when()` and `unless()`:

```php
ArticleFactory::new()
    ->when($condition, fn($factory) => $factory->featured())
    ->unless($otherCondition, fn($factory) => $factory->unpublished())
    ->create();
```

> Learn more about conditional methods in the [Laravel Factory documentation](https://laravel.com/docs/11.x/eloquent-factories).

### Macros

Extend factories with custom methods using macros:

```php
ArticleFactory::macro('popular', function () {
    return $this->state(['views' => $this->faker->numberBetween(10000, 100000)]);
});

$article = ArticleFactory::new()->popular()->create();
```

> Learn more about macros in the [Laravel Factory documentation](https://laravel.com/docs/11.x/eloquent-factories).

### Lazy Creation

Return a closure for deferred creation:

```php
$articleCreator = ArticleFactory::new()->lazy();

// Later...
$article = $articleCreator(); // Creates the article when called
```

> Learn more about lazy creation in the [Laravel Factory documentation](https://laravel.com/docs/11.x/eloquent-factories).

## Configuration

### Factory Namespace

Factories are organized under the `Database\Factories\Statamic\` namespace and stored in `database/factories/Statamic`:

```
database/factories/Statamic/
├── Collections/
│   └── Blog/
│       └── ArticleFactory.php    # Database\Factories\Statamic\Collections\Blog
├── Taxonomies/
│   └── Tags/
│       └── TagFactory.php         # Database\Factories\Statamic\Taxonomies\Tags
└── Users/
    └── UserFactory.php             # Database\Factories\Statamic\Users
```

### Auto-Resolution

Factories automatically resolve collection, taxonomy, and blueprint handles from their namespace and class name:

```php
// Database\Factories\Statamic\Collections\Blog\ArticleFactory
// Resolves to: collection = 'blog', blueprint = 'article'

// Database\Factories\Statamic\Taxonomies\Categories\CategoryFactory
// Resolves to: taxonomy = 'categories', blueprint = 'category'
```

Override auto-resolution by setting properties explicitly:

```php
class ArticleFactory extends Factory
{
    use CreatesEntry;

    protected string $collection = 'custom_collection';
    protected string $blueprint = 'custom_blueprint';
}
```

## Tips & Best Practices

- **Start with `make:factory`** - Use the command to generate factories with blueprint-aware scaffolding. It's much faster than writing from scratch.

- **Use `make()` in tests** - When persistence isn't required, `make()` is significantly faster than `create()` because it skips disk I/O.

- **Recycle models for performance** - When creating many related instances, always use `recycle()`. Creating 100 articles with unique authors means 100 user saves; recycling one user is dramatically faster.

- **Use sequences for variation** - Sequences create predictable variation in test data, making it easier to test different scenarios without manually creating each variation.

- **Organize seeders by dependency** - Create seeders that handle related content together. Users before articles, categories before products, etc.

- **Leverage locale-aware Faker** - When using multi-site features, Faker automatically adjusts to match the site's locale, generating appropriate localized content.

- **Keep factories simple** - Put complex logic in callbacks, not definitions. Definitions should be straightforward attribute generation.

- **Create reusable state methods** - For common variations (featured, archived, published), create state methods rather than repeating the same state configuration.

- **Use `repeat()` for array fields** - The `repeat()` helper makes generating arrays of items much cleaner and more maintainable.

- **Test with `PreventsSavingStacheItemsToDisk`** - This trait prevents factories from writing to disk during tests, making tests faster and cleaner.

## Troubleshooting

### Assets not generating

**Problem:** Assets aren't appearing in entries

**Solution:** Assets are only generated when using `create()`, not `make()`. Ensure you're calling `create()` and have an active internet connection for downloading placeholder images.

```php
// Won't generate assets:
$article = ArticleFactory::new()->make();

// Will generate assets:
$article = ArticleFactory::new()->create();
```

---

### Terms not appearing in all sites

**Problem:** Terms created in specific sites aren't showing data

**Solution:** Terms require data in the default locale. Factory handles this automatically, but ensure your taxonomy is configured for the sites you're targeting:

```php
// Ensure taxonomy has the sites configured
TaxonomyFacade::make('tags')->sites(['default', 'german'])->save();

// Then create terms
$tag = TagFactory::new()->inSite('german')->create();
```

---

### Slow factory execution

**Problem:** Creating content takes a long time

**Solutions:**

1. Use `make()` instead of `create()` when persistence isn't required
2. Use `recycle()` to reuse existing models instead of creating new ones
3. Avoid generating assets when not needed
4. Use `PreventsSavingStacheItemsToDisk` in tests

```php
// Slow: Creates 100 users
$articles = ArticleFactory::times(100)->create();

// Fast: Reuses one user
$author = UserFactory::new()->create();
$articles = ArticleFactory::times(100)->recycle($author)->create();
```

---

### Invalid site errors

**Problem:** Content isn't being created in the specified site

**Solution:** Ensure the collection/taxonomy is configured for that site. If a site isn't configured, Factory falls back to the first available site:

```php
// Configure collection for sites
CollectionFacade::make('blog')->sites(['default', 'german'])->save();

// Then create in that site
$article = ArticleFactory::new()->inSite('german')->create();
```

---

### Blueprint not found

**Problem:** Error about blueprint not being found

**Solution:** Ensure your collection/taxonomy handle and blueprint handle match your actual Statamic configuration. Check the `$collection`, `$taxonomy`, and `$blueprint` properties in your factory:

```php
class ArticleFactory extends Factory
{
    use CreatesEntry;

    // Must match actual collection handle
    protected string $collection = 'blog';

    // Must match actual blueprint handle in that collection
    protected string $blueprint = 'article';
}
```

---

### Complex field structure errors

**Problem:** Errors when creating content with Bard, Replicator, or Grid fields

**Solution:** Complex fields must match Statamic's expected format exactly. Create a test entry through the control panel and inspect the saved YAML to understand the expected structure:

```bash
# View the YAML structure
cat content/collections/blog/test-entry.md
```

Then match that structure in your factory definition.

## API Reference

### Creation Methods

- `create(array $attributes = [])` - Create and persist instance(s)
- `createOne(array $attributes = [])` - Create and persist a single instance
- `createMany(int|iterable|null $records = null)` - Create and persist multiple instances with specific attributes
- `make(array $attributes = [])` - Create instance(s) without persisting
- `makeOne(array $attributes = [])` - Create a single instance without persisting
- `raw(array $attributes = [])` - Get raw attribute array(s) without instantiating
- `lazy(array $attributes = [])` - Return a closure for deferred creation

### Count Methods

- `times(int $count)` - Set the number of instances to create
- `count(?int $count)` - Set or clear the instance count

### State Methods

- `state(mixed $state)` - Apply attribute transformations (array or closure)
- `set(string $key, mixed $value)` - Set a single attribute value
- `unpublished()` - Create unpublished entries (entries only)

### Sequence Methods

- `sequence(...$sequence)` - Cycle through attribute sequences
- `forEachSequence(...$sequence)` - Apply sequence and auto-set count
- `crossJoinSequence(...$sequence)` - Create cartesian product of sequences

### Multi-Site Methods

- `inSite(string $site)` - Create content in a specific site
- `inRandomSite()` - Create content in random sites
- `inEachSite(?int $count = null)` - Create content in each configured site

### Relationship Methods

- `recycle($model)` - Reuse existing model(s) instead of creating new ones

### Callback Methods

- `afterMaking(Closure $callback)` - Execute callback after making instance
- `afterCreating(Closure $callback)` - Execute callback after creating instance

### Conditional Methods

- `when(mixed $condition, Closure $callback)` - Execute callback if condition is true
- `unless(mixed $condition, Closure $callback)` - Execute callback if condition is false

### Helper Methods

- `repeat(int $count, Closure $callback)` - Generate array of repeated items
- `asset(string $field, int $width, int $height)` - Generate placeholder asset

## Contributing

Contributions are welcome! If you find a bug or have a feature request, please open an issue on GitHub.

## License

This addon is open-source software licensed under the [MIT license](LICENSE.md).
