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

use Jumbojett\OpenIDConnectClient;
use Jumbojett\OpenIDConnectClientException;
use SP\Config\ConfigData;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\DataModel\UserLoginData;
use SP\Providers\Auth\AuthInterface;
use SP\Providers\Provider;
use Symfony\Component\EventDispatcher\EventDispatcher;

defined('APP_ROOT') || die();

/**
 * Class OidcAuth
 *
 * Autentificación mediante OpenID Connect
 */
final class OidcAuth extends Provider implements AuthInterface
{
    /**
     * @var ConfigData
     */
    private $configData;

    /**
     * @var OpenIDConnectClient|null
     */
    private $client;

    /**
     * @param UserLoginData $userLoginData
     *
     * @return OidcAuthData
     */
    public function authenticate(UserLoginData $userLoginData): OidcAuthData
    {
        $authData = new OidcAuthData();
        $authData->setServer($this->configData->getOidcDiscoveryUrl());

        try {
            $client = $this->getClientInternal();

            // Attempt OIDC authentication (handles redirect + callback)
            $client->authenticate();

            // Get verified claims from ID token
            $claims = $client->getVerifiedClaims();

            $authData->setAuthenticated(true);
            $authData->setAuthGranted(true);

            // Map OIDC claims to auth data
            $authData->setEmail($claims->email ?? '');
            $authData->setName($claims->name ?? $claims->preferred_username ?? '');
            $authData->setExternalId($claims->sub);

            // Username: prefer preferred_username, then email local part
            if (property_exists($claims, 'preferred_username') && !empty($claims->preferred_username)) {
                $authData->setUsername($claims->preferred_username);
            } elseif (!empty($claims->email)) {
                $authData->setUsername(explode('@', $claims->email)[0]);
            } else {
                $authData->setUsername($claims->sub);
            }

            // Groups from claims (if available)
            if (property_exists($claims, 'groups') && is_array($claims->groups)) {
                $authData->setGroups($claims->groups);
            }

            $this->eventDispatcher->notifyEvent('login.auth.oidc.success',
                new Event($this, EventMessage::factory()
                    ->addDetail(__u('OIDC Server'), $this->configData->getOidcDiscoveryUrl())
                    ->addDetail(__u('User'), $authData->getUsername())
                    ->addDetail(__u('External ID'), $claims->sub))
            );
        } catch (OpenIDConnectClientException $e) {
            $authData->setAuthenticated(false);
            $authData->setFailed(true);
            $authData->setStatusCode(401);

            $this->eventDispatcher->notifyEvent('login.auth.oidc.error',
                new Event($this, EventMessage::factory()
                    ->addDetail(__u('OIDC Server'), $this->configData->getOidcDiscoveryUrl())
                    ->addDetail(__u('Error'), $e->getMessage()))
            );

            processException($e);
        } catch (\Exception $e) {
            $authData->setAuthenticated(false);
            $authData->setFailed(true);
            $authData->setStatusCode(500);

            processException($e);
        }

        return $authData;
    }

    /**
     * Obtener la URL de autorización para redirigir al IdP
     *
     * @return string
     * @throws OpenIDConnectClientException
     */
    public function getAuthorizationUrl(): string
    {
        $client = $this->getClientInternal();

        return $client->getAuthorizeUrl();
    }

    /**
     * Indica si la autentificación OIDC está habilitada
     *
     * @return bool
     */
    public function isAuthGranted(): bool
    {
        return $this->configData->isOidcEnabled();
    }

    /**
     * Obtener la URL de fin de sesión del IdP (SLO)
     *
     * @return string|null
     */
    public function getEndSessionUrl(): ?string
    {
        try {
            $client = $this->getClientInternal();

            return $client->getEndSessionUrl();
        } catch (\Exception $e) {
            processException($e);
            return null;
        }
    }

    /**
     * Obtener el cliente OIDC configurado (para uso externo)
     *
     * @return OpenIDConnectClient
     */
    public function getClient(): OpenIDConnectClient
    {
        return $this->getClientInternal();
    }

    /**
     * Obtener el cliente OIDC configurado
     *
     * @return OpenIDConnectClient
     */
    private function getClientInternal(): OpenIDConnectClient
    {
        if ($this->client === null) {
            $this->client = new OpenIDConnectClient(
                $this->configData->getOidcDiscoveryUrl(),
                $this->configData->getOidcClientId(),
                $this->configData->getOidcClientSecret()
            );

            $this->client->setRedirectURL($this->configData->getOidcRedirectUri());

            $scopes = explode(' ', $this->configData->getOidcScopes());
            $this->client->addScope($scopes);

            // Enable PKCE for security
            $this->client->setCodeChallengeMethod('S256');
        }

        return $this->client;
    }

    /**
     * @param Container $dic
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function initialize(\DI\Container $dic)
    {
        $this->configData = $this->config->getConfigData();
    }
}