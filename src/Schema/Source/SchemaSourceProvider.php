<?php declare(strict_types=1);

namespace Lantern\Schema\Source;

interface SchemaSourceProvider
{
    /** Provide the string contents of the schema definition. */
    public function getSchemaString(): string;
}
