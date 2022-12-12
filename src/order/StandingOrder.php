<?php

namespace SwipeStripe\Order;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use SwipeStripe\Order\Order;

class StandingOrder extends Order
{
    private static $table_name = 'StandingOrder';

    private static $db = [
        'Frequency' => 'Varchar(50)',
        'StartDate' => 'Date',
        'Name' => 'Varchar(255)',
        'Enabled' => 'Boolean',
    ];

    private static $defaults = [
        'Frequency' => 'Weekly',
    ];

    private static $has_many = [
        'Orders' => Order::class
    ];

    private static $cascade_duplicates = [
        'Updates'
    ];

    public function Items()
	{
        // check items in standing order are still valid 
        // and clean up any invalid ones before returning
        $items = parent::Items();
        foreach ($items as $item) {
            $validation = $item->validateForCart();
            if (!$validation->isValid()) {
                $item->delete();
            }
        }
		
		return parent::Items();
	}

    /**
     * The start date of the period is a day before the date given by the user
     * so that orders are placed a day ahead of the chosen dates
     */
    public function Period(): ?CarbonPeriod
    {
        if (empty($this->StartDate)) {
            return null;
        }

        $interval = match ($this->Frequency) {
            'Weekly' => 'P1W',
            'Fortnightly' => 'P2W',
            default => null
        };

        if (empty($interval)) {
            return null;
        }

        // we actually want to place the order a day ahead for next-day delivery
        $startDate = Carbon::parse($this->StartDate)->subDay();

        // set for infinite recurrences. if future requirements call for an end date 
        // to the standing order, we can plug that in here.
        return CarbonPeriod::create($startDate, $interval, INF);
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->isChanged('Enabled')) {
            if ($this->Enabled) {
                $this->logger->info('Standing order enabled', [$this->ID]);
            } else {
                $this->logger->info('Standing order disabled', [$this->ID]);
            }
        }
    }

    public function shouldRun(): bool
    {
        $latestOrderDate = Carbon::parse($this->Orders()->max('Created') ?: '1980-01-01');

        $period = $this->Period();

        if (empty($period)) {
            $this->logger->error('Standing order has no valid period', [$this->ID]);
            return false;
        }

        if (!$period->isInProgress()) {
            $this->logger->info('Standing order not in active period', [$this->ID]);
            return false;
        }

        if (!$this->Enabled) {
            $this->logger->info('Standing order is disabled', [$this->ID]);
            return false;
        }

        // the most recent recurrence date
        $periodDate = $period->untilNow()->last();

        // don't place an order if the last order placed was on or after this date, for idempotency
        if ($periodDate < $latestOrderDate || $periodDate->isSameDay($latestOrderDate)) {
            $this->logger->info('Standing order not yet due or already placed', [$this->ID, $periodDate->toString()]);
            return false;
        }

        // if the day of order placement was missed, do not place, else the customer may get an unexpected order placement after unpausing the standing order
        // $periodDate can be assumed not to be in the future, since we used the untilNow() modifier
        if (!$periodDate->isToday()) {
            $this->logger->info('Standing order placement was missed and the window has expired', [$this->ID, $periodDate->toString()]);
            return false;
        }

        // don't place an order if there are no items in the order
        if ($this->ItemCount() <= 0) {
            $this->logger->info('Standing order skipped because it has no items', [$this->ID, $periodDate->toString()]);
            return false;
        }

        return true;
    }

    public function CartName()
    {
        return $this->Name ?: 'Standing Order #' . $this->ID;
    }
}