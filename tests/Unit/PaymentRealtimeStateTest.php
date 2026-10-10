<?php
namespace Tests\Unit;

use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionAddonController;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionAddonOrder;
use App\Models\SubscriptionAddonPayment;
use ReflectionMethod;
use Tests\TestCase;

class PaymentRealtimeStateTest extends TestCase
{
    public function test_subscription_is_not_reported_completed_by_another_payment(): void
    {
        $subscription = new Subscription(['status' => 'active']);
        $payment = new Payment(['status'=>'pending']);
        $payment->setRelation('subscription', $subscription);
        $method = new ReflectionMethod(SubscriptionController::class, 'statusPayload');
        $payload = $method->invoke(new SubscriptionController(), $payment, 'ref-test');
        $this->assertFalse($payload['active']);
        $payment->status = 'paid';
        $payload = $method->invoke(new SubscriptionController(), $payment, 'ref-test');
        $this->assertTrue($payload['active']);
        $subscription->status = 'pending';
        $payload = $method->invoke(new SubscriptionController(), $payment, 'ref-test');
        $this->assertFalse($payload['active']);
    }

    public function test_addon_requires_paid_payment_and_activated_order(): void
    {
        $order = new SubscriptionAddonOrder(['status'=>'activated']);
        $payment = new SubscriptionAddonPayment(['status'=>'pending']);
        $payment->setRelation('order', $order);
        $method = new ReflectionMethod(SubscriptionAddonController::class, 'statusPayload');
        $payload = $method->invoke(new SubscriptionAddonController(), $payment, 'ref-addon');
        $this->assertFalse($payload['active']);
        $payment->status = 'paid';
        $payload = $method->invoke(new SubscriptionAddonController(), $payment, 'ref-addon');
        $this->assertTrue($payload['active']);
        $order->status = 'pending_verification';
        $payload = $method->invoke(new SubscriptionAddonController(), $payment, 'ref-addon');
        $this->assertFalse($payload['active']);
    }
}
