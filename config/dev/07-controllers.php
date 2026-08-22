<?php

use ByJG\Config\Autowire;

/**
 * Controller Bindings
 *
 * The Server resolves route controllers from the container (see the Server binding in
 * 03-api.php), which is what lets a controller declare its dependencies in the
 * constructor instead of calling Config::get() inside every method.
 *
 * Controllers are autowired by pattern rather than listed one by one. A controller is a
 * terminal class — nothing else depends on it — so a per-class binding would encode no
 * decision: there is one implementation, named directly by the router; it is always
 * per-request; and every constructor argument is a type-hinted service that is itself
 * explicitly bound in 04-repositories.php / 05-services.php.
 *
 * - Classes with no constructor (an ActiveRecord controller, say) degrade to
 *   withConstructorNoArgs() automatically.
 * - toInstance(), not toSingleton(): a controller is per-request, and holding one across
 *   requests invites leaked state.
 * - An explicit binding still wins. Add one below for a controller that needs something
 *   the rule cannot express, such as a scalar constructor argument.
 *
 * The pattern is namespace-scoped deliberately. A bare '*Controller' would also match
 * classes in vendor/, and since Container::has() consults these rules that could quietly
 * change the meaning of `Config::has(X) ? Config::get(X) : $default` checks elsewhere.
 */
return [

    'RestReferenceArchitecture\Controller\*' => Autowire::rule()
        ->withInjectedConstructor()
        ->toInstance(),

];
