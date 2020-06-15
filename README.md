# Factory
Factory is a Statamic v3 addon that provides an easy way to quickly whip up fake `collection entries` and `taxonomy terms` using [Faker](https://github.com/fzaninotto/Faker).

## Installation
Install the addon using Composer.

```bash
composer require aerni/statamic-factory
```

Publish the config of the package.

```bash
php artisan vendor:publish --provider="Aerni\Factory\ServiceProvider"
```

The following config will be published to `config/factory.php`.

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Publish Status
    |--------------------------------------------------------------------------
    |
    | The publish status of collection entries and taxonomy terms
    | created by the factory.
    |
    | Tipp: Randomize the status by setting this to '(bool) random_int(0, 1)'.
    |
    */

    'published' => true,

    /*
    |--------------------------------------------------------------------------
    | Title Fallback Settings
    |--------------------------------------------------------------------------
    |
    | These title settings will function as a fallback to create titles for
    | your collection entries and taxonomy terms, if you didn't explicitly set
    | a 'title' field in the respective blueprint.
    |
    | 'chars': The character count of the title will be in this range.
    | 'real_text': Use real english words by setting this to 'true'.
    |
    */

    'title' => [
        'chars' => [$min = 10, $max = 20],
        'real_text' => false,
    ],

];
```

***

## Basic Usage

Before you go crazy whipping up fake goodies, you need to let the Factory know what fields you want it to create. You do so by defining a `factory` key on each field in your blueprint that you want to fake. The value of the key is a Faker formatter. Please consult the [Faker Documentation](https://github.com/fzaninotto/Faker) for available formatters.

This is an example blueprint for a collection of people:
```yaml
title: Person
sections:
  main:
    display: Main
    fields:
      -
        handle: first_name
        field:
          type: text
          factory: firstName
      -
        handle: last_name
        field:
          type: text
          factory: lastName
      -
        handle: age
        field:
          type: number
          factory: numberBetween(20, 50)
      -
        handle: bio
        field:
          type: textarea
          factory: paragraph(3, true)
```

Run the factory in your terminal and follow the instructions:

```bash
php please factory
```

## Special Fieldtypes

The above example works great for basic fieldtypes. But what about Bard, Replicator, Grid and Tables? I'm glad you asked. To fake content for these "Special Fieldtypes" you need to change the blueprint according to the examples below.

### Table
`rows` defines the number of rows you want to create.  
`cells` defines the number of cells you want to create.  
`faker` defines the faker formatter to use.

```yaml
title: Special Fieldtypes
sections:
  main:
    display: Main
    fields:
      -
        handle: table
        field:
          type: table
          factory:
            rows: 2
            cells: 5
            faker: word
```