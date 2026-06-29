<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Schema;

use Closure;
use Simtabi\Laranail\EnvKit\Headless\Results\ValidationResult;

/**
 * A phpdotenv-style fluent schema for `.env` values. Type rules only fire on
 * PRESENT values — chain `required()` to also enforce presence. Built at runtime
 * (`EnvKit::schema()->...`) or seeded from `config('env-kit.schema')`.
 */
final class EnvSchema
{
    /** @var array<string, list<Closure(?string): ?string>> */
    private array $rules = [];

    public function required(string $key): self
    {
        return $this->rule($key, static fn (?string $v): ?string => $v === null || $v === '' ? 'is required' : null);
    }

    public function string(string $key): self
    {
        return $this->rule($key, static fn (?string $v): ?string => null); // every value is a string at rest
    }

    public function integer(string $key): self
    {
        return $this->rule($key, static fn (?string $v): ?string => $v !== null && $v !== '' && preg_match('/^-?\d+$/', $v) !== 1 ? 'must be an integer' : null);
    }

    public function boolean(string $key): self
    {
        $valid = ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'];

        return $this->rule($key, static fn (?string $v): ?string => $v !== null && $v !== '' && ! in_array(strtolower($v), $valid, true) ? 'must be a boolean' : null);
    }

    public function number(string $key): self
    {
        return $this->rule($key, static fn (?string $v): ?string => $v !== null && $v !== '' && ! is_numeric($v) ? 'must be a number' : null);
    }

    /** @param list<string> $allowed */
    public function in(string $key, array $allowed): self
    {
        return $this->rule($key, static fn (?string $v): ?string => $v !== null && $v !== '' && ! in_array($v, $allowed, true) ? 'must be one of: '.implode(', ', $allowed) : null);
    }

    public function regex(string $key, string $pattern): self
    {
        return $this->rule($key, static fn (?string $v): ?string => $v !== null && $v !== '' && @preg_match($pattern, $v) !== 1 ? 'does not match the required format' : null);
    }

    public function url(string $key): self
    {
        return $this->rule($key, static fn (?string $v): ?string => $v !== null && $v !== '' && filter_var($v, FILTER_VALIDATE_URL) === false ? 'must be a valid URL' : null);
    }

    public function email(string $key): self
    {
        return $this->rule($key, static fn (?string $v): ?string => $v !== null && $v !== '' && filter_var($v, FILTER_VALIDATE_EMAIL) === false ? 'must be a valid email' : null);
    }

    /** @param array<string, string> $values */
    public function validate(array $values): ValidationResult
    {
        $errors = [];

        foreach ($this->rules as $key => $validators) {
            $value = $values[$key] ?? null;
            foreach ($validators as $validate) {
                $error = $validate($value);
                if ($error !== null) {
                    $errors[$key][] = $error;
                    break; // first failure per key
                }
            }
        }

        return new ValidationResult($errors);
    }

    /**
     * Apply a Laravel-style rule spec (`'required|integer|in:a,b'` or a list).
     *
     * @param  string|list<string>  $rules
     */
    public function define(string $key, string|array $rules): self
    {
        $specs = is_string($rules) ? explode('|', $rules) : $rules;

        foreach ($specs as $spec) {
            $spec = trim((string) $spec);
            if ($spec === '') {
                continue;
            }

            [$name, $arg] = array_pad(explode(':', $spec, 2), 2, null);

            match (strtolower((string) $name)) {
                'required' => $this->required($key),
                'string' => $this->string($key),
                'integer', 'int' => $this->integer($key),
                'boolean', 'bool' => $this->boolean($key),
                'number', 'numeric', 'float' => $this->number($key),
                'url' => $this->url($key),
                'email' => $this->email($key),
                'in', 'enum' => $this->in($key, array_map('trim', explode(',', (string) $arg))),
                'regex' => $this->regex($key, (string) $arg),
                default => null,
            };
        }

        return $this;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->rules);
    }

    /** @param Closure(?string): ?string $validator */
    private function rule(string $key, Closure $validator): self
    {
        $this->rules[$key][] = $validator;

        return $this;
    }
}
