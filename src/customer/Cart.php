<?php

namespace SwipeStripe\Customer;

use Psr\Log\LoggerInterface;
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
        $logger = Injector::inst()->get(LoggerInterface::class);

        $customer = Customer::currentUser();

        if (!empty($customer)) {
            // if logged in, check for session cart first (preserves items added while logged out)
            $sessionOrder = self::getOrderFromSession();
            $memberOrder = $customer->getCurrentOrder();

            // Prefer session cart if it has items, otherwise use member's stored cart
            if (!empty($sessionOrder) && $sessionOrder->Items()->exists()) {
                // Session cart has items - use it as current cart
                $order = $sessionOrder;

                // Only update if MemberID or CurrentOrderID need changing
                $needsOrderUpdate = ($order->MemberID != $customer->ID);
                $needsCustomerUpdate = ($customer->CurrentOrderID != $order->ID);

                if ($needsOrderUpdate) {
                    $order->MemberID = $customer->ID;
                    $order->write();
                }
                if ($needsCustomerUpdate) {
                    $customer->setCurrentOrder($order);
                    $customer->write();
                }

                if ($needsOrderUpdate || $needsCustomerUpdate) {
                    $logger->info('Migrated session cart with items to customer', [
                        'SessionOrder' => $order->ID,
                        'Customer' => $customer->ID,
                        'Items' => $order->Items()->count()
                    ]);
                }

                self::clearSessionCart();
            } else if (!empty($memberOrder)) {
                // Use existing member cart
                $order = $memberOrder;
            }
        } else {
            $order = self::getOrderFromSession();
        }

        // otherwise create a new one and return that
        if (empty($order) || !$order->exists()) {
            $order = Order::create();

            if ($persist) {
                $order->write();
                $logger->info('Created order', [$order->ID]);

                if (empty($customer)) {
                    self::saveOrderIntoSession($order);
                    $logger->info('Saved order to session', [$order->ID]);
                } else {
                    $order->MemberID = $customer->ID;
                    $order->write();
                    $customer->setCurrentOrder($order);
                    $customer->write();
                    $logger->info('Saved new order to customer', ['Order' => $order->ID, 'Customer' => $customer->ID]);
                }
            }
        }

        $order->updateTotal();

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
            $order = Order::get()->byID($orderID);
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

    /**
     * Clear session cart after migration to prevent repeated operations
     */
    protected static function clearSessionCart()
    {
        $request = Injector::inst()->get(HTTPRequest::class);
        $session = $request->getSession();
        $session->clear('Cart.OrderID');
    }
}
