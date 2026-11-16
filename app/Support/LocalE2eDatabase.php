<?php

namespace App\Support;

use Illuminate\Support\ConfigurationUrlParser;

class LocalE2eDatabase
{
    public static function allowsWrites(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        $database = (new ConfigurationUrlParser)->parseConfiguration(
            config('database.connections.'.config('database.default')),
        );

        if (isset($database['read']) || isset($database['write'])) {
            return false;
        }

        return (($database['driver'] ?? '') === 'sqlite' && ($database['database'] ?? '') === ':memory:')
            || (in_array($database['driver'] ?? '', ['mysql', 'mariadb'], true)
                && in_array($database['host'] ?? '', ['127.0.0.1', 'localhost'], true)
                && ($database['database'] ?? '') === 'hbiz2_qa');
    }
}
