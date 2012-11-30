# Aktive Merchant for PHP

This project is a php port of Ruby's [Active Merchant](http://github.com/Shopify/active_merchant) library.

The aim is to develop a PHP application to includes payments gateway under a common interface.

## Supported Gateways

* [Authorize.net](http://www.authorize.net)
* [CardStream](http://www.cardstream.com)
* [Centinel 3D Secure](http://www.cardinalcommerce.com)
* [Eway](http://www.eway.com.au/)
* [Hsbc Secure e-Payment](http://www.hsbc.co.uk/1/2/business/cards-payments/secure-epayments)
* [PayPal Payflow Pro](https://www.paypal.com/cgi-bin/webscr?cmd=_payflow-pro-overview-outside)
* [PayPal Payflow Pro Uk](https://www.paypal.com/uk/cgi-bin/webscr?cmd=_wp-pro-overview-outside)
* [Paypal Express Checkout](https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECGettingStarted)
* [PayPal Website Payments Pro](https://merchant.paypal.com/cgi-bin/marketingweb?cmd=_render-content&content_ID=merchant/wp_pro)
* [Piraeus Paycenter](http://www.piraeusbank.gr)
* [Realex](http://www.realexpayments.com)

## Requirements

* PHP 5.3.3+ 
* cUrl
* SimpleXML

## Usage

    require_once('path/to/lib/autoload.php');
    // or require_once('path/to/vendor/autoload.php') if you use composer
    
    use AktiveMerchant\Billing\Base;
    use AktiveMerchant\Billing\CreditCard;
    
    Base::mode('test'); # Remove this on production mode

    try {

      $gateway = new AktiveMerchant\Billing\Gateways\YourPaymentGateway( array(
        'login' => 'login_id',
        'password' => 'password'
      ));

      # Create a credit card object if you need it.
      $credit_card = new CreditCard( array(
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

      if ( $credit_card->isValid() ) {

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
