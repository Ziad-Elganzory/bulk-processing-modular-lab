<?php

namespace App\Messaging\Contracts;

interface MessageContract
{
    public function messageType(): string;

    /** @return array<string, mixed> */
    public function data(): array;

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): static;
}
