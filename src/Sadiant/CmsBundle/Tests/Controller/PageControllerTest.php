<?php

namespace Sadiant\CmsBundle\Tests\Controller;

require_once __DIR__.'/../../../../../app/AppKernel.php';

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Sadiant\CmsBundle\Repository\PageRepository;

class PageControllerTest extends WebTestCase
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    public function __construct()
    {
        // Load and boot kernel
        $kernel = new \AppKernel('test', true);
        $kernel->boot();

        // Load "test" entity manager
        $this->em = $kernel->getContainer()->get('doctrine')->getEntityManager('test');
    }

    /**
     * Test index action
     *
     * @author Vincent Guillon <vincentg@theodo.fr>
     * @since 2011-06-17
     */
    public function testIndex()
    {
        $client = $this->createClient();
        $crawler = $client->request('GET', '/admin');

        print_r("\n> Test page index action");

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertRegexp('/.*Homepage.*/', $client->getResponse()->getContent());
        $this->assertRegexp('/.*About.*/', $client->getResponse()->getContent());
        $this->assertRegexp('/.*Theodo.*/', $client->getResponse()->getContent());
        $this->assertRegexp('/.*Published.*/', $client->getResponse()->getContent());
    }

    /**
     * Test new action
     *
     * @author Vincent Guillon <vincentg@theodo.fr>
     * @since 2011-06-22
     */
    public function testNew()
    {
        $client = $this->createClient();
        $crawler = $client->request('GET', '/admin/pages/1/new');

        print_r("\n> Test page new action");

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertRegexp('/.*New page.*/', $client->getResponse()->getContent());
    }
    
    /**
     * Test edit action
     *
     * @author Vincent Guillon <vincentg@theodo.fr>
     * @since 2011-06-22
     */
    public function testEdit()
    {
        $client = $this->createClient();
        $crawler = $client->request('GET', '/admin/pages/1/edit');

        print_r("\n> Test page edit action");

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertRegexp('/.*Edit page.*/', $client->getResponse()->getContent());
    }
    
    /**
     * Test update action
     *
     * @author Vincent Guillon <vincentg@theodo.fr>
     * @since 2011-06-22
     */
    public function testUpdate()
    {
        print_r("\n> Test page update action");
        
        $client = $this->createClient();
        $crawler = $client->request('GET', '/admin/pages/1/update');

        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $client->followRedirect();
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertRegexp('/.*Edit page.*/', $client->getResponse()->getContent());
    }

    /**
     * Test workflow
     *
     * @author Vincent Guillon <vincentg@theodo.fr>
     * @since 2011-06-22
     */
    public function testWorkflow()
    {
        print_r("\n> Test page workflow");
        
        // Start transaction
        $this->em->getConnection()->beginTransaction();
        
        $client = $this->createClient();
        $crawler = $client->request('GET', '/admin');

        // Test status
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Retrieve "Add child" link and click
        $link = $crawler->filterXPath('//a[@id="new-'.PageRepository::SLUG_HOMEPAGE.'-child"]')->link();
        $crawler = $client->click($link);
        
        // Test status
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        
        // Test page content
        $this->assertRegexp('/.*New page.*/', $client->getResponse()->getContent());
        $this->assertRegexp('/.*admin\/pages\/.*\/new$/', $client->getRequest()->getUri());

        // Retrieve form
        $form = $crawler->filterXPath('//input[@type="submit"]')->form();
        
        // Submit form with errors
        $crawler = $client->submit($form, array());
        $crawler = $client->request('POST', $form->getUri());
        
        // Test return
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertRegexp('/.*admin\/pages\/.*\/new$/', $client->getRequest()->getUri());
        $this->assertRegexp('/.*New page.*/', $client->getResponse()->getContent());
        $this->assertRegexp('/.*This value should not be blank.*/', $client->getResponse()->getContent());
        
        // Submit form with error
        $crawler = $client->submit($form, array( 
            'page[parent_id]'  => '2222',
            'page[name]'       => 'Functional test',
            'page[slug]'       => 'functional-test',
            'page[breadcrumb]' => 'Functional test',
            'page[content]'    => '<p>Functional test page content</p>',
            'page[status]'     => PageRepository::STATUS_PUBLISH
        ));
        
        // Test return
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertRegexp('/.*admin\/pages\/.*\/new$/', $client->getRequest()->getUri());
        $this->assertRegexp('/.*New page.*/', $client->getResponse()->getContent());
        $this->assertRegexp('/.*&quot;id&quot; &quot;2222&quot; does&#039;nt exists.*/', $client->getResponse()->getContent());
        
        // Submit valid form
        $crawler = $client->submit($form, array( 
            'page[parent_id]'  => $this->em->getRepository('SadiantCmsBundle:Page')->findOneBy(array('slug' => PageRepository::SLUG_HOMEPAGE))->getId(),
            'page[name]'       => 'Functional test',
            'page[slug]'       => 'functional-test',
            'page[breadcrumb]' => 'Functional test',
            'page[content]'    => '<p>Functional test page content</p>',
            'page[status]'     => PageRepository::STATUS_PUBLISH
        ));

        // Test return
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $crawler = $client->followRedirect();
        $this->assertRegexp('/.*admin\/pages\/.*\/edit$/', $client->getRequest()->getUri());
        $this->assertRegexp('/.*Edit page.*/', $client->getResponse()->getContent());
        $this->assertRegexp('/.*Functional test.*/', $client->getResponse()->getContent());
        
        // Update Form
        $form = $crawler->filterXPath('//input[@type="submit"]')->form();
        $form['page[published_at][year]']  = date('Y');
        $form['page[published_at][month]'] = date('m');
        $form['page[published_at][day]']   = date('d');

        // Submit the form
        $crawler = $client->submit($form);
        
        // Test return
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $crawler = $client->followRedirect();
        $this->assertRegexp('/.*admin\/pages\/.*\/edit$/', $client->getRequest()->getUri());
        $this->assertRegexp('/.*Edit page.*/', $client->getResponse()->getContent());
        $this->assertRegexp('/.*Functional test.*/', $client->getResponse()->getContent());
        
        // Back to admin homepage
        $crawler = $client->request('GET', '/admin');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertRegexp('/.*Functional test.*/', $client->getResponse()->getContent());

        $this->em->getConnection()->rollBack();
    }
}
