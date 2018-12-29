<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */
namespace craftunit\helpers;


use Codeception\Test\Unit;
use craft\db\Query;
use craft\helpers\UrlHelper;
use craftunit\fixtures\SitesFixture;
use yii\base\Exception;

/**
 * Unit tests for the Url Helper class.
 *
 * TODO: The URL helper test needs some refactoring work to accommodate the new Craft module. It currently will not run.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.0
 */
class UrlHelperTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $entryScript;
    protected $entryUrl;
    protected $baseUrl;
    protected $baseUrlWithScript;
    protected $cpTrigger;

    protected function _before()
    {
        $generalConfig = \Craft::$app->getConfig()->getGeneral();
        $this->cpTrigger = $generalConfig->cpTrigger;
        $configSiteUrl = $generalConfig->siteUrl;

        $yii2 = $this->getModule('Yii2');
        $this->entryScript = $yii2->_getConfig('entryScript');
        $this->entryUrl = $yii2->_getConfig('entryUrl');

        if (!$configSiteUrl) {
            $configSiteUrl = $this->entryUrl;
        }

        $this->baseUrl = $configSiteUrl;
        // Add the entry script. This  is for the withScript variable.
        if (strpos($this->entryScript, $configSiteUrl) === false) {
            $configSiteUrl .= $this->entryScript;
        }

        if (\Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->baseUrlWithScript = $configSiteUrl ?: $this->entryScript;
        } else {
            $this->baseUrlWithScript = $configSiteUrl ?: '/';
        }
    }

    const ABSOLUTE_URL = 'http://craftcms.com/';
    const ABSOLUTE_URL_HTTPS = 'https://craftcms.com/';
    const ABSOLUTE_URL_WWW = 'http://www.craftcms.com/';
    const ABSOLUTE_URL_HTTPS_WWW = 'https://www.craftcms.com/';
    const NON_ABSOLUTE_URL = 'craftcms.com/';
    const NON_ABSOLUTE_URL_WWW = 'www.craftcms.com/';
    const PROTOCOL_RELATIVE_URL = '//craftcms.com/';

    /**
     * @dataProvider protocolRelativeUrlData
     * @dataProvider absoluteUrlData
     * @dataProvider fulUrlData
     */
    public function testIsUrlFunction($url, bool $result, $method)
    {
        $urlHelperResult = UrlHelper::$method($url);
        $this->assertSame($urlHelperResult, $result);
        $this->assertInternalType('boolean', $urlHelperResult);
    }

    /**
     * @return array
     */
    public function absoluteUrlData()
    {
        return [
            'absolute-url' => [ self::ABSOLUTE_URL, true, 'isAbsoluteUrl' ],
            'absolute-url-https' => [ self::ABSOLUTE_URL_HTTPS, true, 'isAbsoluteUrl' ],
            'absolute-url-https-www' => [ self::ABSOLUTE_URL_HTTPS_WWW, true, 'isAbsoluteUrl' ],
            'absolute-url-www' => [ self::ABSOLUTE_URL_WWW, true, 'isAbsoluteUrl' ],
            'non-url' => [self::NON_ABSOLUTE_URL, false, 'isAbsoluteUrl'],
            'non-absolute-url-www' => [ self::NON_ABSOLUTE_URL_WWW, false, 'isAbsoluteUrl' ]
        ];
    }

    /**
     * @return array
     */
    public function fulUrlData()
    {
        return [
            'absolute-url' => [ self::ABSOLUTE_URL, true, 'isFullUrl' ],
            'absolute-url-https' => [ self::ABSOLUTE_URL_HTTPS, true, 'isFullUrl' ],
            'absolute-url-https-www' => [ self::ABSOLUTE_URL_HTTPS_WWW, true, 'isFullUrl' ],
            'absolute-url-www' => [ self::ABSOLUTE_URL_WWW, true, 'isFullUrl' ],
            'root-relative' => [ '/22', true, 'isFullUrl' ],
            'protocol-relative' => [ self::PROTOCOL_RELATIVE_URL, true, 'isFullUrl' ],
            'mb4-string' => [ '😀😘', false, 'isFullUrl' ],
            'random-chars' => [ '!@#$%^&*()<>', false, 'isFullUrl' ],
            'random-string' => ['hello', false, 'isFullUrl'],
            'non-url' => [self::NON_ABSOLUTE_URL, false, 'isFullUrl'],
            'non-absolute-url-www' => [ self::NON_ABSOLUTE_URL_WWW, false, 'isFullUrl' ],
        ];
    }

    /**
     * @return array
     */
    public function protocolRelativeUrlData()
    {
        return [
            'root-relative-true' => [ '/22', true, 'isRootRelativeUrl'],
            'protocol-relative' => [ '//cdn.craftcms.com/22', false, 'isRootRelativeUrl' ],
            'absolute-url-https-www' => [ self::ABSOLUTE_URL_HTTPS_WWW, false, 'isRootRelativeUrl' ]
        ];
    }

    /**
     * @dataProvider urlWithParamsData()
     */
    public function testUrlWithParams($result, $url, $params)
    {
        $this->assertSame($result, UrlHelper::urlWithParams($url, $params));
    }

    public function urlWithParamsData()
    {
        return [
            '#' => [
                self::ABSOLUTE_URL_HTTPS_WWW.'?param1=name&param2=name2#anchor',
                self::ABSOLUTE_URL_HTTPS_WWW,
                ['param1' => 'name', 'param2' => 'name2', '#' => 'anchor']
            ],
            'basic-array' => [
                self::ABSOLUTE_URL_HTTPS_WWW.'?param1=name&param2=name2',
                self::ABSOLUTE_URL_HTTPS_WWW,
                ['param1' => 'name', 'param2' => 'name2']
            ],
            'empty-array' => [
                self::ABSOLUTE_URL_HTTPS_WWW,
                self::ABSOLUTE_URL_HTTPS_WWW,
               []
            ],
            '4-spaces' => [
                self::ABSOLUTE_URL_HTTPS_WWW.'?    ',
                self::ABSOLUTE_URL_HTTPS_WWW,
                '    '
            ],
            'numerical-index-array'  => [
                self::ABSOLUTE_URL_HTTPS_WWW.'?0=someparam',
                self::ABSOLUTE_URL_HTTPS_WWW,
                ['someparam']
            ],
            'query-string' => [
                self::ABSOLUTE_URL_HTTPS_WWW.'?param1=name&param2=name2',
                self::ABSOLUTE_URL_HTTPS_WWW,
                '?param1=name&param2=name2'
            ],
            'pre-queried-url' => [
                self::ABSOLUTE_URL_HTTPS_WWW.'?param3=name3&param1=name&param2=name2',
                self::ABSOLUTE_URL_HTTPS_WWW.'?param3=name3',
                '?param1=name&param2=name2'
            ],
    ];
    }


    /**
     * @dataProvider cpUrlCreationData
     */
    public function testCpUrlCreation($result, $inputUrl, $params, $scheme = 'https')
    {
        // Make sure https is enabled for the base url.
        if ($scheme === 'https') {
            $baseUrl = str_replace('http://', 'https://', $this->baseUrlWithScript);
        } else {
            $baseUrl = str_replace('https://', 'http://', $this->baseUrlWithScript);
        }

        $this->assertSame(
            $baseUrl.'?p='.$this->cpTrigger.''.$result.'',
            UrlHelper::cpUrl($inputUrl, $params, $scheme)
        );
    }

    /**
     * @return array
     */
    public function cpUrlCreationData()
    {
        return [
            'test-empty' => ['', '', []],
            'test-simple-endpoint' => [
                '/nav&param1=entry1&param2=entry2',
                'nav',
                ['param1' => 'entry1', 'param2' => 'entry2']
            ],
            'test-preexisting-endpoints' => [
                '/nav&param3=entry3&param1=entry1&param2=entry2',
                'nav?param3=entry3',
                ['param1' => 'entry1', 'param2' => 'entry2']
            ],
            [
                '/nav&param1=entry1&param2=entry2',
                'nav',
                [
                    'param1' => 'entry1',
                    'param2' => 'entry2'
                ],
                'https'
            ],
            'test-url-gets-ignored' => [
                '/https://test.craftcms.dev&param1=entry1&param2=entry2',
                'https://test.craftcms.dev',
                ['param1' => 'entry1', 'param2' => 'entry2'],
                'https'
            ]
        ];
    }

    /**
     * @dataProvider urlWithSchemeProvider
     * @dataProvider urlWithTokenProvider
     * @dataProvider urlWithParamsProvider
     * @dataProvider stripQueryStringProvider
     * @param      $url
     * @param      $data
     * @param bool $result
     * @param      $method
     */
    public function testUrlModifiers($result, $url, $modifier, $method)
    {
        $this->assertSame($result, UrlHelper::$method($url, $modifier));
    }

    public function stripQueryStringProvider()
    {
        return [
            'invalid-query-string' => [
                self::ABSOLUTE_URL_HTTPS_WWW,
                self::ABSOLUTE_URL_HTTPS_WWW.'&query=string',
                null,
                'stripQueryString'
            ],
            [
                self::ABSOLUTE_URL_HTTPS_WWW,
                self::ABSOLUTE_URL_HTTPS_WWW,
                null,
                'stripQueryString'
            ],
            [
                self::ABSOLUTE_URL_HTTPS_WWW,
                self::ABSOLUTE_URL_HTTPS_WWW.'?param1=entry1',
                null,
                'stripQueryString'
            ],
            [
            self::ABSOLUTE_URL_HTTPS_WWW,
                self::ABSOLUTE_URL_HTTPS_WWW.'?param1=entry1?param2=entry2',
                null,
                'stripQueryString'
            ]

        ];
    }

    public function urlWithParamsProvider()
    {
        return [
            'with-fragment' => [
                self::ABSOLUTE_URL_HTTPS.'?param1=entry1#some-hashtag',
                self::ABSOLUTE_URL_HTTPS,
                ['param1' => 'entry1', '#' => 'some-hashtag'],
                'urlWithParams'
            ],
            [
                self::ABSOLUTE_URL_HTTPS.'?param1=entry1',
                self::ABSOLUTE_URL_HTTPS,
                ['param1' => 'entry1'],
                'urlWithParams'
            ],
            [
                self::ABSOLUTE_URL_HTTPS.'?param1=entry1&param2=entry2',
                self::ABSOLUTE_URL_HTTPS,
                ['param1' => 'entry1', 'param2' => 'entry2'],
                'urlWithParams'
            ],
            [
                self::ABSOLUTE_URL_HTTPS.'?param1=entry1&param2=entry2',
                self::ABSOLUTE_URL_HTTPS,
                'param1=entry1&param2=entry2',
                'urlWithParams'
            ],
            'anchor-gets-kept' => [
                self::ABSOLUTE_URL_HTTPS.'#anchor?param1=entry1&param2=entry2',
                self::ABSOLUTE_URL_HTTPS.'#anchor',
                'param1=entry1&param2=entry2',
                'urlWithParams'
            ],
            'prev-param-gets-kept' => [
                self::ABSOLUTE_URL_HTTPS_WWW.'#anchor?param3=entry3&param1=entry1&param2=entry2',
                self::ABSOLUTE_URL_HTTPS_WWW.'#anchor?param3=entry3',
                '?param1=entry1&param2=entry2',
                'urlWithParams'
            ],
        ];
    }

    public function urlWithTokenProvider()
    {
        $requiredScheme = UrlHelper::getSchemeForTokenizedUrl();
        if (strpos($requiredScheme, 'https') !== false) {
            $https = true;
            $baseUrl = self::ABSOLUTE_URL_HTTPS;
        } else {
            $https = false;
            $baseUrl = self::ABSOLUTE_URL;
        }

        return [
            [
                $baseUrl.'?token=value',
                $baseUrl,
                'value',
                'urlWithToken'
            ],
            [
                $baseUrl.'?token=value&token=value',
                $baseUrl.'?token=value',
                'value',
                'urlWithToken'
            ],
            [
                $baseUrl.'?token=',
                $baseUrl.'',
                '',
                'urlWithToken'
            ],
            'ensure-scheme-is-overridden' => [
                $https ? self::ABSOLUTE_URL_HTTPS.'?token=value' : self::ABSOLUTE_URL.'?token=value',
                $https ? self::ABSOLUTE_URL : self::ABSOLUTE_URL_HTTPS,
                'value',
                'urlWithToken'
            ],
            'no-protocol' => [
                'craft?token=value',
                'craft',
                'value',
                'urlWithToken'
            ]
        ];
    }

    public function urlWithSchemeProvider()
    {
        return [
                'no-scheme' => [
                    'imaurl',
                    'imaurl',
                    '',
                    'urlWithScheme'
                ],
                'nothing' => [
                    '',
                    '',
                    '',
                    'urlWithScheme'
                ],
                'protocol-relative' => [
                    'https://cdn.craftcms.com',
                    '//cdn.craftcms.com',
                    'https',
                    'urlWithScheme'
                ],
                'php-replace' => [
                    str_replace('https://', 'php://', self::ABSOLUTE_URL_HTTPS_WWW),
                    self::ABSOLUTE_URL_HTTPS_WWW,
                    'php',
                    'urlWithScheme'
                ],
                'ftp-replace' => [
                    str_replace('https://', 'ftp://', self::ABSOLUTE_URL_HTTPS),
                    self::ABSOLUTE_URL_HTTPS,
                    'ftp',
                    'urlWithScheme'
                ],
                'non-valid-protocol' => [
                    str_replace('http://', 'walawalabingbang://', self::ABSOLUTE_URL),
                    self::ABSOLUTE_URL_HTTPS,
                    'walawalabingbang',
                    'urlWithScheme'
                ],
                'www-replace' => [
                    self::ABSOLUTE_URL_HTTPS_WWW,
                    self::ABSOLUTE_URL_HTTPS_WWW,
                    'https',
                    'urlWithScheme'
                ],
                'no-change-needed' => [
                     self::ABSOLUTE_URL_HTTPS,
                    self::ABSOLUTE_URL_HTTPS,
                    'https',
                    'urlWithScheme'
                ],
                'ftp-https' => [
                    str_replace('https://', 'sftp://', self::ABSOLUTE_URL_HTTPS_WWW),
                    self::ABSOLUTE_URL_HTTPS_WWW,
                    'sftp',
                    'urlWithScheme'
            ],
        ];
    }

    /**
     * @dataProvider urlFunctionDataProvider
     *
     * @param             $result
     * @param string      $path
     * @param null        $params
     * @param string|null $scheme
     * @param bool|null   $showScriptName
     */
    public function testUrlFunction($result, string $path = '', $params = null, string $scheme = null, bool $showScriptName = null, bool $isNonCompletedUrl = false)
    {
        if ($isNonCompletedUrl === true || !UrlHelper::isAbsoluteUrl($result)) {
            $oldResult = $result;
            $result = $this->baseUrl.$oldResult;

            $this->assertSame($result, UrlHelper::url($path, $params, $scheme, false));
            $result = $this->baseUrlWithScript.'?p='.$oldResult;
        }

        // If no scheme was passed in. We need to set the result to whatever the the url() function will use aswell.
        if ($scheme === null) {
            $scheme = !\Craft::$app->getRequest()->getIsConsoleRequest() && \Craft::$app->getRequest()->getIsSecureConnection() ? 'https' : 'http';
            $result = $this->urlWithScheme($result, $scheme);
        }

        $this->assertSame($result, UrlHelper::url($path, $params, $scheme, $showScriptName));
    }

    public function urlFunctionDataProvider()
    {
        return [
            'base' => ['endpoint', 'endpoint',  null,  null, null, true],
            'full-url-scheme' => [self::ABSOLUTE_URL_HTTPS, self::ABSOLUTE_URL,  null,  'https'],
            'full-url-scheme' => [self::ABSOLUTE_URL_HTTPS, self::ABSOLUTE_URL,  null,  'https'],
            'scheme-override' => [self::ABSOLUTE_URL_HTTPS, self::ABSOLUTE_URL,  null,  'https'],
            'scheme-override-param-add' => [
                self::ABSOLUTE_URL_HTTPS.'?param1=entry1&param2=entry2',
                self::ABSOLUTE_URL,
                ['param1'=> 'entry1', 'param2'=>'entry2'],
                'https'
            ],

        ];
    }

    /**
     * Replaces the http or https in a url to the $scheme variable.
     * @param $url
     * @param $scheme
     * @return string
     */
    public function urlWithScheme(string $url, string $scheme) : string
    {
        // Did they pass the $scheme in with :// or without? If no exists then add it.
        $scheme = strpos('://', $scheme) !== false ? $scheme : $scheme.'://';

        if (strpos($url, 'http://') !== false) {
            $url = str_replace('http://', $scheme, $url);
            return $url;
        }
        if (strpos($url, 'https://') !== false) {
            $url = str_replace('https://', $scheme, $url);
            return $url;
        }

        return $url;
    }

    /**
     * Tests that when a $scheme is not defined when creating a url.
     * It uses the below described method to determine the scheme type and adds this to a url.
     */
    public function testAutomaticProtocolType()
    {
        $schemeType = !\Craft::$app->getRequest()->getIsConsoleRequest() && \Craft::$app->getRequest()->getIsSecureConnection() ? 'https' : 'http';

        // Dont pass in a scheme type. Ensure it determines this itself.
        $result = UrlHelper::url('someendpoint');
        $conformsScheme = (strpos($result, $schemeType) !== false);
        $this->assertTrue($conformsScheme);
    }

    public function testBaseTesting()
    {
        $this->assertSame($this->baseUrl, UrlHelper::baseUrl());
        $this->assertSame($this->baseUrl, UrlHelper::baseSiteUrl());
        $this->assertSame(rtrim($this->baseUrl, '/'), UrlHelper::host());

        $this->assertSame('/', UrlHelper::baseCpUrl());
        $this->assertSame('/', UrlHelper::baseRequestUrl());
        $this->assertSame('', UrlHelper::cpHost());
    }

    public function testHostInfoRetrieval()
    {
        $this->assertSame('https://google.com', UrlHelper::hostInfo('https://google.com'));
        $this->assertSame('http://facebook.com', UrlHelper::hostInfo('http://facebook.com'));
        $this->assertSame('ftp://www.craftcms.com', UrlHelper::hostInfo('ftp://www.craftcms.com/why/craft/is/cool/'));
        $this->assertSame('walawalabingbang://gt.com', UrlHelper::hostInfo('walawalabingbang://gt.com/'));
        $this->assertSame('sftp://volkswagen', UrlHelper::hostInfo('sftp://volkswagen/2/2/2/2/2/2/2/2/2///222////222'));

        // If nothing is passed to the hostInfo() your mileage may vary depending on request type. So we need to know what to expect before hand..
        $expectedValue = \Craft::$app->getRequest()->getIsConsoleRequest() ? '' : \Craft::$app->getRequest()->getHostInfo();
        $this->assertSame($expectedValue, UrlHelper::hostInfo(''));
    }

    /**
     * @return bool
     * @throws \craft\errors\SiteNotFoundException
     */
    public function testGetSchemeForTokenUrl()
    {
        $this->assertTrue(in_array(UrlHelper::getSchemeForTokenizedUrl(), ['http', 'https']));

        // Run down the logic to see what we will need to require.
        $useSslOnTokenizedUrls = \Craft::$app->getConfig()->getGeneral()->useSslOnTokenizedUrls;

        // If they've explicitly set `useSslOnTokenizedUrls` to true, require https.
        if ($useSslOnTokenizedUrls === true) {
            $this->assertSame('https', UrlHelper::getSchemeForTokenizedUrl());
            return true;
        }

        // If they've explicitly set `useSslOnTokenizedUrls` to false, require http.
        if ($useSslOnTokenizedUrls === false) {
            $this->assertSame('http', UrlHelper::getSchemeForTokenizedUrl());
            return true;
        }

        // If the siteUrl is https or the current request is https, require https://.
        $scheme = parse_url(UrlHelper::baseSiteUrl(), PHP_URL_SCHEME);
        $request = \Craft::$app->getRequest();
        if (($scheme !== false && strtolower($scheme) === 'https') || (!$request->getIsConsoleRequest() && $request->getIsSecureConnection())) {
            $this->assertSame('https', UrlHelper::getSchemeForTokenizedUrl());
            return true;
        }

        $this->assertSame('http', UrlHelper::getSchemeForTokenizedUrl());
    }

    public function testSchemeForTokenizedBasedOnConfig()
    {
        // Run down the logic to see what we will need to require.
        $config =  \Craft::$app->getConfig()->getGeneral();

        $config->useSslOnTokenizedUrls = true;
        $this->assertSame('https', UrlHelper::getSchemeForTokenizedUrl());

        $config->useSslOnTokenizedUrls = false;
        $this->assertSame('http', UrlHelper::getSchemeForTokenizedUrl());
    }

    /**
     * @dataProvider siteUrlData
     * @param      $result
     * @param      $path
     * @param null $params
     * @param null $scheme
     * @param null $siteId
     */
    public function testSiteUrl($result, $path, $params = null, $scheme = null, $siteId = null)
    {
        $siteUrl = UrlHelper::siteUrl($path, $params, $scheme, $siteId);
        $this->assertSame($result, $siteUrl);
    }
    public function siteUrlData()
    {
        return [
            ['http://test.craftcms.dev/index.php?p=endpoint', 'endpoint'],
        ];
    }
    public function testSiteUrlExceptions()
    {
        $this->tester->expectThrowable(Exception::class, function () {
            UrlHelper::siteUrl('', null, null, 12892);
        });
    }
}