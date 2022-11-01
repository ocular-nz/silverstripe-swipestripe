<?php

namespace SwipeStripe\Customer;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SwipeStripe\Order\Order;

/**
 * Extends {@link PageController} adding some functions to retrieve the current cart, 
 * and link to the cart.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage customer
 */
class Cart extends Extension
{

	/**
	 * Retrieve the current cart for display in the template.
	 * 
	 * @return Order The current order (cart)
	 */
	public function getCart()
	{
		$order = self::get_current_order();
		$order->Items();
		$order->Total;

		//HTTP::set_cache_age(0);
		return $order;
	}

	/**
	 * Convenience method to return links to cart related page.
	 * 
	 * @param String $type The type of cart page a link is needed for
	 * @return String The URL to the particular page
	 */
	function CartLink($type = 'Cart')
	{
		switch ($type) {
			case 'Account':
				if ($page = DataObject::get_one(AccountPage::class)) return $page->Link();
				else break;
			case 'Checkout':
				if ($page = DataObject::get_one(CheckoutPage::class)) return $page->Link();
				else break;
			case 'Login':
				return Director::absoluteBaseURL() . 'Security/login';
				break;
			case 'Logout':
				return Director::absoluteBaseURL() . 'Security/logout?BackURL=%2F';
				break;
			case 'Cart':
			default:
				if ($page = DataObject::get_one(CartPage::class)) return $page->Link();
				else break;
		}
	}

	/**
	 * Get the current order from the session, if order does not exist create a new one.
	 * 
	 * @return Order The current order (cart)
	 */
	public static function get_current_order($persist = false)
	{
		$customer = Customer::currentUser();

		if (!empty($customer)) {
			// if logged in, get current order from customer
			// this may be a cart or a standing order currently selected for editing
			$order = $customer->getCurrentOrder();

			if (empty($order)) {
				// if there's no order, attempt to migrate one from the session
				$order = self::getOrderFromSession();
				if (!empty($order)) {
					$customer->setCurrentOrder($order);
				}
			}
		} else {
			$order = self::getOrderFromSession();
		}

		// otherwise create a new one and return that
		if (empty($order) || !$order->exists()) {
			$order = Order::create();

			if ($persist) {
				$order->MemberID = $customer->ID;
				$order->write();
				if (empty($customer)) {
					self::saveOrderIntoSession($order);
				} else {
					$customer->setCurrentOrder($order);
					$customer->write();
				}
			}
		}

		return $order;
	}

	/**
	 * We only use the session when a logged in user is not present
	 * When logged in we use the customer's CurrentOrderID field instead
	 */
	protected static function saveOrderIntoSession(Order $order)
	{
		/** @var HTTPRequest $request */
		$request = Injector::inst()->get(HTTPRequest::class);
		$session = $request->getSession();
		$session->set('Cart', [
			'OrderID' => $order->ID
		]);
		$session->save($request);
	}

	/**
	 * We only use the session when a logged in user is not present
	 * When logged in we use the customer's CurrentOrderID field instead
	 */
	protected static function getOrderFromSession(): ?Order
	{
		/** @var HTTPRequest $request */
		$request = Injector::inst()->get(HTTPRequest::class);
		$session = $request->getSession();

		$orderID = $session->get('Cart.OrderID');
		$order = null;

		if ($orderID) {
			$order = DataObject::get_by_id(Order::class, $orderID);
		}

		return $order;
	}

	/**
	 * Updates timestamp LastActive on the order, called on every page request. 
	 */
	function onBeforeInit()
	{
		$request = Injector::inst()->get(HTTPRequest::class);
		$orderID = $request->getSession()->get('Cart.OrderID');
		if ($orderID && $order = DataObject::get_by_id(Order::class, $orderID)) {
			$order->LastActive = DBDatetime::now()->getValue();
			$order->write();
		}
	}
}
