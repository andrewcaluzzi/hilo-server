<?php

namespace Arcium\ApiBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GameControllerTest extends WebTestCase
{
    public function testLogin()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/login');
    }

    public function testSignup()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/signup');
    }

    public function testCreategame()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/createGame');
    }

    public function testSubmitaction()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/api/submitAction');
    }

}
