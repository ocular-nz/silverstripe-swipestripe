<?php
namespace SwipeStripe;

use SwipeStripe\Order\Order;
use SwipeStripe\Customer\AccountPage;
use SwipeStripe\Customer\Customer;

/**
 * Testing {@link Product} attributes and options on product pages.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage tests
 */
class SWS_AccountTest extends \SWS_Test {
	
	public function setUp() {
		parent::setUp();
		
		$this->loginAs('admin');
		$this->objFromFixture(AccountPage::class, 'account')->doPublish();
		$this->logOut();
	}

	public function testCustomerCanViewAccount() {

		$buyer = $this->objFromFixture('Customer', 'buyer');
		$accountPage = $this->objFromFixture(AccountPage::class, 'account');

		$this->loginAs($buyer);
		$this->get(Director::makeRelative($accountPage->Link()));
		$this->assertPartialMatchBySelector('h2', array(
			'Account Page'
		));
		$this->logOut();
	}

	public function testAdminCanViewAccount() {

		$accountPage = $this->objFromFixture(AccountPage::class, 'account');

		$this->loginAs('admin');
		$this->get(Director::makeRelative($accountPage->Link()));
		$this->assertPartialMatchBySelector('h2', array(
			'Account Page'
		));
		$this->logOut();
	}

	public function testAnonCannotViewAccount() {

		$accountPage = $this->objFromFixture(AccountPage::class, 'account');

		$this->get(Director::makeRelative($accountPage->Link()));
		$this->assertPartialMatchBySelector('h1', array(
			'Log in'
		));
	}

	public function testCustomerCanViewOrder() {

		$buyer = $this->objFromFixture(Customer::class, 'buyer');
		$order = $this->objFromFixture(Order::class, 'orderOne');

		$this->loginAs($buyer);
		$this->get(Director::makeRelative($order->Link()));
		$this->assertTrue($this->Content() != "Action 'order' isn't allowed on class AccountPageController");
		$this->logOut();
	}
	
	public function testCustomerCannotViewOrder() {
		
		$buyer = $this->objFromFixture('Customer', 'buyer2');
		$order = $this->objFromFixture('Order', 'orderOne');

		$this->loginAs($buyer);
		$this->get(Director::makeRelative($order->Link()));
		$this->assertTrue($this->Content() == "You cannot view orders that do not belong to you.");
		$this->logOut();
	}

	public function testAdminCanViewOrder() {

		$order = $this->objFromFixture('Order', 'orderOne');

		$this->loginAs('admin');
		$this->get(Director::makeRelative($order->Link()));
		$this->assertTrue($this->Content() != "Action 'order' isn't allowed on class AccountPageController");
		$this->logOut();
	}

	public function testAnonCannotViewOrder() {

		$order = $this->objFromFixture('Order', 'orderOne');

		$this->get(Director::makeRelative($order->Link()));
		$this->assertPartialMatchBySelector('h1', array(
			'Log in'
		));
	}
	
}