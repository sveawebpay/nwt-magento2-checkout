<?php


namespace Svea\Checkout\Model\Client;

use \Exception;
use \Throwable;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


class ClientException extends Exception
{

    /** @var RequestInterface $request */
    protected $request;

    /** @var ResponseInterface $response */
    protected $response;


    /** @var string $responseBody */
    protected $responseBody = "";

    protected $method;

    protected $url;


    public function __construct($request = null, $response = null, $message = "", $code = 0, ?Throwable $previous = null) {
        $this->request = $request;
        $this->response = $response;

        $message = $this->buildMessage($response, $message);

        parent::__construct($message, $code, $previous);
    }


    public function getHttpStatusCode()
    {
        if ($this->response) {
            return $this->response->getStatusCode();
        }

        return null;
    }

    public function getResponseBody()
    {
        return $this->responseBody;
    }

    /**
     * @param ResponseInterface|null $response
     * @param string $fallbackMessage
     * @return string
     */
    protected function buildMessage($response, $fallbackMessage = "") {
        if (!$response || !($response instanceof ResponseInterface)) {
            return $fallbackMessage;
        }


        $this->responseBody = $response->getBody()->getContents();

        $headerError = $response->getHeader("ErrorMessage");
        if (!empty($headerError)) {
            return $headerError[0];
        }

        // try to parse the response body with messages
        try {
            $content = json_decode($this->responseBody, true);
            if ($content === null) { // not valid json, so its a string?
                return $this->responseBody;
            }

            $errors = [];
            if (isset($content['Message'])) {
                $errors[] = $content['Message'];
            }

            if (isset($content['Errors'])) {
                foreach ($content['Errors'] as $err) {
                    if (isset($err['ErrorMessage'])) {
                        $errors[] = $err['ErrorMessage'];
                    }
                }
            }

            if ($response->getStatusCode() >= 500) {
                return "Svea are experiencing technical issues. Try again, or contact the site admin! " . "Svea Error: " . implode(". ", $errors);
            }

            if (!empty($errors)) {
                return "Svea Error: " . implode(". ", $errors);
            } else {
                return $fallbackMessage;
            }

        } catch (\Exception $e) {
            return $fallbackMessage;
        }
    }




}