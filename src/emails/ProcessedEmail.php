<?php

namespace SwipeStripe\Emails;

use SilverStripe\Control\Email\Email;
use Pelago\Emogrifier;

/**
 * Same as the normal system email class, but runs the content through
 * Emogrifier to merge css rules inline before sending.
 * 
 * @author Mark Guinn
 * @package swipestripe
 * @subpackage emails
 */
class ProcessedEmail extends Email
{

	/**
	 * Email signature
	 * 
	 * @var string HTML content from central config for signature
	 * @see ShopConfig
	 */
	public $signature;

	/**
	 * @var string The CSS to use for emogrification
	 */
	protected $css;

	/**
	 * Runs the content through Emogrifier to merge css style inline before sending
	 * 
	 * @see Email::render()
	 */
	public function render($plainOnly = false)
	{
		// the parent class stores the rendered output in Body
		parent::render($plainOnly);

		// if it's an html email, filter it through emogrifier
		if (!$plainOnly && $this->css) {

			$html = str_replace(
				[
					"<p>\n<table>",
					"</table>\n</p>",
					'&copy ',
				],
				[
					"<table>",
					"</table>",
					'',
				],
				$this->getBody()
			);

			$emog = new Emogrifier($html, $this->css);
			$this->setBody($emog->emogrify());
		}
	}
}
