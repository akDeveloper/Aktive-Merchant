<?php
/**
 * Description of Error
 *
 * @author Andreas Kollaros
 */
class Merchant_Error {
  private $errors = array();

  public function add($field, $message) {
     $this->errors[$field] = $message;
  }

  public function errors() {
    return $this->errors;
  }
}
?>
