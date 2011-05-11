# Aktive Merchant for PHP

This project is a php port of Ruby's [Active Merchant](http://github.com/Shopify/active_merchant) library.

The aim is to develop a PHP application to includes payments gateway under a common interface.

## Supported Gateways

* [Authorize.net](http://www.authorize.net)
* [Centinel 3D Secure](http://www.cardinalcommerce.com)
* [Eurobank Payment](http://www.eurobank.gr/online/home/generic.aspx?id=79&mid=635)
* [Hsbc Secure e-Payment](http://www.hsbc.co.uk/1/2/business/cards-payments/secure-epayments)
* [Paypal Express Checkout](https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECGettingStarted)
* [PayPal Website Payments Pro](https://merchant.paypal.com/cgi-bin/marketingweb?cmd=_render-content&content_ID=merchant/wp_pro)
* [PayPal Payflow Pro](https://www.paypal.com/cgi-bin/webscr?cmd=_payflow-pro-overview-outside)
* [Barclay's ePDQ Gateway](http://www.barclaycard.co.uk)
* [Realex](http://www.realexpayments.com)
* [Piraeus Paycenter](http://www.piraeusbank.gr)

## Requirements

* PHP 5 ( Test it on php 5.2.13 )
* cUrl
* SimpleXML

## Usage

    require_once('path/to/lib/merchant.php');

    Merchant_Billing_Base::mode('test'); # Remove this on production mode

    try {

      $gateway = new Merchant_Billing_YourPaymentGateway( array(
        'login' => 'login_id',
        'password' => 'password'
      ));

      # Create a credit card object if you need it.
      $credit_card = new Merchant_Billing_CreditCard( array(
        "first_name" => "John",
        "last_name" => "Doe",
        "number" => "41111111111111",
        "month" => "12",
        "year" => "2012",
        "verification_value" => "123"
        )
      );

      # Extra options for transaction
      $options = array(
        'order_id' => 'REF' . $gateway->generate_unique_id(),
        'description' => 'Test Transaction',
        'address' => array(
          'address1' => '1234 Street',
          'zip' => '98004',
          'state' => 'WA'
        )
      );

      if ( $credit_card->is_valid() ) {

        # Authorize transaction
        $response = $gateway->authorize('100', $credit_card, $options);
        if ( $response->success() ) {
          echo 'Success Authorize';
        } else {
          echo $response->message();
        }
      }
    } catch (Exception $e) {
      echo $e->getMessage();
    }
