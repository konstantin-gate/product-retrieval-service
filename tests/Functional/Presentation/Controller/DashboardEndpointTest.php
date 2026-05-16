<?php

declare(strict_types=1);

namespace App\Tests\Functional\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardEndpointTest extends WebTestCase
{
    public function testDashboardIndexReturns200(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Dashboard vyhledávání produktů');
    }

    public function testToggleWithValidCsrfReturns200(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $form = $crawler->filter('form[action="/toggle"]')->first()->form();
        $client->submit($form, [
            'key' => 'ACTIVE_PRODUCT_SOURCE',
            'value' => 'mysql',
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertSame('ACTIVE_PRODUCT_SOURCE', $data['key']);
        self::assertSame('mysql', $data['value']);
    }

    public function testToggleWithoutCsrfReturns403(): void
    {
        $client = static::createClient();
        $client->request('POST', '/toggle', [
            'key' => 'ACTIVE_PRODUCT_SOURCE',
            'value' => 'mysql',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
