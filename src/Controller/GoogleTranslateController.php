<?php
declare(strict_types=1);

namespace Bratiask\Autotranslate\Controller;

use Bratiask\Autotranslate\SiteConfig\AutotranslateSiteConfig;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Security\Permission;
use SilverStripe\Security\SecurityToken;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Proxy for the CMS "Translate" action: sends one field value to Google Translate with the site's API key.
 * Only for logged-in CMS users (it spends the site's paid key), POST with the CMS security token; the answer is
 * plain text, never rendered as HTML on the site's domain.
 */
class GoogleTranslateController extends Controller
{
    const BASE_URI = 'https://translation.googleapis.com/language/translate/v2';

    /** Longest text sent in one request (characters) */
    private static int $max_query_length = 30000;

    private static $allowed_actions = [
        'translate' => 'CMS_ACCESS_LeftAndMain',
    ];

    public function translate(HTTPRequest $request): HTTPResponse
    {
        if (!Permission::check('CMS_ACCESS_LeftAndMain')) {
            return $this->plain('Forbidden', 403);
        }
        if (!$request->isPOST()) {
            return $this->plain('Method not allowed', 405)->addHeader('Allow', 'POST');
        }
        if (!SecurityToken::inst()->checkRequest($request)) {
            return $this->plain('Invalid security token, reload the page', 400);
        }

        $source = (string) $request->postVar('source');
        $target = (string) $request->postVar('target');
        $query = (string) $request->postVar('query');
        $languageCode = '/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/';
        if (!preg_match($languageCode, $source) || !preg_match($languageCode, $target)) {
            return $this->plain('Invalid language', 400);
        }
        if ($query === '' || mb_strlen($query) > static::config()->get('max_query_length')) {
            return $this->plain('Nothing to translate, or the text is too long', 400);
        }
        $key = (string) SiteConfig::current_site_config()->{AutotranslateSiteConfig::GOOGLE_TRANSLATE_API_KEY};
        if ($key === '') {
            return $this->plain('No Google Translate API key in Settings', 500);
        }

        $result = @file_get_contents(self::BASE_URI, false, stream_context_create([
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'timeout' => 15,
                'content' => http_build_query([
                    'key' => $key,
                    'source' => $source,
                    'target' => $target,
                    'q' => $query,
                ]),
            ],
        ]));
        $translated = $result ? (json_decode($result, true)['data']['translations'][0]['translatedText'] ?? null) : null;
        if (!is_string($translated)) {
            return $this->plain('Cannot translate', 502);
        }

        return $this->plain($translated);
    }

    private function plain(string $body, int $status = 200): HTTPResponse
    {
        return HTTPResponse::create($body, $status)
            ->addHeader('Content-Type', 'text/plain; charset=utf-8')
            ->addHeader('X-Content-Type-Options', 'nosniff')
            ->addHeader('Cache-Control', 'no-store');
    }
}
