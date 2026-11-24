<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base des tests Arcade.
 * SÉCURITÉ : RefreshDatabase vide la base. On refuse donc de tourner
 * si les tests ne sont pas configurés sur SQLite en mémoire (phpunit.xml).
 */
abstract class ArcadeTestCase extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        if (
            config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
        ) {
            throw new \RuntimeException(
                'Tests refusés : dans phpunit.xml, active DB_CONNECTION=sqlite et DB_DATABASE=:memory: '
                .'(sinon RefreshDatabase effacerait ta vraie base de données).'
            );
        }
    }
}
