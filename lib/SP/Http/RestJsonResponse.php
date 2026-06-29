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

namespace SP\Http;

use SP\Core\Exceptions\SPException;

/**
 * Class RestJsonResponse
 *
 * A simple JSON response helper for REST API endpoints.
 *
 * @package SP\Http
 */
final class RestJsonResponse
{
    /**
     * Generate a success JSON response.
     *
     * @param mixed $data       The response data
     * @param int   $statusCode HTTP status code (default 200)
     *
     * @return string JSON-encoded response
     */
    public static function success($data, int $statusCode = 200): string
    {
        http_response_code($statusCode);

        return self::encode([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Generate an error JSON response.
     *
     * @param string      $message    Error message
     * @param int         $statusCode HTTP status code (default 400)
     * @param string|null $code       Optional error code
     *
     * @return string JSON-encoded response
     */
    public static function error(string $message, int $statusCode = 400, ?string $code = null): string
    {
        http_response_code($statusCode);

        $error = [
            'message' => __($message),
        ];

        if ($code !== null) {
            $error['code'] = $code;
        }

        return self::encode([
            'status' => 'error',
            'error' => $error,
        ]);
    }

    /**
     * Encode data as JSON.
     *
     * @param mixed $data
     *
     * @return string
     */
    private static function encode($data): string
    {
        $json = json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($json === false || json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(500);
            return json_encode([
                'status' => 'error',
                'error' => [
                    'message' => __('Encoding error'),
                ],
            ], JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return $json;
    }
}
