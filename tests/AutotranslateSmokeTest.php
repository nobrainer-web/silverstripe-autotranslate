<?php

namespace Bratiask\Autotranslate\Tests;

use Bratiask\Autotranslate\Controller\GoogleTranslateController;
use Bratiask\Autotranslate\Extension\AutotranslateExtension;
use Bratiask\Autotranslate\SiteConfig\AutotranslateSiteConfig;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Smoke test: the module's classes load on the installed SilverStripe version, the SiteConfig field and the route
 * are registered, and the CMS fields of a record with the extension build without errors.
 */
class AutotranslateSmokeTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testSiteConfigHasApiKeyFieldAndTab(): void
    {
        $this->assertTrue(SiteConfig::has_extension(AutotranslateSiteConfig::class));
        $config = SiteConfig::current_site_config();
        $this->assertTrue($config->hasField(AutotranslateSiteConfig::GOOGLE_TRANSLATE_API_KEY));
        $this->assertNotNull($config->getCMSFields()->dataFieldByName(AutotranslateSiteConfig::GOOGLE_TRANSLATE_API_KEY));
    }

    public function testRouteIsRegistered(): void
    {
        $rules = Config::inst()->get(Director::class, 'rules');
        $this->assertSame(GoogleTranslateController::class, $rules['autotranslate//translate'] ?? null);
    }

    public function testCmsFieldsBuildWithTheExtension(): void
    {
        // any project class that has the extension (sites apply it to pages, blocks, links ...)
        $owners = array_filter(
            \SilverStripe\Core\ClassInfo::subclassesFor(\SilverStripe\ORM\DataObject::class, false),
            fn ($c) => $c::has_extension(AutotranslateExtension::class) && !(new \ReflectionClass($c))->isAbstract()
        );
        if (!$owners) {
            $this->markTestSkipped('no class in this project has AutotranslateExtension');
        }
        $config = SiteConfig::current_site_config();
        $config->{AutotranslateSiteConfig::GOOGLE_TRANSLATE_API_KEY} = 'dummy';
        $config->write();
        foreach (array_slice(array_values($owners), 0, 10) as $class) {
            $this->assertNotNull($class::create()->getCMSFields(), $class);
        }
    }
}
