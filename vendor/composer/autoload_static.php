<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit150f3641fba2aa7f9c7e1c8d776f657c
{
    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Faker\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Faker\\' => 
        array (
            0 => __DIR__ . '/..' . '/fzaninotto/faker/src/Faker',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit150f3641fba2aa7f9c7e1c8d776f657c::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit150f3641fba2aa7f9c7e1c8d776f657c::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit150f3641fba2aa7f9c7e1c8d776f657c::$classMap;

        }, null, ClassLoader::class);
    }
}
