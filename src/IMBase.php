<?php

/**
 * @author Felix Huang <yelfivehuang@gmail.com>
 * @date 2017-06-13
 */

namespace fk\ease\mob;

use Curl\Curl;
use Illuminate\Support\Facades\Cache;

abstract class IMBase
{

    /**
     * @method Curl post()
     */
    /**
     * @var string
     */
    public $host;
    public $appKey;
    public $orgName;
    public $appName;
    public $clientID;
    public $clientSecret;

    private $_curl;
    protected $maxTrial = 3;
    protected $cachePrefix = __CLASS__ . '_';

    protected $apiWorker;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $appKey = $this->appKey;
        if (!$appKey || false === strpos($appKey, '#')) {
            throw new \Exception('Invalid App Key given');
        }
        list ($this->orgName, $this->appName) = explode('#', $appKey);
        $this->_curl = $this->initCurl();
    }

    /**
     * @param string $token
     * @param \DateTime|float|int $expireMinutes
     */
    protected function tokenPut(string $token, $expireMinutes)
    {
        $key = "{$this->cachePrefix}token";
        Cache::put($key, $token, $expireMinutes);
    }

    protected function tokenRetrieve()
    {
        $key = $this->cachePrefix . 'token';
        return Cache::get($key);
    }

    protected function tokenForget()
    {
        $key = $this->cachePrefix . 'token';
        Cache::forget($key);
        return $this;
    }

    protected function __call($method, $arguments)
    {
        // Call API
        $preparer = 'prepare' . ucfirst($method);
        if (method_exists($this, $preparer)) {
            $data = $this->$preparer(...$arguments);

            $this->request(...$data);
            return [$this->getStatusCode(), $this->getResponse()];
        }

        throw new \Exception("Calling to undefined method: $method");
    }

    protected function request(string $method, string $api, array $data = null)
    {
        $this->trial($method, $api, $data, $this->maxTrial);
        return $this->_curl;
    }

    protected function post(string $api, array $data)
    {
        return $this->request('post', $api, $data);
    }

    protected function put(string $api, array $data)
    {
        return $this->request('put', $api, $data);
    }

    protected function trial($method, $api, $data, $countDown)
    {
        if (!in_array($api, [API::TOKEN])) {
            $token = $this->getToken();
            $this->_curl->setHeader('Authorization', $token);
        }
        // Copy original $arguments, in case it is changed by subsequent calling
        $url = "$this->host/$this->orgName/$this->appName/$api";

        $body = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

        $this->_curl->$method($url, $body, true); // Third parameter is for curl->put, whether it's a payload

        if (--$countDown <= 0) return;

        if (400 > $statusCode = $this->getStatusCode()) return;

        $tryAgain = false;
        if ($statusCode == 401) { // Unauthorized
            $this->tokenForget(); // Forget token
            $tryAgain = true;
        } else if (in_array($this->getStatusCode(), [429, 503])) {
            // Rate limit reached, 10req/sec
            // sleep 0.5 seconds and try again
            usleep(500);
            $tryAgain = true;
        }
        if ($tryAgain) $this->trial($method, $api, $data, $countDown);
    }

    /**
     * @return Curl
     */
    protected function initCurl()
    {
        $curl = new Curl();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        foreach ($headers as $k => $v) {
            $curl->setHeader($k, $v);
        }
        return $curl;
    }

    /**
     * @return int HTTP status code
     * @throws \Exception
     */
    protected function getStatusCode()
    {
        $headers = $this->_curl->response_headers;
        if (empty($headers[0])) {
            throw new \Exception('Unable to get response');
        }
        $startLine = $headers[0];
        if (preg_match('#^HTTP/1\.[0-2] +(\d{3}) +[\w ]+$#', $startLine, $match)) {
            return (int)$match[1];
        } else {
            throw new \Exception("Unable to retrieve status code from start line: $startLine");
        }
    }

    protected function getResponse()
    {
        $response = $this->_curl->response;
        if (!$response) return $response;

        return json_decode($response, true) ?? $response;
    }

    protected function setHeader($name, $value)
    {
        $this->_curl->setHeader($name, $value);
        return $this;
    }

    /**
     * @param bool $renewCurl
     * @return false|string `Bearer YWMtzEQxKk9qEeeOpt...`
     * @throws \Exception
     */
    protected function getToken($renewCurl = true)
    {
        if ($token = $this->tokenRetrieve()) {
            return $token;
        }
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientID,
            'client_secret' => $this->clientSecret
        ];
        $this->post(API::TOKEN, $data);
        if ($this->getStatusCode() >= 400 || false == $response = $this->getResponse()) {
            throw new \Exception();
        }
        $token = "Bearer {$response['access_token']}";
        $this->tokenPut($token, $response['expires_in'] / 60);
        if ($renewCurl) {
            $this->_curl = $this->initCurl();
        }
        return $token;
    }
}