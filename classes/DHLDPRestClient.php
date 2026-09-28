<?php
/**
 * dhldp
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2023 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   2.0.0
 * @link      http://www.silbersaiten.de
 */

require_once(dirname(__FILE__).'/DHLDPRestClientException.php');

class DHLDPRestClient implements Iterator, ArrayAccess
{
    public $options;
    public $handle; // cURL resource handle.
    public $parameters;
    public $method;
    public $url;

    // Populated after execution:
    public $response; // Response body.
    public $headers; // Parsed reponse header object.
    public $info; // Response info object.
    public $error; // Response error string.
    public $response_status_lines; // indexed array of raw HTTP response status lines.
    // Populated as-needed.
    public $decoded_response;

    public function __construct($options = array())
    {
        $default_options = array(
            'headers' => array(),
            'parameters' => array(),
            'curl_options' => array(),
            'user_agent' => "",
            'base_url' => null,
            'format' => null,
            'format_regex' => "/(\w+)\/(\w+)(;[.+])?/",
            'decoders' => array(
                'json' => 'json_decode',
                //'php' => 'unserialize',
                'plain' => ''
            ),
            'username' => null,
            'password' => null,
            'savelog_callback' => null
        );

        $this->options = array_merge($default_options, $options);
        if (array_key_exists('decoders', $options)) {
            $this->options['decoders'] = array_merge($default_options['decoders'], $options['decoders']);
        }
    }

    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }

    public function registerDecoder($format, $method)
    {
        // Decoder callbacks must adhere to the following pattern:
        //   array my_decoder(string $data)
        $this->options['decoders'][$format] = $method;
    }

    // Iterable methods:
    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->decodeResponse();
        return reset($this->decoded_response);
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->decoded_response);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->decoded_response);
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        return next($this->decoded_response);
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return is_array($this->decoded_response)
            && (key($this->decoded_response) !== null);
    }

    // ArrayAccess methods:
    #[\ReturnTypeWillChange]
    public function offsetExists($key)
    {
        $this->decodeResponse();
        return is_array($this->decoded_response)?
            isset($this->decoded_response[$key]) : isset($this->decoded_response->{$key});
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        $this->decodeResponse();
        if (!$this->offsetExists($key)) {
            return null;
        }

        return is_array($this->decoded_response)?$this->decoded_response[$key] : $this->decoded_response->{$key};
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($key, $value)
    {
        unset($key);
        unset($value);
        throw new DHLDPRestClientException("Decoded response data is immutable.");
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($key)
    {
        unset($key);
        throw new DHLDPRestClientException("Decoded response data is immutable.");
    }

    // Request methods:
    public function get($url, $parameters = array(), $headers = array())
    {
        return $this->execute($url, 'GET', $parameters, $headers);
    }

    public function post($url, $parameters = array(), $headers = array())
    {
        return $this->execute($url, 'POST', $parameters, $headers);
    }

    public function put($url, $parameters = array(), $headers = array())
    {
        return $this->execute($url, 'PUT', $parameters, $headers);
    }

    public function patch($url, $parameters = array(), $headers = array())
    {
        return $this->execute($url, 'PATCH', $parameters, $headers);
    }

    public function delete($url, $parameters = array(), $headers = array())
    {
        return $this->execute($url, 'DELETE', $parameters, $headers);
    }

    public function head($url, $parameters = array(), $headers = array())
    {
        return $this->execute($url, 'HEAD', $parameters, $headers);
    }

    public function execute($url, $method = 'GET', $parameters = array(), $headers = array())
    {
        $client = clone $this;
        $client->url = $url;
        $client->handle = curl_init();
        $client->method = $method;
        $curlopt = array(
            CURLINFO_HEADER_OUT => true,
            //CURLINFO_PRIVATE => true,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $client->options['user_agent']
        );
        if ($client->options['username'] && $client->options['password']) {
            $curlopt[CURLOPT_USERPWD] = sprintf("%s:%s", $client->options['username'], $client->options['password']);
        }

        if ($client->options['format']) {
            $client->url .= '.'.$client->options['format'];
        }

        // Allow passing parameters as a pre-encoded string (or something that
        // allows casting to a string). Parameters passed as strings will not be
        // merged with parameters specified in the default options.
        if (is_array($parameters)) {
            $parameters = array_merge($client->options['parameters'], $parameters);
            $parameters_string = http_build_query($parameters);
        } else {
            $parameters_string = (string)$parameters;
        }

        $client->parameters = $parameters_string;

        if (Tools::strtoupper($method) == 'POST') {
            $curlopt[CURLOPT_POST] = true;
            $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;
            $client->options['headers']['Content-Length'] = Tools::strlen($curlopt[CURLOPT_POSTFIELDS]);
        } elseif (Tools::strtoupper($method) != 'GET') {
            $curlopt[CURLOPT_CUSTOMREQUEST] = Tools::strtoupper($method);
            $curlopt[CURLOPT_POSTFIELDS] = $parameters_string;
        } elseif ($parameters_string) {
            $client->url .= strpos($client->url, '?')? '&' : '?';
            $client->url .= $parameters_string;
        }

        if (count($client->options['headers']) || count($headers)) {
            $curlopt[CURLOPT_HTTPHEADER]  = array();
            $headers = array_merge($client->options['headers'], $headers);
            foreach ($headers as $key => $values) {
                foreach (is_array($values)? $values : array($values) as $value) {
                    $curlopt[CURLOPT_HTTPHEADER][] = sprintf("%s:%s", $key, $value);
                }
            }
        }
        if ($client->options['base_url']) {
            if ($client->url[0] != '/' && Tools::substr($client->options['base_url'], -1) != '/') {
                $client->url = '/'.$client->url;
            }
            $client->url = $client->options['base_url'] . $client->url;
        }
        $curlopt[CURLOPT_URL] = $client->url;

        if ($client->options['curl_options']) {
            // array_merge would reset our numeric keys.
            foreach ($client->options['curl_options'] as $key => $value) {
                $curlopt[$key] = $value;
            }
        }
        curl_setopt_array($client->handle, $curlopt);

        $client->parseResponse(curl_exec($client->handle));
        $client->info = (object) curl_getinfo($client->handle);

        $client->error = curl_error($client->handle);
        // Do not log request headers: they may contain credentials.
        $client->saveLogData('DHL', $method . ' ' . $client->maskSensitiveUrl($client->url), null, array(
            'http_status' => $client->info->http_code,
            'content_type' => isset($client->headers->content_type) ? $client->headers->content_type : null,
            'body' => $client->response,
        ), $client->error);

        curl_close($client->handle);
        return $client;
    }

    public function parseResponse($response)
    {
        $headers = array();
        $this->response_status_lines = array();
        $line = strtok($response, "\n");
        do {
            if (Tools::strlen(trim($line)) == 0) {
                // Since we tokenize on \n, use the remaining \r to detect empty lines.
                if (count($headers) > 0) {
                    break; // Must be the newline after headers, move on to response body
                }
            } elseif (strpos($line, 'HTTP') === 0) {
                // One or more HTTP status lines
                $this->response_status_lines[] = trim($line);
            } else {
                // Has to be a header
                list($key, $value) = explode(':', $line, 2);
                $key = trim(Tools::strtolower(str_replace('-', '_', $key)));
                $value = trim($value);

                if (empty($headers[$key])) {
                    $headers[$key] = $value;
                } elseif (is_array($headers[$key])) {
                    $headers[$key][] = $value;
                } else {
                    $headers[$key] = array($headers[$key], $value);
                }
            }
        } while ($line = strtok("\n"));

        $this->headers = (object) $headers;
        $this->response = strtok("");
    }

    public function getResponseFormat()
    {
        if (!$this->response) {
            throw new DHLDPRestClientException("A response must exist before it can be decoded.");
        }

        if (!empty($this->options['format'])) {
            return $this->options['format'];
        }

        if (!empty($this->headers->content_type)) {
            $mediaType = strtolower(trim(explode(';', $this->headers->content_type)[0]));
            if (preg_match('~^[^/]+/[^;]+\+json$~', $mediaType)) {
                return 'json';
            }
            if (preg_match($this->options['format_regex'], $this->headers->content_type, $matches)) {
                return strtolower($matches[2]);
            }
        }

        throw new DHLDPRestClientException("Response format could not be determined.");
    }

    public function decodeResponse()
    {
        if (empty($this->decoded_response)) {
            $format = $this->getResponseFormat();
            if (!array_key_exists($format, $this->options['decoders'])) {
                throw new DHLDPRestClientException("'{$format}' is not a supported format, register a decoder to handle this response.");
            }

            $this->decoded_response = call_user_func($this->options['decoders'][$format], $this->response, true);
            if ($this->options['decoders'][$format] === 'json_decode' && json_last_error() !== JSON_ERROR_NONE) {
                throw new DHLDPRestClientException('Invalid JSON response: ' . json_last_error_msg());
            }
        }

        return $this->decoded_response;
    }

//    public function saveLogData($request_header, $request, $response_header, $response, $curl_error)
//    {
//        $aresph = array();
//        foreach ($this->objectToArray($response_header) as $k => $v) {
//            $aresph[] = print_r($k, true).": ".print_r($v, true);
//        }
//        $msg = "\r\n*Request header*: ".$request_header.
//            "\r\n*Request*: ".$request.
//            "\r\n*Response header*: \r\n".implode("\r\n", $aresph).
//            "\r\n*Response*: ".$response.
//            "\r\n*Curl error*: ".$curl_error."\r\n";
//        if ($this->options['savelog_callback'] != null) {
//            call_user_func($this->options['savelog_callback'], 'DHL', $msg, 'dhl_api');
//        }
//    }

    public function saveLogData($type, $operation, $request = null, $response = null, $error = null)
    {
        $log_message = '';

        if ($request) {
            $maskedRequest = $this->maskSensitiveData($request);
            if (is_array($maskedRequest) && isset($maskedRequest['endpoint'])) {
                $maskedRequest['endpoint'] = $this->maskSensitiveUrl($maskedRequest['endpoint']);
            } elseif (is_object($maskedRequest) && isset($maskedRequest->endpoint)) {
                $maskedRequest->endpoint = $this->maskSensitiveUrl($maskedRequest->endpoint);
            }

            $log_message .= sprintf(
                "\n=== DHL API Request ===\n" .
                "Timestamp: %s\n" .
                "Operation: %s\n" .
                "Request Data: %s\n" .
                "===================\n",
                date('Y-m-d H:i:s'),
                $operation,
                is_string($maskedRequest) ? $maskedRequest : json_encode($maskedRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }

        if ($response || $error) {
            $maskedResponse = $this->maskSensitiveData($response);
            if (is_array($maskedResponse) && isset($maskedResponse['label']['url'])) {
                $maskedResponse['label']['url'] = $this->maskSensitiveUrl($maskedResponse['label']['url']);
            } elseif (is_object($maskedResponse) && isset($maskedResponse->label->url)) {
                $maskedResponse->label->url = $this->maskSensitiveUrl($maskedResponse->label->url);
            }

            $log_message .= sprintf(
                "\n=== DHL API Response ===\n" .
                "Timestamp: %s\n" .
                "Operation: %s\n" .
                "Response Data: %s\n" .
                "%s" .
                "=====================\n",
                date('Y-m-d H:i:s'),
                $operation,
                is_string($maskedResponse) ? $maskedResponse : json_encode($maskedResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                $error ? "Error: " . $error . "\n" : ""
            );
        }

        if (is_callable($this->options['savelog_callback'])) {
            call_user_func($this->options['savelog_callback'], $type, $log_message, 'dhl_api');
        }
    }

    public function objectToArray($object)
    {
        if (!is_object($object) && !is_array($object)) {
            return $object;
        }

        return array_map(array($this, 'objectToArray'), (array) $object);
    }
    private function maskSensitiveData($data) {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $this->maskSensitiveData($decoded);
            }
            return $this->maskSensitiveUrl($data);
        }
        $sensitiveFields = [
            'client_id',
            'client_secret',
            'password',
            'username',
            'email',
            'phone',
//            'name1',
//            'name2',
//            'name3',
//            'addressStreet',
            'token', 'access_token', 'refresh_token', 'id_token', 'authorization', 'dhl-api-key'
        ];
        if (is_object($data)) {
            $data = (array)$data;
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (in_array(strtolower((string)$key), $sensitiveFields)) {
                    $data[$key] = '***';
                } else {
                    $data[$key] = $this->maskSensitiveData($value);
                }
            }
        }
        return $data;
    }

    private function maskSensitiveUrl($url) {
        return preg_replace('/([?&](?:token|access_token|refresh_token|client_secret|password)=)[^&#\s]*/i', '$1***', $url);
    }
}
