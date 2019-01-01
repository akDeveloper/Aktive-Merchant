* __1.1.4__ (2019-01-01)
    * Fix typo for Trexle and Stripe gateways.
* __1.1.3__ (2019-01-01)
    * Fix typo in creditcard for Pin Payments.
* __1.1.2__ (2017-05-03)
  * Add Trexle gateway ( @hossamhossny )
* __1.1.1__ (2016-07-19)
  * Fix live urls for Cardlink and Alphabank gateways.
* __1.1.0__ (2016-05-18)
  * Added Datacash gateway (direct and 3D secure)
  * Added NBG gateway, based on Datacash implementation.
  * Added Psigate gateway from develop branch as a working gateway.
  * Added Eurobank (Cardlink) support through Modirum gateway.
  * Added Alpha bank support through Modirum gateway.
  * Added Pin payments gateway.
  * Added Stripe gateway.
  * Added Beanstream gateway.
* __1.0.17__ (2015-12-22)
  * Added Ticket method to PiraeusPaycenter gateway.
  * Added new Gateway for PiraeusPaycenter redirection.
* __1.0.16__ (2014-12-19)
  * Add ExpirePreauth element for authorize in PiraeusPaycenter gateway.
* __1.0.15__ (2014-11-21)
  * Minor fixes to Centinel and PiraeusPaycenter gateways.
* __1.0.14__ (2014-11-20)
  * Fix ssl version on curl for CentinelPaypal.
* __1.0.13__ (2014-11-16)
  * Allow transparent setting options to adapter.
* __1.0.12__ (2014-07-16)
  * Added Iridium payment gateway ( @dimitrisGiannakakis )
* __1.0.11__ (2014-05-22)
  * Added Bridge Pay payment gateway ( @dimitrisGiannakakis )
* __1.0.10__ (2014-05-08)
  * Added WorldPay payment gateway ( @tomglue )
* __1.0.9__ (2013-10-08)
  * Added Exact payment gateway
  * Added Mercury payment gateway
  * Fix Paypal express authorization ( @wakeless-net )
* __1.0.8__ (2013-07-16)
  * Hide user login info from fixtures.ini. Added fixtures.ini dist which should be copied as fixtures.ini
  * Fix FatZebra recurring bug
  * Add Inflect class
  * Improve Eway gateway ( @wakeless-net )
  * Fix bugs in Realex gateway and add 3d secure integration ( @tomglue )
* __1.0.7__ (2013-04-29)
  * Added Fat Zebra Gateway
  * Fix minor bugs
* __1.0.6__ (2013-02-16)
  * Implement Hsbc Global Iris gateway ( @tomglue )
  * Implement Moneris payment gateway
  * Add capture, credit, void and recurring to PaypalExpress gateway
  * Introduce Options class for easy handling options
  * Introduce Address class for mapping address fields for gateway
  * Introduce AdapterInterface for setting custom options to a request.
* __1.0.5__ (2012-12-03)
  * Add support for Paypal Payflow UK ( @tomglue )
* __1.0.4__ (2012-11-26)
  * Add support for Centinel 3D secure and Paypal Payflow ( @tomglue )
* __1.0.3__ (2012-11-10)
* __1.0.2__ (2012-11-08)
* __1.0.1__ (2012-11-08)
* __1.0.0__ (2012-10-05)
  * Initial release
