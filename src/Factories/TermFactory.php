<?php

namespace Aerni\Factory\Factories;

use Aerni\Factory\Contracts\Factory;
use Aerni\Factory\Faker;
use Statamic\Contracts\Taxonomies\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Support\Str;

class TermFactory implements Factory
{
    protected Faker $faker;

    public function __construct(protected Taxonomy $taxonomy, protected Blueprint $blueprint)
    {
        $this->faker = app(Faker::class, ['blueprint' => $this->blueprint]);
    }

    public function run(int $amount = 1): void
    {
        for ($i = 0; $i < $amount; $i++) {
            $data = collect([
                'title' => $this->faker->title(),
            ])->merge($this->faker->data());

            Term::make()
                ->taxonomy($this->taxonomy->handle())
                ->blueprint($this->blueprint->handle())
                ->slug(Str::slug($data['title']))
                ->data($data)
                ->set('updated_by', User::all()->random()->id())
                ->set('updated_at', now()->timestamp)
                ->save();
        }
    }
}
