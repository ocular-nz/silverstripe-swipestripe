<?php

namespace SwipeStripe\Order;

class StandingOrder extends Order
{
    private static $table_name = 'StandingOrder';

    private static $db = [
        'Frequency' => 'Varchar(50)',
        'StartDate' => 'Date',
        'Name' => 'Varchar(255)',
        'LastDispatch' => 'Datetime',
        'Enabled' => 'Boolean',
    ];

    private static $defaults = [
        'Frequency' => 'Weekly',
    ];
}