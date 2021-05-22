<?php /** @noinspection PhpComposerExtensionStubsInspection */

use ComboStrap\TplUtility;

require_once(__DIR__ . '/../class/TplUtility.php');
require_once(__DIR__ . '/../class/DomUtility.php');

/**
 *
 * Test the {@link tpl_strap_meta_header()
 *
 * @group template_strap
 * @group templates
 */
class template_strap_script_test extends DokuWikiTest
{

    public function setUp()
    {

        global $conf;
        parent::setUp();
        $conf ['template'] = 'strap';

        /**
         * static variable bug in the {@link tpl_getConf()}
         * that does not load the configuration twice
         */
        TplUtility::reloadConf();

    }

    /**
     * An utility function that test if the headers meta are still
     * on the page (ie response)
     * @param TestResponse $response
     * @param $selector - the DOM elementselector
     * @param $attr - the attribute to check
     * @param $scriptSignatures - the pattern signature to find
     * @param string $testDescription - the login type (anonymous, logged in, ...)
     */
    private function checkMeta(TestResponse $response, $selector, $attr, $scriptSignatures, $testDescription)
    {


        /**
         * @var array|DomElement $scripts
         */
        $domElements = $response->queryHTML($selector)->get();

        $domValueToChecks = [];
        foreach ($domElements as $domElement) {
            /**
             * @var DOMElement $domElement
             */
            $value = $domElement->getAttribute($attr);
            if (empty($value)) {
                $value = $domElement->textContent;
            }
            $domValueToChecks[] = $value;
        }
        $domValueNotFounds = $domValueToChecks;
        foreach ($scriptSignatures as $signatureToFind) {
            $patternFound = 0;
            foreach ($domValueToChecks as $domValueToCheck) {
                $patternFound = preg_match("/$signatureToFind/i", $domValueToCheck);
                if ($patternFound === 1) {
                    if (($key = array_search($domValueToCheck, $domValueNotFounds)) !== false) {
                        unset($domValueNotFounds[$key]);
                    }
                    break;
                }
            }
            $this->assertTrue($patternFound !== 0, "Unable to find ($signatureToFind) for ${testDescription}");
        }


        foreach ($domValueNotFounds as $domValueNotFound) {
            $this->assertNull($domValueNotFound, "All selected element have been found by a signature, for ($selector) on ${testDescription}");
        }


    }


    /**
     * Test the default configuration
     *
     * Test the {@link \Combostrap\TplUtility::handleBootstrapMetaHeaders()} function
     */
    public function test_handleBootStrapMetaHeaders_anonymous_default()
    {

        $bootstrapStylesheetVersions = ["5.0.1 - bootstrap", "4.5.0 - bootstrap"];

        foreach ($bootstrapStylesheetVersions as $bootstrapStylesheetVersion) {
            TplUtility::setConf(TplUtility::CONF_BOOTSTRAP_VERSION_STYLESHEET, $bootstrapStylesheetVersion);

            $version = TplUtility::getBootStrapVersion();
            if ($version == "4.5.0") {
                /**
                 * Script signature
                 * CDN is on by default
                 *
                 * js.php is needed for custom script such as a consent box
                 */
                $scriptsSignature = [
                    "jquery.com\/jquery-(.*).js",
                    "cdn.jsdelivr.net\/npm\/popper.js",
                    "stackpath.bootstrapcdn.com\/bootstrap\/$version\/js\/bootstrap.min.js",
                    'JSINFO',
                    'js.php'
                ];

                /**
                 * Stylesheet signature (href)
                 */
                $stylsheetSignature = ["stackpath.bootstrapcdn.com\/bootstrap\/$version\/css\/bootstrap.min.css", '\/lib\/exe\/css.php\?t\=strap'];

            } else {

                $scriptsSignature = [
                    //    "jquery.com\/jquery-(.*).js", no more need in Bootstrap 5
                    // "cdn.jsdelivr.net\/npm\/popper.js", in the bundle below
                    "cdn.jsdelivr.net\/npm\/bootstrap\@$version\/dist\/js\/bootstrap.bundle.min.js",
                    'JSINFO',
                    'js.php'
                ];

                /**
                 * Stylesheet signature (href)
                 */
                $stylsheetSignature = [
                    "cdn.jsdelivr.net\/npm\/bootstrap\@$version\/dist\/css\/bootstrap.min.css",
                    '\/lib\/exe\/css.php\?t\=strap'
                ];

            }

            // Anonymous
            $pageId = 'start';
            saveWikiText($pageId, "Content", 'Script Test base');
            idx_addPage($pageId);

            $request = new TestRequest();
            $response = $request->get(array('id' => $pageId, '/doku.php'));

            $cdn = tpl_getConf(TplUtility::CONF_USE_CDN);
            $this->assertEquals(1, $cdn, "The CDN is by default on on version $bootstrapStylesheetVersion");

            /**
             * Meta script test
             */
            $testDescription = "Anonymous on version ($bootstrapStylesheetVersion)";
            $this->checkMeta($response, 'script', "src", $scriptsSignature, $testDescription);
            /**
             * Meta stylesheet test
             */
            $this->checkMeta($response, 'link[rel="stylesheet"]', "href", $stylsheetSignature, $testDescription);
        }


    }

    /**
     * Test the Jquery conf
     *
     * Test the {@link \Combostrap\TplUtility::handleBootstrapMetaHeaders()} function
     * @throws Exception
     */
    public function test_handleBootStrapMetaHeaders_anonymous_jquery_doku()
    {

        /**
         * Jquery is off by default, enable
         */
        $jqueryUI = tpl_getConf(TplUtility::CONF_JQUERY_DOKU);
        $this->assertEquals(0, $jqueryUI, "jquery is off");
        TplUtility::setConf(TplUtility::CONF_JQUERY_DOKU, 1);
        $jqueryUI = tpl_getConf(TplUtility::CONF_JQUERY_DOKU);
        $this->assertEquals(1, $jqueryUI, "jquery is on");

        // Anonymous
        $pageId = 'start';
        saveWikiText($pageId, "Content", 'Script Test base');
        idx_addPage($pageId);

        $request = new TestRequest();
        $response = $request->get(array('id' => $pageId, '/doku.php'));


        /**
         * Script signature
         * CDN is on by default
         *
         * js.php is needed for custom script such as a consent box
         */
        $version = TplUtility::getBootStrapVersion();
        $scriptsSignature = ["jquery.php", "cdn.jsdelivr.net\/npm\/popper.js", "stackpath.bootstrapcdn.com\/bootstrap\/$version\/js\/bootstrap.min.js", 'JSINFO', 'js.php'];
        $this->checkMeta($response, 'script', "src", $scriptsSignature, "Anonymous");

        /**
         * Stylesheet signature (href)
         */
        $stylsheetSignature = ["stackpath.bootstrapcdn.com\/bootstrap\/$version\/css\/bootstrap.min.css", '\/lib\/exe\/css.php\?t\=strap'];
        $this->checkMeta($response, 'link[rel="stylesheet"]', "href", $stylsheetSignature, "Anonymous");


    }

    /**
     * @throws Exception
     */
    public function test_handleBootStrapMetaHeaders_anonymous_nocdn()
    {

        /**
         * CDN is on by default, disable
         */
        TplUtility::setConf(TplUtility::CONF_USE_CDN, 0);

        // Anonymous
        $pageId = 'start';
        saveWikiText($pageId, "Content", 'Script Test base');
        idx_addPage($pageId);

        $request = new TestRequest();
        $response = $request->get(array('id' => $pageId, '/doku.php'));

        /**
         * Script signature
         */
        $version = tpl_getConf('bootstrapVersion');
        $localDirPattern = '\/lib\/tpl\/strap\/bootstrap\/' . $version;
        $scriptsSignature = ["$localDirPattern\/jquery-(.*).js", "$localDirPattern\/popper.min.js", "$localDirPattern\/bootstrap.min.js", 'JSINFO', 'js.php'];
        $this->checkMeta($response, 'script', "src", $scriptsSignature, "Anonymous");

        /**
         * Stylesheet signature (href)
         */
        $stylsheetSignature = ["$localDirPattern\/bootstrap.min.css", '\/lib\/exe\/css.php\?t\=strap'];
        $this->checkMeta($response, 'link[rel="stylesheet"]', "href", $stylsheetSignature, "Anonymous");

    }


    /**
     * When a user is logged in, the CDN is no more
     */
    public function test_handleBootStrapMetaHeaders_loggedin_default()
    {

        $pageId = 'start';
        saveWikiText($pageId, "Content", 'Script Test base');
        idx_addPage($pageId);
        // Log in
        global $conf;
        $conf['useacl'] = 1;
        $user = 'admin';
        $conf['superuser'] = $user;
        $request = new TestRequest();
        $request->setServer('REMOTE_USER', $user);
        $response = $request->get(array('id' => $pageId, '/doku.php'));

        /**
         * No Css preloading
         */
        $stylesheets = $response->queryHTML('link[rel="preload"]')->get();
        $this->assertEquals(0, sizeof($stylesheets));

        /**
         * Script signature
         */
        $version = tpl_getConf('bootstrapVersion');

        $scriptsSignature = ["jquery.php", "cdn.jsdelivr.net\/npm\/popper.js", "stackpath.bootstrapcdn.com\/bootstrap\/$version\/js\/bootstrap.min.js", 'JSINFO', 'js.php'];
        $this->checkMeta($response, 'script', "src", $scriptsSignature, "Logged in");

        /**
         * Stylesheet signature (href)
         */
        $stylsheetSignature = ["stackpath.bootstrapcdn.com\/bootstrap\/$version\/css\/bootstrap.min.css", '\/lib\/exe\/css.php\?t\=strap'];
        $this->checkMeta($response, 'link[rel="stylesheet"]', "href", $stylsheetSignature, "Logged in");


    }

    /**
     * test the css preload configuration
     *
     * @throws Exception
     */
    public function test_css_preload_anonymous()
    {

        TplUtility::setConf('preloadCss', 1);

        $pageId = 'start';
        saveWikiText($pageId, "Content", 'Script Test base');
        idx_addPage($pageId);

        $request = new TestRequest();
        $response = $request->get(array('id' => $pageId, '/doku.php'));

        $stylesheets = $response->queryHTML('link[rel="preload"]')->get();


        $node = array();
        foreach ($stylesheets as $key => $stylesheet) {
            if ($stylesheet->hasAttributes()) {
                foreach ($stylesheet->attributes as $attr) {
                    $name = $attr->name;
                    $value = $attr->value;
                    $node[$key][$name] = $value;
                }
            }
        }

        $this->assertEquals(2, sizeof($node), "The stylesheet count should be 2");

        $version = TplUtility::getBootStrapVersion();
        $stylsheetSignature = ["stackpath.bootstrapcdn.com\/bootstrap\/$version\/css\/bootstrap.min.css", '\/lib\/exe\/css.php\?t\=strap'];
        $this->checkMeta($response, 'link[rel="stylesheet"]', "href", $stylsheetSignature, "Anonymous");


    }


    /**
     * Test the {@link \Combostrap\TplUtility::getBootstrapMetaHeaders()} function
     * with default conf
     * @throws Exception
     */
    public function test_getBootstrapMetaHeaders()
    {
        $metas = TplUtility::getBootstrapMetaHeaders();
        $this->assertEquals(2, sizeof($metas));

        $this->assertEquals(3, sizeof($metas['script']), "There is three js script");
        $this->assertEquals(1, sizeof($metas['link']), "There is one css script");

    }

    /**
     * Test the {@link \Combostrap\TplUtility::getBootstrapMetaHeaders()} function
     * with bootswatch stylesheet and cdn (default)
     * @throws Exception
     */
    public function test_getBootstrapMetaHeadersWithCustomStyleSheet()
    {
        TplUtility::setConf(TplUtility::CONF_BOOTSTRAP_STYLESHEET, "bootstrap.simplex");
        $metas = TplUtility::getBootstrapMetaHeaders();
        $this->assertEquals(2, sizeof($metas));

        $this->assertEquals(3, sizeof($metas['script']), "There is three js script");
        $this->assertEquals(1, sizeof($metas['link']), "There is one css script");
        $this->assertEquals("https://cdn.jsdelivr.net/npm/bootswatch@4.5.0/dist/simplex/bootstrap.min.css", $metas['link']['css']['href'], "The href is the cdn");

    }


    /**
     * Test that a detail page is rendering
     */
    public function test_favicon()
    {
        $pageId = 'start';
        saveWikiText($pageId, "Content", 'Script Test base');
        idx_addPage($pageId);

        $request = new TestRequest();
        $response = $request->get(array('id' => $pageId));

        $generator = $response->queryHTML('link[rel="shortcut icon"]')->count();
        $this->assertEquals(1, $generator);

    }

    /**
     * Test that a media page is rendering
     */
    public function test_media_manager_php()
    {
        $pageId = 'start';
        saveWikiText($pageId, "Content", 'Script Test base');
        idx_addPage($pageId);

        $request = new TestRequest();
        $response = $request->get(array('id' => $pageId, '/mediamanager.php'));

        $generator = $response->queryHTML('meta[name="generator"]')->attr("content");
        $this->assertEquals("DokuWiki", $generator);

    }

    /**
     * Test that a toolbar is not shown when it's private
     * @throws Exception
     */
    public function test_privateToolbar()
    {
        TplUtility::setConf('privateToolbar', 0);

        $pageId = 'start';
        saveWikiText($pageId, "Content", 'Script Test base');
        idx_addPage($pageId);

        $request = new TestRequest();
        $response = $request->get(array('id' => $pageId, '/doku.php'));

        $toolbarCount = $response->queryHTML('#dokuwiki__pagetools')->count();
        $this->assertEquals(1, $toolbarCount);

        // Anonymous user should not see it
        TplUtility::setConf('privateToolbar', 1);
        $request = new TestRequest();
        $response = $request->get(array('id' => $pageId, '/doku.php'));
        $toolbarCount = $response->queryHTML('#dokuwiki__pagetools')->count();
        $this->assertEquals(0, $toolbarCount);

        // Connected user should see it
        $request = new TestRequest();
        $request->setServer('REMOTE_USER', 'auser');
        $response = $request->get(array('id' => $pageId, '/doku.php'));
        $toolbarCount = $response->queryHTML('#dokuwiki__pagetools')->count();
        $this->assertEquals(1, $toolbarCount);

    }


}
