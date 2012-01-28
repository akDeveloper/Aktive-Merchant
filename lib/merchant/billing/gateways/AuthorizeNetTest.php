<?php
class Merchant_Billing_AuthorizeNetTest extends Merchant_Billing_AuthorizeNet {
  protected static $URL = "https://test.authorize.net/gateway/transact.dll";
  protected static $ARB_URL = 'https://apitest.authorize.net/xml/v1/request.api';
}