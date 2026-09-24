<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\IsolatedTestCase;

class LoginRateLimitTest extends IsolatedTestCase
{
    public function test_lockout_message_matches_retry_after_header(): void
    {
        $request = Request::create('/api/v1/auth/login', 'POST', ['username' => 'student']);
        $limit = RateLimiter::limiter('login')($request);

        foreach ([37, '37'] as $seconds) {
            $response = ($limit->responseCallback)($request, ['Retry-After' => $seconds]);

            $this->assertSame(429, $response->getStatusCode());
            $this->assertSame('37', $response->headers->get('Retry-After'));
            $this->assertSame(
                'Too many login attempts. Please try again in 37 seconds.',
                $response->getData(true)['message']
            );
        }
    }
}
