<?php

namespace SwipeStripe\Product;

use SilverStripe\ORM\FieldType\DBMoney;

class Price extends DBMoney
{

	protected $symbol;

	public function setSymbol($symbol)
	{
		$this->symbol = $symbol;
		return $this;
	}

	public function getSymbol($currency = null, $locale = null)
	{
		return $this->symbol;
	}

	public function getAmount()
	{
		return round($this->getField('Amount') ?? 0, 2);
	}

	public function Nice($options = array())
	{
		$amount = $this->getAmount();

		if (is_numeric($amount)) {
			if ($amount < 0) {
				return '- ' . $this->symbol . sprintf('%0.2f', abs($amount));
			} else {
				return $this->symbol . sprintf('%0.2f', $amount);
			}
		} else {
			return $this->symbol;
		}
	}
}
