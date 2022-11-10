<?php

namespace SwipeStripe\Customer;

use SilverStripe\Dev\Debug;
use SilverStripe\Forms\ConfirmedPasswordField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SwipeStripe\Order\Order;
use SwipeStripe\Order\StandingOrder;

/**
 * Represents a {@link Customer}, a type of {@link Member}.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage customer
 */
class Customer extends Member {

	private static $table_name = 'Customer';

	private static $db = array(
		'Phone' => 'Text',
		'Code' => 'Int',
		'CurrentOrderID' => 'Int'
	);
	
	/**
	 * Link customers to {@link Address}es and {@link Order}s.
	 * 
	 * @var Array
	 */
	private static $has_many = array(
		'Orders' => Order::class
	);

	private static $searchable_fields = array(
		'Surname',
		'Email'
	);

	/**
	 * Get current order from stored order ID, but only if the order is a 
	 * cart or standing order belonging to the customer.
	 */
	public function getCurrentOrder(): ?Order
	{
		if (empty($this->CurrentOrderID)) {
			return null;
		}
		$orderIDs = $this->Orders(all: true)->filterAny([ 'Status' => 'Cart', 'ClassName' => StandingOrder::class ])->column('ID');
		if (!in_array($this->CurrentOrderID, $orderIDs)) {
			return null;
		}
		return Order::get()->byID($this->CurrentOrderID);
	}

	public function setCurrentOrder(Order|int $order): static
	{
		$this->CurrentOrderID = ($order instanceof Order) ? $order->ID : $order;
		return $this;
	}
	
	/**
	 * Prevent customers from being deleted.
	 * 
	 * @see Member::canDelete()
	 */
	public function canDelete($member = null) {
		$extended = $this->extendedCan(__FUNCTION__, $member);
		if($extended !== null) {
			return $extended;
		}
		$orders = $this->Orders();
		if ($orders && $orders->exists()) {
			return false;
		}
		return Permission::check('ADMIN', 'any', $member);
	}

	public function delete() {
		if ($this->canDelete(Security::getCurrentUser())) {
			parent::delete();
		}
	}

	function requireDefaultRecords() {
		parent::requireDefaultRecords();

		//Create a new group for customers
		$allGroups = DataObject::get(Group::class);
		$existingCustomerGroup = $allGroups->find('Title', 'Customers');
		if (!$existingCustomerGroup) {
			
			$customerGroup = new Group();
			$customerGroup->Title = 'Customers';
			$customerGroup->setCode($customerGroup->Title);
			$customerGroup->write();

			Permission::grant($customerGroup->ID, 'VIEW_ORDER');
		}
	}

	/**
	 * Add some fields for managing Members in the CMS.
	 * 
	 * @return FieldList
	 */
	public function getCMSFields() {

		$fields = new FieldList();

		$fields->push(new TabSet('Root', 
			Tab::create('Customer')
		));

		$password = new ConfirmedPasswordField(
			'Password', 
			null, 
			null, 
			null, 
			true // showOnClick
		);
		$password->setCanBeEmpty(true);
		if(!$this->ID) $password->showOnClick = false;

		$fields->addFieldsToTab('Root.Customer', array(
			new TextField('FirstName'),
			new TextField('Surname'),
			new EmailField('Email'),
			new TextField('Phone'),
			new ConfirmedPasswordField('Password'),
			$password
		));

		$this->extend('updateCMSFields', $fields);

		return $fields;
	}
	
	/**
	 * Overload getter to return only non-cart orders
	 * 
	 * @return DataList Set of previous orders for this member
	 */
	public function Orders(bool $all = false) 
	{
		$orders = Order::get()->filter('MemberID', $this->ID)->sort('"Created" DESC');
		if (!$all) {
			$orders->exclude('Status', 'Cart');
		}
		return $orders;
	}
	
	/**
	 * Returns the current logged in customer
	 *
	 * @return bool|Customer Returns the member object of the current logged in
	 *                     user or FALSE.
	 */
	public static function currentUser() {
		$member = Security::getCurrentUser();
		return $member ? Customer::get()->byID($member->ID) : false;
	}
}
