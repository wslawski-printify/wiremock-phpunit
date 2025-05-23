<?php

declare(strict_types=1);

namespace WireMock\Phpunit;

use http\Env\Request;
use WireMock\Client\CountMatchingRequestsResult;
use WireMock\Client\FindRequestsResult;
use WireMock\Client\LoggedRequest;
use WireMock\Client\ServeEventQuery;
use WireMock\Client\ValueMatchingStrategy;
use WireMock\Phpunit\Exception\RequestVerificationException;
use GuzzleHttp\Exception\ClientException;
use WireMock\Client\MappingBuilder;
use WireMock\Client\RequestPatternBuilder;
use WireMock\Client\ResponseDefinitionBuilder;
use WireMock\Client\VerificationException;
use WireMock\Client\WireMock;
use WireMock\Stubbing\StubMapping;

trait WireMockTrait
{
    protected function wireMock(
        string            $method,
        string            $path,
        array            $requestHeaders = [],
        array|string|null $requestBody = null,
        array            $responseHeaders = [],
        array|string|null $responseBody = null,
        int               $responseStatusCode = 200,
        ?string $requestContentType = null,
        bool $stubRequestBody = false,
        ?string $whenScenario = null,
        ?string $toScenario = null,
        ?string $inScenario = null,
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
            $request->inScenario($this->appendTestToken($inScenario));
            WireMockProxy::addScenario($inScenario);
        }

        $stub = WireMockProxy::instance()->stubFor($request->willReturn($response));

        // wire request
        WireMockProxy::$verifyCallbacks[(string) $stub->getId()] = function () use ($stub, $method, $path, $requestHeaders, $requestBodyMatchingStrategy) {
            $requestPatternBuilder = $this->wireMethodRequestedFor($method, $path);

            if ($requestBodyMatchingStrategy !== null) {
                $requestPatternBuilder->withRequestBody($requestBodyMatchingStrategy);
            }

            foreach ($requestHeaders as $name => $value) {
                $requestPatternBuilder->withHeader($name, WireMock::equalTo($value));
            }

            try {
                WireMockProxy::instance()->verify($requestPatternBuilder);
                $this->checkServeEventsMatchingStub($stub->getId());
            } catch (VerificationException $verificationException) { // @phpstan-ignore-line
                throw RequestVerificationException::verificationFailed(
                    $path,
                    $method,
                    $verificationException,
                    $stub->getId()
                );
            } catch (ClientException $clientException) {
                throw RequestVerificationException::clientException(
                    $path,
                    $method,
                    $clientException,
                    $stub->getId()
                );
            }
        };
    }

    protected function appendStub(StubMapping $stub): void
    {
        $requestPattern = $stub->getRequest();

        WireMockProxy::$verifyCallbacks[(string) $stub->getId()] = function () use ($requestPattern, $stub) {
            try {
                $reflectionMethod = new \ReflectionMethod(WireMockProxy::instance(), 'doPost');
                $response = $reflectionMethod->invoke(
                    WireMockProxy::instance(),
                    '__admin/requests/count',
                    $requestPattern,
                    CountMatchingRequestsResult::class
                );
                $count = $response->getCount();

                if ($count < 1) {
                    throw new VerificationException("Expected at least one request, but found $count");
                }

                $this->checkServeEventsMatchingStub($stub->getId());
            } catch (VerificationException $verificationException) { // @phpstan-ignore-line
                throw RequestVerificationException::verificationFailed(
                    (string) $requestPattern->getUrlMatchingStrategy()?->getMatchingValue(),
                    $requestPattern->getMethod(),
                    $verificationException,
                    $stub->getId()
                );
            } catch (ClientException $clientException) {
                throw RequestVerificationException::clientException(
                    (string) $requestPattern->getUrlMatchingStrategy()?->getMatchingValue(),
                    $requestPattern->getMethod(),
                    $clientException,
                    $stub->getId()
                );
            }
        };
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

        if ($requestBody !== null ) {
            $equalTo = match ($requestContentType) {
                WireMockRequestBodyType::JSON => WireMock::equalToJson($requestBody),
                WireMockRequestBodyType::XML => WireMock::equalToXml($requestBody),
                default => WireMock::equalTo($requestBody),
            };

            return $equalTo;
        }

        return null;
    }

    private function appendTestToken(string $value): string
    {
        $testToken = getenv('TEST_TOKEN');

        if ($testToken !== false) {
            return sprintf('%s_%s', $testToken, $value);
        }

        return $value;
    }

    private function checkServeEventsMatchingStub(string $stubId): void
    {
        $serveEvents = WireMockProxy::instance()->getAllServeEvents(
            (new ServeEventQuery())
                ->withStubMapping($stubId)
        );

        if (count($serveEvents?->getRequests() ?? []) === 0) {
            throw new VerificationException(sprintf('No requests found for stub %s', $stubId));
        }
    }
}
