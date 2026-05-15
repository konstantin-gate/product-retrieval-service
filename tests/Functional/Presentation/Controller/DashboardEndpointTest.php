<?php

declare(strict_types=1);

namespace App\Tests\Functional\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardEndpointTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Product Search Dashboard');
    }

    public function testToggleSuccess(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $form = $crawler->selectButton('Toggle')->form();
        $client->submit($form, [
            'key' => 'ACTIVE_PRODUCT_SOURCE',
            'value' => 'mysql',
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertSame('ACTIVE_PRODUCT_SOURCE', $data['key']);
        self::assertSame('mysql', $data['value']);
    }

    public function testToggleNoCsrf(): void
    {
        $client = static::createClient();
        $client->request('POST', '/toggle', [
            'key' => 'ACTIVE_PRODUCT_SOURCE',
            'value' => 'mysql',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testToggleInvalidKey(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // Extract CSRF token
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/toggle', [
            'key' => 'INVALID_KEY',
            'value' => 'mysql',
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testToggleInvalidValue(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/toggle', [
            'key' => 'ACTIVE_PRODUCT_SOURCE',
            'value' => 'invalid_value',
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(400);
        $data = \json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Invalid toggle value', $data['error']);
    }
}
