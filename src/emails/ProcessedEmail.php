<?php

namespace SwipeStripe\Emails;

use Pelago\Emogrifier\CssInliner;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\View\ViewableData;

/**
 * Decorator for the Email class to allow for inlined CSS
 */
class ProcessedEmail
{
	use Injectable;

	protected Email $mail;

	protected string $template;

	public function __construct()
	{
		$this->mail = Email::create();
	}

	/**
	 * Runs the content through Emogrifier to merge css style inline before sending
	 */
	public function renderBody(array $data = [], ?string $template = null): void
	{
		$viewModel = ViewableData::create();

		$template ??= $this->template;

		if (empty($template)) {
			throw new \InvalidArgumentException('Template not set');
		}

		$html = $viewModel->renderWith($template, $data);

		$css = $data['Css'] ?? null;

		if (!empty($css)) {

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
				$html
			);

			$inlined = CssInliner::fromHtml($html)
				->inlineCss($css)
				->render();
		}
		
		$this->mail->setBody($inlined);
	}

	public function send(): void
	{
		$this->mail->send();
	}

	public function setSubject(string $subject): void
	{
		$this->mail->setSubject($subject);
	}
}
