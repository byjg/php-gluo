<?php

namespace RestReferenceArchitecture\Command;

use ByJG\Scriptify\CommandProvider;

/**
 * The project commands of ./gluo. The ./gluo script passes this class to the launcher.
 *
 * Map a command name to a method:
 *
 *     'user:cleanup' => MaintenanceService::class . '::cleanOldRecords',
 *
 * runs as `./gluo user:cleanup 30`: the values after the name are passed, in order, to the
 * method parameters, and `./gluo user:cleanup --help` prints the method's PHPDoc.
 *
 * Or map a group name to a class, for every public method it declares, in kebab-case:
 *
 *     'maintenance' => MaintenanceService::class,
 *
 * gives `./gluo maintenance:clean-old-records 30`, and one command per other public method.
 * A name produced twice is an error.
 *
 * - Names are lowercase, `group:action`, using a-z, 0-9, ':' and '-'. The built-in names
 *   (migrate:update, install, ...) are reserved.
 * - The class must be registered in the config definitions (config/dev/05-services.php):
 *   it is built by the container, never with `new`.
 * - The method must be public.
 *
 * `./gluo list --check` validates every command without running it, and
 * `./gluo --env=prod install <command>` installs one as a system service.
 */
class Commands implements CommandProvider
{
    #[\Override]
    public function commands(): array
    {
        return [
        ];
    }
}
