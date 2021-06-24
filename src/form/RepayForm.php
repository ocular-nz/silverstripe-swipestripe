<?php

namespace SwipeStripe\Form;

use Exception;
use Payment\PaymentFactory;
use Payment\PaymentProcessor;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\DataObject;
use SwipeStripe\Customer\Customer;
use SwipeStripe\Order\Order;

/**
 * Form for displaying on the {@link CheckoutPage} with all the necessary details 
 * for a visitor to complete their order and pass off to the {@link Payment} gateway class.
 */
class RepayForm extends Form implements LoggerAwareInterface
{

	use LoggerAwareTrait;

	private static $dependencies = [
		'Logger' => '%$' . LoggerInterface::class,
	];

	protected $order;
	protected $customer;

	/**
	 * Construct the form, get the grouped fields and set the fields for this form appropriately,
	 * the fields are passed in an associative array so that the fields can be grouped into sets 
	 * making it easier for the template to grab certain fields for different parts of the form.
	 * 
	 * @param RequestHandler|null $controller
	 * @param String $name
	 * @param Array $groupedFields Associative array of fields grouped into sets
	 * @param FieldList $actions
	 * @param Validator $validator
	 * @param Order $currentOrder
	 */
	function __construct($controller, $name)
	{

		parent::__construct($controller, $name, FieldList::create(), FieldList::create(), null);

		$orderID = Injector::inst()->get(HTTPRequest::class)->getSession()->get('Repay.OrderID');
		if ($orderID) {
			$this->order = DataObject::get_by_id(Order::class, $orderID);
		}
		$this->customer = Customer::currentUser() ? Customer::currentUser() : singleton(Customer::class);

		$this->fields = $this->createFields();
		$this->actions = $this->createActions();
		$this->validator = $this->createValidator();

		$this->restoreFormState();

		$this->setTemplate('Includes\\RepayForm');
		$this->addExtraClass('order-form');
	}

	/**
	 * Set up current form errors in session to
	 * the current form if appropriate.
	 */
	public function restoreFormState()
	{
		//Only run when fields exist
		if ($this->fields->exists()) {
			parent::restoreFormState();
		}
	}

	public function createFields()
	{

		$order = $this->order;
		$member = $this->customer;

		//Payment fields
		$supported_methods = PaymentProcessor::get_supported_methods();

		$source = array();
		foreach ($supported_methods as $methodName) {
			$methodConfig = PaymentFactory::get_factory_config($methodName);
			$source[$methodName] = $methodConfig['title'];
		}

		$outstanding = $order->TotalOutstanding()->Nice();

		$paymentFields = CompositeField::create(
			new HeaderField(_t('CheckoutPage.PAYMENT', "Payment"), 3),
			LiteralField::create('RepayLit', "<p>Process a payment for the oustanding amount: $outstanding</p>"),
			DropdownField::create(
				'PaymentMethod',
				_t('CheckoutPage.SELECTPAYMENT', "Select Payment Method"),
				$source
			)->setCustomValidationMessage(_t('CheckoutPage.SELECT_PAYMENT_METHOD', "Please select a payment method."))
			 ->setValue(array_key_first($source))
		)->setName('PaymentFields');


		$fields = FieldList::create(
			$paymentFields
		);

		$this->extend('updateFields', $fields);
		$fields->setForm($this);
		return $fields;
	}

	public function createActions()
	{
		$actions = FieldList::create(
			new FormAction('process', _t('CheckoutPage.PROCEED_TO_PAY', "Proceed to pay"))
		);

		$this->extend('updateActions', $actions);
		$actions->setForm($this);
		return $actions;
	}

	public function createValidator()
	{

		$validator = RequiredFields::create(
			'PaymentMethod'
		);

		$this->extend('updateValidator', $validator);
		$validator->setForm($this);
		return $validator;
	}

	public function getPaymentFields()
	{
		return $this->Fields()->fieldByName('PaymentFields');
	}

	/**
	 * Helper function to return the current {@link Order}, used in the template for this form
	 * 
	 * @return Order
	 */
	function Cart()
	{
		return $this->order;
	}

	/**
	 * Overloaded so that form error messages are displayed.
	 * 
	 * @see OrderFormValidator::php()
	 * @see Form::validate()
	 */
	function validate()
	{
		$valid = true;
		if ($this->validator) {
			$errors = $this->validator->validate();

			if ($errors) {
				// Load errors into session and post back
				$data = $this->getData();
				$this->getSession()->set("FormInfo.{$this->FormName()}.errors", $errors);
				$this->getSession()->set("FormInfo.{$this->FormName()}.data", $data);
				$valid = false;
			}
		}
		return $valid;
	}

	public function process($data, $form)
	{

		//Check payment type
		try {
			$paymentMethod = $data['PaymentMethod'];
			$paymentProcessor = PaymentFactory::factory($paymentMethod);
		} catch (Exception $e) {
			$this->getRequestHandler()->httpError(403, "Sorry, that is not a valid payment method. Please go back and try again");
			return;
		}

		$member = Customer::currentUser();

		$orderID = $this->getRequest()->getSession()->get('Repay.OrderID');
		if ($orderID) {
			$order = DataObject::get_by_id(Order::class, $orderID);
		}
		$this->getRequest()->getSession()->clear('Repay.OrderID');

		$order->onBeforePayment();

		try {

			$paymentData = array(
				'Amount' => number_format($order->TotalOutstanding()->getAmount(), 2, '.', ''),
				'Currency' => $order->TotalOutstanding()->getCurrency(),
				'Reference' => $order->ID
			);
			$paymentProcessor->payment->OrderID = $order->ID;
			$paymentProcessor->payment->PaidByID = $member->ID;

			$paymentProcessor->setRedirectURL($order->Link());
			$paymentProcessor->capture($paymentData);
		} catch (\Exception $e) {

			//This is where we catch gateway validation or gateway unreachable errors
			$result = $paymentProcessor->gateway->getValidationResult();
			$payment = $paymentProcessor->payment;

			//TODO: Need to get errors and save for display on order page
			$this->logger->notice($e, $result->getMessages());

			$this->controller->redirect($order->Link());
		}
	}

	function populateFields()
	{

		//Populate values in the form the first time
		if (!$this->getRequest()->getSession()->get("FormInfo.{$this->FormName()}.errors")) {

			$member = Customer::currentUser() ? Customer::currentUser() : singleton('Customer');
			$data = array_merge(
				$member->toMap()
			);

			$this->extend('updatePopulateFields', $data);
			$this->loadDataFrom($data);
		}
	}
}
