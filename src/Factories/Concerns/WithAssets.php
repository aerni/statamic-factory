<?php

namespace Aerni\Factory\Factories\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Statamic\Contracts\Entries\Entry;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Fieldtypes\Assets\Assets;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Grid;
use Statamic\Fieldtypes\Replicator;
use Statamic\Forms\Uploaders\AssetsUploader;

trait WithAssets
{
    // TODO: This can be a lot of work since we are augmenting the full entry upfront.
    // Can we do it differently by only augmenting the fields we need?
    public function moveAssets(Entry $entry): void
    {
        $field = $entry->toAugmentedCollection()
            ->map(fn ($field) => match (true) {
                $field->fieldtype() instanceof Replicator => $field->value(),
                $field->fieldtype() instanceof Bard => $field->value(),
                $field->fieldtype() instanceof Grid => $field->value(),
                default => $field,
            })
            ->dot()
            ->map(fn ($value) => $value instanceof Values ? $value->all() : $value)
            ->flatten()
            ->filter(fn ($value) => $value instanceof Value && $value->fieldtype() instanceof Assets)
            ->each(fn ($value) => $value->value()?->move($this->assetsFolder($value->fieldtype())));
    }

    protected function assetsFolder(Assets $fieldtype): ?string
    {
        if (! in_array($field = $fieldtype->config('dynamic'), ['id', 'slug', 'author'])) {
            return null;
        }

        if (! ($parent = $fieldtype->field()->parent()) instanceof Entry) {
            return null;
        }

        $value = $parent->$field;

        return match (true) {
            $value instanceof Collection => $value->first(), /* If the author field doesn't have a max_items of 1, it'll be a collection, so grab the first one. */
            is_object($value) => $value->id(), /* If the author field had max_items 1 it would be a user, or since we got it above, use its id. */
            default => $value
        };
    }

    public function asset(string $field, int $width, int $height): string
    {
        $fields = $this->processFields($this->blueprint()->fields()->all());

        $field = collect($fields)->dot()->get($field);

        $image = $this->image($width, $height);

        if (!$image) {
            ray('no image');
            return '';
        }

        // TODO: Running into "The file "" does not exist" exception.
        // The issue seems to be that the image faker library returns an empty string. Maybe because it's returning before the image is downloaded or something like that?
        $uploadedFile = new UploadedFile($image, basename($image));

        $id = AssetsUploader::field($field->config())->upload($uploadedFile);

        return $field->setValue($id)->process()->value();
    }

    // TODO: This is basically a copy from the DefinitionGenerator. Can we abstract and reuse it?
    protected function processFields(Collection $fields): array
    {
        return $fields->map(fn (Field $field) => match ($field->type()) {
            'bard' => $this->processBardAndReplicator($field),
            'replicator' => $this->processBardAndReplicator($field),
            'grid' => $this->processGrid($field),
            default => $field,
        })->all();
    }

    // TODO: This is basically a copy from the DefinitionGenerator. Can we abstract and reuse it?
    protected function processBardAndReplicator(Field $field): array
    {
        return collect($field->toArray()['sets'])
            ->flatMap(function ($setGroup) {
                return collect($setGroup['sets'])->mapWithKeys(function ($set, $type) {
                    return [$type => $this->processFields((new Fields($set['fields']))->all())];
                });
            })->toArray();
    }

    // TODO: This is basically a copy from the DefinitionGenerator. Can we abstract and reuse it?
    protected function processGrid(Field $field): array
    {
        $fields = (new Fields($field->toArray()['fields']))
            ->all()
            ->pipe($this->processFields(...));

        return $fields;
    }

    protected function image(int $width, int $height): string
    {
        $storage = Storage::disk('local');

        $dir = 'factory-tmp';

        if (! $storage->exists($dir)) {
            $storage->makeDirectory($dir);
        }

        return $this->faker->image($storage->path($dir), $width, $height);
    }
}
