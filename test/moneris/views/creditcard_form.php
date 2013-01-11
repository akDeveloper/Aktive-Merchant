<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="Content-Language" content="us" />
    <title>Moneris Payment Gateway - Demo</title>
      <style type="text/css" media=screen>
      <!--
        body{ font-size: 12px; font-family: Arial, Tahoma, Verdana, sans-serif; }
        input,select{font-family: Arial, Tahoma, Verdana, sans-serif;float:left;}
        label{ float:left; width: 200px;}
        input{ float:left;}
        span{ float:left;}
        p {margin: 5px 0px;}
        .clearfloat { clear:both; height:0;font-size: 1px; line-height: 0px;}
        .clearfix:after { content: "."; display: block; clear: both; visibility: hidden; line-height: 0; height: 0;}
        .clearfix {display: inline-block;}
        html[xmlns] .clearfix {display: block;}
        * html .clearfix {height: 1%;}
      -->
      </style>
  </head>
  <body>
    <h1>Moneris Payment Gateway - Demo</h1>
    <form method="post" name="moneris-form" action="index.php" class="clearfix">
      <p class="clearfix">
      <label for="pay-action">Action: </label>
      <select name="pay-action" id="pay-action">
        <option value="PreAuth">PreAuth</option>
        <option value="Purchase">Purchase</option>
        <option value="Capture">Capture</option>
        <option value="Void">Void</option>
        <option value="Credit">Credit</option>
      </select>
      </p>
      <p class="clearfix">
      <label for="txn_number">Transaction Number: </label>
      <input type="text" name="txn_number" id="txn_number" value=""/>
      <span>(Only for actions Capture, Void and Credit. Must be the Transaction Number returned from PreAuth or Purchase action)</span>
      </p>
      <p class="clearfix">
      <label for="order_id">Order ID: </label>
      <input type="text" name="order_id" id="order_id" value="<?php echo $gateway->generateUniqueId() ?>" />
      <span>(Unique for every PreAuth, Purchase action)</span>
      </p>
      <p class="clearfix">
      <label for="first_name">First name: </label>
      <input type="text" name="first_name" id="first_name" value="John"/>
      </p>
      <p class="clearfix">
      <label for="last_name">Last name: </label>
      <input type="text" name="last_name" id= "last_name" value="Doe"/>
      </p>
      <p class="clearfix">
      <label for="card_number">Card number: </label>
      <input type="text" id="card_number" name="card_number" value="4242424242424242"/>
      </p>
      <p class="clearfix">
      <label for="verification_value">CVV: </label>
      <input type="text" name="verification_value" id= "verification_value" value="000" size="3" maxlength="3"/>
      </p>
      <p class="clearfix">
      <label for="month">Month: </label>
      <select name="month" id="month">
        <?php for ($i=1; $i<=12; $i++): ?>
        <option value="<?php echo $i?>"><?php echo $i?></option>
        <?php endfor;?>
      </select>
      </p>
      <p class="clearfix">
      <label for="year">Year: </label>
      <select name="year" id="year">
       <?php for ($i=2011; $i<=2020; $i++): ?>
       <option value="<?php echo $i?>"><?php echo $i?></option>
       <?php endfor;?>
      </select>
      </p>
      <p class="clearfix">
      <label for="amount">Amount: </label>
      <input type="text" name="amount" id="amount" value="10"/>
      </p>
      <p class="clearfix">Buyer Address (Optional)</p>
      <p class="clearfix">
      <label for="address_name">Name: </label>
      <input type="text" name="address[name]" id="address_name" value="John Dows"/>
      </p>
      <p class="clearfix">
      <label for="address_address1">Address: </label>
      <input type="text" name="address[address1]" id="address_address1" value="1 Main St"/>
      </p>
      <p class="clearfix">
      <label for="address_zip">Zip: </label>
      <input type="text" name="address[zip]" id="address_zip" value="95131"/>
      </p>
      <p class="clearfix">
      <label for="address_state">State: </label>
      <input type="text" name="address[state]" id="address_state" value="CA"/>
      </p>
      <p class="clearfix">
      <label for="address_country">Country: </label>
      <input type="text" name="address[country]" id="address_country" value="United States"/>
      </p>
      <p class="clearfix">
      <label for="address_city">City: </label>
      <input type="text" name="address[city]" id="address_city" value="San Jose"/>
      </p>
      <p class="clearfix">
      <label for="street_number">Street Number: </label>
      <input type="text" name="street_number" id="street_number" value="1"/>
      </p>
      <p class="clearfix">
      <label for="street_name">Street Name: </label>
      <input type="text" name="street_name" id="street_name" value="Main St"/>
      </p>
      <p class="clearfix">
      <input type="submit" name="submit" value="Submit"/>
      </p>
    </form>
  </body>
</html>
