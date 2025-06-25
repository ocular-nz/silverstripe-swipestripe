<?php

namespace SwipeStripe\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Input\InputInterface;
use SwipeStripe\Order\Order;

/**
 * Remove orders that were placed while the site was in 'dev' mode. Useful for cleaning up after
 * testing a new site.
 * 
 * @author Frank Mullenger <frankmullenger@gmail.com>
 * @copyright Copyright (c) 2011, Frank Mullenger
 * @package swipestripe
 * @subpackage tasks
 */

class RemoveDevOrdersTask extends BuildTask
{	
	protected string $title = "Remove testing orders";
	
	protected static string $description = "Remove orders that were placed while website was in 'dev' mode.";

	protected function execute(InputInterface $input, PolyOutput $output): int {
		$orders = Order::get()
			->where("\"Order\".\"Env\" = 'dev'");

		if ($orders && $orders->exists()) foreach ($orders as $order) {
			$order->delete();
			$order->destroy();
		}
		return 0;
	}
}
