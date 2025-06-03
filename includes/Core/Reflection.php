<?php

namespace FluentMail\Includes\Core;

use ReflectionParameter;
use ReflectionNamedType;

class Reflection
{
    private static function isPhp8OrHigher()
    {
        return PHP_VERSION_ID >= 80000;
    }

    public static function getClassName(ReflectionParameter $parameter)
    {
        if (static::isPhp8OrHigher()) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                return $type->getName();
            }

            return null;
        }

        $class = $parameter->getClass();

        return $class ? $class->getName() : null;
    }
}


