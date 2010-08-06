<?php
#!/usr/bin/php
if(!$_SERVER['SHELL']) trigger_error("This script should be run from command line only.", E_USER_ERROR);

/**
 * Neevo - Tiny open-source database abstraction layer for PHP
 *
 * Copyright 2010 Martin Srank (http://smasty.net)
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file license.txt.
 *
 * @author   Martin Srank (http://smasty.net)
 * @license  http://www.opensource.org/licenses/mit-license.php  MIT license
 * @link     http://labs.smasty.net/neevo/
 * @package  compiler
 *
 */

// Default file to process
define("DEFAULT_FILE", "neevo.php");

// PhpDoc builder path
define("PHPDOC_PATH", "http://localhost/phpdoc/docbuilder/builder.php?setting_useconfig=neevo&interface=web&dataform=true");


if($_SERVER['SHELL'])
  $args = $_SERVER['argv'];

if($args[0] == basename(__FILE__))
  unset ($args[0]);

echo "\n";

foreach ($args as $key => $value) {
  $match = preg_match("#([0-9a-z-_]){1}\.php#i", $value);
  if($match && file_exists($value)) $file = $value;
  else $file = DEFAULT_FILE;
}

if(in_array('rev+', $args)) // Increment Revision number
  echo rev_number(1, $file);

if(in_array('rev-', $args)) // Decrement Revision number
  echo rev_number(-1, $file);

if(in_array('doc', $args)) // Generate PHPDoc TODO: add config
  echo phpdoc();

if(in_array('min', $args)) // Minify file
  echo minify($file);

echo "\n";


function phpdoc(){
  $response = file_get_contents(PHPDOC_PATH);
  if(strstr($response, "<h1>Operation Completed!!</h1>"))
    $response = "PHPDoc generated successfuly";
  else $response = "Error: PHPDoc generation failed";

  return "$response\n";
}


function rev_number($i, $file){
  $source = file_get_contents($file);
  global $inc;
  $inc = $i;
  $newsource = preg_replace_callback("#const REVISION = (\d+);#", "rev_number_callback", $source);
  $x = file_put_contents($file, $newsource);
  $response = $x ? "Revision number successfuly changed" : "Error: Revision number change failed";
  return "$response (File: '$file')\n";
}


function rev_number_callback($n){
  global $inc;
  $res = $n[1]+$inc;
  return "const REVISION = $res;";
}


function minify($file){
  $path = pathinfo($file);
  $result_file = $path['dirname']."/".$path['filename'].".minified.".$path['extension'];
  $source = preg_replace_callback('~include "([^"]+)";~', 'include_file', file_get_contents($file));
  $source = str_replace(array("<?php", "?>"), "", $source);
  $source = "<?php\n$source\n?>";
  $result = php_shrink($source);
  $x = file_put_contents($result_file, $result);
  //highlight_string($result);
  $response =  $x ? "Project minified successfuly!" : "Error: Project minification failed!";
  return "$response (File: '$file')\n";
}


/**
 * Core minify functions (include_file, short_identifier and php_shrink) used in this
 * script are written by Jakub Vrana (http://php.vrana.cz) and extracted from
 * his open-soure "Compact MySQL management" - Adminer (http://adminer.org)
 * released under Apache license 2.0.
 */

/**
 * Include source from file
 * @param string $match File to include
 * @return string
 * @copyright Jakub Vrana, http://php.vrana.cz. Used with permission.
 */
function include_file($match) {
  $file = file_get_contents($match[1]);
  $token = end(token_get_all($file));
  $php = (is_array($token) && in_array($token[0], array(T_CLOSE_TAG, T_INLINE_HTML)));
  $file = "// FILE = ".basename($match[1]).$file;
  return "?>\n$file" . ($php ? "<?php" : "");
}


/**
 * Create short alpha identifier for number.
 *
 * Part of Adminer - "Compact MySQL management", http://adminer.org
 * @param int $number
 * @param string $chars Available chars
 * @return string
 * @copyright Jakub Vrana, http://php.vrana.cz. Used with permission.
 */
function short_identifier($number, $chars) {
	$return = '';
	while ($number >= 0) {
		$return .= $chars{$number % strlen($chars)};
		$number = floor($number / strlen($chars)) - 1;
	}
	return $return;
}

/**
 * Shrinks PHP code.
 *
 * Part of Adminer - "Compact MySQL management", http://adminer.org
 * Based on http://latrine.dgx.cz/jak-zredukovat-php-skripty
 * @param string $input Input PHP code
 * @return string
 *  @copyright Jakub Vrana, http://php.vrana.cz. Used with permission.
 */
function php_shrink($input) {
	$special_variables = array_flip(array('$this', '$GLOBALS', '$_GET', '$_POST', '$_FILES', '$_COOKIE', '$_SESSION', '$_SERVER'));
	$short_variables = array();
	$shortening = false;
	$tokens = token_get_all($input);

	foreach ($tokens as $i => $token) {
		if ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
			$short_variables[$token[1]]++;
		}
	}

	arsort($short_variables);
	foreach (array_keys($short_variables) as $number => $key) {
		$short_variables[$key] = short_identifier($number, implode("", range('a', 'z')) . '_' . implode("", range('A', 'Z'))); // could use also numbers and \x7f-\xff
	}

	$set = array_flip(preg_split('//', '!"#$&\'()*+,-./:;<=>?@[\]^`{|}'));
	$space = '';
	$output = '';
	$in_echo = false;
	$doc_comment = false; // include only first /**
	for (reset($tokens); list($i, $token) = each($tokens); ) {
		if (!is_array($token)) {
			$token = array(0, $token);
		}
		if ($tokens[$i+2][0] === T_CLOSE_TAG && $tokens[$i+3][0] === T_INLINE_HTML && $tokens[$i+4][0] === T_OPEN_TAG
			&& strlen(addcslashes($tokens[$i+3][1], "'\\")) < strlen($tokens[$i+3][1]) + 3
		) {
			$tokens[$i+2] = array(T_ECHO, 'echo');
			$tokens[$i+3] = array(T_CONSTANT_ENCAPSED_STRING, "'" . addcslashes($tokens[$i+3][1], "'\\") . "'");
			$tokens[$i+4] = array(0, ';');
		}
		if ($token[0] == T_COMMENT || $token[0] == T_WHITESPACE || ($token[0] == T_DOC_COMMENT && $doc_comment)) {
			$space = "\n";
		} else {
			if ($token[0] == T_DOC_COMMENT) {
				$doc_comment = true;
			}
			if ($token[0] == T_VAR) {
				$shortening = false;
			} elseif (!$shortening) {
				if ($token[1] == ';') {
					$shortening = false;
				}
			} elseif ($token[0] == T_ECHO) {
				$in_echo = true;
			} elseif ($token[1] == ';' && $in_echo) {
				if ($tokens[$i+1][0] === T_WHITESPACE && $tokens[$i+2][0] === T_ECHO) {
					next($tokens);
					$i++;
				}
				if ($tokens[$i+1][0] === T_ECHO) {
					// join two consecutive echos
					next($tokens);
					$token[1] = ','; // '.' would conflict with "a".1+2 and would use more memory //! remove ',' and "," but not $var","
				} else {
					$in_echo = false;
				}
			} elseif ($token[0] === T_VARIABLE && !isset($special_variables[$token[1]])) {
				$token[1] = '$' . $short_variables[$token[1]];
			}
			if (isset($set[substr($output, -1)]) || isset($set[$token[1]{0}])) {
				$space = '';
			}
			$output .= $space . $token[1];
			$space = '';
		}
	}
	return $output;
}
?>
