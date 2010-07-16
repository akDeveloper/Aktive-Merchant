<html>
  <body>
    <form method="post" name="centinel-form" action="cmpi_lookup_response.php">
      
      <label for="first_name">First name: </label>
      <input type="text" name="first_name" id="first_name" value="John"><br>
      <label for="last_name">Last name: </label>
      <input type="text" name="last_name" id= "last_name" value="Doe"><br>
      <label for="card_number">Card number: </label>
      <select name="card_number" id="card_number">
        <option value="4000000000000002">4000000000000002( Test case 1 )</option>
        <option value="4000000000000010">4000000000000010( Test case 2 )</option>
        <option value="4000000000000028">4000000000000028( Test case 3 )</option>
        <option value="4000000000000101">4000000000000101( Test case 4 )</option>
        <option value="4000000000000044">4000000000000044( Test case 5 )</option>
        <option value="4000000000000051">4000000000000051( Test case 6 )</option>
        <option value="4000000000000069">4000000000000069( Test case 7 )</option>
        <option value="4000000000000077">4000000000000077( Test case 8 )</option>
        <option value="4000000000000085">4000000000000085( Test case 9 )</option>
        <option value="4000000000000093">4000000000000093( Test case 10 )</option>
        <option value="4000000000000036">4000000000000036( Test case 11 )</option>
      </select>
      <br>      
      <label for="verification_value">CVV: </label>
      <input type="text" name="verification_value" id= "verification_value" value="000" size="3" maxlength="3"><br>
      <label for="month">Month: </label>
      <select name="month" id="month">
        <?php for ($i=1; $i<=12; $i++): ?>
        <option value="<?php echo $i?>"><?php echo $i?></option>
        <?php endfor;?>
      </select>
      <label for="year">Year: </label>
      <select name="year" id="year">
       <?php for ($i=2011; $i<=2020; $i++): ?>
       <option value="<?php echo $i?>"><?php echo $i?></option>
       <?php endfor;?>
      </select>
      <br>
      <input type="submit" name="submit-cmpi-lookup" value="Submit Lookup"> 
    </form>
  </body>
</html>
