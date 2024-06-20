<?php
/*
 * ValidateSortParameters.php
 * Copyright (c) 2024 james@firefly-iii.org.
 *
 * This file is part of Firefly III (https://github.com/firefly-iii).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

declare(strict_types=1);

namespace FireflyIII\Support\JsonApi;

use LaravelJsonApi\Core\Query\SortFields;

trait ValidateSortParameters
{
    public function validateParams(string $class, ?SortFields $params): bool
    {
        if (null === $params) {
            return false;
        }

        $config = config(sprintf('firefly.full_data_set.%s', $class)) ?? [];

        foreach ($params->all() as $field) {
            if (in_array($field->name(), $config, true)) {
                return true;
            }
        }

        return false;
    }
}
