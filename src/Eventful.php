<?php

namespace BlueBayTravel\Eventful;

use SimpleXMLElement;

class Eventful
{
    /**
     * API endpoint.
     *
     * @var string
     */
    protected $apiRoot = 'http://api.eventful.com';

    /**
     * Application key.
     *
     * @var string
     */
    protected $appKey = null;

    /**
     * Username.
     *
     * @var string
     */
    private $username = null;

    /**
     * User Authentication Key.
     *
     * @var string
     */
    private $userKey = null;

    /**
     * Latest request URI.
     *
     * @var string
     */
    private $requestUri = null;

    /**
     * Latest request parameters.
     *
     * @var string
     */
    private $requestParameters = null;

    /**
     * Latest response data.
     *
     * @var string
     */
    private $responseData = null;

    /**
     * Latest response error message.
     *
     * @var string
     */
    private $responseError = null;

    /**
     * Latest response code.
     *
     * @var string
     */
    private $responseCode = null;

    /**
     * Create a new client.
     *
     * @param string $appKey
     */
    public function __construct($appKey)
    {
        $this->appKey = $appKey;
    }

    public function getUserKey()
    {
        return $this->userKey;
    }

    public function getRequestUri()
    {
        return $this->requestUri;
    }

    public function getRequestParameters()
    {
        return $this->requestParameters;
    }

    public function getResponseData()
    {
        return $this->responseData;
    }

    public function getResponseError()
    {
        return $this->responseError;
    }

    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * Login and verify the user connection.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function login($username, $password)
    {
        $this->username = $username;

        $result = $this->call('users/login', [], 'json');
        if (isset($result->nonce)) {
            $nonce = $result->nonce;

            $login_hash = md5($nonce . ':' . md5($password));

            $args = [
                'nonce'    => $nonce,
                'response' => $login_hash,
            ];

            $result = $this->call('users/login', $args, 'json');

            if (isset($result->userKey)) {
                $this->userKey = $result->userKey;
                return true;
            }
        }
        return false;
    }

    public function addDefaultParameters($parameters = [])
    {
        $default = [
            'app_key'  => $this->appKey,
            'user'     => $this->username,
            'user_key' => $this->userKey,
        ];
        foreach ($default as $key => $value) {
            $parameters[$key] = $value;
        }
        return $parameters;
    }

    /**
     * Call a method of the Eventful API.
     *
     * @param string $method
     * @param mixed  $args
     * @param string $output
     *
     * @return mixed;
     */
    public function call($method, $args = [], $output = 'json')
    {
        $method = trim($method, '/ ');

        $url = $this->apiRoot . '/' . $output . '/' . $method;
        $this->requestUri = $url;

        $postArgs = [];

        foreach ($args as $argKey => $argValue) {
            if (is_array($argValue)) {
                foreach ($argValue as $instance) {
                    $postArgs[$argKey] = $instance;
                }
            } else {
                $postArgs[$argKey] = $argValue;
            }
        }

        $postArgs = $this->addDefaultParameters($postArgs);

        $this->requestParameters = $postArgs;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->requestUri);
        curl_setopt($ch, CURLOPT_POST, count($postArgs));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postArgs));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return data instead of display to std out

        $cResult = curl_exec($ch);
        if (false === $cResult) {
            $this->responseError = curl_error($ch);
            $this->responseCode = curl_errno($ch);
            return false;
        }

        $this->responseData = $cResult;
        curl_close($ch);

        $data = false;

        if ($output == 'rest') {
            // Process result to XML
            $data = new SimpleXMLElement($cResult);
            if ($data->getName() === 'error') {
                $this->responseError = $data['string'] . ': ' . $data->description;
                $this->responseCode = $data['string'];
                return false;
            }
        } elseif ($output == 'json') {
            // Process result to stdClass
            $data = json_decode($cResult);
            if (isset($data->error)) {
                $this->responseError = $data->status . ': ' . $data->description;
                $this->responseCode = $data->error;
                return false;
            }
        }
        return $data;
    }
}
