<?php

namespace Tests\Unit;

use App\Support\AppUrl;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class AppUrlTest extends TestCase
{
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            $this->env($key, $value === false ? null : $value);
        }

        parent::tearDown();
    }

    public function test_a_public_app_url_is_used_as_is(): void
    {
        $this->env('APP_URL', 'https://shop.example/');
        $this->env('SERVICE_URL_APP', 'https://other.example');

        $this->assertSame('https://shop.example', AppUrl::resolve());
    }

    #[TestWith(['http://localhost'])]
    #[TestWith(['http://localhost:8000'])]
    #[TestWith(['http://127.0.0.1'])]
    #[TestWith([''])]
    #[TestWith([null])]
    public function test_a_local_or_missing_app_url_falls_back_to_the_coolify_domain(?string $appUrl): void
    {
        $this->env('APP_URL', $appUrl);
        $this->env('SERVICE_URL_APP', 'https://shop.example,https://www.shop.example');

        $this->assertSame('https://shop.example', AppUrl::resolve());
    }

    public function test_localhost_stays_when_there_is_nothing_better(): void
    {
        $this->env('APP_URL', 'http://localhost:8000');
        $this->env('SERVICE_URL_APP', null);

        $this->assertSame('http://localhost:8000', AppUrl::resolve());
    }

    private function env(string $key, ?string $value): void
    {
        $this->saved[$key] ??= getenv($key);

        putenv($value === null ? $key : "$key=$value");
        unset($_ENV[$key], $_SERVER[$key]);

        if ($value !== null) {
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
    }
}
