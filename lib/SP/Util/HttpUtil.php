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

namespace SP\Util;

use SP\Config\ConfigData;
use SP\Html\Html;
use SP\Http\Request;

/**
 * Class HttpUtil
 *
 * @package SP\Util
 */
final class HttpUtil
{
    /**
     * Comprobar y forzar (si es necesario) la conexión HTTPS
     *
     * @param ConfigData $configData
     * @param Request    $request
     */
    public static function checkHttps(ConfigData $configData, Request $request)
    {
        if ($configData->isHttpsEnabled() && !$request->isHttps()) {
            $host = $request->getHttpHost();

            // Handle reverse proxy: build HTTPS URL correctly
            if (strpos($host, 'http://') === 0) {
                $host = str_replace('http://', 'https://', $host);
            } elseif (strpos($host, 'https://') !== 0) {
                $host = 'https://' . $host;
            }

            $serverPort = $request->getServerPort();
            $port = ($serverPort && $serverPort != 80 && $serverPort != 443) ? ':' . $serverPort : '';

            if (!headers_sent()) {
                header('Location: ' . $host . $port . $_SERVER['REQUEST_URI']);
                exit();
            }
        }
    }

    /**
     * Comprobar si existen parámetros pasados por POST para enviarlos por GET
     */
    public static function importUrlParamsToGet()
    {
        $params = [];

        foreach ($_REQUEST as $param => $value) {
            $param = Filter::getString($param);

            if (strpos($param, 'g_') !== false) {
                $params[] = substr($param, 2) . '=' . Html::sanitize($value);
            }
        }

        return count($params) > 0 ? '?' . implode('&', $params) : '';
    }
}