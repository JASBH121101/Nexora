<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_owner_is_denied_with_an_existing_authenticated_session(): void
    {
        $this->post('/setup', [
            'country_code' => 'NI',
            'locale' => 'es-419',
            'business_name' => 'Tienda de prueba',
            'business_type' => 'perfumery',
            'owner_name' => 'Propietario de prueba',
            'owner_username' => 'ownerinactive',
            'owner_email' => 'owner-inactive@example.com',
            'owner_password' => 'Strong-Password-2026!',
            'owner_password_confirmation' => 'Strong-Password-2026!',
            'description' => 'Prueba de acceso del propietario.',
            'primary_color' => '#123456',
            'primary_currency' => 'USD',
            'additional_currencies' => ['NIO'],
            'phones' => [
                ['country_calling_code' => '+505', 'number' => '8888-1234', 'show_public' => '1'],
            ],
            'primary_phone' => '0',
            'photos_enabled' => '1',
        ])->assertRedirect(route('admin.home'));

        $owner = User::query()->where('username', 'ownerinactive')->firstOrFail();
        $owner->forceFill(['is_active' => false])->save();

        $this->get(route('admin.home'))->assertForbidden();
    }
}
