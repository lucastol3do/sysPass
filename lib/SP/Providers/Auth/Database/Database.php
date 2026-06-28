<?php
declare(strict_types=1);

/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      https://syspass.org
 * @copyright 2012-2019, Rubén Domínguez nuxsmin@$syspass.org
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

namespace SP\Providers\Auth\Database;

use Exception;
use SP\Core\Crypt\Hash;
use SP\DataModel\UserLoginData;
use SP\Providers\Auth\AuthInterface;
use SP\Services\User\UserLoginResponse;
use SP\Services\User\UserPassService;
use SP\Services\User\UserService;

/**
 * Class Database
 *
 * Autentificación basada en base de datos
 *
 * @package SP\Providers\Auth\Database
 */
final class Database implements AuthInterface
{
    /**
     * @var UserLoginData $userLoginData
     */
    protected $userLoginData;
    /**
     * @var UserService
     */
    private $userService;
    /**
     * @var UserPassService
     */
    private $userPassService;

    /**
     * Database constructor.
     *
     * @param UserService     $userService
     * @param UserPassService $userPassService
     */
    public function __construct(UserService $userService, UserPassService $userPassService)
    {
        $this->userService = $userService;
        $this->userPassService = $userPassService;
    }


    /**
     * Autentificar al usuario
     *
     * @param UserLoginData $userLoginData Datos del usuario
     *
     * @return DatabaseAuthData
     */
    public function authenticate(UserLoginData $userLoginData)
    {
        $this->userLoginData = $userLoginData;

        $authData = new DatabaseAuthData();
        $authData->setAuthGranted($this->isAuthGranted());
        $authData->setAuthenticated($this->authUser());

        return $authData;
    }

    /**
     * Indica si es requerida para acceder a la aplicación
     *
     * @return boolean
     */
    public function isAuthGranted()
    {
        return true;
    }

    /**
     * Autentificación de usuarios con BD.
     *
     * Esta función comprueba la clave del usuario. Si el usuario necesita ser migrado desde phpPMS,
     * se ejecuta el proceso para actualizar la clave.
     *
     * @return bool
     */
    protected function authUser()
    {
        try {
            $userLoginResponse = UserService::mapUserLoginResponse($this->userService->getByLogin($this->userLoginData->getLoginUser()));

            $this->userLoginData->setUserLoginResponse($userLoginResponse);

            if ($userLoginResponse->getIsMigrate() && $this->checkMigrateUser($userLoginResponse)) {
                return $this->userPassService->migrateUserPassById($userLoginResponse->getId(), $this->userLoginData->getLoginPass());
            }

            return Hash::checkHashKey($this->userLoginData->getLoginPass(), $userLoginResponse->getPass());
        } catch (Exception $e) {
            processException($e);
        }

        return false;
    }

    /**
     * Verify migrated user credentials using secure hash algorithms only.
     *
     * MD5 and SHA1 hashes are no longer accepted due to cryptographic weakness.
     * Only bcrypt-based hashes (via password_verify or crypt with $2y$ prefix)
     * are allowed for migration. Users with MD5/SHA1 hashes must reset their passwords.
     *
     * @param UserLoginResponse $userLoginResponse
     *
     * @return bool
     */
    protected function checkMigrateUser(UserLoginResponse $userLoginResponse)
    {
        // Only accept bcrypt-based password verification for migration.
        // MD5 and SHA1 have been removed as they are cryptographically broken.
        $storedPass = $userLoginResponse->getPass();
        $inputPass = $this->userLoginData->getLoginPass();

        // Check if the stored hash is a bcrypt hash ($2y$ or $2a$ prefix)
        // and verify using crypt() with hash_equals() for timing-safe comparison
        if (preg_match('/^\$2[ay]\$/', $storedPass)
            && hash_equals($storedPass, crypt($inputPass, $storedPass))
        ) {
            return true;
        }

        // Use the standard bcrypt verification as fallback
        return Hash::checkHashKey($inputPass, $storedPass);
    }
}