<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard\Internal\Database\Schema;

enum ColumnArguments: string
{
    case NAME = 'name';
    case TYPE = 'type';
    case LENGTH = 'length';
    case NULLABLE = 'nullable';
    case PRIMARY = 'primary';
    case IDENTITY = 'identity';
    case DEFAULT = 'default';
}
