<?php

namespace SwipeStripe\Form;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;
use SwipeStripe\Customer\Cart;
use SwipeStripe\Order\Item;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\NumericField;
use SwipeStripe\Customer\CheckoutPage;

/**
 * Form to display the {@link Order} contents on the {@link CartPage}.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage form
 */
class CartForm extends Form implements LoggerAwareInterface
{

	use LoggerAwareTrait;

	private static $dependencies = [
		'Logger' => '%$' . LoggerInterface::class,
	];

	/**
	 * The current {@link Order} (cart).
	 * 
	 * @var Order
	 */
	public $order;

	/**
	 * Construct the form, set the current order and the template to be used for rendering.
	 * 
	 * @param RequestHandler $controller
	 * @param String $name
	 * @param FieldList $fields
	 * @param FieldList $actions
	 * @param Validator $validator
	 * @param Order $currentOrder
	 */
	function __construct($controller = null, $name = SELF::DEFAULT_NAME)
	{

		parent::__construct($controller, $name, FieldList::create(), FieldList::create(), null);

		Requirements::javascript('silverstripe/admin:thirdparty/jquery-entwine/jquery.entwine.js');
		Requirements::javascript('swipestripe/javascript/CartForm.js');

		$this->order = Cart::get_current_order();

		$this->fields = $this->createFields();
		$this->actions = $this->createActions();
		$this->validator = $this->createValidator();

		$this->restoreFormState();

		$this->addExtraClass('cart-form');
		$this->setTemplate('CartForm');
	}

	/**
	 * Set up current form errors in session to
	 * the current form if appropriate.
	 */
	public function restoreFormState()
	{
		// Only run when fields exist
		if ($this->fields->exists()) {
			parent::restoreFormState();
		}
	}

	public function createFields()
	{

		$fields = FieldList::create();
		$items = $this->order->Items();

		if ($items) foreach ($items as $item) {

			$fields->push(CartForm_QuantityField::create(
				'Quantity[' . $item->ID . ']',
				$item->Quantity,
				$item
			));
		}

		$this->extend('updateFields', $fields);
		$fields->setForm($this);
		return $fields;
	}

	public function createActions()
	{

		$actions = FieldList::create(
			FormAction::create('updateCart', _t('CartForm.UPDATE_CART', 'Update Cart')),
			FormAction::create('goToCheckout', _t('CartForm.GO_TO_CHECKOUT', 'Go To Checkout'))
		);
		$this->extend('updateActions', $actions);
		$actions->setForm($this);
		return $actions;
	}

	public function createValidator()
	{

		$validator = RequiredFields::create();

		$items = $this->order->Items();
		if ($items) foreach ($items as $item) {
			$validator->addRequiredField('Quantity[' . $item->ID . ']');
		}

		$this->extend('updateValidator', $validator);
		$validator->setForm($this);
		return $validator;
	}

	/**
	 * Update the current cart quantities then redirect back to the cart page.
	 * 
	 * @param Array $data Data submitted from the form via POST
	 * @param Form $form Form that data was submitted from
	 */
	public function updateCart(array $data, Form $form)
	{

		$this->saveCart($data, $form);
		$this->controller->redirectBack();
	}

	/**
	 * Update the current cart quantities and redirect to checkout.
	 * 
	 * @param Array $data Data submitted from the form via POST
	 * @param Form $form Form that data was submitted from
	 */
	public function goToCheckout(array $data, Form $form)
	{

		$this->saveCart($data, $form);

		if ($checkoutPage = DataObject::get_one(CheckoutPage::class)) {
			$this->controller->redirect($checkoutPage->AbsoluteLink());
		} else Debug::friendlyError(500);
	}


	/**
	 * Save the cart, update the order item quantities and the order total.
	 * 
	 * @param array $data Data submitted from the form via POST
	 * @param Form $form Form that data was submitted from
	 */
	private function saveCart(array $data, Form $form)
	{
		$currentOrder = Cart::get_current_order();
		$quantities = (isset($data['Quantity'])) ? $data['Quantity'] : null;

		if ($quantities) foreach ($quantities as $itemID => $quantity) {

			if ($item = $currentOrder->Items()->find('ID', $itemID)) {
				if ($quantity == 0) {

					$this->logger->notice('Item removed from cart as quantity = 0', $item->toMap());

					$item->delete();
				} else {
					$item->Quantity = $quantity;
					$item->write();
				}
			}
		}
		$currentOrder->updateTotal();
	}

	/*
	 * Retrieve the current {@link Order} which is the cart.
	 * 
	 * @return Order The current order (cart)
	 */
	public function Cart()
	{
		return $this->order;
	}
}

/**
 * Quantity field for displaying each {@link Item} in an {@link Order} on the {@link CartPage}.
 */
class CartForm_QuantityField extends NumericField
{

	/**
	 * Current {@link Item} represented by this field.
	 * 
	 *  @var Item
	 */
	protected $item;

	/** 
	 * @var bool
	 */
	protected $html5 = true;

	/**
	 * Construct the field and set the current {@link Item} that this field represents.
	 * 
	 * @param String $name
	 * @param String $title
	 * @param String $value
	 * @param int $maxLength
	 * @param Form $form
	 * @param Item $item
	 */
	function __construct($name, $value = "", $item = null)
	{
		$this->item = $item;
		parent::__construct($name, '', $value, null, null);
	}

	/**
	 * Retrieve the current {@link Item} this field represents. Used in the template.
	 * 
	 * @return Item
	 */
	function Item()
	{
		return $this->item;
	}

	/**
	 * Set the current {@link Item} this field represents
	 * 
	 * @param Item $item
	 */
	function setItem(Item $item)
	{
		$this->item = $item;
	}

	/**
	 * Validate this field, check that the current {@link Item} is in the current 
	 * {@Link Order} and is valid for adding to the cart.
	 * 
	 * @see FormField::validate()
	 * @return Boolean
	 */
	function validate($validator)
	{

		$valid = true;
		$item = $this->Item();
		$currentOrder = Cart::get_current_order();
		$items = $currentOrder->Items();
		$quantity = $this->Value();

		$removingItem = false;
		if ($quantity <= 0) {
			$removingItem = true;
		}

		//Check that item exists and is in the current order
		if (!$item || !$item->exists() || !$items->find('ID', $item->ID)) {

			$errorMessage = _t('Form.ITEM_IS_NOT_IN_ORDER', 'This product is not in the Cart.');
			if ($msg = $this->getCustomValidationMessage()) {
				$errorMessage = $msg;
			}

			$validator->validationError(
				$this->getName(),
				$errorMessage,
				"error"
			);
			$valid = false;
		} else if ($item) {

			//If removing item, cannot subtract past 0
			if ($removingItem) {
				if ($quantity < 0) {
					$errorMessage = _t('Form.ITEM_QUANTITY_LESS_ONE', 'The quantity must be at least 0');
					if ($msg = $this->getCustomValidationMessage()) {
						$errorMessage = $msg;
					}

					$validator->validationError(
						$this->getName(),
						$errorMessage,
						"error"
					);
					$valid = false;
				}
			} else {
				//If quantity is invalid
				if ($quantity == null || !is_numeric($quantity)) {
					$errorMessage = _t('Form.ITEM_QUANTITY_INCORRECT', 'The quantity must be a number');
					if ($msg = $this->getCustomValidationMessage()) {
						$errorMessage = $msg;
					}

					$validator->validationError(
						$this->getName(),
						$errorMessage,
						"error"
					);
					$valid = false;
				} else if ($quantity > 2147483647) {
					$errorMessage = _t('Form.ITEM_QUANTITY_INCORRECT', 'The quantity must be less than 2,147,483,647');
					if ($msg = $this->getCustomValidationMessage()) {
						$errorMessage = $msg;
					}

					$validator->validationError(
						$this->getName(),
						$errorMessage,
						"error"
					);
					$valid = false;
				}

				$validation = $item->validateForCart();
				if (!$validation->valid()) {

					$errorMessage = $validation->message();
					if ($msg = $this->getCustomValidationMessage()) {
						$errorMessage = $msg;
					}

					$validator->validationError(
						$this->getName(),
						$errorMessage,
						"error"
					);
					$valid = false;
				}
			}
		}

		return $valid;
	}

	public function Type()
	{
		return 'cartquantity';
	}

	public function getAttributes()
	{
		return array_merge(
			parent::getAttributes(),
			['min' => 0]
		);
	}
}
