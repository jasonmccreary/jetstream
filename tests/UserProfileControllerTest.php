<?php

namespace Laravel\Jetstream\Tests;

use JMac\Testing\Double;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Tests\Fixtures\User;
use Mockery as m;
use Orchestra\Testbench\Attributes\WithConfig;

#[WithConfig('jetstream.stack', 'inertia')]
class UserProfileControllerTest extends OrchestraTestCase
{
    /** {@inheritdoc} */
    #[\Override]
    protected function defineEnvironment($app)
    {
        tap($app->make('config'), function ($config) {
            $config->set([
                'fortify.features' => [
                    Features::registration(),
                    Features::resetPasswords(),
                    // Features::emailVerification(),
                    Features::updateProfileInformation(),
                    Features::updatePasswords(),
                    Features::twoFactorAuthentication([
                        'confirm' => true,
                        'confirmPassword' => true,
                    ]),
                ],
            ]);
        });

        Jetstream::useUserModel(User::class);
    }

    public function test_empty_two_factor_state_is_noted()
    {
        $disable = $this->mock(DisableTwoFactorAuthentication::class);
        $disable->expects('__invoke');

        Jetstream::$inertiaManager = $inertia = Double::for(\stdClass::class);
        $inertia->expects('render');

        $user = User::forceCreate([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
        ]);

        $response = $this->actingAs($user)->get('/user/profile');

        $response->assertSessionHas('two_factor_empty_at');

        $response->assertStatus(200);
    }

    public function test_two_factor_is_not_disabled_if_was_previously_empty_and_currently_confirming()
    {
        $disable = $this->mock(DisableTwoFactorAuthentication::class);
        $disable->expects('__invoke')->never();

        Jetstream::$inertiaManager = $inertia = Double::for(\stdClass::class);
        $inertia->expects('render');

        $user = User::forceCreate([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
            'two_factor_secret' => 'test-secret',
        ]);

        $response = $this->actingAs($user)
                        ->withSession(['two_factor_empty_at' => time()])
                        ->get('/user/profile');

        $response->assertStatus(200);
    }

    public function test_two_factor_is_disabled_if_was_previously_confirming_and_page_is_reloaded()
    {
        $disable = $this->mock(DisableTwoFactorAuthentication::class);
        $disable->expects('__invoke');

        Jetstream::$inertiaManager = $inertia = Double::for(\stdClass::class);
        $inertia->expects('render');

        $user = User::forceCreate([
            'name' => 'Taylor Otwell',
            'email' => 'taylor@laravel.com',
            'password' => 'secret',
            'two_factor_secret' => 'test-secret',
        ]);

        $response = $this->actingAs($user)
                        ->withSession([
                            'two_factor_empty_at' => time(),
                            'two_factor_confirming_at' => time() - 10,
                        ])
                        ->get('/user/profile');

        $response->assertStatus(200);
    }
}
