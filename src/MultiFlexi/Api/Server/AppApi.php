<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\Api\Server;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Description of DefaultApi.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class AppApi extends \MultiFlexi\Api\Server\AbstractAppApi
{
    public $engine;

    /**
     * App Handler Engine.
     */
    public function __construct()
    {
        $this->engine = new \MultiFlexi\Application();
    }

    /**
     * App Info by ID.
     *
     * @url http://localhost/EASE/MultiFlexi/src/api/VitexSoftware/MultiFlexi/1.0.0/app/1
     */
    public function getAppById(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, int $appId, string $suffix): \Psr\Http\Message\ResponseInterface
    {
        $this->engine->loadFromSQL($appId);
        $appData = $this->engine->getData();

        // Add environment configuration fields
        $conffield = new \MultiFlexi\Conffield();
        $envFields = $conffield->appConfigs($appId);
        $appData['environment'] = [];

        foreach ($envFields as $keyname => $fieldData) {
            $appData['environment'][$keyname] = [
                'type' => $fieldData['type'],
                'description' => $fieldData['description'],
                'defval' => $fieldData['defval'],
                'required' => (bool) $fieldData['required'],
            ];
        }

        // Add exit codes by querying directly
        $appData['exitCodes'] = [];
        $exitCodesEngine = new \MultiFlexi\DBEngine();
        $exitCodesEngine->myTable = 'app_exit_codes';
        $exitCodesData = $exitCodesEngine->listingQuery()
            ->where('app_id', $appId)
            ->orderBy('exit_code')
            ->orderBy('lang')
            ->fetchAll();

        foreach ($exitCodesData as $exitCodeRow) {
            $code = (string) $exitCodeRow['exit_code'];
            $lang = $exitCodeRow['lang'];

            if (!isset($appData['exitCodes'][$code])) {
                $appData['exitCodes'][$code] = [
                    'severity' => $exitCodeRow['severity'],
                    'retry' => (bool) $exitCodeRow['retry'],
                    'description' => [],
                ];
            }

            $appData['exitCodes'][$code]['description'][$lang] = $exitCodeRow['description'];
        }

        // The spec declares environment/exitCodes as objects (maps keyed by
        // env var name / exit code). json_encode() only emits a JSON object
        // for an array if it's non-empty AND has at least one non-sequential
        // key -- an empty array, or exit codes that happen to be numbered
        // 0,1,2,... sequentially, both encode as a JSON array `[]`/`[...]`
        // instead. Casting to stdClass forces the object shape unconditionally.
        $appData['environment'] = (object) $appData['environment'];
        $appData['exitCodes'] = (object) $appData['exitCodes'];

        switch ($suffix) {
            case 'html':
                //                $appData['name'] = new \Ease\Html\ATag($appData['id'] . '.html', $appData['name']);
                $appData['image'] = new \Ease\Html\ATag($appData['id'].'.html', new \Ease\Html\ImgTag($appData['image'], $appData['name'], ['width' => '64']));
                $appData = [array_keys($appData), $appData];

                break;

            default:
                break;
        }

        return DefaultApi::prepareResponse($response, $appData, $suffix, null, 'application');
    }

    /**
     * All Apps.
     *
     * @url http://localhost/EASE/MultiFlexi/src/api/VitexSoftware/MultiFlexi/1.0.0/apps
     */
    public function listApps(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, string $suffix): \Psr\Http\Message\ResponseInterface
    {
        $appsList = [];

        foreach ($this->engine->getAll() as $app) {
            // getAll() returns raw DB rows (tinyint 0/1), unlike
            // loadFromSQL()->getData() used by getAppById() which casts
            // typed columns; cast here so listApps() matches the spec's
            // `enabled: boolean` for both endpoints.
            $app['enabled'] = (bool) $app['enabled'];
            $appsList[$app['id']] = $app;

            switch ($suffix) {
                case 'html':
                    $appsList[$app['id']]['name'] = new \Ease\Html\ATag('app/'.$app['id'].'.html', $app['name']);

                    if (file_exists('../images/'.$app['uuid'].'.svg')) {
                        $appIcon = \Ease\Html\ImgTag::fileBase64src('../images/'.$app['uuid'].'.svg');
                    } else {
                        $appIcon = $app['image'];
                    }

                    $appsList[$app['id']]['image'] = new \Ease\Html\ATag('app/'.$app['id'].'.html', new \Ease\Html\ImgTag($appIcon, $app['name'], ['width' => '64']));

                    break;

                default:
                    break;
            }
        }

        return DefaultApi::prepareResponse($response, $appsList, $suffix, null, 'application');
    }

    /**
     * POST setAppById
     * Summary: Create or Update Application
     * Notes: Create or Update App by ID
     * Output-Formats: [application/xml, application/json].
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     */
    public function setAppById(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $appId = (\array_key_exists('appId', $queryParams)) ? $queryParams['appId'] : null;
        $appInfo = ['id' => $appId, 'success' => $this->engine->dbsync($queryParams)];

        return DefaultApi::prepareResponse($response, $appInfo, $suffix, 'app'.$appId);
    }
}
