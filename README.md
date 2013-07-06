# Aktive Merchant for PHP

This project is a PHP port of [Active Merchant](http://github.com/Shopify/active_merchant) library from Ruby.

The aim is to develop a PHP application to includes payment gateways under common interfaces.

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

## Documentations

    require_once('path/to/lib/autoload.php');
    // or require_once('path/to/vendor/autoload.php') if you use composer
    
    use AktiveMerchant\Billing\Base;
    use AktiveMerchant\Billing\CreditCard;
    
    Base::mode('test'); # Remove this on production mode

## Contributing

You can fork this project and implement new gateways.

Be sure that you checkout the `develop` branch, add your gateway and make your Pull Requests to
`develop` branch.
