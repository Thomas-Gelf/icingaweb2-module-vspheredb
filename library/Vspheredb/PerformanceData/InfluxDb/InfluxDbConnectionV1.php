<?php

namespace Icinga\Module\Vspheredb\PerformanceData\InfluxDb;

use Clue\React\Buzz\Browser;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use function React\Promise\resolve;

class InfluxDbConnectionV1
{
    const API_VERSION = 'v1';

    const USER_AGENT = 'Icinga-vSphereDB/1.2';

    /** @var LoopInterface */
    protected $loop;

    /** @var string */
    protected $baseUrl;

    /** @var Browser */
    protected $browser;

    protected $version;

    /** @var string|null */
    protected $username;

    /** @var string|null */
    protected $password;

    /**
     * AsyncInfluxDbWriter constructor.
     * @param $baseUrl string InfluxDB base URL
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop, $baseUrl, $username = null, $password = null)
    {
        $this->baseUrl = $baseUrl;
        $this->browser = new Browser($loop);
        $this->setUsername($username);
        $this->setPassword($password);
    }

    /**
     * @param string|null $username
     * @return $this
     */
    public function setUsername($username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @param string|null $password
     * @return $this
     */
    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function ping($verbose = false)
    {
        $params = [];
        if ($verbose) {
            $params['verbose'] = 'true';
        }
        return $this->getUrl('ping', $params);
    }

    public function getVersion()
    {
        if ($this->version) {
            return resolve($this->version);
        }

        return $this->get('ping')->then(function (ResponseInterface $response) {
            foreach ($response->getHeader('X-Influxdb-Version') as $version) {
                return $this->version = $version;
            }

            return null;
        });
    }

    public function listDatabases()
    {
        return $this->query('SHOW DATABASES')->then(function ($result) {
            return InfluxDbQueryResult::extractColumn($result);
        });
    }

    public function createDatabase($name)
    {
        return $this->query('CREATE DATABASE ' . Escape::fieldValue($name))->then(function ($result) {
            return $result;
        });
    }

    /**
     *
     * only since vX
     */
    public function getHealth()
    {
        // Works without Auth
        return $this->getUrl('health');
    }

    protected function query($query)
    {
        if (is_array($query)) {
            $sendQueries = \array_values($query);
        } else {
            $sendQueries = [$query];
        }
        if (empty($query)) {
            throw new \InvalidArgumentException('Cannot run no query');
        }

        if (preg_match('/^(SELECT|SHOW|ALTER|CREATE|DELETE|DROP|GRANT|KILL|REVOKE) /', $sendQueries[0], $match)) {
            $queryType = $match[1];
        } else {
            throw new \InvalidArgumentException('Unable to detect query type: ' . $sendQueries[0]);
        }
        if ($queryType === 'SHOW') {
            $queryType = 'GET';
        } elseif ($queryType === 'SELECT') {
            if (strpos($sendQueries[0], ' INTO ') === false) {
                $queryType = 'POST';
            } else {
                $queryType = 'GET';
            }
        } else {
            $queryType = 'POST';
        }
        $prefix = '';

        // TODO: Temporarily disabled, had problems with POST params in the body
        if ($queryType === 'xPOST') {
            $headers = ['Content-Type' => 'x-www-form-urlencoded'];
            $body = \http_build_query(['q' => implode(';', $sendQueries)]);
            $urlParams = [];
            $promise = $this->browser->post(
                $this->url("${prefix}query", $urlParams),
                $this->getRequestHeaders() + $headers,
                $body
            );
        } else {
            $urlParams = ['q' => implode(';', $sendQueries)];
            $promise = $this->browser->get(
                $this->url("${prefix}query", $urlParams),
                $this->getRequestHeaders()
            );
        }

        /** @var Promise $promise */
        $promise = $promise->then(function (ResponseInterface $response) use ($sendQueries, $query) {
            $body = $response->getBody();
            if (preg_match('#^application/json#', \current($response->getHeader('content-type')))) {
                $decoded = \json_decode((string) $body);
            } else {
                throw new \RuntimeException(\sprintf(
                    'JSON response expected, got %s: %s',
                    current($response->getHeader('content-type')),
                    $body
                ));
            }
            $results = [];
            foreach ($decoded->results as $result) {
                if (isset($result->series)) {
                    $results[$result->statement_id] = $result->series[0];
                } elseif (isset($result->error)) {
                    $results[$result->statement_id] = new \Exception('InfluxDB error: ' . $result->error);
                } else {
                    $results[$result->statement_id] = null;
                }
            }
            if (\count($results) !== \count($sendQueries)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Sent %d statemends, but got %d results',
                    \count($sendQueries),
                    \count($results)
                ));
            }

            if (is_array($query)) {
                return \array_combine(\array_keys($query), $results);
            } else {
                return $results[0];
            }
        });

        return $promise;
    }

    /**
     * @param string $dbName
     * @param DataPoint[] $dataPoints
     * @param string|null $precision ns,u,ms,s,m,h
     * @return \React\Promise\Promise
     */
    public function writeDataPoints($dbName, array $dataPoints, $precision = null)
    {
        $params = ['db' => $dbName];
        if ($precision !== null) {
            $params['precision'] = $precision;
        }
        // params['rp'] = $retentionPolicy
        /** @var Promise $promise */
        $promise = $this->browser->post(
            $this->url('write', $params),
            $this->getRequestHeaders(),
            \implode($dataPoints)
        );

        return $promise;
    }

    protected function getRequestHeaders()
    {
        $headers = [
            'User-Agent' => static::USER_AGENT,
        ];
        if ($this->username !== null) {
            $headers[' Authorization'] = 'Basic '
                . \base64_encode($this->username . ':' . $this->password);
        }

        return $headers;
    }

    protected function get($url, $params = null)
    {
        return $this->browser->get(
            $this->url($url, $params),
            $this->getRequestHeaders()
        );
    }

    protected function getRaw($url, $params = null)
    {
        /** @var Promise $promise */
        $promise = $this
            ->get($url, $params)
            ->then(function (ResponseInterface $response) {
                return (string) $response->getBody();
            });

        return $promise;
    }

    protected function postRaw($url, $body, $headers = [], $urlParams = [])
    {
        /** @var Promise $promise */
        $promise = $this->browser->post(
            $this->url($url, $urlParams),
            $this->getRequestHeaders() + $headers + [
                'Content-Type' => 'application/json'
            ],
            $body
        )->then(function (ResponseInterface $response) {
            return (string) $response->getBody();
        });

        return $promise;
    }

    protected function getUrl($url, $params = null)
    {
        return $this->getRaw($url, $params)->then(function ($raw) {
            return \json_decode((string) $raw);
        });
    }

    protected function postUrl($url, $body, $headers = [], $urlParams = [])
    {
        return $this->postRaw($url, json_encode($body), $headers, $urlParams)->then(function ($raw) {
            return \json_decode((string) $raw);
        });
    }

    protected function url($path, $params = [])
    {
        $url = $this->baseUrl . "/$path";
        if (! empty($params)) {
            $url .= '?' . \http_build_query($params);
        }

        return $url;
    }
}
