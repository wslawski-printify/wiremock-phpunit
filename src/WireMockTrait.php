<?php

declare(strict_types=1);

namespace WireMock\Phpunit;

use DateTime;
use WireMock\Client\FindNearMissesResult;
use WireMock\Client\ValueMatchingStrategy;
use WireMock\Client\VerificationException;
use WireMock\Phpunit\Dto\Stub;
use WireMock\Phpunit\Exception\RequestVerificationException;
use GuzzleHttp\Exception\ClientException;
use WireMock\Client\MappingBuilder;
use WireMock\Client\RequestPatternBuilder;
use WireMock\Client\ResponseDefinitionBuilder;
use WireMock\Client\WireMock;
use WireMock\Stubbing\StubMapping;

trait WireMockTrait
{
    /**
     * @param bool $stubRequestBody If true, the request body will be included in stub, otherwise we will check it during verification process
     * @param ?int $stubRequestCount In case stub serve events count is different from request count, it can be provided using this argument
     */
    protected function wireMock(
        string $method,
        string $path,
        array $requestHeaders = [],
        array|string|null $requestBody = null,
        array $responseHeaders = [],
        array|string|null $responseBody = null,
        int $responseStatusCode = 200,
        ?string $requestContentType = null,
        bool $stubRequestBody = false,
        ?string $whenScenario = null,
        ?string $toScenario = null,
        ?string $inScenario = null,
        int $requestCount = 1,
        ?int $stubRequestCount = null
    ): void {
        $response = $this->wireResponse($responseStatusCode, $responseBody, $responseHeaders);

        $request = $this->wireRequest($method, $path);

        $requestBodyMatchingStrategy = $this->requestBodyMatchingStrategy($requestBody, $requestContentType);

        if ($requestBodyMatchingStrategy !== null && $stubRequestBody) {
            $request->withRequestBody($requestBodyMatchingStrategy);
        }

        if ($toScenario !== null) {
            $request->willSetStateTo($toScenario);
        }

        if ($whenScenario !== null) {
            $request->whenScenarioStateIs($whenScenario);
        }

        if ($inScenario !== null) {
            $inScenario = WireMockHelper::appendTestToken($inScenario);
            $request->inScenario($inScenario);
            WireMockProxy::addScenario($inScenario);
        }

        $stub = WireMockProxy::instance()->stubFor($request->willReturn($response));
        $stubId = $stub->getId();
        $createdAt = new DateTime();
        $stubRequestCount = $stubRequestCount ?? $requestCount;

        WireMockProxy::$verifyCallbacks[(string) $stubId] = new Stub(
            $stubId,
            function (DateTime $since) use (
                $method,
                $path,
                $requestHeaders,
                $requestBodyMatchingStrategy,
                $requestCount,
                $stubId,
                $stubRequestCount
            ) {
                $requestPatternBuilder = $this->wireMethodRequestedFor($method, $path);

                if ($requestBodyMatchingStrategy !== null) {
                    $requestPatternBuilder->withRequestBody($requestBodyMatchingStrategy);
                }

                foreach ($requestHeaders as $name => $value) {
                    $requestPatternBuilder->withHeader($name, WireMock::equalTo($value));
                }

                try {
                    WireMockProxy::instance()->verify(
                        $requestCount,
                        $requestPatternBuilder,
                    );

                    // make sure that we actually verified it against expected stub
                    $serveEventsCount = WireMockHelper::serveEventsStubCount($stubId, $since);

                    if ($serveEventsCount < $stubRequestCount) {
                        $nearMissesResult = WireMockProxy::instance()->findNearMissesFor($requestPatternBuilder);

                        throw RequestVerificationException::verificationFailed(
                            $path,
                            $method,
                            $nearMissesResult,
                            $stubId
                        );
                    }
                } catch (VerificationException) {
                    $nearMissesResult = WireMockProxy::instance()->findNearMissesFor($requestPatternBuilder);

                    throw RequestVerificationException::verificationFailed(
                        $path,
                        $method,
                        $nearMissesResult,
                        $stubId,
                    );
                }
                catch (ClientException $clientException) {
                    throw RequestVerificationException::clientException(
                        $path,
                        $method,
                        $clientException,
                        $stubId
                    );
                }
            },
            true,
            $createdAt
        );
    }

    protected function appendStubMappingVerification(
        StubMapping $stub,
        bool $resetStub = true,
        int $requestCount = 1
    ): void {
        $requestPattern = $stub->getRequest();
        $stubId = WireMockHelper::stubId($stub);

        $createdAt = new DateTime();
        WireMockContext::$stubServedRequestCount[$stubId] = WireMockHelper::serveEventsStubCount(
            $stubId,
            $createdAt
        );

        $resetStub = WireMockProxy::$testToken !== null ? $resetStub : true;

        if (!empty($stub->getScenarioName())) {
            WireMockProxy::addScenario(WireMockHelper::appendTestToken($stub->getScenarioName()));
        }

        WireMockProxy::$verifyCallbacks[$stubId] = new Stub(
            $stubId,
            function (DateTime $date) use ($requestPattern, $stubId, $requestCount) {
                try {
                    $serveEventsCount = WireMockHelper::serveEventsStubCount($stubId, $date);

                    if (($serveEventsCount - WireMockContext::$stubServedRequestCount[$stubId]) !== $requestCount) {
                        throw RequestVerificationException::verificationFailed(
                            (string) $requestPattern->getUrlMatchingStrategy()?->getMatchingValue(),
                            $requestPattern->getMethod(),
                            new FindNearMissesResult([]),
                            $stubId
                        );
                    }

                    WireMockContext::$stubServedRequestCount[$stubId] = $serveEventsCount;
                } catch (ClientException $clientException) {
                    throw RequestVerificationException::clientException(
                        (string)$requestPattern->getUrlMatchingStrategy()?->getMatchingValue(),
                        $requestPattern->getMethod(),
                        $clientException,
                        $stubId
                    );
                }
            },
            $resetStub,
            $createdAt
        );
    }

    protected function appendStubIdVerification(
        string $stubId,
        bool $resetStub = false,
        int $requestCount = 1
    ): void {
        $createdAt = new DateTime();
        $stubId = WireMockHelper::stubId($stubId);
        $resetStub = WireMockProxy::$testToken !== null ? $resetStub : true;
        $stubMapping = WireMockProxy::instance()->getSingleStubMapping($stubId);
        assert($stubMapping !== null);

        WireMockContext::$stubServedRequestCount[$stubId] = WireMockHelper::serveEventsStubCount(
            $stubId,
            $createdAt
        );

        if (!empty($stubMapping->getScenarioName())) {
            WireMockProxy::addScenario($stubMapping->getScenarioName());
        }

        WireMockProxy::$verifyCallbacks[$stubId] = new Stub(
            $stubId,
            function (DateTime $since) use ($stubId, $requestCount) {
                try {
                    $serveEventsCount = WireMockHelper::serveEventsStubCount($stubId, $since);

                    if (($serveEventsCount - WireMockContext::$stubServedRequestCount[$stubId]) !== $requestCount) {
                        $stub = WireMockProxy::instance()->getSingleStubMapping($stubId);

                        throw RequestVerificationException::verificationFailed(
                            (string)$stub?->getRequest()->getUrlMatchingStrategy()?->getMatchingValue(),
                            (string)$stub?->getRequest()->getMethod(),
                            new FindNearMissesResult([]),
                            $stubId
                        );
                    }

                    WireMockContext::$stubServedRequestCount[$stubId] = $serveEventsCount;
                } catch (ClientException $clientException) {
                    throw RequestVerificationException::clientException(
                        (string)$clientException->getRequest()->getUri(),
                        $clientException->getRequest()->getMethod(),
                        $clientException,
                        $stubId
                    );
                }
            },
            $resetStub,
            $createdAt
        );
    }

    private function wireRequest(string $method, string $path): MappingBuilder
    {
        return new MappingBuilder(new RequestPatternBuilder(strtoupper($method), WireMock::urlEqualTo($path)));
    }

    private function wireMethodRequestedFor(string $method, string $path): RequestPatternBuilder
    {
        return new RequestPatternBuilder(strtoupper($method), WireMock::urlEqualTo($path));
    }

    private function wireResponse(
        int $responseStatusCode,
        array|string|null $responseBody,
        array $responseHeaders
    ): ResponseDefinitionBuilder {
        $response = WireMock::aResponse()->withStatus($responseStatusCode);

        if (is_array($responseBody)) {
            $response->withBody(json_encode($responseBody, JSON_THROW_ON_ERROR));
        }

        if (is_string($responseBody)) {
            $response->withBody($responseBody);
        }

        foreach ($responseHeaders as $name => $value) {
            $response->withHeader($name, $value);
        }

        return $response;
    }

    private function requestBodyMatchingStrategy(
        array|string|null $requestBody,
        ?string $requestContentType
    ): ?ValueMatchingStrategy {
        if (is_array($requestBody)) {
            $requestBody = json_encode($requestBody, JSON_THROW_ON_ERROR);

            if ($requestContentType === null) {
                $requestContentType = WireMockRequestBodyType::JSON;
            }
        }

        if ($requestBody !== null) {
            $equalTo = match ($requestContentType) {
                WireMockRequestBodyType::JSON => WireMock::equalToJson($requestBody),
                WireMockRequestBodyType::XML => WireMock::equalToXml($requestBody),
                default => WireMock::equalTo($requestBody),
            };

            return $equalTo;
        }

        return null;
    }
}
