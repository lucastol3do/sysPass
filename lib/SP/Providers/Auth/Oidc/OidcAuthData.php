<?php
declare(strict_types=1);

/**
 * sysPass
 *
 * @author    lucastol3do
 * @link      https://syspass.org
 * @copyright 2026, sysPass revival project
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 *  along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace SP\Providers\Auth\Oidc;

use SP\Providers\Auth\AuthDataBase;

/**
 * Class OidcAuthData
 *
 * Datos de autentificación OIDC
 */
final class OidcAuthData extends AuthDataBase
{
    /**
     * @var string OIDC subject identifier (unique user ID from IdP)
     */
    protected $externalId;

    /**
     * @var array Groups from IdP claims
     */
    protected $groups = [];

    /**
     * @var string Username from IdP
     */
    protected $username;

    /**
     * @return string
     */
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    /**
     * @param string $externalId
     */
    public function setExternalId(string $externalId): void
    {
        $this->externalId = $externalId;
    }

    /**
     * @return array
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * @param array $groups
     */
    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    /**
     * @return string
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }
}