<?php
/**
 * @package cms
 * @subpackage tests
 */
class LeftAndMainTest extends FunctionalTest {
	static $fixture_file = 'cms/tests/CMSMainTest.yml';
	
	function setUp() {
		parent::setUp();
		
		// @todo fix controller stack problems and re-activate
		//$this->autoFollowRedirection = false;
		CMSMenu::populate_menu();
	}
	
	public function testSaveTreeNodeSorting() {	
		$this->loginWithPermission('ADMIN');
		
		$rootPages = DataObject::get('SiteTree', '"ParentID" = 0'); // implicitly sorted
		$siblingIDs = $rootPages->column('ID');
		$page1 = $rootPages->offsetGet(0);
		$page2 = $rootPages->offsetGet(1);
		$page3 = $rootPages->offsetGet(2);
		
		// Move page2 before page1
		$siblingIDs[0] = $page2->ID;
		$siblingIDs[1] = $page1->ID;
		$data = array(
			'SiblingIDs' => $siblingIDs,
			'ID' => $page2->ID,
			'ParentID' => 0
		);

		$response = $this->post('admin/savetreenode', $data);
		$this->assertEquals(200, $response->getStatusCode());
		$page1 = DataObject::get_by_id('SiteTree', $page1->ID, false);
		$page2 = DataObject::get_by_id('SiteTree', $page2->ID, false);
		$page3 = DataObject::get_by_id('SiteTree', $page3->ID, false);
		
		$this->assertEquals(2, $page1->Sort, 'Page1 is sorted after Page2');
		$this->assertEquals(1, $page2->Sort, 'Page2 is sorted before Page1');
		$this->assertEquals(3, $page3->Sort, 'Sort order for other pages is unaffected');
	}
	
	public function testSaveTreeNodeParentID() {
		$this->loginWithPermission('ADMIN');

		$page1 = $this->objFromFixture('Page', 'page1');
		$page2 = $this->objFromFixture('Page', 'page2');
		$page3 = $this->objFromFixture('Page', 'page3');
		$page31 = $this->objFromFixture('Page', 'page31');
		$page32 = $this->objFromFixture('Page', 'page32');

		// Move page2 into page3, between page3.1 and page 3.2
		$siblingIDs = array(
			$page31->ID,
			$page2->ID,
			$page32->ID
		);
		$data = array(
			'SiblingIDs' => $siblingIDs,
			'ID' => $page2->ID,
			'ParentID' => $page3->ID
		);
		$response = $this->post('admin/savetreenode', $data);
		$this->assertEquals(200, $response->getStatusCode());
		$page2 = DataObject::get_by_id('SiteTree', $page2->ID, false);
		$page31 = DataObject::get_by_id('SiteTree', $page31->ID, false);
		$page32 = DataObject::get_by_id('SiteTree', $page32->ID, false);

		$this->assertEquals($page3->ID, $page2->ParentID, 'Moved page gets new parent');
		$this->assertEquals(1, $page31->Sort, 'Children pages before insertaion are unaffected');
		$this->assertEquals(2, $page2->Sort, 'Moved page is correctly sorted');
		$this->assertEquals(3, $page32->Sort, 'Children pages after insertion are resorted');
	}
	
	/**
	 * Test that CMS versions can be interpreted appropriately
	 */
	public function testCMSVersion() {
		$l = new LeftAndMain();
		$this->assertEquals("2.4", $l->versionFromVersionFile(
			'$URL: http://svn.silverstripe.com/open/modules/cms/branches/2.4/silverstripe_version $'));
		$this->assertEquals("2.2.0", $l->versionFromVersionFile(
			'$URL: http://svn.silverstripe.com/open/modules/cms/tags/2.2.0/silverstripe_version $'));
		$this->assertEquals("trunk", $l->versionFromVersionFile(
			'$URL: http://svn.silverstripe.com/open/modules/cms/trunk/silverstripe_version $'));
		$this->assertEquals("2.4.0-alpha1", $l->versionFromVersionFile(
			'$URL: http://svn.silverstripe.com/open/modules/cms/tags/alpha/2.4.0-alpha1/silverstripe_version $'));
		$this->assertEquals("2.4.0-beta1", $l->versionFromVersionFile(
			'$URL: http://svn.silverstripe.com/open/modules/cms/tags/beta/2.4.0-beta1/silverstripe_version $'));
		$this->assertEquals("2.4.0-rc1", $l->versionFromVersionFile(
			'$URL: http://svn.silverstripe.com/open/modules/cms/tags/rc/2.4.0-rc1/silverstripe_version $'));
	}
	
	/**
	 * Check that all subclasses of leftandmain can be accessed
	 */
	public function testLeftAndMainSubclasses() {
		$adminuser = $this->objFromFixture('Member','admin');
		$this->session()->inst_set('loggedInAs', $adminuser->ID);
		
		$menuItems = singleton('CMSMain')->MainMenu();
		foreach($menuItems as $menuItem) {
			$link = $menuItem->Link;
			
			// don't test external links
			if(preg_match('/^https?:\/\//',$link)) continue;

			$response = $this->get($link);
			
			$this->assertInstanceOf('SS_HTTPResponse', $response, "$link should return a response object");
			$this->assertEquals(200, $response->getStatusCode(), "$link should return 200 status code");
			// Check that a HTML page has been returned
			$this->assertRegExp('/<html[^>]*>/i', $response->getBody(), "$link should contain <html> tag");
			$this->assertRegExp('/<head[^>]*>/i', $response->getBody(), "$link should contain <head> tag");
			$this->assertRegExp('/<body[^>]*>/i', $response->getBody(), "$link should contain <body> tag");
		}
		
		$this->session()->inst_set('loggedInAs', null);

	}

	function testCanView() {
		$adminuser = $this->objFromFixture('Member', 'admin');
		$assetsonlyuser = $this->objFromFixture('Member', 'assetsonlyuser');
		$allcmssectionsuser = $this->objFromFixture('Member', 'allcmssectionsuser');
		
		// anonymous user
		$this->session()->inst_set('loggedInAs', null);
		$menuItems = singleton('LeftAndMain')->MainMenu();
		$this->assertEquals(
			$menuItems->column('Code'),
			array(),
			'Without valid login, members cant access any menu entries'
		);
		
		// restricted cms user
		$this->session()->inst_set('loggedInAs', $assetsonlyuser->ID);
		$menuItems = singleton('LeftAndMain')->MainMenu();
		$this->assertEquals(
			$menuItems->column('Code'),
			array('AssetAdmin','Help'),
			'Groups with limited access can only access the interfaces they have permissions for'
		);
		
		// all cms sections user
		$this->session()->inst_set('loggedInAs', $allcmssectionsuser->ID);
		$menuItems = singleton('LeftAndMain')->MainMenu();
		$requiredSections = array('CMSMain','AssetAdmin','SecurityAdmin','Help');
		$this->assertEquals(
			array_diff($requiredSections, $menuItems->column('Code')),
			array(),
			'Group with CMS_ACCESS_LeftAndMain permission can access all sections'
		);
		
		// admin
		$this->session()->inst_set('loggedInAs', $adminuser->ID);
		$menuItems = singleton('LeftAndMain')->MainMenu();
		$this->assertContains(
			'CMSMain',
			$menuItems->column('Code'),
			'Administrators can access CMS'
		);
		$this->assertContains(
			'AssetAdmin',
			$menuItems->column('Code'),
			'Administrators can access Assets'
		);
		
		$this->session()->inst_set('loggedInAs', null);
	}
	
}

