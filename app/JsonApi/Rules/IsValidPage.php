<?php
/*
 * IsValidFilter.php
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

namespace FireflyIII\JsonApi\Rules;

use Illuminate\Contracts\Validation\ValidationRule;

class IsValidPage implements ValidationRule
{
    private array $allowed;

    public function __construct(array $keys)
    {
        $this->allowed = $keys;
    }

    #[\Override]
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        if ('page' !== $attribute) {
            $fail('validation.bad_api_filter')->translate();
        }
        if (!is_array($value)) {
            $value = explode(',', $value);
        }
        foreach ($value as $key => $val) {
            if (!in_array($key, $this->allowed, true)) {
                $fail('validation.bad_api_page')->translate();
            }
        }
    }
}
