# Aktive Merchant for PHP

This project is a PHP port of [Active Merchant](http://github.com/Shopify/active_merchant) library from Ruby.

The aim is to develop a PHP application to includes payment gateways under common interfaces.

## Supported Gateways

* [Authorize.net](http://www.authorize.net)
* [Bridge Pay](http://www.bridgepaynetwork.com/)
* [CardStream](http://www.cardstream.com)
* [Centinel 3D Secure](http://www.cardinalcommerce.com)
* [Eway](http://www.eway.com.au/)
* [Fat Zebra](https://www.fatzebra.com.au)
* [E-xact](http://www.e-xact.com)
* [Hsbc Secure e-Payment](http://www.hsbc.co.uk/1/2/business/cards-payments/secure-epayments)
* [Mercury](http://www.mercurypay.com)
* [Moneris](http://www.moneris.com)
* [Moneris US](http://www.monerisusa.com)
* [PayPal Payflow Pro](https://www.paypal.com/cgi-bin/webscr?cmd=_payflow-pro-overview-outside)
* [PayPal Payflow Pro Uk](https://www.paypal.com/uk/cgi-bin/webscr?cmd=_wp-pro-overview-outside)
* [Paypal Express Checkout](https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_ECGettingStarted)
* [PayPal Website Payments Pro](https://merchant.paypal.com/cgi-bin/marketingweb?cmd=_render-content&content_ID=merchant/wp_pro)
* [Piraeus Paycenter](http://www.piraeusbank.gr)
* [Realex](http://www.realexpayments.com)
* [WorldPay](http://www.worldpay.com)

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
