--TEST--
Testing conditional statements
--FILE--
<?php

// IF test
echo $db->select('software')
  ->if(true)->limit(1)
  ->else()->limit(2)
  ->end()->getLimit() === 1 ? "if ok\n" : "if failed\n";

// ELSE test
echo $db->select('software')
  ->if(false)->limit(1)
  ->else()->limit(2)
  ->end()->getLimit() === 2 ? "else ok\n" : "else failed\n";

?>
--EXPECT--
if ok
else ok
