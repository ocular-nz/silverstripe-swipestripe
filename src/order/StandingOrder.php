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

        // set for infinite recurrences. if future requirements call for an end date 
        // to the standing order, we can plug that in here.
        return CarbonPeriod::create($this->StartDate, $interval, INF);
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
        $now = date_create();

        $latestOrderDate = Carbon::parse($this->Orders()->filter('Created:LessThanOrEqual', $now->format('Y-m-d'))->max('Created') ?: '1980-01-01');

        $period = $this->Period();

        if (empty($period)) {
            $this->logger->error('Standing order has no valid period', [$this->ID]);
            return false;
        }

        if (!$period->isInProgress()) {
            $this->logger->info('Standing order not in active period', [$this->ID]);
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

        return true;
    }

    public function CartName()
    {
        return $this->Name ?: 'Standing Order #' . $this->ID;
    }
}