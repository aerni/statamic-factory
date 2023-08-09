![Statamic](https://flat.badgen.net/badge/Statamic/4.0+/FF269E) ![Packagist version](https://flat.badgen.net/packagist/v/aerni/factory/latest) ![Packagist Total Downloads](https://flat.badgen.net/packagist/dt/aerni/factory)

# Factory

This addon provides an easy way to quickly whip up fake data for your `Collection Entries`, `Taxonomy Terms` and `Globals` using [Faker](https://github.com/FakerPHP/Faker).


## Installation
Install the addon using Composer:

```bash
composer require aerni/factory
```

Publish the config of the package:

```bash
php please vendor:publish --tag=factory-config
```

The following config will be published to `config/factory.php`:

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
    | Tip: Randomize the status by setting this to '(bool) random_int(0, 1)'.
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
    | 'real_text': Use real english words instead of Lorem Ipsum.
    |
    */

    'title' => [
        'chars' => [$min = 20, $max = 30],
        'real_text' => true,
    ],

];
```

## Basic Usage

Before you go crazy whipping up fake goodies, you need to let the Factory know what fields you want it to create. You do so by defining a `factory` key on each field in your blueprint that you want to fake. The value of the key is a Faker formatter for simple fieldtypes and an array of options for advanced fieldtypes like a grid. Please consult the [Faker Documentation](https://github.com/FakerPHP/Faker) for available formatters.

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

### Bard & Replicator
`min_sets` defines the minimum number of sets to create.
`max_sets` defines the maximum number of sets to create.

```yaml
title: 'Bard & Replicator'
sections:
  main:
    display: Main
    fields:
      -
        handle: replicator
        field:
          type: replicator
          sets:
            text:
              display: Text
              factory:
                min_sets: 1
                max_sets: 3
              fields:
                -
                  handle: text
                  field:
                    type: text
                    factory: word
                -
                  handle: textarea
                  field:
                    type: textarea
                    factory: 'paragraph(3, true)'

```

### Grid
`min_rows` defines the minimum number of rows to create.
`max_rows` defines the maximum number of rows to create.

```yaml
title: Table
sections:
  main:
    display: Main
    fields:
      -
        handle: grid
        field:
          type: grid
          factory:
            min_rows: 1
            max_rows: 4
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
```

### Table
`min_rows` defines the minimum number of rows you want to create.
`max_rows` defines the maximum number of rows you want to create.
`min_cells` defines the minimum number of cells you want to create.
`max_cells` defines the maximum number of cells you want to create.
`formatter` defines the faker formatter to use.

```yaml
title: Table
sections:
  main:
    display: Main
    fields:
      -
        handle: table
        field:
          type: table
          factory:
            min_rows: 1
            max_rows: 3
            min_cells: 3
            max_cells: 5
            formatter: word
```
