<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$url = $_GET['url'];

// Validate and sanitize the URL input
if (!filter_var($url, FILTER_VALIDATE_URL)) {
  echo json_encode(['error' => 'Invalid URL']);
  exit;
}
$html = file_get_contents($url);
// Create a DOMDocument object and load the HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true); // Ignore any HTML parsing errors
$dom->loadHTML($html);
libxml_use_internal_errors(false);

// Create a DOMXPath object to query the DOM
$xpath = new DOMXPath($dom);
$bodynode = $xpath->query('//body');
$body = $bodynode ? $bodynode->textContent : '';
echo json_encode($body);


?>