# Factory

This addon provides the Statamic equivalent of [Laravel model factories](https://laravel.com/docs/master/eloquent-factories#main-content).

Factory makes it easy to generate test data for your Statamic site by generating factory and seeder classes for entries, terms, and users. The package analyzes your blueprints and automatically scaffolds factory definitions based on your field configuration, giving you a solid starting point that you can customize.

## Installation
Install the addon using Composer:

```bash
composer require aerni/factory --dev
```

## Basic Usage

### Creating Factories

Generate a new factory using the interactive make command:

```bash
php please make:factory
```

The command will guide you through selecting the content you want to create a factory for. The factory will be generated in `database/factories/Statamic/` with a prefilled `definition()` method based on the selected content's blueprint fields. You'll also have the option to create a seeder class alongside your factory.

### Creating Seeders

Create a seeder for an existing factory:

```bash
php please make:seeder
```

You'll be prompted to select which factory you want to create a seeder for. Seeders are generated in `database/seeders/Statamic/`.

### Running Seeders

Seed your Statamic site with content:

```bash
php please seed
```

The command will guide you through an interactive selection of available seeders in `database/seeders/Statamic/`. You can also use the `--all` option to run all seeders without the interactive prompt.
