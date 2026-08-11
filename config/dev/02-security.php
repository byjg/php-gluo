<?php

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\Enum\LoginField;
use ByJG\Authenticate\Repository\UserPropertiesRepository;
use ByJG\Authenticate\Repository\UsersRepository;
use ByJG\Authenticate\Service\UsersService;
use ByJG\Config\Config;
use ByJG\Config\DependencyInjection as DI;
use ByJG\Config\Param;
use ByJG\JwtWrapper\JwtHashHmacSecret;
use ByJG\JwtWrapper\JwtKeyInterface;
use ByJG\JwtWrapper\JwtWrapper;
use RestReferenceArchitecture\Model\User;
use RestReferenceArchitecture\Model\UserProperties;

return [

    // JWT Configuration
    JwtKeyInterface::class => DI::bind(JwtHashHmacSecret::class)
        ->withConstructorArgs([Param::get('JWT_SECRET')])
        ->toSingleton(),

    JwtWrapper::class => DI::bind(JwtWrapper::class)
        ->withConstructorArgs([Param::get('API_SERVER'), Param::get(JwtKeyInterface::class)])
        ->toSingleton(),

    // Password Policy
    PasswordDefinition::class => DI::bind(PasswordDefinition::class)
        ->withConstructorArgs([[
            PasswordDefinition::MINIMUM_CHARS => 12,
            PasswordDefinition::REQUIRE_UPPERCASE => 1,  // Number of uppercase characters
            PasswordDefinition::REQUIRE_LOWERCASE => 1,  // Number of lowercase characters
            PasswordDefinition::REQUIRE_SYMBOLS => 1,    // Number of symbols
            PasswordDefinition::REQUIRE_NUMBERS => 1,    // Number of numbers
            PasswordDefinition::ALLOW_WHITESPACE => 0,   // Allow whitespace
            PasswordDefinition::ALLOW_SEQUENTIAL => 0,   // Allow sequential characters
            PasswordDefinition::ALLOW_REPEATED => 0      // Allow repeated characters
        ]])
        ->toSingleton(),

    // User Repositories
    UsersRepository::class => DI::bind(UsersRepository::class)
        ->withInjectedConstructorOverrides([
            'usersClass' => User::class
        ])
        ->toSingleton(),

    UserPropertiesRepository::class => DI::bind(UserPropertiesRepository::class)
        ->withInjectedConstructorOverrides([
            'propertiesClass' => UserProperties::class
        ])
        ->toSingleton(),

    // Users Service (replaces UserDefinition and UsersDBDataset)
    UsersService::class => DI::bind(UsersService::class)
        ->withInjectedConstructorOverrides([
            'loginField' => LoginField::Email,  // Use email as login field
        ])
        ->toSingleton(),

    // CORS Configuration
    'CORS_SERVER_LIST' => function () {
        return preg_split('/,(?![^{}]*})/', Config::get('CORS_SERVERS'));
    },

];
