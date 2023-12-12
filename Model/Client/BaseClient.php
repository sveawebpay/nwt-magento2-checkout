<?php
namespace Svea\Checkout\Model\Client;


use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Svea\Checkout\Model\Client\DTO\AbstractRequest;

/**
 * Class Client
 * @package Svea\Checkout\Model\Api
 */
abstract class BaseClient
{

    /** @var $lastResponse ResponseInterface */
    protected $lastResponse;

    /**
     * @var int
     */
    protected $timeout = 30;

    /** @var Context $apiContext */
    protected $apiContext;

    /** @var string $sharedSecret */
    protected $sharedSecret;

    /** @var string $merchantId */
    protected $merchantId;

    /** @var bool $testMode */
    protected $testMode;

    /** @var \GuzzleHttp\Client $httpClient */
    protected $httpClient;

    /**
     * Constructor
     *
     * @param Context $apiContext
     *
     */
    public function __construct(
        Context $apiContext
    ) {
        $this->apiContext = $apiContext;

        // this will set base store view
        $this->resetCredentials();
    }



    /**
     * @param $endpoint
     * @param array $params
     * @param bool $useTenant
     * @return string
     */
    protected function buildEndpoint($endpoint, $params = []) {
        $buildEndpoint = ltrim($endpoint, "/");
        if (!empty($params)) {
            $query =  http_build_query($params);
            $buildEndpoint .= "?" . $query;
        }

        return $buildEndpoint;
    }

    /**
     * @param $endpoint
     * @param array $options
     * @return string
     * @throws ClientException
     */
    protected function get($endpoint, $options = []){
        if (!is_array($options)) {
            $options = [];
        }

        $options = array_merge($options, $this->getDefaultOptions());

        try {
            $result = $this->httpClient->get($endpoint, $options);

            $content = $result->getBody()->getContents();
            if ($this->testMode) {
                $this->getLogger()->info("Got response from Svea: Get $endpoint");

                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['Gui']['Snippet'])) { // Easier to debug without this
                    $decoded['Gui']['Snippet'] = "Removed.";
                    $logContent = json_encode($decoded);
                } else {
                    $logContent = $content;
                }
                $this->getLogger()->info($logContent);
            }

            return $content;
        } catch (BadResponseException $e) {
            $exception = $this->handleException($e);
        } catch (\Exception $e) {
            $exception = $this->handleException($e);
        }

        if ($exception) {
            $this->getLogger()->error("Failed sending request to svea integration: GET $endpoint");
            $this->getLogger()->error(json_encode($this->removeAuthForLogging($options)));
            $this->getLogger()->error($exception->getMessage());
            $this->getLogger()->error($exception->getHttpStatusCode());
            $this->getLogger()->error($exception->getResponseBody());
            throw $exception;
        }




    }

    /**
     * @param $endpoint
     * @param AbstractRequest $request
     * @param array $options
     * @return string
     * @throws ClientException
     */
    protected function post($endpoint, AbstractRequest $request, $options = [])
    {
        return $this->doRequest($endpoint, "post", $request, $options);
    }

    /**
     * @param $endpoint
     * @param AbstractRequest $request
     * @param array $options
     * @return string
     * @throws ClientException
     */
    protected function patch($endpoint, AbstractRequest $request, $options = [])
    {
        return $this->doRequest($endpoint, "patch", $request, $options);
    }

    /**
     * @param $endpoint
     * @param AbstractRequest $request
     * @param array $options
     * @return string
     * @throws ClientException
     */
    protected function put($endpoint, AbstractRequest $request, $options = [])
    {
        return $this->doRequest($endpoint, "put", $request, $options);
    }


    protected function doRequest($endpoint, $method, AbstractRequest $request, $options = [])
    {
        $method = strtolower($method);
        if (!is_array($options)) {
            $options = [];
        }

        $body = $request->toArray();
        $options = array_merge($options, $this->getDefaultOptions($body));
        $options[RequestOptions::JSON] = $body;
        $exception = null;

        try {
            /** @var ResponseInterface $result */
            $result = $this->httpClient->$method($endpoint, $options);
            $this->lastResponse = $result;
            $content =  $result->getBody()->getContents();

            if ($this->testMode) {
                $this->getLogger()->info("Sending request to svea integration: $method $endpoint");
                $this->getLogger()->info($request->toJSON());

                $this->getLogger()->info("Response Headers from Svea:");
                $this->getLogger()->info(json_encode($result->getHeaders()));
                $this->getLogger()->info("Response Body from Svea:");



                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['Gui']['Snippet'])) { // Easier to debug without this
                    $decoded['Gui']['Snippet'] = "Removed.";
                    $logContent = json_encode($decoded);
                } else {
                    $logContent = $content;
                }
                $this->getLogger()->info($logContent);
            }

            return $content;
        } catch (BadResponseException $e) {
            $exception = $this->handleException($e);
        } catch (\Exception $e) {
            $exception = $this->handleException($e);
        }

        if ($exception) {
            $this->getLogger()->error("Failed sending request to svea integration: $method $endpoint");
            $this->getLogger()->error(json_encode($this->removeAuthForLogging($options)));
            $this->getLogger()->error($request->toJSON());
            $this->getLogger()->error($exception->getMessage());
            $this->getLogger()->error($exception->getHttpStatusCode());
            $this->getLogger()->error($exception->getResponseBody());
            throw $exception;
        }
    }

    /**
     * @param $bodyArray
     * @return mixed
     */
    protected function getDefaultOptions($bodyArray = [])
    {
        $body = "";
        if (is_array($bodyArray) && !empty($bodyArray)) {
            $body = json_encode($bodyArray);
        }

        $timestamp = gmdate('Y-m-d H:i:s');
        $options['headers'] = [
            'Content-Type' => 'application/json',
            'Timestamp' => $timestamp,
            'Authorization' => $this->createAuthorizationToken($timestamp, $body),
        ];

        return $options;
    }

    private function createAuthorizationToken($timestamp, $body) {

        return "Svea " . base64_encode($this->merchantId . ':' . hash('sha512', $body . $this->sharedSecret . $timestamp));
    }

    private function removeAuthForLogging($options) {

        // we dont want to expose these values!
        if (isset($options['headers']['Authorization'])) {
            unset($options['headers']['Authorization']);
        }

        return $options;
    }


    /**
     * @param \Exception $e
     * @return ClientException
     */
    private function handleException(\Exception $e)
    {
        if ($e instanceof BadResponseException) {
            if ($e->hasResponse()) {
                return new ClientException($e->getRequest(),$e->getResponse(), $e->getMessage(),$e->getCode(), $e);
            } else {
                return new ClientException($e->getRequest(), null, $e->getMessage(), $e->getCode());
            }
        }

        return new ClientException(null, null, $e->getMessage(), $e->getCode());
    }

    /**
     * @param null|int|string $store
     */
    public function resetCredentials($store = null)
    {
        $this->sharedSecret = $this->apiContext->getHelper()->getSharedSecret($store);
        $this->merchantId = $this->apiContext->getHelper()->getMerchantId($store);
        $this->testMode = $this->apiContext->getHelper()->isTestMode($store);

        // the guzzlehttp client, we set base URL here as well.
        // base url could be test or prod, so we need $store, to get if its Testmode!
        $this->setGuzzleHttpClient($this->getHelper(), $store);
    }


    /**
     * @return \Svea\Checkout\Logger\Logger
     */
    public function getLogger()
    {
        return $this->apiContext->getLogger();
    }

    /**
     * @return \Svea\Checkout\Helper\Data
     */
    public function getHelper()
    {
        return $this->apiContext->getHelper();
    }

    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @param \Svea\Checkout\Helper\Data $helper
     * @param $storeView null
     */
    protected abstract function setGuzzleHttpClient(\Svea\Checkout\Helper\Data $helper, $storeView = null);
}









