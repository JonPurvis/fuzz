<?php

declare(strict_types=1);

namespace Fuzz\Support;

use Closure;
use Fuzz\Exceptions\FuzzException;
use Laravel\SerializableClosure\SerializableClosure;
use Laravel\SerializableClosure\UnsignedSerializableClosure;
use ReflectionFunction;

final class TargetSerializer
{
    public function serialize(Closure $target): string
    {
        return serialize(new SerializableClosure($this->withoutBoundThis($target)));
    }

    public function unserialize(string $payload): Closure
    {
        $result = unserialize($payload);

        if ($result instanceof SerializableClosure || $result instanceof UnsignedSerializableClosure) {
            return $result->getClosure();
        }

        throw new FuzzException('Unable to unserialize fuzz target closure.');
    }

    public function write(string $path, Closure $target): void
    {
        if (file_put_contents($path, $this->serialize($target)) === false) {
            throw new FuzzException("Unable to write target payload [{$path}].");
        }
    }

    public function read(string $path): Closure
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new FuzzException("Unable to read target payload [{$path}].");
        }

        return $this->unserialize($contents);
    }

    /**
     * Pest binds closures to the generated test case class. That binding cannot be
     * restored inside the isolated worker, so strip $this before serializing.
     */
    private function withoutBoundThis(Closure $target): Closure
    {
        $reflection = new ReflectionFunction($target);

        if ($reflection->getClosureThis() === null) {
            return $target;
        }

        $unbound = @$target->bindTo(null, 'static');

        if ($unbound instanceof Closure) {
            return $unbound;
        }

        $unbound = @$target->bindTo(null);

        if ($unbound instanceof Closure) {
            return $unbound;
        }

        throw new FuzzException(
            'Fuzz targets must not be bound to $this. Use a static function (string $input): void { ... } '
            .'or Closure::fromCallable([SomeClass::class, \'method\']).'
        );
    }
}
