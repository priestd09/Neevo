--TEST
Result manipulating
--CODE
<?php

$author = db()->select('id')->from('author')->where('name', 'Martin Srank')->fetchSingle();
$result = db()->select('*')->from('software')->where('aid', $author)->orderBy('id ASC')->fetch();

$row = $result[0];
$row->slogan = 'Tiny database abstraction layer '.rand(0, 999);
$affected = $row->update();

$current = db()->select('slogan')
               ->from('software')
               ->where('slogan LIKE', 'Tiny database abstraction layer%')
               ->rows();

echo "Selected ".count($result)." rows.\n";
echo "Updated $affected rows.\n";
echo "Selected: $current\n";

?>
--RESULT
Selected 2 rows.
Updated 1 rows.
Selected: 1
