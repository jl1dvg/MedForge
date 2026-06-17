<?php

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuthCsrfFallbackTest extends TestCase
{
    public function test_expired_browser_exception_redirects_to_fresh_login(): void
    {
        $request = Request::create('/auth/login', 'POST');

        $response = app(ExceptionHandler::class)
            ->render($request, new HttpException(419, 'Page Expired'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(url('/auth/login?expired=1'), $response->headers->get('Location'));
    }
}
