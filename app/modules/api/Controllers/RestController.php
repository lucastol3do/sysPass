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

namespace SP\Modules\Api\Controllers;

use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Klein\Response;
use SP\Core\Acl\ActionsInterface;
use SP\Core\Crypt\Crypt;
use SP\Core\Events\Event;
use SP\Core\Events\EventMessage;
use SP\Core\Exceptions\InvalidClassException;
use SP\Core\Exceptions\SPException;
use SP\Http\RestJsonResponse;
use SP\Mvc\Model\QueryCondition;
use SP\Repositories\NoSuchItemException;
use SP\Services\Account\AccountPresetService;
use SP\Services\Account\AccountRequest;
use SP\Services\Account\AccountSearchFilter;
use SP\Services\Account\AccountService;
use SP\Services\Category\CategoryService;
use SP\Services\Client\ClientService;
use SP\Services\Tag\TagService;
use SP\Services\UserGroup\UserGroupService;

/**
 * Class RestController
 *
 * REST API controller for sysPass.
 * Maps HTTP methods to resource actions with Bearer token authentication.
 *
 * @package SP\Modules\Api\Controllers
 */
final class RestController
{
    /**
     * @var AccountService
     */
    private $accountService;
    /**
     * @var AccountPresetService
     */
    private $accountPresetService;
    /**
     * @var CategoryService
     */
    private $categoryService;
    /**
     * @var ClientService
     */
    private $clientService;
    /**
     * @var TagService
     */
    private $tagService;
    /**
     * @var UserGroupService
     */
    private $userGroupService;
    /**
     * @var \SP\Core\Context\StatelessContext
     */
    private $context;
    /**
     * @var \SP\Core\Events\EventDispatcher
     */
    private $eventDispatcher;
    /**
     * @var \SP\Services\Api\ApiService
     */
    private $apiService;
    /**
     * @var \Psr\Container\ContainerInterface
     */
    private $dic;
    /**
     * @var Response
     */
    private $response;

    /**
     * RestController constructor.
     *
     * @param \Psr\Container\ContainerInterface $container
     * @param Response                          $response
     *
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct(\Psr\Container\ContainerInterface $container, Response $response)
    {
        $this->dic = $container;
        $this->response = $response;
        $this->context = $container->get(\SP\Core\Context\StatelessContext::class);
        $this->eventDispatcher = $container->get(\SP\Core\Events\EventDispatcher::class);
        $this->apiService = $container->get(\SP\Services\Api\ApiService::class);
        $this->accountService = $container->get(AccountService::class);
        $this->accountPresetService = $container->get(AccountPresetService::class);
        $this->categoryService = $container->get(CategoryService::class);
        $this->clientService = $container->get(ClientService::class);
        $this->tagService = $container->get(TagService::class);
        $this->userGroupService = $container->get(UserGroupService::class);
    }

    /**
     * Authenticate the request using Bearer token.
     *
     * @param int $actionId
     *
     * @throws SPException
     * @throws \SP\Services\ServiceException
     */
    private function authenticate(int $actionId): void
    {
        $this->apiService->setup($actionId);
    }

    /**
     * Send a JSON response.
     *
     * @param string $json
     */
    private function sendJson(string $json): void
    {
        $this->response->header('Content-type', 'application/json; charset=utf-8');
        $this->response->body($json);
    }

    /**
     * Send a success response.
     *
     * @param mixed $data
     * @param int   $statusCode
     */
    private function sendSuccess($data, int $statusCode = 200): void
    {
        $this->sendJson(RestJsonResponse::success($data, $statusCode));
    }

    /**
     * Send an error response.
     *
     * @param string      $message
     * @param int         $statusCode
     * @param string|null $code
     */
    private function sendError(string $message, int $statusCode = 400, ?string $code = null): void
    {
        $this->sendJson(RestJsonResponse::error($message, $statusCode, $code));
    }

    // ----------------------------------------------------------------
    //  Account endpoints
    // ----------------------------------------------------------------

    /**
     * GET /api/rest/accounts — Search accounts.
     *
     * @param \Klein\Request $request
     */
    public function searchAccounts(\Klein\Request $request): void
    {
        try {
            $this->authenticate(ActionsInterface::ACCOUNT_SEARCH);

            $accountSearchFilter = new AccountSearchFilter();
            $accountSearchFilter->setCleanTxtSearch($request->param('text'));
            $accountSearchFilter->setCategoryId((int)$request->param('categoryId', 0));
            $accountSearchFilter->setClientId((int)$request->param('clientId', 0));

            $tagsId = array_map('intval', array_filter(explode(',', (string)$request->param('tagsId', ''))));

            if (!empty($tagsId)) {
                $accountSearchFilter->setTagsId($tagsId);
            }

            $op = $request->param('op');

            if ($op !== null) {
                switch ($op) {
                    case 'and':
                        $accountSearchFilter->setFilterOperator(QueryCondition::CONDITION_AND);
                        break;
                    case 'or':
                        $accountSearchFilter->setFilterOperator(QueryCondition::CONDITION_OR);
                        break;
                }
            }

            $accountSearchFilter->setLimitCount((int)$request->param('count', 50));
            $accountSearchFilter->setSortOrder((int)$request->param('order', AccountSearchFilter::SORT_DEFAULT));

            $results = $this->accountService->getByFilter($accountSearchFilter)->getDataAsArray();

            $this->sendSuccess($results);
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    /**
     * GET /api/rest/accounts/{id} — View account.
     *
     * @param \Klein\Request $request
     * @param int            $id
     */
    public function viewAccount(\Klein\Request $request, int $id): void
    {
        try {
            $this->authenticate(ActionsInterface::ACCOUNT_VIEW);

            $accountDetails = $this->accountService->getById($id)->getAccountVData();

            $this->accountService->incrementViewCounter($id);

            $this->eventDispatcher->notifyEvent('show.account',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Account displayed'))
                    ->addDetail(__u('Name'), $accountDetails->getName())
                    ->addDetail(__u('Client'), $accountDetails->getClientName())
                    ->addDetail('ID', $id))
            );

            $this->sendSuccess($accountDetails);
        } catch (NoSuchItemException $e) {
            processException($e);
            $this->sendError(__u('Account not found'), 404);
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    /**
     * POST /api/rest/accounts — Create account.
     *
     * @param \Klein\Request $request
     */
    public function createAccount(\Klein\Request $request): void
    {
        try {
            $this->authenticate(ActionsInterface::ACCOUNT_CREATE);

            $body = json_decode($request->body(), true);

            if ($body === null || !isset($body['name'], $body['clientId'], $body['categoryId'])) {
                $this->sendError(__u('Missing required fields: name, clientId, categoryId'), 400);
                return;
            }

            $accountRequest = new AccountRequest();
            $accountRequest->name = $body['name'];
            $accountRequest->clientId = (int)$body['clientId'];
            $accountRequest->categoryId = (int)$body['categoryId'];
            $accountRequest->login = $body['login'] ?? null;
            $accountRequest->url = $body['url'] ?? null;
            $accountRequest->notes = $body['notes'] ?? null;
            $accountRequest->isPrivate = isset($body['private']) ? (int)$body['private'] : null;
            $accountRequest->isPrivateGroup = isset($body['privateGroup']) ? (int)$body['privateGroup'] : null;
            $accountRequest->passDateChange = isset($body['expireDate']) ? (int)$body['expireDate'] : null;
            $accountRequest->parentId = isset($body['parentId']) ? (int)$body['parentId'] : null;

            $userData = $this->context->getUserData();

            $accountRequest->userId = isset($body['userId']) ? (int)$body['userId'] : $userData->getId();
            $accountRequest->userGroupId = isset($body['userGroupId']) ? (int)$body['userGroupId'] : $userData->getUserGroupId();

            $accountRequest->tags = isset($body['tagsId']) ? array_map('intval', $body['tagsId']) : [];
            $accountRequest->pass = $body['pass'] ?? '';

            $this->accountPresetService->checkPasswordPreset($accountRequest);

            $accountId = $this->accountService->create($accountRequest);

            $accountDetails = $this->accountService->getById($accountId)->getAccountVData();

            $this->eventDispatcher->notifyEvent('create.account',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Account created'))
                    ->addDetail(__u('Name'), $accountDetails->getName())
                    ->addDetail(__u('Client'), $accountDetails->getClientName())
                    ->addDetail('ID', $accountDetails->getId()))
            );

            $this->sendSuccess($accountDetails, 201);
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    /**
     * PUT /api/rest/accounts/{id} — Edit account.
     *
     * @param \Klein\Request $request
     * @param int            $id
     */
    public function editAccount(\Klein\Request $request, int $id): void
    {
        try {
            $this->authenticate(ActionsInterface::ACCOUNT_EDIT);

            $body = json_decode($request->body(), true);

            if ($body === null || !isset($body['name'], $body['clientId'], $body['categoryId'])) {
                $this->sendError(__u('Missing required fields: name, clientId, categoryId'), 400);
                return;
            }

            $accountRequest = new AccountRequest();
            $accountRequest->id = $id;
            $accountRequest->name = $body['name'];
            $accountRequest->clientId = (int)$body['clientId'];
            $accountRequest->categoryId = (int)$body['categoryId'];
            $accountRequest->login = $body['login'] ?? null;
            $accountRequest->url = $body['url'] ?? null;
            $accountRequest->notes = $body['notes'] ?? null;
            $accountRequest->isPrivate = isset($body['private']) ? (int)$body['private'] : null;
            $accountRequest->isPrivateGroup = isset($body['privateGroup']) ? (int)$body['privateGroup'] : null;
            $accountRequest->passDateChange = isset($body['expireDate']) ? (int)$body['expireDate'] : null;
            $accountRequest->parentId = isset($body['parentId']) ? (int)$body['parentId'] : null;
            $accountRequest->userId = isset($body['userId']) ? (int)$body['userId'] : null;
            $accountRequest->userGroupId = isset($body['userGroupId']) ? (int)$body['userGroupId'] : null;
            $accountRequest->userEditId = $this->context->getUserData()->getId();

            $tagsId = isset($body['tagsId']) ? array_map('intval', $body['tagsId']) : [];

            if (!empty($tagsId)) {
                $accountRequest->updateTags = true;
                $accountRequest->tags = $tagsId;
            }

            $this->accountService->update($accountRequest);

            $accountDetails = $this->accountService->getById($id)->getAccountVData();

            $this->eventDispatcher->notifyEvent('edit.account',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Account updated'))
                    ->addDetail(__u('Name'), $accountDetails->getName())
                    ->addDetail(__u('Client'), $accountDetails->getClientName())
                    ->addDetail('ID', $accountDetails->getId()))
            );

            $this->sendSuccess($accountDetails);
        } catch (NoSuchItemException $e) {
            processException($e);
            $this->sendError(__u('Account not found'), 404);
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    /**
     * DELETE /api/rest/accounts/{id} — Delete account.
     *
     * @param \Klein\Request $request
     * @param int            $id
     */
    public function deleteAccount(\Klein\Request $request, int $id): void
    {
        try {
            $this->authenticate(ActionsInterface::ACCOUNT_DELETE);

            $accountDetails = $this->accountService->getById($id)->getAccountVData();

            $this->accountService->delete($id);

            $this->eventDispatcher->notifyEvent('delete.account',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Account removed'))
                    ->addDetail(__u('Name'), $accountDetails->getName())
                    ->addDetail(__u('Client'), $accountDetails->getClientName())
                    ->addDetail('ID', $id))
            );

            $this->sendSuccess($accountDetails);
        } catch (NoSuchItemException $e) {
            processException($e);
            $this->sendError(__u('Account not found'), 404);
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    /**
     * GET /api/rest/accounts/{id}/permissions — Account ACL.
     *
     * @param \Klein\Request $request
     * @param int            $id
     */
    public function accountPermissions(\Klein\Request $request, int $id): void
    {
        try {
            $this->authenticate(ActionsInterface::ACCOUNT_VIEW);

            $accountDetails = $this->accountService->getById($id);

            $this->eventDispatcher->notifyEvent('show.account',
                new Event($this, EventMessage::factory()
                    ->addDescription(__u('Account permissions displayed'))
                    ->addDetail(__u('Name'), $accountDetails->getAccountVData()->getName())
                    ->addDetail('ID', $id))
            );

            $this->sendSuccess([
                'accountId' => $id,
                'users' => $accountDetails->getUsers(),
                'userGroups' => $accountDetails->getUserGroups(),
                'owner' => $accountDetails->getAccountVData()->getUserId(),
                'ownerGroup' => $accountDetails->getAccountVData()->getUserGroupId(),
            ]);
        } catch (NoSuchItemException $e) {
            processException($e);
            $this->sendError(__u('Account not found'), 404);
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    // ----------------------------------------------------------------
    //  Reference data endpoints
    // ----------------------------------------------------------------

    /**
     * GET /api/rest/categories — List categories.
     *
     * @param \Klein\Request $request
     */
    public function listCategories(\Klein\Request $request): void
    {
        try {
            $this->authenticate(ActionsInterface::CATEGORY_SEARCH);

            $this->sendSuccess($this->categoryService->getAllBasic());
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    /**
     * GET /api/rest/clients — List clients.
     *
     * @param \Klein\Request $request
     */
    public function listClients(\Klein\Request $request): void
    {
        try {
            $this->authenticate(ActionsInterface::CLIENT_SEARCH);

            $this->sendSuccess($this->clientService->getAllBasic());
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    /**
     * GET /api/rest/tags — List tags.
     *
     * @param \Klein\Request $request
     */
    public function listTags(\Klein\Request $request): void
    {
        try {
            $this->authenticate(ActionsInterface::TAG_SEARCH);

            $this->sendSuccess($this->tagService->getAllBasic());
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }

    /**
     * GET /api/rest/user-groups — List user groups.
     *
     * @param \Klein\Request $request
     */
    public function listUserGroups(\Klein\Request $request): void
    {
        try {
            $this->authenticate(ActionsInterface::GROUP_SEARCH);

            $this->sendSuccess($this->userGroupService->getAllBasic());
        } catch (Exception $e) {
            processException($e);
            $this->sendError(__u('Internal error'), 500);
        }
    }
}
