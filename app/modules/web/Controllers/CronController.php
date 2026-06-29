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

namespace SP\Modules\Web\Controllers;

use Exception;
use Klein\Klein;
use Psr\Container\ContainerInterface;
use SP\Config\Config;
use SP\Config\ConfigData;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Services\Notification\PasswordExpiryNotificationService;

/**
 * Class CronController
 *
 * Controlador para tareas programadas (cron)
 *
 * @package SP\Modules\Web\Controllers
 */
final class CronController
{
    /**
     * @var Klein
     */
    private $router;

    /**
     * @var ConfigData
     */
    private $configData;

    /**
     * @var ContainerInterface
     */
    private $dic;

    /**
     * CronController constructor.
     *
     * @param ContainerInterface $container
     * @param string             $actionName
     */
    public function __construct(ContainerInterface $container, $actionName)
    {
        $this->dic = $container;
        $this->router = $container->get(Klein::class);
        $this->configData = $container->get(Config::class)->getConfigData();
    }

    /**
     * notifyAction
     *
     * Ejecutar la comprobación de contraseñas próximas a caducar y enviar notificaciones.
     * Requiere un token de seguridad (sk) para evitar accesos no autorizados.
     *
     * Uso: ?r=cron/notify&sk=CRON_SECRET
     */
    public function notifyAction()
    {
        try {
            $token = $this->router->request()->param('sk');

            if (empty($token)) {
                $this->router->response()->body(__u('Access denied'));
                $this->router->response()->code(403);

                return;
            }

            // Validar el token contra la clave de configuración
            $configHash = $this->configData->getConfigHash();

            if (empty($configHash) || !hash_equals($configHash, sha1($token))) {
                logger('Cron: Invalid security token');

                $this->router->response()->body(__u('Access denied'));
                $this->router->response()->code(403);

                return;
            }

            /** @var PasswordExpiryNotificationService $passwordExpiryService */
            $passwordExpiryService = $this->dic->get(PasswordExpiryNotificationService::class);

            $notificationsCreated = $passwordExpiryService->checkAndNotify();

            $message = sprintf(__u('Password expiry notification check completed. %d notifications created.'), $notificationsCreated);

            logger('Cron: ' . $message);

            $this->router->response()->body($message);
            $this->router->response()->code(200);
        } catch (Exception $e) {
            processException($e);

            logger('Cron error: ' . $e->getMessage());

            $this->router->response()->body(__u('Error while running cron job'));
            $this->router->response()->code(500);
        }
    }
}
