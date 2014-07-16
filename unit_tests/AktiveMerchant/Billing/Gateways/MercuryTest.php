<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\Mercury;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

class MercuryTest extends \AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    /**
     * Setup
     */
    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('mercury');

        $login_info['tokenization'] = false;
        $this->gateway = new Mercury($login_info);

        $this->amount = 10;
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "5499990123456781",
                "month" => "11",
                "year" => "2013",
                "verification_value" => "123"
            )
        );
        $this->options = array(
            'order_id' => $this->gateway->generateUniqueId(),
            'description' => 'Mercury Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testSuccessfulPurchase()
    {
        $this->mock_request($this->successful_purchase_response());
        $response = $this->gateway->purchase($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_purchase_request($this->options['order_id']),
            $request_body
        );
    }

    public function testSuccessfulAuthorize()
    {
        $this->mock_request($this->successful_purchase_response());
        $response = $this->gateway->authorize($this->amount, $this->creditcard, $this->options);

        $this->assert_success($response);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_authorize_request($this->options['order_id']),
            $request_body
        );


    }

    public function testSuccessfulCapture()
    {
        $this->mock_request($this->successful_capture_response());
        $authorization = '1108870343;;000015;KbMCC0821071007  e00;|14|410100701000;;1000';
        $options = array_merge($this->options, array('creditcard' => $this->creditcard));
        $response = $this->gateway->capture($this->amount, $authorization, $options);

        $this->assert_success($response);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_capture_request(),
            $request_body
        );
    }

    public function testSuccessfulCredit()
    {
        $this->mock_request($this->succesful_credit_response());
        $identification = '1108870343;0001;000015;KbMCC1000171007  ;|00|410100701000;;1000';
        $options = array_merge($this->options, array('creditcard' => $this->creditcard));
        $response = $this->gateway->credit($this->amount, $identification, $options);

        $this->assert_success($response);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_credit_request(),
            $request_body
        );
    }

    public function testSuccessfulVoid()
    {
        $this->mock_request($this->successful_void_response());
        $authorization = '4852831525;;000015;KbMCC1224211007  e00;|14|410100701000;;1000';
        $options = array_merge($this->options, array('creditcard' => $this->creditcard));
        $response = $this->gateway->void($authorization, $options);

        $this->assert_success($response);

        $request_body = $this->request->getBody();
        $this->assertEquals(
            $this->successful_void_request(),
            $request_body
        );
    }

    private function successful_credit_request()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Body><CreditTransaction xmlns="http://www.mercurypay.com"><tran>&lt;?xml version=&quot;1.0&quot; encoding=&quot;utf-8&quot;?&gt;
&lt;TStream&gt;&lt;Transaction&gt;&lt;TranType&gt;Credit&lt;/TranType&gt;&lt;TranCode&gt;Return&lt;/TranCode&gt;&lt;TranCode&gt;Return&lt;/TranCode&gt;&lt;InvoiceNo&gt;1108870343&lt;/InvoiceNo&gt;&lt;RefNo&gt;0001&lt;/RefNo&gt;&lt;Memo&gt;Mercury Test Transaction&lt;/Memo&gt;&lt;MerchantID&gt;595901&lt;/MerchantID&gt;&lt;Amount&gt;&lt;Purchase&gt;10.00&lt;/Purchase&gt;&lt;/Amount&gt;&lt;Account&gt;&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;&lt;ExpDate&gt;1113&lt;/ExpDate&gt;&lt;/Account&gt;&lt;CardType&gt;M/C&lt;/CardType&gt;&lt;CVVData&gt;123&lt;/CVVData&gt;&lt;AVS&gt;&lt;Address&gt;1234 Street&lt;/Address&gt;&lt;zip&gt;98004&lt;/zip&gt;&lt;/AVS&gt;&lt;TranInfo&gt;&lt;AuthCode&gt;000015&lt;/AuthCode&gt;&lt;/TranInfo&gt;&lt;/Transaction&gt;&lt;/TStream&gt;
</tran><pw>xyz</pw></CreditTransaction></soap:Body></soap:Envelope>
';
    }

    private function succesful_credit_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CreditTransactionResponse xmlns="http://www.mercurypay.com"><CreditTransactionResult>&lt;?xml version="1.0"?&gt;
&lt;RStream&gt;
	&lt;CmdResponse&gt;
		&lt;ResponseOrigin&gt;Processor&lt;/ResponseOrigin&gt;
		&lt;DSIXReturnCode&gt;000000&lt;/DSIXReturnCode&gt;
		&lt;CmdStatus&gt;Approved&lt;/CmdStatus&gt;
		&lt;TextResponse&gt;AP&lt;/TextResponse&gt;
		&lt;UserTraceData&gt;&lt;/UserTraceData&gt;
	&lt;/CmdResponse&gt;
	&lt;TranResponse&gt;
		&lt;MerchantID&gt;595901&lt;/MerchantID&gt;
		&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;
		&lt;ExpDate&gt;1113&lt;/ExpDate&gt;
		&lt;CardType&gt;M/C&lt;/CardType&gt;
		&lt;TranCode&gt;Return&lt;/TranCode&gt;
		&lt;AuthCode&gt;000015&lt;/AuthCode&gt;
		&lt;CaptureStatus&gt;Captured&lt;/CaptureStatus&gt;
		&lt;RefNo&gt;0002&lt;/RefNo&gt;
		&lt;InvoiceNo&gt;1108870343&lt;/InvoiceNo&gt;
		&lt;Amount&gt;
			&lt;Purchase&gt;10.00&lt;/Purchase&gt;
			&lt;Authorize&gt;10.00&lt;/Authorize&gt;
		&lt;/Amount&gt;
		&lt;AcqRefData&gt;KbMCC1137381007  &lt;/AcqRefData&gt;
		&lt;ProcessData&gt;|20|410100700000&lt;/ProcessData&gt;
	&lt;/TranResponse&gt;
&lt;/RStream&gt;
</CreditTransactionResult></CreditTransactionResponse></soap:Body></soap:Envelope>';
    }

    private function successful_purchase_request($order_id)
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Body><CreditTransaction xmlns="http://www.mercurypay.com"><tran>&lt;?xml version=&quot;1.0&quot; encoding=&quot;utf-8&quot;?&gt;
&lt;TStream&gt;&lt;Transaction&gt;&lt;TranType&gt;Credit&lt;/TranType&gt;&lt;TranCode&gt;Sale&lt;/TranCode&gt;&lt;PartialAuth&gt;Allow&lt;/PartialAuth&gt;&lt;InvoiceNo&gt;'.$order_id.'&lt;/InvoiceNo&gt;&lt;RefNo&gt;'.$order_id.'&lt;/RefNo&gt;&lt;Memo&gt;Mercury Test Transaction&lt;/Memo&gt;&lt;MerchantID&gt;595901&lt;/MerchantID&gt;&lt;Amount&gt;&lt;Purchase&gt;10.00&lt;/Purchase&gt;&lt;/Amount&gt;&lt;Account&gt;&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;&lt;ExpDate&gt;1113&lt;/ExpDate&gt;&lt;/Account&gt;&lt;CardType&gt;M/C&lt;/CardType&gt;&lt;CVVData&gt;123&lt;/CVVData&gt;&lt;AVS&gt;&lt;Address&gt;1234 Street&lt;/Address&gt;&lt;zip&gt;98004&lt;/zip&gt;&lt;/AVS&gt;&lt;/Transaction&gt;&lt;/TStream&gt;
</tran><pw>xyz</pw></CreditTransaction></soap:Body></soap:Envelope>
';
    }

    private function successful_purchase_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CreditTransactionResponse xmlns="http://www.mercurypay.com"><CreditTransactionResult>&lt;?xml version="1.0"?&gt;
&lt;RStream&gt;
	&lt;CmdResponse&gt;
		&lt;ResponseOrigin&gt;Processor&lt;/ResponseOrigin&gt;
		&lt;DSIXReturnCode&gt;000000&lt;/DSIXReturnCode&gt;
		&lt;CmdStatus&gt;Approved&lt;/CmdStatus&gt;
		&lt;TextResponse&gt;AP&lt;/TextResponse&gt;
		&lt;UserTraceData&gt;&lt;/UserTraceData&gt;
	&lt;/CmdResponse&gt;
	&lt;TranResponse&gt;
		&lt;MerchantID&gt;595901&lt;/MerchantID&gt;
		&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;
		&lt;ExpDate&gt;1113&lt;/ExpDate&gt;
		&lt;CardType&gt;M/C&lt;/CardType&gt;
		&lt;TranCode&gt;Sale&lt;/TranCode&gt;
		&lt;AuthCode&gt;000015&lt;/AuthCode&gt;
		&lt;CaptureStatus&gt;Captured&lt;/CaptureStatus&gt;
		&lt;RefNo&gt;0001&lt;/RefNo&gt;
		&lt;InvoiceNo&gt;1108870343&lt;/InvoiceNo&gt;
		&lt;CVVResult&gt;M&lt;/CVVResult&gt;
		&lt;Amount&gt;
			&lt;Purchase&gt;10.00&lt;/Purchase&gt;
			&lt;Authorize&gt;10.00&lt;/Authorize&gt;
		&lt;/Amount&gt;
		&lt;AcqRefData&gt;KbMCC1000171007  &lt;/AcqRefData&gt;
		&lt;ProcessData&gt;|00|410100701000&lt;/ProcessData&gt;
	&lt;/TranResponse&gt;
&lt;/RStream&gt;
</CreditTransactionResult></CreditTransactionResponse></soap:Body></soap:Envelope>';
    }

    private function decline_purchase_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CreditTransactionResponse xmlns="http://www.mercurypay.com"><CreditTransactionResult>&lt;?xml version="1.0"?&gt;
&lt;RStream&gt;
	&lt;CmdResponse&gt;
		&lt;ResponseOrigin&gt;Processor&lt;/ResponseOrigin&gt;
		&lt;DSIXReturnCode&gt;000000&lt;/DSIXReturnCode&gt;
		&lt;CmdStatus&gt;Declined&lt;/CmdStatus&gt;
		&lt;TextResponse&gt;DECLINE&lt;/TextResponse&gt;
		&lt;UserTraceData&gt;&lt;/UserTraceData&gt;
	&lt;/CmdResponse&gt;
	&lt;TranResponse&gt;
		&lt;MerchantID&gt;595901&lt;/MerchantID&gt;
		&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;
		&lt;ExpDate&gt;0115&lt;/ExpDate&gt;
		&lt;CardType&gt;M/C&lt;/CardType&gt;
		&lt;TranCode&gt;Sale&lt;/TranCode&gt;
		&lt;RefNo&gt;REF2117314507&lt;/RefNo&gt;
		&lt;InvoiceNo&gt;271416582&lt;/InvoiceNo&gt;
		&lt;Amount&gt;
			&lt;Purchase&gt;100.00&lt;/Purchase&gt;
			&lt;Authorize&gt;100.00&lt;/Authorize&gt;
		&lt;/Amount&gt;
		&lt;AcqRefData&gt;KbMCC0559111007  &lt;/AcqRefData&gt;
		&lt;ProcessData&gt;|00|410100701000&lt;/ProcessData&gt;
	&lt;/TranResponse&gt;
&lt;/RStream&gt;
</CreditTransactionResult></CreditTransactionResponse></soap:Body></soap:Envelope>';
    }

    private function successful_authorize_request($order_id)
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Body><CreditTransaction xmlns="http://www.mercurypay.com"><tran>&lt;?xml version=&quot;1.0&quot; encoding=&quot;utf-8&quot;?&gt;
&lt;TStream&gt;&lt;Transaction&gt;&lt;TranType&gt;Credit&lt;/TranType&gt;&lt;TranCode&gt;PreAuth&lt;/TranCode&gt;&lt;PartialAuth&gt;Allow&lt;/PartialAuth&gt;&lt;InvoiceNo&gt;'.$order_id.'&lt;/InvoiceNo&gt;&lt;RefNo&gt;'.$order_id.'&lt;/RefNo&gt;&lt;Memo&gt;Mercury Test Transaction&lt;/Memo&gt;&lt;MerchantID&gt;595901&lt;/MerchantID&gt;&lt;Amount&gt;&lt;Purchase&gt;10.00&lt;/Purchase&gt;&lt;Authorize&gt;10.00&lt;/Authorize&gt;&lt;/Amount&gt;&lt;Account&gt;&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;&lt;ExpDate&gt;1113&lt;/ExpDate&gt;&lt;/Account&gt;&lt;CardType&gt;M/C&lt;/CardType&gt;&lt;CVVData&gt;123&lt;/CVVData&gt;&lt;AVS&gt;&lt;Address&gt;1234 Street&lt;/Address&gt;&lt;zip&gt;98004&lt;/zip&gt;&lt;/AVS&gt;&lt;/Transaction&gt;&lt;/TStream&gt;
</tran><pw>xyz</pw></CreditTransaction></soap:Body></soap:Envelope>
';
    }

    private function successful_authorize_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CreditTransactionResponse xmlns="http://www.mercurypay.com"><CreditTransactionResult>&lt;?xml version="1.0"?&gt;
&lt;RStream&gt;
	&lt;CmdResponse&gt;
		&lt;ResponseOrigin&gt;Processor&lt;/ResponseOrigin&gt;
		&lt;DSIXReturnCode&gt;000000&lt;/DSIXReturnCode&gt;
		&lt;CmdStatus&gt;Approved&lt;/CmdStatus&gt;
		&lt;TextResponse&gt;AP&lt;/TextResponse&gt;
		&lt;UserTraceData&gt;&lt;/UserTraceData&gt;
	&lt;/CmdResponse&gt;
	&lt;TranResponse&gt;
		&lt;MerchantID&gt;595901&lt;/MerchantID&gt;
		&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;
		&lt;ExpDate&gt;1113&lt;/ExpDate&gt;
		&lt;CardType&gt;M/C&lt;/CardType&gt;
		&lt;TranCode&gt;PreAuth&lt;/TranCode&gt;
		&lt;AuthCode&gt;000015&lt;/AuthCode&gt;
		&lt;InvoiceNo&gt;4852831525&lt;/InvoiceNo&gt;
		&lt;CVVResult&gt;M&lt;/CVVResult&gt;
		&lt;Memo&gt;Mercury Test Transaction&lt;/Memo&gt;
		&lt;Amount&gt;
			&lt;Purchase&gt;10.00&lt;/Purchase&gt;
			&lt;Authorize&gt;10.00&lt;/Authorize&gt;
		&lt;/Amount&gt;
		&lt;AcqRefData&gt;KbMCC1224211007  e00&lt;/AcqRefData&gt;
		&lt;ProcessData&gt;|14|410100701000&lt;/ProcessData&gt;
	&lt;/TranResponse&gt;
&lt;/RStream&gt;
</CreditTransactionResult></CreditTransactionResponse></soap:Body></soap:Envelope>';
    }

    private function successful_capture_request()
    {
        return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Body><CreditTransaction xmlns="http://www.mercurypay.com"><tran>&lt;?xml version=&quot;1.0&quot; encoding=&quot;utf-8&quot;?&gt;
&lt;TStream&gt;&lt;Transaction&gt;&lt;TranType&gt;Credit&lt;/TranType&gt;&lt;TranCode&gt;PreAuthCapture&lt;/TranCode&gt;&lt;PartialAuth&gt;Allow&lt;/PartialAuth&gt;&lt;TranCode&gt;PreAuthCapture&lt;/TranCode&gt;&lt;InvoiceNo&gt;1108870343&lt;/InvoiceNo&gt;&lt;RefNo&gt;1108870343&lt;/RefNo&gt;&lt;Memo&gt;Mercury Test Transaction&lt;/Memo&gt;&lt;MerchantID&gt;595901&lt;/MerchantID&gt;&lt;Amount&gt;&lt;Purchase&gt;10.00&lt;/Purchase&gt;&lt;Authorize&gt;10.00&lt;/Authorize&gt;&lt;/Amount&gt;&lt;Account&gt;&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;&lt;ExpDate&gt;1113&lt;/ExpDate&gt;&lt;/Account&gt;&lt;CardType&gt;M/C&lt;/CardType&gt;&lt;CVVData&gt;123&lt;/CVVData&gt;&lt;AVS&gt;&lt;Address&gt;1234 Street&lt;/Address&gt;&lt;zip&gt;98004&lt;/zip&gt;&lt;/AVS&gt;&lt;TranInfo&gt;&lt;AuthCode&gt;000015&lt;/AuthCode&gt;&lt;/TranInfo&gt;&lt;/Transaction&gt;&lt;/TStream&gt;
</tran><pw>xyz</pw></CreditTransaction></soap:Body></soap:Envelope>
';
    }

    private function successful_capture_response()
    {
       return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CreditTransactionResponse xmlns="http://www.mercurypay.com"><CreditTransactionResult>&lt;?xml version="1.0"?&gt;
&lt;RStream&gt;
	&lt;CmdResponse&gt;
		&lt;ResponseOrigin&gt;Processor&lt;/ResponseOrigin&gt;
		&lt;DSIXReturnCode&gt;000000&lt;/DSIXReturnCode&gt;
		&lt;CmdStatus&gt;Approved&lt;/CmdStatus&gt;
		&lt;TextResponse&gt;AP&lt;/TextResponse&gt;
		&lt;UserTraceData&gt;&lt;/UserTraceData&gt;
	&lt;/CmdResponse&gt;
	&lt;TranResponse&gt;
		&lt;MerchantID&gt;595901&lt;/MerchantID&gt;
		&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;
		&lt;ExpDate&gt;1113&lt;/ExpDate&gt;
		&lt;CardType&gt;M/C&lt;/CardType&gt;
		&lt;TranCode&gt;PreAuthCapture&lt;/TranCode&gt;
		&lt;AuthCode&gt;000015&lt;/AuthCode&gt;
		&lt;CaptureStatus&gt;Captured&lt;/CaptureStatus&gt;
		&lt;RefNo&gt;0207&lt;/RefNo&gt;
		&lt;InvoiceNo&gt;1108870343&lt;/InvoiceNo&gt;
		&lt;Amount&gt;
			&lt;Purchase&gt;10.00&lt;/Purchase&gt;
			&lt;Authorize&gt;10.00&lt;/Authorize&gt;
		&lt;/Amount&gt;
		&lt;AcqRefData&gt;bMCC0917151007  &lt;/AcqRefData&gt;
		&lt;ProcessData&gt;|15|410100200000&lt;/ProcessData&gt;
	&lt;/TranResponse&gt;
&lt;/RStream&gt;
</CreditTransactionResult></CreditTransactionResponse></soap:Body></soap:Envelope>';
    }

    private function successful_void_request()
    {
       return '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><soap:Body><CreditTransaction xmlns="http://www.mercurypay.com"><tran>&lt;?xml version=&quot;1.0&quot; encoding=&quot;utf-8&quot;?&gt;
&lt;TStream&gt;&lt;Transaction&gt;&lt;TranType&gt;Credit&lt;/TranType&gt;&lt;TranCode&gt;VoidSale&lt;/TranCode&gt;&lt;TranCode&gt;VoidSale&lt;/TranCode&gt;&lt;InvoiceNo&gt;4852831525&lt;/InvoiceNo&gt;&lt;RefNo&gt;4852831525&lt;/RefNo&gt;&lt;Memo&gt;Mercury Test Transaction&lt;/Memo&gt;&lt;MerchantID&gt;595901&lt;/MerchantID&gt;&lt;Amount&gt;&lt;Purchase&gt;10.00&lt;/Purchase&gt;&lt;/Amount&gt;&lt;Account&gt;&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;&lt;ExpDate&gt;1113&lt;/ExpDate&gt;&lt;/Account&gt;&lt;CardType&gt;M/C&lt;/CardType&gt;&lt;CVVData&gt;123&lt;/CVVData&gt;&lt;AVS&gt;&lt;Address&gt;1234 Street&lt;/Address&gt;&lt;zip&gt;98004&lt;/zip&gt;&lt;/AVS&gt;&lt;TranInfo&gt;&lt;AuthCode&gt;000015&lt;/AuthCode&gt;&lt;AcqRefData&gt;KbMCC1224211007  e00&lt;/AcqRefData&gt;&lt;ProcessData&gt;|14|410100701000&lt;/ProcessData&gt;&lt;/TranInfo&gt;&lt;/Transaction&gt;&lt;/TStream&gt;
</tran><pw>xyz</pw></CreditTransaction></soap:Body></soap:Envelope>
';
    }

    private function successful_void_response()
    {
        return '<?xml version="1.0" encoding="utf-8"?><soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"><soap:Body><CreditTransactionResponse xmlns="http://www.mercurypay.com"><CreditTransactionResult>&lt;?xml version="1.0"?&gt;
&lt;RStream&gt;
	&lt;CmdResponse&gt;
		&lt;ResponseOrigin&gt;Processor&lt;/ResponseOrigin&gt;
		&lt;DSIXReturnCode&gt;000000&lt;/DSIXReturnCode&gt;
		&lt;CmdStatus&gt;Approved&lt;/CmdStatus&gt;
		&lt;TextResponse&gt;REVERSED&lt;/TextResponse&gt;
		&lt;UserTraceData&gt;&lt;/UserTraceData&gt;
	&lt;/CmdResponse&gt;
	&lt;TranResponse&gt;
		&lt;MerchantID&gt;595901&lt;/MerchantID&gt;
		&lt;AcctNo&gt;5499990123456781&lt;/AcctNo&gt;
		&lt;ExpDate&gt;1113&lt;/ExpDate&gt;
		&lt;CardType&gt;M/C&lt;/CardType&gt;
		&lt;TranCode&gt;VoidSale&lt;/TranCode&gt;
		&lt;AuthCode&gt;000015&lt;/AuthCode&gt;
		&lt;CaptureStatus&gt;Captured&lt;/CaptureStatus&gt;
		&lt;RefNo&gt;4852831525&lt;/RefNo&gt;
		&lt;InvoiceNo&gt;4852831525&lt;/InvoiceNo&gt;
		&lt;Memo&gt;Mercury Test Transaction&lt;/Memo&gt;
		&lt;Amount&gt;
			&lt;Purchase&gt;10.00&lt;/Purchase&gt;
			&lt;Authorize&gt;10.00&lt;/Authorize&gt;
		&lt;/Amount&gt;
		&lt;AcqRefData&gt;K&lt;/AcqRefData&gt;
	&lt;/TranResponse&gt;
&lt;/RStream&gt;
</CreditTransactionResult></CreditTransactionResponse></soap:Body></soap:Envelope>';
    }
}
