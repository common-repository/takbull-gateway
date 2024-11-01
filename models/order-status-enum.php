<?php
abstract class OrderStatus
{
    const Pending = 0;
    const Process = 1;
    const Complite = 2;
    const Error = 3;
}
abstract class TransactionStatus
{
    const UNKONW = 0;
    const Complite = 1;
    const DECLINED = 2;
    const PARTIALLY_REFUNDED = 3;
    const PENDING = 4;
    const REFUNDED = 5;
    const FAILED = 6;
}
