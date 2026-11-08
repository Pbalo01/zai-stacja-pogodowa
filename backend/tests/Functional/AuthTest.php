<?php

namespace App\Tests\Functional;

final class AuthTest extends ApiTestCase
{
    public function testLoginReturnsBearerToken(): void
    {
        $response = $this->request('POST', '/api/auth/login', ['username' => self::ADMIN_USERNAME, 'password' => self::ADMIN_PASSWORD]);

        self::assertSame(200, $response->getStatusCode());
        $body = $this->json($response);
        self::assertSame('Bearer', $body['tokenType']);
        self::assertSame(3600, $body['expiresIn']);
        self::assertSame(64, strlen($body['accessToken']));
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $response = $this->request('POST', '/api/auth/login', ['username' => self::ADMIN_USERNAME, 'password' => 'zle-haslo']);

        $this->assertProblem($response, 401);
    }

    public function testLogoutInvalidatesToken(): void
    {
        $token = $this->login();

        self::assertSame(204, $this->request('POST', '/api/auth/logout', null, $this->bearer($token))->getStatusCode());
        $this->assertProblem($this->request('POST', '/api/auth/logout', null, $this->bearer($token)), 401);
    }

    public function testForgedTokenReturns401(): void
    {
        $response = $this->request('POST', '/api/series', ['name' => 'X', 'minValue' => 0, 'maxValue' => 1, 'color' => '#000000'], $this->bearer('sfalszowany'));

        $this->assertProblem($response, 401);
    }

    public function testPasswordChangeRules(): void
    {
        $token = $this->login();

        $wrongCurrent = ['currentPassword' => 'zle-haslo', 'newPassword' => 'NoweHaslo-123'];
        $this->assertProblem($this->request('PUT', '/api/auth/password', $wrongCurrent, $this->bearer($token)), 403);
        $this->assertProblem($this->request('PUT', '/api/auth/password', $wrongCurrent), 401);

        $tooShort = ['currentPassword' => self::ADMIN_PASSWORD, 'newPassword' => 'krotkie'];
        $response = $this->request('PUT', '/api/auth/password', $tooShort, $this->bearer($token));
        $this->assertProblem($response, 422);
        self::assertSame('newPassword', $this->json($response)['errors'][0]['field']);

        $valid = ['currentPassword' => self::ADMIN_PASSWORD, 'newPassword' => 'NoweHaslo-123'];
        self::assertSame(204, $this->request('PUT', '/api/auth/password', $valid, $this->bearer($token))->getStatusCode());
        $relogin = $this->request('POST', '/api/auth/login', ['username' => self::ADMIN_USERNAME, 'password' => 'NoweHaslo-123']);
        self::assertSame(200, $relogin->getStatusCode());
    }
}
