<?php

namespace SwipeStripe\Order;

use App\Web\StandingOrderNotificationEmail;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Control\Email\Email;
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

    private static $has_one = [
        'SavedCard' => 'App\Web\SavedCard'
    ];

    private static $cascade_duplicates = [
        'Updates'
    ];

    /**
     * Validate that the saved card belongs to the same member
     */
    public function validate(): ValidationResult
    {
        $result = parent::validate();

        if ($this->SavedCardID && $this->MemberID) {
            $card = $this->SavedCard();
            if ($card && $card->exists() && $card->MemberID !== $this->MemberID) {
                $result->addError('SavedCard must belong to the same member as the StandingOrder');
            }
        }

        return $result;
    }

    /**
     * Get the saved card if it belongs to this order's member
     */
    public function getValidSavedCard()
    {
        $card = $this->SavedCard();
        if ($card && $card->exists() && $card->MemberID === $this->MemberID && $card->IsActive) {
            return $card;
        }
        return null;
    }

    /**
     * Get detailed status of payment method for this standing order
     * 
     * @return array ['valid' => bool, 'reason' => string, 'card' => SavedCard|null]
     */
    public function getPaymentMethodStatus(): array
    {
        $card = $this->SavedCard();

        if (!$card || !$card->exists()) {
            return ['valid' => false, 'reason' => 'no_card', 'card' => null];
        }

        if ($card->MemberID !== $this->MemberID) {
            return ['valid' => false, 'reason' => 'wrong_member', 'card' => $card];
        }

        if (!$card->IsActive) {
            return ['valid' => false, 'reason' => 'card_inactive', 'card' => $card];
        }

        if ($card->isExpired()) {
            return ['valid' => false, 'reason' => 'card_expired', 'card' => $card];
        }

        return ['valid' => true, 'reason' => 'valid', 'card' => $card];
    }

    /**
     * Check if payment method is valid (backwards compatible)
     */
    public function hasValidPaymentMethod(): bool
    {
        return $this->getPaymentMethodStatus()['valid'];
    }

    /**
     * Check if standing order should run based on schedule (excluding payment method)
     */
    public function shouldRunBasedOnSchedule(): bool
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

    public function Items()
    {
        // // check items in standing order are still valid 
        // // and clean up any invalid ones before returning
        // $items = parent::Items();
        // foreach ($items as $item) {
        //     $validation = $item->validateForCart();
        //     if (!$validation->isValid()) {
        //         $item->delete();
        //     }
        // }

        // return parent::Items();

        // instead of deleting, let's filter out the ones that are invalid
        return parent::Items()->filterByCallback(function ($item) {
            $validation = $item->validateForCart();
            return $validation->isValid();
        });
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

    public function DayOfWeek()
    {
        if (empty($this->StartDate)) {
            return null;
        }

        return Carbon::parse($this->StartDate)->format('l');
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

    /**
     * Check if standing order should run (backwards compatible method)
     * Combines schedule and payment method validation
     */
    public function shouldRun(): bool
    {
        // Check schedule first
        if (!$this->shouldRunBasedOnSchedule()) {
            return false;
        }

        // Then check payment method
        if (!$this->hasValidPaymentMethod()) {
            $status = $this->getPaymentMethodStatus();
            $this->logger->info('Standing order payment method invalid', [
                'StandingOrderID' => $this->ID,
                'Reason' => $status['reason']
            ]);
            return false;
        }

        return true;
    }

    /**
     * Send email notification to customer about standing order issues
     */
    public function notifyCustomer(string $type, array $context = []): void
    {
        $member = $this->Member();
        if (!$member || !$member->Email) {
            $this->logger->warning('Cannot notify customer - no email address', [
                'StandingOrderID' => $this->ID,
                'Type' => $type
            ]);
            return;
        }

        try {
            $email = new StandingOrderNotificationEmail($member, $this, $type, $context);
            $email->send();
            $this->logger->info('Standing order notification sent', [
                'StandingOrderID' => $this->ID,
                'Type' => $type,
                'Email' => $member->Email
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send standing order notification', [
                'StandingOrderID' => $this->ID,
                'Type' => $type,
                'Error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Disable this standing order and log the reason
     */
    public function disable(string $reason = ''): void
    {
        $this->Enabled = false;
        $this->write();

        $this->logger->info('Standing order disabled', [
            'StandingOrderID' => $this->ID,
            'Reason' => $reason
        ]);
    }

    public function CartName()
    {
        return $this->Name ?: 'Standing Order #' . $this->ID;
    }
}
