<?php

// Project commands for ./gluo: command name => "Namespace/Class::method".
//
//     'user:cleanup' => 'RestReferenceArchitecture/Service/MaintenanceService::cleanOldRecords',
//
// runs as `./gluo user:cleanup 30`: the values after the name are passed, in order, to the
// method parameters, and `./gluo user:cleanup --help` prints the method's PHPDoc.
//
// A class, without "::", exposes every public method it declares, in kebab-case:
//
//     'maintenance' => 'RestReferenceArchitecture/Service/MaintenanceService',
//
// gives `./gluo maintenance:clean-old-records 30`, and one command per other public method.
// A name produced twice is an error.
//
// - Names are lowercase, `group:action`, using a-z, 0-9, ':' and '-'.
// - The class must be registered in the config definitions (config/dev/05-services.php):
//   it is built by the container, never with `new`.
// - The method must be public.
//
// `./gluo list --check` validates every entry without running it.

return [
];
