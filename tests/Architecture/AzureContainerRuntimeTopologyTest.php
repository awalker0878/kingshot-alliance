<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AzureContainerRuntimeTopologyTest extends TestCase
{
    public function test_compose_and_azure_nginx_profiles_use_the_correct_fastcgi_boundary(): void
    {
        $compose = $this->read('docker/nginx/default.conf');
        $azure = $this->read('docker/nginx/azure.conf');

        self::assertStringContainsString('listen 8080;', $compose);
        self::assertStringContainsString('fastcgi_pass app:9000;', $compose);

        self::assertStringContainsString('listen 8080;', $azure);
        self::assertStringContainsString('fastcgi_pass 127.0.0.1:9000;', $azure);
        self::assertStringNotContainsString('fastcgi_pass app:9000;', $azure);
    }

    public function test_azure_nginx_reconstructs_the_public_https_request_without_forwarded_host_or_port(): void
    {
        $azure = $this->read('docker/nginx/azure.conf');

        foreach ([
            'HTTP_HOST $host',
            'SERVER_NAME $host',
            'SERVER_PORT 443',
            'HTTPS on',
            'HTTP_X_FORWARDED_FOR $http_x_forwarded_for',
            'HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto',
        ] as $requestParameter) {
            self::assertStringContainsString($requestParameter, $azure);
        }

        self::assertStringNotContainsString('HTTP_X_FORWARDED_HOST $http_x_forwarded_host', $azure);
        self::assertStringNotContainsString('HTTP_X_FORWARDED_PORT $http_x_forwarded_port', $azure);
    }

    public function test_azure_runtime_only_trusts_required_forwarded_headers(): void
    {
        $bootstrap = $this->read('bootstrap/app.php');

        self::assertStringContainsString('Request::HEADER_X_FORWARDED_FOR', $bootstrap);
        self::assertStringContainsString('Request::HEADER_X_FORWARDED_PROTO', $bootstrap);
        self::assertStringNotContainsString('Request::HEADER_X_FORWARDED_HOST', $bootstrap);
        self::assertStringNotContainsString('Request::HEADER_X_FORWARDED_PORT', $bootstrap);
        self::assertStringNotContainsString('Request::HEADER_X_FORWARDED_PREFIX', $bootstrap);
    }

    public function test_runtime_image_preserves_generic_entrypoint_and_packages_azure_nginx_profile(): void
    {
        $dockerfile = $this->read('Dockerfile');

        self::assertStringContainsString('COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf', $dockerfile);
        self::assertStringContainsString('COPY docker/nginx/azure.conf /etc/nginx/azure.conf', $dockerfile);
        self::assertStringContainsString('nginx -t -c /etc/nginx/azure.conf', $dockerfile);
        self::assertStringContainsString('ENTRYPOINT ["kingshot-entrypoint"]', $dockerfile);
        self::assertStringContainsString('CMD ["php-fpm"]', $dockerfile);
        self::assertStringNotContainsString('start-web.sh', $dockerfile);
    }

    public function test_redis_scheme_is_configurable_for_tls_managed_redis(): void
    {
        $database = $this->read('config/database.php');
        $localEnvironment = $this->read('.env.example');
        $stagingEnvironment = $this->read('deploy/staging.env.example');

        self::assertSame(2, substr_count($database, "'scheme' => env('REDIS_SCHEME', 'tcp')"));
        self::assertStringContainsString('REDIS_SCHEME=tcp', $localEnvironment);
        self::assertStringContainsString('REDIS_SCHEME=tcp', $stagingEnvironment);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }
}
