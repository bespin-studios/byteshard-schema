<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */
namespace byteShard\Database\Enum;

/**
 * Portable function defaults. Each database layer translates these
 * to its native syntax (MariaDB: current_timestamp(), PGSQL: CURRENT_TIMESTAMP, ...).
 * For anything not covered, use byteShard\Database\Schema\RawDefault.
 */
enum DefaultValue: string
{
    case CURRENT_TIMESTAMP = 'current_timestamp';
    case CURRENT_DATE      = 'current_date';
    case CURRENT_TIME      = 'current_time';
    case UUID              = 'uuid';

    public static function tryFromExpression(string $expression): ?self
    {
        return match (strtolower(trim($expression))) {
            'current_timestamp()', 'current_timestamp', 'now()' => self::CURRENT_TIMESTAMP,
            'curdate()', 'current_date', 'current_date()'       => self::CURRENT_DATE,
            'curtime()', 'current_time', 'current_time()'       => self::CURRENT_TIME,
            'uuid()', 'gen_random_uuid()'                       => self::UUID,
            default                                             => null,
        };
    }
}