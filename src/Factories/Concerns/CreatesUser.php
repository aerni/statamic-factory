<?php

namespace Aerni\Factory\Factories\Concerns;

use Illuminate\Support\Arr;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\User as UserFacade;

trait CreatesUser
{
    protected $model = User::class;

    public function newModel(array $attributes = []): User
    {
        $user = UserFacade::make();

        if ($id = Arr::pull($attributes, 'id')) {
            $user->id($id);
        }

        if ($email = Arr::pull($attributes, 'email')) {
            $user->email($email);
        }

        if ($password = Arr::pull($attributes, 'password')) {
            $user->password($password);
        }

        if ($preferences = Arr::pull($attributes, 'preferences')) {
            $user->preferences($preferences);
        }

        return $user->data($attributes);
    }
}
