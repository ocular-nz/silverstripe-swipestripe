<?php

namespace SwipeStripe\Emails;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Member;
use SwipeStripe\Admin\ShopConfig;
use SwipeStripe\Order\Order;

/**
 * A receipt email that is sent to the customer after they have completed their {@link Order}.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage emails
 */
class ReceiptEmail extends ProcessedEmail
{

	/**
	 * Create the new receipt email.
	 * 
	 * @param Member $customer
	 * @param Order $order
	 * @param String $from
	 * @param String $to
	 * @param String $subject
	 * @param String $body
	 * @param String $bounceHandlerURL
	 * @param String $cc
	 * @param String $bcc
	 */
	public function __construct(Member $customer, Order $order, $from = null, $to = null, $subject = null, $body = null, $bounceHandlerURL = null, $cc = null, $bcc = null)
	{

		$siteConfig = ShopConfig::get()->first();
		if ($customer->Email) {
			$this->to = $customer->Email;
		}
		if ($siteConfig->ReceiptSubject) {
			$this->subject = $siteConfig->ReceiptSubject . ' - Order #' . $order->ID;
		}
		if ($siteConfig->ReceiptBody) {
			$this->body = $siteConfig->ReceiptBody;
		}

		if ($order->IsStandingOrder()) {
			$this->subject = 'Your Standing Order from Commonsense Organics - ' . $order->CartName();
		}
		
		$adminEmail = Config::inst()->get(Email::class, 'admin_email');
		if ($siteConfig->ReceiptFrom) {
			$this->from = $siteConfig->ReceiptFrom;
		} elseif ($adminEmail) {
			$this->from = $adminEmail;
		} else {
			$this->from = 'no-reply@' . $_SERVER['HTTP_HOST'];
		}

		if ($siteConfig->EmailSignature) $this->signature = $siteConfig->EmailSignature;

		// Get css for Email by reading css file and put css inline for emogrification
		$this->setTemplate('Emails/Order_ReceiptEmail');

		if (file_exists(Director::getAbsFile($this->ThemeDir() . '/css/ShopEmail.css'))) {
			$css = file_get_contents(Director::getAbsFile($this->ThemeDir() . '/css/ShopEmail.css'));
		} else {
			$css = file_get_contents(Director::getAbsFile('swipestripe/css/ShopEmail.css'));
		}

		$this->populateTemplate(
			array(
				'Message' => $this->Body(),
				'Order' => $order,
				'Customer' => $customer,
				'InlineCSS' => "<style>$css</style>",
				'Signature' => $this->signature
			)
		);

		parent::__construct($from, null, $subject, $body, $bounceHandlerURL, $cc, $bcc);
	}
}
