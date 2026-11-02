<?php

namespace Bratiask\Autotranslate\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Security\SecurityToken;

/**
 * The translate endpoint spends the site's paid Google key: it must refuse anonymous users, GET and requests
 * without the CMS security token, and never answer as HTML. None of these cases reaches Google.
 */
class GoogleTranslateControllerTest extends FunctionalTest
{
    protected $usesDatabase = true;

    private array $post = ['source' => 'da', 'target' => 'en', 'query' => '<b>hej</b>'];

    protected function setUp(): void
    {
        parent::setUp();
        SecurityToken::enable();
    }

    public function testAnonymousIsRefused(): void
    {
        $response = $this->post('autotranslate/translate', $this->post);
        // allowed_actions sends anonymous users to the login page (302) or refuses them (403); never 200
        $this->assertContains($response->getStatusCode(), [302, 403], 'anonymous must not reach the translate action');
    }

    public function testCmsUserWithoutTokenIsRefused(): void
    {
        $this->logInWithPermission('CMS_ACCESS_LeftAndMain');
        $response = $this->post('autotranslate/translate', $this->post);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringStartsWith('text/plain', $response->getHeader('Content-Type'));
        $this->assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
    }

    public function testGetIsRefused(): void
    {
        $this->logInWithPermission('CMS_ACCESS_LeftAndMain');
        $response = $this->get('autotranslate/translate');
        $this->assertSame(405, $response->getStatusCode());
    }

    public function testInvalidLanguageIsRefusedBeforeGoogle(): void
    {
        $this->logInWithPermission('CMS_ACCESS_LeftAndMain');
        $token = SecurityToken::inst();
        $response = $this->post('autotranslate/translate', ['source' => 'da"><x', 'target' => 'en', 'query' => 'hej', $token->getName() => $token->getValue()]);
        $this->assertSame(400, $response->getStatusCode());
    }
}
