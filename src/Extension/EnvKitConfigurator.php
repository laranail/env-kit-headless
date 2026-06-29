<?php

declare(strict_types=1);

namespace Simtabi\Laranail\EnvKit\Headless\Extension;

use Closure;
use Simtabi\Laranail\EnvKit\Headless\Contracts\AuditSinkInterface;
use Simtabi\Laranail\EnvKit\Headless\Contracts\DoctorRuleInterface;
use Simtabi\Laranail\EnvKit\Headless\Contracts\PortFormatInterface;
use Simtabi\Laranail\EnvKit\Headless\Contracts\ValueCipherInterface;
use Simtabi\Laranail\EnvKit\Headless\Contracts\WriterInterface;
use Simtabi\Laranail\EnvKit\Headless\EnvKit;

/**
 * The fluent registration DSL (`EnvKit::configure()->…`). A bound singleton a
 * consumer drives from their OWN service provider to reshape EnvKit at runtime —
 * extra protected keys, a custom writer, mutation-pipeline middleware, additional
 * audit sinks, ad-hoc macros — with zero edits to package source (Open/Closed,
 * §2A). EnvKit reads this state when it builds each commit pipeline.
 */
final class EnvKitConfigurator
{
    /** @var list<object> */
    private array $mutationMiddleware = [];

    /** @var list<string> */
    private array $protectedKeys = [];

    /** @var list<string> */
    private array $editableKeys = [];

    private ?WriterInterface $writer = null;

    /** @var list<AuditSinkInterface> */
    private array $auditSinks = [];

    private ?ValueCipherInterface $cipher = null;

    /** @var (Closure(): ValueCipherInterface)|null */
    private $cipherResolver = null;

    /** @var list<DoctorRuleInterface> */
    private array $doctorRules = [];

    /** @var list<PortFormatInterface> */
    private array $portFormats = [];

    /** Append a pipe to the commit pipeline (runs after the built-in guards, before write). */
    public function pushMutationMiddleware(object $pipe): self
    {
        $this->mutationMiddleware[] = $pipe;

        return $this;
    }

    /** @param list<string> $keys */
    public function protectKeys(array $keys): self
    {
        foreach ($keys as $key) {
            $this->protectedKeys[] = $key;
        }

        return $this;
    }

    /**
     * Restrict writable keys to an allowlist (supports wildcards, e.g. `APP_*`).
     * Empty = no restriction. Merged with config('env-kit.editable_keys').
     *
     * @param  list<string>  $keys
     */
    public function onlyEditable(array $keys): self
    {
        foreach ($keys as $key) {
            $this->editableKeys[] = $key;
        }

        return $this;
    }

    /** @return list<string> */
    public function editableKeys(): array
    {
        return $this->editableKeys;
    }

    /** Swap the writer strategy (e.g. wrap it in a decorator). */
    public function useWriter(WriterInterface $writer): self
    {
        $this->writer = $writer;

        return $this;
    }

    /** Register an additional audit destination (events fan out to all sinks). */
    public function registerAuditSink(AuditSinkInterface $sink): self
    {
        $this->auditSinks[] = $sink;

        return $this;
    }

    /** Register a custom doctor health-check rule (runs after the built-ins). */
    public function registerDoctorRule(DoctorRuleInterface $rule): self
    {
        $this->doctorRules[] = $rule;

        return $this;
    }

    /** @return list<DoctorRuleInterface> */
    public function doctorRules(): array
    {
        return $this->doctorRules;
    }

    /** Register a custom import/export format (e.g. YAML). */
    public function registerPortFormat(PortFormatInterface $format): self
    {
        $this->portFormats[] = $format;

        return $this;
    }

    /** @return list<PortFormatInterface> */
    public function portFormats(): array
    {
        return $this->portFormats;
    }

    /** Swap the value cipher used for encrypt()/decrypt() (e.g. a Vault-backed one). */
    public function useCipher(ValueCipherInterface $cipher): self
    {
        $this->cipher = $cipher;

        return $this;
    }

    /**
     * Provider-seeded: lazily resolve the default cipher so the Encrypter (and its
     * APP_KEY) is only touched when encryption is actually used.
     *
     * @param  Closure(): ValueCipherInterface  $resolver
     */
    public function resolveCipherUsing(Closure $resolver): self
    {
        $this->cipherResolver = $resolver;

        return $this;
    }

    public function cipher(): ?ValueCipherInterface
    {
        if ($this->cipher !== null) {
            return $this->cipher;
        }

        if ($this->cipherResolver !== null) {
            return $this->cipher = ($this->cipherResolver)();
        }

        return null;
    }

    /** Add a fluent method to EnvKit without subclassing (Macroable). */
    public function macro(string $name, callable $macro): self
    {
        EnvKit::macro($name, $macro);

        return $this;
    }

    /** @return list<object> */
    public function mutationMiddleware(): array
    {
        return $this->mutationMiddleware;
    }

    /** @return list<string> */
    public function protectedKeys(): array
    {
        return $this->protectedKeys;
    }

    public function writer(): ?WriterInterface
    {
        return $this->writer;
    }

    /** @return list<AuditSinkInterface> */
    public function auditSinks(): array
    {
        return $this->auditSinks;
    }
}
