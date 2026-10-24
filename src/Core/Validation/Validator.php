<?php

declare(strict_types=1);

namespace Phai\Core\Validation;

use Phai\Core\Http\HttpException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Builds an input object from request data using its constructor:
 *
 *     final class UnitInput {
 *         public function __construct(
 *             #[Length(max: 10)] public string $um_um,     // required (no default, not nullable)
 *             public ?string $um_note = null,               // optional
 *             public bool $um_active = true,
 *         ) {}
 *     }
 *
 * Types are checked and converted (string, int, float, bool, array), then rule
 * attributes run. All errors are collected and thrown together as HTTP 422.
 */
final class Validator
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function make(string $class, array $data): object
    {
        $ctor = (new ReflectionClass($class))->getConstructor();
        $args = [];
        $errors = [];

        foreach ($ctor?->getParameters() ?? [] as $param) {
            $name = $param->getName();
            $error = null;
            $value = self::convert($param, $data, $error);

            if ($error === null && $value !== null) {
                foreach ($param->getAttributes() as $attr) {
                    $rule = $attr->newInstance();
                    if ($rule instanceof Rule && ($error = $rule->check($value)) !== null) {
                        break;
                    }
                }
            }

            if ($error !== null) {
                $errors[$name] = $error;
            }
            $args[$name] = $value;
        }

        if ($errors) {
            throw HttpException::invalid($errors);
        }
        return new $class(...$args);
    }

    private static function convert(ReflectionParameter $param, array $data, ?string &$error): mixed
    {
        $name = $param->getName();
        $type = $param->getType();
        $typeName = $type instanceof ReflectionNamedType ? $type->getName() : 'mixed';
        $nullable = $type === null || $type->allowsNull();

        $value = $data[$name] ?? null;
        if (is_string($value)) {
            $value = trim($value);
        }

        $missing = $value === null || $value === '';
        if ($missing) {
            if ($param->isDefaultValueAvailable() && !array_key_exists($name, $data)) {
                return $param->getDefaultValue();
            }
            if ($nullable) {
                return null;
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            if ($typeName === 'bool') {
                return false; // unchecked checkbox
            }
            $error = t('validation.required');
            return null;
        }

        switch ($typeName) {
            case 'string':
                if (is_scalar($value)) {
                    return (string) $value;
                }
                break;
            case 'int':
                if (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value))) {
                    return (int) $value;
                }
                $error = t('validation.int');
                return null;
            case 'float':
                if (is_numeric($value)) {
                    return (float) $value;
                }
                $error = t('validation.number');
                return null;
            case 'bool':
                $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                if ($bool !== null) {
                    return $bool;
                }
                break;
            case 'array':
                if (is_array($value)) {
                    return $value;
                }
                break;
            case 'mixed':
                return $value;
        }

        $error = t('validation.type');
        return null;
    }
}
