<?php

namespace Aerni\Factory\Factories;

use Aerni\Factory\Contracts\Factory;
use Aerni\Factory\Faker;
use Statamic\Contracts\Entries\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Support\Str;

class EntryFactory implements Factory
{
    protected Faker $faker;

    public function __construct(protected Collection $collection, protected Blueprint $blueprint)
    {
        $this->faker = app(Faker::class, ['blueprint' => $this->blueprint]);
    }

    public function run(int $amount = 1): void
    {
        for ($i = 0; $i < $amount; $i++) {
            $data = collect([
                'title' => $this->faker->title(),
            ])->merge($this->faker->data());

            Entry::make()
                ->collection($this->collection->handle())
                ->blueprint($this->blueprint->handle())
                ->locale(Site::default()->handle())
                ->published(config('factory.published'))
                ->slug(Str::slug($data['title']))
                ->data($data)
                ->set('updated_by', User::all()->random()->id())
                ->set('updated_at', now()->timestamp)
                ->save();
        }
    }
}
