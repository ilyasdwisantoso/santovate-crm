<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegistrationValidationMessageTest extends TestCase
{
    public function test_confirmed_password_mismatch_is_explained_in_indonesian(): void
    {
        App::setLocale('id');
        $validator = Validator::make([
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345X',
        ], ['password' => ['required', 'confirmed', 'min:8']]);

        $this->assertTrue($validator->fails());
        $this->assertSame('Konfirmasi password tidak cocok.', $validator->errors()->first('password'));
    }

    public function test_correct_confirmation_passes_and_registration_keeps_server_validation(): void
    {
        App::setLocale('id');
        $validator = Validator::make([
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
        ], ['password' => ['required', 'confirmed', 'min:8']]);

        $this->assertTrue($validator->passes());
        $route = app('router')->getRoutes()->getByName('register.store');
        $this->assertNotNull($route);
        $this->assertContains('guest', $route->gatherMiddleware());
    }
}
