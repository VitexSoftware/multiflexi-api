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
class JobApi extends AbstractJobApi
{
    public $engine;

    /**
     * Job Handler Engine.
     */
    public function __construct()
    {
        $this->engine = new \MultiFlexi\Job();
        $this->engine->limit = 20;
    }

    /**
     * Job Info by ID.
     *
     * @url http://localhost/EASE/MultiFlexi/src/api/VitexSoftware/MultiFlexi/1.0.0/job/1
     */
    public function getJobById(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, int $jobId, string $suffix): \Psr\Http\Message\ResponseInterface
    {
        $this->engine->loadFromSQL((int) $jobId);
        $jobData = $this->engine->getData();
        $jobData['env'] = unserialize($jobData['env']);

        switch ($suffix) {
            case 'html':
                $jobData['id'] = new \Ease\Html\ATag('job/'.$jobData['id'].'.html', $jobData['id']);
                $jobData['env'] = new \MultiFlexi\Ui\EnvironmentView($jobData['env']);
                $jobData['stdout'] = new \Ease\Html\PreTag((new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert((string) $jobData['stdout']));
                $jobData['stderr'] = new \Ease\Html\PreTag((new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert((string) $jobData['stderr']));
                $jobData['app_id'] = new \Ease\Html\ATag('app/'.$jobData['app_id'].'.html', $jobData['app_id']);
                $jobData['company_id'] = new \Ease\Html\ATag('company/'.$jobData['company_id'].'.html', $jobData['company_id']);
                $jobData['runtemplate_id'] = new \Ease\Html\ATag('runtemplate/'.$jobData['runtemplate_id'].'.html', $jobData['runtemplate_id']);
                $jobData = [array_keys($jobData), $jobData];

                break;

            default:
                if ($jobData['env'] instanceof \MultiFlexi\ConfigFields) {
                    $jobData['env'] = $jobData['env']->getRedactedArray();
                }

                break;
        }

        return DefaultApi::prepareResponse($response, $jobData, $suffix, null, 'job');
    }

    /**
     * Create (schedule) a Job from a RunTemplate.
     *
     * POST /job/
     *
     * Schedules a job for an existing RunTemplate, mirroring the
     * `multiflexi-cli run-template:schedule` command. This is the inbound
     * trigger used by external orchestrators such as Node-RED.
     *
     * Accepted JSON body fields:
     *  - runtemplate_id (int, required) — RunTemplate to schedule
     *  - scheduled (string, optional)   — "now" or "Y-m-d H:i:s" (default "now")
     *  - executor (string, optional)    — overrides the RunTemplate executor
     *  - env (object, optional)         — KEY=>VALUE environment overrides
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     */
    public function setjobById(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $suffix = 'json';
        $body = json_decode($request->getBody()->getContents(), true);

        if (!\is_array($body)) {
            $body = [];
        }

        $queryParams = $request->getQueryParams();
        $runtemplateId = (int) ($body['runtemplate_id'] ?? $queryParams['runtemplate_id'] ?? 0);

        if ($runtemplateId === 0) {
            return DefaultApi::prepareResponse($response->withStatus(400), ['error' => 'Missing runtemplate_id'], $suffix);
        }

        $runTemplate = new \MultiFlexi\RunTemplate($runtemplateId);

        if (empty($runTemplate->getMyKey())) {
            return DefaultApi::prepareResponse($response->withStatus(404), ['error' => 'RunTemplate not found'], $suffix);
        }

        if ((int) $runTemplate->getDataValue('active') !== 1) {
            return DefaultApi::prepareResponse($response->withStatus(409), ['error' => 'RunTemplate is not active'], $suffix);
        }

        $executor = $body['executor'] ?? $runTemplate->getDataValue('executor');
        $executor = !empty($executor) ? $executor : 'Native';

        $scheduleTime = $body['scheduled'] ?? 'now';

        try {
            $scheduleDateTime = new \DateTime($scheduleTime);
        } catch (\Exception $e) {
            return DefaultApi::prepareResponse($response->withStatus(400), ['error' => 'Invalid scheduled value'], $suffix);
        }

        $now = new \DateTime();
        $isImmediate = ($scheduleDateTime->getTimestamp() <= $now->getTimestamp() + 5);
        $scheduleType = $isImmediate ? 'adhoc' : 'cli';

        $overridedEnv = new \MultiFlexi\ConfigFields('ApiOverride');

        if (!empty($body['env']) && \is_array($body['env'])) {
            foreach ($body['env'] as $key => $value) {
                $overridedEnv->addField(new \MultiFlexi\ConfigField((string) $key, 'string', (string) $key, '', '', (string) $value));
            }
        }

        try {
            $jobber = new \MultiFlexi\Job();
            $jobber->prepareJob($runTemplate, $overridedEnv, $scheduleDateTime, $executor, $scheduleType);

            return DefaultApi::prepareResponse($response->withStatus(201), [
                'job_id' => $jobber->getMyKey(),
                'runtemplate_id' => $runtemplateId,
                'scheduled' => $scheduleDateTime->format('Y-m-d H:i:s'),
                'executor' => $executor,
                'schedule_type' => $scheduleType,
            ], $suffix, null, 'job');
        } catch (\Exception $e) {
            return DefaultApi::prepareResponse($response->withStatus(500), ['error' => 'Failed to schedule job: '.$e->getMessage()], $suffix);
        }
    }

    /**
     * GET jobsGet
     * Summary: List all Jobs
     * Notes: List all Jobs.
     *
     * @param ServerRequestInterface $request  Request
     * @param ResponseInterface      $response Response
     */
    public function listJobs(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response, string $suffix): \Psr\Http\Message\ResponseInterface
    {
        $jobsList = [];
        $queryParams = $request->getQueryParams();
        $limit = (\array_key_exists('limit', $queryParams)) ? $queryParams['limit'] : $this->engine->limit;

        foreach ($this->engine->listingQuery()->limit($limit) as $job) {
            $jobId = $job['id'];
            $job['env'] = unserialize($job['env']);

            switch ($suffix) {
                case 'html':
                    $job['id'] = new \Ease\Html\ATag('job/'.$job['id'].'.html', $job['id']);
                    $job['env'] = new \MultiFlexi\Ui\EnvironmentView($job['env']);
                    $job['stdout'] = new \Ease\Html\PreTag((new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert((string) $job['stdout']));
                    $job['stderr'] = new \Ease\Html\PreTag((new \SensioLabs\AnsiConverter\AnsiToHtmlConverter())->convert((string) $job['stderr']));
                    $job['app_id'] = new \Ease\Html\ATag('app/'.$job['app_id'].'.html', $job['app_id']);
                    $job['company_id'] = new \Ease\Html\ATag('company/'.$job['company_id'].'.html', $job['company_id']);
                    $job['runtemplate_id'] = new \Ease\Html\ATag('runtemplate/'.$job['runtemplate_id'].'.html', $job['runtemplate_id']);

                    break;

                default:
                    if ($job['env'] instanceof \MultiFlexi\ConfigFields) {
                        $job['env'] = $job['env']->getRedactedArray();
                    }

                    break;
            }

            $jobsList[$jobId] = $job;
        }

        return DefaultApi::prepareResponse($response, $jobsList, $suffix, null, 'job');
    }
}
