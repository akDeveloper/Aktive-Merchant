<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Common\Options;
use AktiveMerchant\TestCase;
use AktiveMerchant\Event\RequestEvents;

class IridiumTest extends TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $options = $this->getFixtures()->offsetGet('iridium');

        $this->amount = 10;

        $this->gateway = new Iridium($options);

        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => '4976000000003436',
                "month" => "12",
                "year" => date('Y') + 1,
                "verification_value" => "452"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'email' => "buyer@email.com",
            'description' => 'Iridium Test Transaction',
            'billing_address' => array(
                'address1' => '32 Edward Street',
                'city' => 'Camborne',
                'state' => 'Cornwall',
                'country' => 'US',
                'zip' => 'TR14 8PA'
            ),
            'ip' => '10.0.0.1',
            'currency' => 'USD'
        );
    }

    public function testSuccessfulAuthorization()
    {
        $this->mock_request($this->successfulAuthorizeResponse());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/033976/', $response->authorization());
        $this->assertNotNull($response->authorization());

        return $response->authorization();
    }

    /**
     * @depends testSuccessfulAuthorization
     */
    public function testSuccesfulCapture($authorization)
    {
        $this->mock_request($this->successfulCaptureResponse());

        $response = $this->gateway->capture(
            $this->amount,
            $authorization,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/140529115328094701097945/', $response->authorization());
    }

    public function testSuccesfulPurchase()
    {
        $this->mock_request($this->successfulPurchaseResponse());

        $response = $this->gateway->purchase(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/140529120244578301140694/', $response->authorization());

        return $response->authorization();
    }

    /**
     * @depends testSuccesfulPurchase
     */
    public function testSuccessfulCredit($authorization)
    {
        $this->mock_request($this->successfulCreditResponse());

        $response = $this->gateway->credit(
            $this->amount,
            $authorization,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/140529131137341901180555/', $response->authorization());
    }

    /**
     * @depends testSuccessfulAuthorization
     */
    public function testSuccessfulVoid($authorization)
    {
        $this->mock_request($this->successfulVoidResponse());

        $response = $this->gateway->void(
            $authorization,
            $this->options
        );

        $this->assert_success($response);
        $this->assertRegExp('/140529132114901901941710/', $response->authorization());
    }

    private function successfulAuthorizeResponse()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
            <soap:Body><CardDetailsTransactionResponse xmlns="https://www.thepaymentgateway.net/">
            <CardDetailsTransactionResult AuthorisationAttempted="True"><StatusCode>0</StatusCode>
            <Message>AuthCode: 033976</Message></CardDetailsTransactionResult>
            <TransactionOutputData CrossReference="140529115207528401556604"><AuthCode>033976</AuthCode>
            <AddressNumericCheckResult>PASSED</AddressNumericCheckResult><PostCodeCheckResult>PASSED</PostCodeCheckResult>
            <CV2CheckResult>PASSED</CV2CheckResult><GatewayEntryPoints><GatewayEntryPoint EntryPointURL="https://gw1.payvector.net/" Metric="100" />
            <GatewayEntryPoint EntryPointURL="https://gw2.payvector.net/" Metric="200" /></GatewayEntryPoints></TransactionOutputData>
            </CardDetailsTransactionResponse></soap:Body></soap:Envelope>';
    }

    private function successfulCaptureResponse()
    {
        return '<?xml version="1.0" encoding="utf-8" ?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
            <soap:Body><CrossReferenceTransactionResponse xmlns="https://www.thepaymentgateway.net/"><CrossReferenceTransactionResult AuthorisationAttempted="True">
            <StatusCode>0</StatusCode><Message>Collection successful</Message>
            </CrossReferenceTransactionResult><TransactionOutputData CrossReference="140529115328094701097945">
            <GatewayEntryPoints><GatewayEntryPoint EntryPointURL="https://gw1.payvector.net/" Metric="100" />
            <GatewayEntryPoint EntryPointURL="https://gw2.payvector.net/" Metric="200" /></GatewayEntryPoints>
            </TransactionOutputData></CrossReferenceTransactionResponse></soap:Body></soap:Envelope>';
    }


    private function successfulPurchaseResponse()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
            <soap:Body><CardDetailsTransactionResponse xmlns="https://www.thepaymentgateway.net/">
            <CardDetailsTransactionResult AuthorisationAttempted="True"><StatusCode>0</StatusCode>
            <Message>AuthCode: 931597</Message></CardDetailsTransactionResult>
            <TransactionOutputData CrossReference="140529120244578301140694"><AuthCode>931597</AuthCode>
            <AddressNumericCheckResult>PASSED</AddressNumericCheckResult><PostCodeCheckResult>PASSED</PostCodeCheckResult>
            <CV2CheckResult>PASSED</CV2CheckResult><GatewayEntryPoints><GatewayEntryPoint EntryPointURL="https://gw1.payvector.net/" Metric="100" />
            <GatewayEntryPoint EntryPointURL="https://gw2.payvector.net/" Metric="200" /></GatewayEntryPoints>
            </TransactionOutputData></CardDetailsTransactionResponse></soap:Body></soap:Envelope>';

    }

    private function successfulCreditResponse()
    {
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
            <soap:Body><CrossReferenceTransactionResponse xmlns="https://www.thepaymentgateway.net/">
            <CrossReferenceTransactionResult AuthorisationAttempted="True">
            <StatusCode>0</StatusCode><Message>Refund successful</Message>
            </CrossReferenceTransactionResult><TransactionOutputData CrossReference="140529131137341901180555">
            <GatewayEntryPoints><GatewayEntryPoint EntryPointURL="https://gw1.payvector.net/" Metric="100" />
            <GatewayEntryPoint EntryPointURL="https://gw2.payvector.net/" Metric="200" /></GatewayEntryPoints>
            </TransactionOutputData></CrossReferenceTransactionResponse></soap:Body></soap:Envelope>';
    }

    private function successfulVoidResponse()
    {
        return'<?xml version="1.0" encoding="utf-8"?>
            <soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
            <soap:Body><CrossReferenceTransactionResponse xmlns="https://www.thepaymentgateway.net/">
            <CrossReferenceTransactionResult AuthorisationAttempted="True"><StatusCode>0</StatusCode>
            <Message>Void successful</Message></CrossReferenceTransactionResult>
            <TransactionOutputData CrossReference="140529132114901901941710"><GatewayEntryPoints>
            <GatewayEntryPoint EntryPointURL="https://gw1.payvector.net/" Metric="100" />
            <GatewayEntryPoint EntryPointURL="https://gw2.payvector.net/" Metric="200" />
            </GatewayEntryPoints></TransactionOutputData></CrossReferenceTransactionResponse>
            </soap:Body></soap:Envelope>';
    }
}
