<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiLaravel\Adapters;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionServiceDTO;
use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiLaravel\Exceptions\TenantAdapterException;
use Throwable;

abstract class AbstractTenantProvisioningAdapter
{
    protected function buildDatabaseName(string $projectCode): string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $projectCode) ?? $projectCode);
        $normalized = trim($normalized, '_');
        $envSuffix = app()->environment('production') ? 'prod' : 'dev';

        return 'yubus_' . $envSuffix . '_' . $normalized;
    }

    protected function createDatabaseIfMissing(string $dbName): void
    {
        $quoted = str_replace('`', '``', $dbName);
        $charset = (string) config('database.connections.mysql.charset', 'utf8mb4');
        $collation = (string) config('database.connections.mysql.collation', 'utf8mb4_unicode_ci');

        $sql = sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
            $quoted,
            $charset,
            $collation
        );

        try {
            DB::connection('mysql')->statement($sql);
        } catch (Throwable $exception) {
            throw new TenantAdapterException(
                ErrorFactory::dbCreateFailed($exception->getMessage(), [
                    'database' => $dbName,
                ])
            );
        }
    }

    protected function findServiceByKey(ProvisionPayloadDTO $dto, string $key): ?ProvisionServiceDTO
    {
        foreach ($dto->services as $service) {
            if ($service instanceof ProvisionServiceDTO && $service->key === $key) {
                return $service;
            }
        }

        return null;
    }

    protected function upsertSystemParameter(string $code, string $value): void
    {
        $updated = DB::table('system_parameter')
            ->where('code', $code)
            ->where('level', 'sistema')
            ->update(['parameter_value' => $value]);

        if ($updated === 0) {
            DB::table('system_parameter')->insert([
                'code' => $code,
                'level' => 'sistema',
                'description' => $code,
                'parameter_value' => $value,
            ]);
        }
    }

    protected function switchDatabase(string $dbName): void
    {
        Config::set('database.connections.mysql.database', $dbName);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    protected function restoreDatabase(string $dbName): void
    {
        if ($dbName === '') {
            return;
        }

        $this->switchDatabase($dbName);
    }
}
