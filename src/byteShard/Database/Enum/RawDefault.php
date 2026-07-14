<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */
namespace byteShard\Database\Enum;

/**
 * Escape hatch for arbitrary default expressions, emitted verbatim and NOT translated
 * between database engines, e.g. new RawDefault('unix_timestamp()').
 * Prefer Enum\DB\DefaultValue for portable function defaults.
 */
class RawDefault
{
    public function __construct(private readonly string $expression)
    {
    }

    public function getExpression(): string
    {
        return $this->expression;
    }
}