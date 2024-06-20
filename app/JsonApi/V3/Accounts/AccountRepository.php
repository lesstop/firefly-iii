<?php
/*
 * AccountRepository.php
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

namespace FireflyIII\JsonApi\V3\Accounts;

use FireflyIII\Models\Account;
use FireflyIII\Support\JsonApi\Concerns\UsergroupAware;
use LaravelJsonApi\Contracts\Store\QueriesAll;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class AccountRepository extends AbstractRepository implements QueriesAll
{
    use UsergroupAware;

    /**
     * SiteRepository constructor.
     */
    public function __construct() {}

    public function find(string $resourceId): ?object
    {
        return Account::find((int) $resourceId);
    }

    public function queryAll(): Capabilities\AccountQuery
    {
        return Capabilities\AccountQuery::make()
            ->withUserGroup($this->userGroup)
            ->withServer($this->server)
            ->withSchema($this->schema)
        ;
    }
}
