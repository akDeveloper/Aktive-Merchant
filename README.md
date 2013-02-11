# Aktive Merchant for PHP

This project is a PHP port of [Active Merchant](http://github.com/Shopify/active_merchant) library from Ruby.

The aim is to develop a PHP application to includes payment gateways under common interfaces.

## Supported Gateways

* [Authorize.net](http://www.authorize.net)
* [Centinel 3D Secure](http://www.cardinalcommerce.com)
* [CardStream](http://www.cardstream.com)
* [Eurobank Payment](http://www.eurobank.gr/online/home/generic.aspx?id=79&mid=635)
* [Hsbc Secure e-Payment](http://www.hsbc.co.uk/1/2/business/cards-payments/secure-epayments)
* [Paypal Express Checkout](https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECGettingStarted)
* [PayPal Website Payments Pro](https://merchant.paypal.com/cgi-bin/marketingweb?cmd=_render-content&content_ID=merchant/wp_pro)
* [PayPal Payflow Pro](https://www.paypal.com/cgi-bin/webscr?cmd=_payflow-pro-overview-outside)
* [Barclay's ePDQ Gateway](http://www.barclaycard.co.uk)
* [Realex](http://www.realexpayments.com)
* [Piraeus Paycenter](http://www.piraeusbank.gr)

## Requirements

* PHP 5.3.3+ 
* cUrl
* SimpleXML

## Documentations

* [Basic usage](https://github.com/akDeveloper/Aktive-Merchant/wiki/Usage)
* Creating a gateway

## Contributing

You can fork this project and implement new gateways.

Be sure that you checkout the `develop` branch, add your gateway and make your Pull Requests to
`develop` branch.
