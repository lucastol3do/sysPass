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

namespace SP\Modules\Web\Controllers;

use Exception;
use SP\Core\Context\ContextInterface;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Core\Exceptions\SPException;
use SP\DataModel\UserLoginData;
use SP\Http\Uri;
use SP\Providers\Auth\Oidc\OidcAuth;
use SP\Providers\Auth\Oidc\OidcAuthData;
use SP\Services\User\UserService;

defined('APP_ROOT') || die();

/**
 * Class OidcController
 *
 * Controlador para autenticación OpenID Connect
 */
final class OidcController extends ControllerBase
{
    /**
     * Redirigir al IdP para autenticación OIDC
     *
     * @return string
     */
    public function loginAction()
    {
        if (!$this->configData->isOidcEnabled()) {
            return $this->router->response()->redirect('index.php?r=index/index');
        }

        try {
            $oidcAuth = $this->dic->get(OidcAuth::class);
            $authUrl = $oidcAuth->getAuthorizationUrl();

            $this->eventDispatcher->notifyEvent('login.oidc.redirect',
                new Event($this, EventMessage::factory()
                    ->addDetail(__u('OIDC Server'), $this->configData->getOidcDiscoveryUrl()))
            );

            $this->router->response()->redirect($authUrl);
        } catch (Exception $e) {
            processException($e);

            $this->router->response()->redirect('index.php?r=login/index&oidc_error=1');
        }

        return '';
    }

    /**
     * Manejar callback del IdP OIDC
     *
     * @return string
     */
    public function callbackAction()
    {
        if (!$this->configData->isOidcEnabled()) {
            return $this->router->response()->redirect('index.php?r=index/index');
        }

        try {
            $oidcAuth = $this->dic->get(OidcAuth::class);

            $userLoginData = new UserLoginData();

            $authData = $oidcAuth->authenticate($userLoginData);

            if ($authData->getAuthenticated()) {
                $userService = $this->dic->get(UserService::class);
                $user = $this->findOrCreateUser($authData, $userService);

                if ($user !== null) {
                    $this->session->setUserData($user);

                    $this->eventDispatcher->notifyEvent('login.oidc.success',
                        new Event($this, EventMessage::factory()
                            ->addDetail(__u('User'), $authData->getUsername())
                            ->addDetail(__u('OIDC Server'), $authData->getServer()))
                    );

                    $uri = new Uri('index.php');
                    $uri->addParam('r', 'index');

                    return $this->router->response()->redirect($uri->getUri());
                }
            }

            $this->router->response()->redirect('index.php?r=login/index&oidc_error=1');
        } catch (Exception $e) {
            processException($e);
            $this->router->response()->redirect('index.php?r=login/index&oidc_error=1');
        }

        return '';
    }

    /**
     * Cerrar sesión OIDC (SLO)
     *
     * @return string
     */
    public function logoutAction()
    {
        try {
            if ($this->configData->isOidcEnabled()) {
                $oidcAuth = $this->dic->get(OidcAuth::class);

                $endSessionUrl = $oidcAuth->getEndSessionUrl();

                if ($endSessionUrl !== null) {
                    $this->session->setUserData(null);

                    $this->router->response()->redirect($endSessionUrl);
                    return '';
                }
            }
        } catch (Exception $e) {
            processException($e);
        }

        $this->router->response()->redirect('index.php?r=login/logout');

        return '';
    }

    /**
     * Buscar o crear usuario a partir de datos OIDC
     *
     * @param OidcAuthData $authData
     * @param UserService $userService
     *
     * @return \SP\DataModel\UserData|null
     */
    private function findOrCreateUser(OidcAuthData $authData, UserService $userService)
    {
        try {
            $userLogin = $authData->getUsername() ?: $authData->getEmail();

            if (empty($userLogin)) {
                return null;
            }

            try {
                $user = $userService->getByLogin($userLogin);

                if ($user !== null) {
                    return $user;
                }
            } catch (SPException $e) {
                // User not found — try to create
            }

            // Create new user if OIDC auto-provisioning is enabled
            if ($this->configData->getOidcDefaultGroup() > 0
                || $this->configData->getOidcDefaultProfile() > 0
            ) {
                return $userService->createFromOidc(
                    $userLogin,
                    $authData->getName(),
                    $authData->getEmail(),
                    $authData->getExternalId(),
                    $this->configData->getOidcDefaultGroup(),
                    $this->configData->getOidcDefaultProfile()
                );
            }

            return null;
        } catch (Exception $e) {
            processException($e);
            return null;
        }
    }

    protected function initialize()
    {
        // configData is already set by WebControllerTrait::setUp()
    }
}