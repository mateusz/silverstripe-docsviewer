<?php

/**
 * Some of these tests are simply checking that pages load. They should not assume
 * somethings working.
 *
 * @package    docsviewer
 * @subpackage tests
 */

class DocumentationViewerTest extends FunctionalTest
{
    protected $autoFollowRedirection = false;
        
    protected $manifest;

    public function setUp()
    {
        parent::setUp();

        Config::nest();

        // explicitly use dev/docs. Custom paths should be tested separately 
        Config::inst()->update(
            'DocumentationViewer', 'link_base', 'dev/docs/'
        );

        // disable automatic module registration so modules don't interfere.
        Config::inst()->update(
            'DocumentationManifest', 'automatic_registration', false
        );

        Config::inst()->remove('DocumentationManifest', 'register_entities');

        Config::inst()->update(
            'DocumentationManifest', 'register_entities', array(
                array(
                    'Path' => DOCSVIEWER_PATH . "/tests/docs/",
                    'Title' => 'Doc Test',
                    'Version' => '2.3'
                ),
                array(
                    'Path' => DOCSVIEWER_PATH . "/tests/docs-v2.4/",
                    'Title' => 'Doc Test',
                    'Version' => '2.4',
                    'Stable' => true
                ),
                array(
                    'Path' => DOCSVIEWER_PATH . "/tests/docs-v3.0/",
                    'Title' => 'Doc Test',
                    'Version' => '3.0'
                ),
                array(
                    'Path' => DOCSVIEWER_PATH . "/tests/docs-parser/",
                    'Title' => 'DocumentationViewerAltModule1'
                ),
                array(
                    'Path' => DOCSVIEWER_PATH . "/tests/docs-search/",
                    'Title' => 'DocumentationViewerAltModule2'
                )
            )
        );

        Config::inst()->update('SSViewer', 'theme_enabled', false);

        $this->manifest = new DocumentationManifest(true);
    }
    
    public function tearDown()
    {
        parent::tearDown();
        
        Config::unnest();
    }

    /**
     * This tests that all the locations will exist if we access it via the urls.
     */
    public function testLocationsExists()
    {
        $this->autoFollowRedirection = false;

        $response = $this->get('dev/docs/en/doc_test/2.3/subfolder/');
        $this->assertEquals($response->getStatusCode(), 200, 'Existing base folder');

        $response = $this->get('dev/docs/en/doc_test/2.3/subfolder/subsubfolder/');
        $this->assertEquals($response->getStatusCode(), 200, 'Existing base folder');

        $response = $this->get('dev/docs/en/doc_test/2.3/subfolder/subsubfolder/subsubpage/');
        $this->assertEquals($response->getStatusCode(), 200, 'Existing base folder');

        $response = $this->get('dev/docs/en/');
        $this->assertEquals($response->getStatusCode(), 200, 'Lists the home index');

        $response = $this->get('dev/docs/');
        $this->assertEquals($response->getStatusCode(), 302, 'Go to english view');


        $response = $this->get('dev/docs/en/doc_test/3.0/empty.md');
        $this->assertEquals(301, $response->getStatusCode(), 'Direct markdown links also work. They should redirect to /empty/');
    

        // 2.4 is the stable release. Not in the URL
        $response = $this->get('dev/docs/en/doc_test/2.4');
        $this->assertEquals($response->getStatusCode(), 200, 'Existing base folder');
        $this->assertContains('english test', $response->getBody(), 'Toplevel content page');
        
        // accessing base redirects to the version with the version number.
        $response = $this->get('dev/docs/en/doc_test/');
        $this->assertEquals($response->getStatusCode(), 301, 'Existing base folder redirects to with version');

        $response = $this->get('dev/docs/en/doc_test/3.0/');
        $this->assertEquals($response->getStatusCode(), 200, 'Existing base folder');
        
        $response = $this->get('dev/docs/en/doc_test/2.3/nonexistant-subfolder');
        $this->assertEquals($response->getStatusCode(), 404, 'Nonexistant subfolder');
        
        $response = $this->get('dev/docs/en/doc_test/2.3/nonexistant-file.txt');
        $this->assertEquals($response->getStatusCode(), 301, 'Nonexistant file');

        $response = $this->get('dev/docs/en/doc_test/2.3/nonexistant-file/');
        $this->assertEquals($response->getStatusCode(), 404, 'Nonexistant file');
        
        $response = $this->get('dev/docs/en/doc_test/2.3/test');
        $this->assertEquals($response->getStatusCode(), 200, 'Existing file');
        
        $response = $this->get('dev/docs/en/doc_test/3.0/empty?foo');
        $this->assertEquals(200, $response->getStatusCode(), 'Existing page');
        
        $response = $this->get('dev/docs/en/doc_test/3.0/empty/');
        $this->assertEquals($response->getStatusCode(), 200, 'Existing page');
        
        $response = $this->get('dev/docs/en/doc_test/3.0/test');
        $this->assertEquals($response->getStatusCode(), 404, 'Missing page');
        
        $response = $this->get('dev/docs/en/doc_test/3.0/test.md');
        $this->assertEquals($response->getStatusCode(), 301, 'Missing page');
        
        $response = $this->get('dev/docs/en/doc_test/3.0/test/');
        $this->assertEquals($response->getStatusCode(), 404, 'Missing page');
        
        $response = $this->get('dev/docs/dk/');
        $this->assertEquals($response->getStatusCode(), 404, 'Access a language that doesn\'t exist');
    }


    public function testGetMenu()
    {
        $v = new DocumentationViewer();
        // check with children
        $response = $v->handleRequest(new SS_HTTPRequest('GET', 'en/doc_test/2.3/'), DataModel::inst());

        $expected = array(
            'dev/docs/en/doc_test/2.3/sort/' => 'Sort',
            'dev/docs/en/doc_test/2.3/subfolder/' => 'Subfolder',
            'dev/docs/en/doc_test/2.3/test/' => 'Test'
        );

        $actual = $v->getMenu()->first()->Children->map('Link', 'Title');
        $this->assertEquals($expected, $actual);


        $response = $v->handleRequest(new SS_HTTPRequest('GET', 'en/doc_test/2.4/'), DataModel::inst());
        $this->assertEquals('current', $v->getMenu()->first()->LinkingMode);

        // 2.4 stable release has 1 child page (not including index)
        $this->assertEquals(1, $v->getMenu()->first()->Children->count());

        // menu should contain all the english entities
        $expected = array(
            'dev/docs/en/doc_test/2.4/' => 'Doc Test',
            'dev/docs/en/documentationvieweraltmodule1/' => 'DocumentationViewerAltModule1',
            'dev/docs/en/documentationvieweraltmodule2/' => 'DocumentationViewerAltModule2'
        );

        $this->assertEquals($expected, $v->getMenu()->map('Link', 'Title'));
    }



    public function testGetLanguage()
    {
        $v = new DocumentationViewer();
        $response = $v->handleRequest(new SS_HTTPRequest('GET', 'en/doc_test/2.3/'), DataModel::inst());

        $this->assertEquals('en', $v->getLanguage());

        $response = $v->handleRequest(new SS_HTTPRequest('GET', 'en/doc_test/2.3/subfolder/subsubfolder/subsubpage/'), DataModel::inst());
        $this->assertEquals('en', $v->getLanguage());
    }
    

    public function testAccessingAll()
    {
        $response = $this->get('dev/docs/en/all/');

        // should response with the documentation index
        $this->assertEquals(200, $response->getStatusCode());

        $items = $this->cssParser()->getBySelector('#documentation_index');
        $this->assertNotEmpty($items);

        // should also have a DE version of the page
        $response = $this->get('dev/docs/de/all/');

        // should response with the documentation index
        $this->assertEquals(200, $response->getStatusCode());

        $items = $this->cssParser()->getBySelector('#documentation_index');
        $this->assertNotEmpty($items);

        // accessing a language that doesn't exist should throw a 404
        $response = $this->get('dev/docs/fu/all/');
        $this->assertEquals(404, $response->getStatusCode());

        // accessing all without a language should fail
        $response = $this->get('dev/docs/all/');
        $this->assertEquals(404, $response->getStatusCode());
    }


    public function testRedirectStripExtension()
    {
        // get url with .md extension
        $response = $this->get('dev/docs/en/doc_test/3.0/tutorials.md');

        // response should be a 301 redirect
        $this->assertEquals(301, $response->getStatusCode());

        // redirect should have been to the absolute url minus the .md extension
        $this->assertEquals(Director::absoluteURL('dev/docs/en/doc_test/3.0/tutorials/'), $response->getHeader('Location'));
    }
}
