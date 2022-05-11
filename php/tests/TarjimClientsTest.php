<?php

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use PHPUnit\Framework\TestCase;
App::import('Core', ['cake_session', 'View', 'Controller', 'Model', 'Router']);

class TarjimClientsTest extends TestCase {

  /**
   *
   */
  public function setUp(): void {

    ## project test1 on production
    $this->project_id = '17';
    $this->apikey = 'tarjim-17-1-acy3u23z';
    $this->default_namespace = 'default';
    $this->additional_namespaces = '';

    ## updateCache
    $this->Tarjim = new Tarjimclient($this->project_id, $this->apikey, $this->default_namespace, $this->additional_namespaces);
    $result = $this->Tarjim->getLatestFromTarjim();
    $this->Tarjim->updateCache($result);

    ## Insert test data
    $phinx = new PhinxApplication();
    $phinx->setAutoExit(false);
  }

  /**
   *
   */
  public function tearDown(): void {
    ## Delete test data
  }


  /**
   *
   */
  public function test_T() {
    $this->Tarjim->setTranslations('en');
    $expect = '<span data-tid=24993>welcome</span>';

    $result = _T('welcome');
    $this->assertEquals($expect, $result);

    $this->Tarjim->setTranslations('fr');
    $expect = '<span data-tid=24993>bienvenue</span>';

    $result = _T('welcome');
    $this->assertEquals($expect, $result);
  }

  /**
   *
   */
  public function test_TS() {
    $this->Tarjim->setTranslations('en');
    $expect = 'welcome';

    $result = _TS('welcome');
    $this->assertEquals($expect, $result);

    $this->Tarjim->setTranslations('fr');
    $expect = 'bienvenue';

    $result = _TS('welcome');
    $this->assertEquals($expect, $result);
  }

  /**
   *
   */
  public function testImageWithGoodSEO() {
    $this->Tarjim->setTranslations('en');
    $expect = 'src=https://d25b3ngygxsbuv.cloudfront.net/d804ab10-7427-4a50-80d0-c0f3bdb3418b.jpg data-tid=24994 title="pafco" alt="pafco"';

    $result = _TM('image_good_seo',['title'=> 'test', 'alt' => 'test']);
    $this->assertEquals($expect, $result);

    $this->Tarjim->setTranslations('fr');
    $expect = 'src=https://d25b3ngygxsbuv.cloudfront.net/fd31566e-a070-4a6f-adba-73b76fbf47f0.jpg data-tid=24994 title="pafco" alt="pafco"';

    $result = _TM('image_good_seo');
    $this->assertEquals($expect, $result);
  }

  /**
   *
   */
  public function testImageWithBadSEO() {
    $this->Tarjim->setTranslations('en');
    $expect = 'src=https://d25b3ngygxsbuv.cloudfront.net/3fb680f2-4efe-4cc0-b0e7-69660930f8f1.jpg data-tid=24995';

    $result = _TM('image_bad_seo');
    $this->assertEquals($expect, $result);

    $this->Tarjim->setTranslations('fr');
    $expect = 'src=https://d25b3ngygxsbuv.cloudfront.net/3fb680f2-4efe-4cc0-b0e7-69660930f8f1.jpg data-tid=24995';

    $result = _TM('image_bad_seo');
    $this->assertEquals($expect, $result);
  }

  /**
   *
   */
  public function test_TMT() {
    $this->Tarjim->setTranslations('en');
    $expect = '<meta property="og:title" content="title" /><meta property="og:description" content="description" /><meta property="og:site_name" content="test" /><meta property="og:url" content="test.com" /><meta property="og:image" content="https://tarjim.s3.amazonaws.com/b7a2aa40-2dcf-48ba-bcec-e89a93980365.jpeg" />';

    $result = _TMT('Open Graph');
    $this->assertEquals($expect, $result);

    $this->Tarjim->setTranslations('fr');
    $expect = '<meta property="og:title" content="description du titre" /><meta property="og:description" content="description" /><meta property="og:site_name" content="test" /><meta property="og:url" content="test.com" /><meta property="og:image" content="https://tarjim.s3.amazonaws.com/b7a2aa40-2dcf-48ba-bcec-e89a93980365.jpeg" />';

    $result = _TMT('Open Graph');
    $this->assertEquals($expect, $result);
  }

  /**
   *
   */
  public function test_TTT() {
    $this->Tarjim->setTranslations('en');
    $expect = '<title>title</title>';

    $result = _TTT('title');
    $this->assertEquals($expect, $result);

    $this->Tarjim->setTranslations('fr');
    $expect = '<title>title</title>';

    $result = _TTT('title');
    $this->assertEquals($expect, $result);
  }

  /**
   *
   */
  public function test_TMD() {
    $this->Tarjim->setTranslations('en');
    $expect = '<meta name="description" content="test description">';

    $result = _TMD('test description');
    $this->assertEquals($expect, $result);

    $this->Tarjim->setTranslations('fr');
    $expect = '<meta name="description" content="Description du test">';

    $result = _TMD('test description');
    $this->assertEquals($expect, $result);
  }

}

