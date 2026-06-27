<?php
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

namespace SP\Services\Api;

/**
 * Class ApiRequest
 *
 * @package SP\Services\Api
 */
final class ApiRequest
{
    /**
     * @var string
     */
    protected $method;
    /**
     * @var int
     */
    protected $id;
    /**
     * @var ApiRequestData
     */
    protected $data;

    /**
     * ApiRequest constructor.
     *
     * @param string $request
     *
     * @throws ApiRequestException
     */
    public function __construct($request = null)
    {
        if ($request === null) {
            $this->requestFromJsonData($this->getDataFromRequest());
        } else {
            $this->requestFromJsonData($request);
        }
    }

    /**
     * Obtener los datos de la petición
     *
     * Comprueba que el JSON esté bien formado
     *
     * @param null $request
     *
     * @return ApiRequest
     * @throws ApiRequestException
     */
    public function requestFromJsonData($request)
    {
        $data = json_decode($request, true);

        if ($data === null) {
            throw new ApiRequestException(
                __u('Invalid data'),
                ApiRequestException::ERROR,
                json_last_error_msg(),
                JsonRpcResponse::PARSE_ERROR
            );
        }

        // Try to get the auth token from the Authorization header first (more secure),
        // then fall back to the JSON-RPC params for backward compatibility.
        // Passing tokens in headers prevents them from appearing in server/proxy logs.
        $authToken = $this->getAuthTokenFromHeader();
        if ($authToken !== null) {
            $data['params']['authToken'] = $authToken;
        }

        if (!isset($data['jsonrpc'], $data['method'], $data['id'])
            || (!isset($data['params']['authToken']) && $authToken === null)
        ) {
            throw new ApiRequestException(
                __u('Invalid format'),
                ApiRequestException::ERROR,
                null,
                JsonRpcResponse::INVALID_REQUEST
            );
        }

        $this->method = preg_replace('#[^a-z/]+#i', '', $data['method']);
        $this->id = filter_var($data['id'], FILTER_VALIDATE_INT) ?: 1;
        $this->data = new ApiRequestData();
        $this->data->replace($data['params']);

        return $this;
    }

    /**
     * Extract auth token from Authorization header
     *
     * Supports both "Bearer <token>" and raw token formats.
     * This is preferred over passing the token in the JSON body
     * because headers are less likely to be logged by proxies/servers.
     *
     * @return string|null
     */
    private function getAuthTokenFromHeader()
    {
        $authHeader = isset($_SERVER['HTTP_AUTHORIZATION'])
            ? trim($_SERVER['HTTP_AUTHORIZATION'])
            : null;

        if (empty($authHeader)) {
            // Also check for redirect/auth headers from Apache/nginx
            $authHeader = isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
                ? trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])
                : null;
        }

        if (empty($authHeader)) {
            return null;
        }

        // Strip "Bearer " prefix if present
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Return raw header value as token
        return $authHeader;
    }

    /**
     * @return string
     * @throws ApiRequestException
     */
    public function getDataFromRequest()
    {
        $content = file_get_contents('php://input');

        if ($content === false || empty($content)) {
            throw new ApiRequestException(
                __u('Invalid data'),
                ApiRequestException::ERROR,
                null,
                JsonRpcResponse::PARSE_ERROR
            );
        }

        return $content;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data->get($key, $default);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function exists(string $key)
    {
        return $this->data->exists($key);
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}