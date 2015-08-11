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
    protected $apiRoot = 'https://api.eventful.com';

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
    protected $requestUri = null;

    /**
     * Latest request parameters.
     *
     * @var string
     */
    protected $requestParameters = null;

    /**
     * Latest response data.
     *
     * @var string
     */
    protected $responseData = null;

    /**
     * Latest response error message.
     *
     * @var string
     */
    protected $responseError = null;

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

            $response = md5($nonce . ':' . md5($password));
            $args = [
                'nonce'    => $nonce,
                'response' => $response,
            ];
            $result = $this->call('users/login', $args, 'json');

            if (isset($result->userKey)) {
                $this->userKey = $result->userKey;
                return true;
            }
        }
        return false;
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
    public function call($method, $args = [], $output = 'rest')
    {
        $method = trim($method, '/ ');

        $url = $this->apiRoot . '/' . $output . '/' . $method;
        $this->requestUri = $url;

        $postArgs = [
            'appKey'  => $this->appKey,
            'user'    => $this->username,
            'userKey' => $this->userKey,
        ];

        foreach ($args as $argKey => $argValue) {
            if (is_array($argValue)) {
                foreach ($argValue as $instance) {
                    $postArgs[$argKey] = $instance;
                }
            } else {
                $postArgs[$argKey] = $argValue;
            }
        }

        $this->requestParameters = $postArgs;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->requestUri);
        curl_setopt($ch, CURLOPT_POST, count($postArgs));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postArgs));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return data instead of display to std out

        $cResult = curl_exec($ch);
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
