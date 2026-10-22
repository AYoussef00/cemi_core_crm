<?php

namespace Tests\Concerns;

use Database\Seeders\RolesAndPermissionsSeeder;

trait SeedsAuthorization
{
    protected function seedAuthorization(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }
}
