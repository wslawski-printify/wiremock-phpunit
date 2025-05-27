## PHPUnit extension for WireMock

This library provides easy way to integrate [WireMock](https://wiremock.org) with PHPUnit. It will verify mocked interactions after each test and fail them if they are not met.

### Requirements

- Running wiremock service(in tests we use `wiremock/wiremock:latest`)
- Set up `host` and `port` as parameters of extension(see below)
- PHP 8.1
- PHPUnit 10 or higher
- wiremock-php 2.0 or higher

### Usage

To use extension add to your phpunit.xml configuration:

```xml
<extensions>
    <bootstrap class="WireMock\Phpunit\Extension\WireMockExtension">
        <parameter name="host" value="wiremock"/>
        <parameter name="port" value="8080"/>
    </bootstrap>
</extensions>
```

It listens for those events and triggers specific actions:
- `PHPUnit\Event\TestRunner\BootstrapFinished` - Starts wiremock instance
- `PHPUnit\Event\Test\Finished` - Verifies interactions cleanups requests and stubs

#### Mocking requests

To mock requests you need to use `WireMock\Phpunit\WireMockTrait::wireMock()`. We recommend creating your own traits with your own methods. This is one of examples:

```php
trait RequestTrait
{
    use WireMockTrait;

    public function mockTestRequest(string $expectedBody): void
    {
        $this->wireMock(
            'GET',
            '/test',
            [],
            null,
            [],
            $expectedBody
        );
    }

    public function mockTestPostRequest(string $expectedBody, string $requestBody): void
    {
        $this->wireMock(
            'POST',
            '/test',
            [],
            $requestBody,
            [],
            $expectedBody
        );
    }
}
```

You can also use methods `WireMock\Phpunit\WireMockTrait::appendStubMappingVerification()` and `WireMock\Phpunit\WireMockTrait::appendStubIdVerification()`

First method will allow to you append existing `StubMapping` instance and check if it was served during the tests. By default, it will clean stub from WireMock instance, but it can be changed. This will require if you already have in your code stubFor calls to add just append verification for existing stubs.

Second method accepts stub id, and will keep stub state as it is after the test. Use case for it for example is importing all stubs to wiremock instance when container is started, and then verifying that specific stub was served during the test, instead of stubbing them during the tests.

### Configuration

By default, extension waits for 3 seconds for wiremock server. If you need more time you can change it by setting parameter `timeout` in phpunit configuration:

```xml
<extensions>
    <bootstrap class="WireMock\Phpunit\Extension\WireMockExtension">
        <parameter name="timeout" value="60"/>
    </bootstrap>
</extensions>
```
