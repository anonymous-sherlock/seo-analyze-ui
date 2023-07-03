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


// Extract the domain from the provided URL
$urlParts = parse_url($url);
$domain = $urlParts['host'];


// Function to fetch the HTML content of a URL
function fetchHTML($url)
{
  $options = array(
    'http' => array(
      'method' => 'GET',
      'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36'
    )
  );
  $context = stream_context_create($options);
  $html = file_get_contents($url, false, $context);
  return $html;
}

// Function to check for URL redirects
function checkURLRedirects($url)
{
  $headers = get_headers($url, 1);

  if (isset($headers['Location'])) {
    return is_array($headers['Location'])
      ? end($headers['Location'])
      : $headers['Location'];
  }
  return null;
}

// Function to check if robots.txt exists
function checkRobotsTxt($url)
{
  $robotsTxtUrl = rtrim($url, '/') . '/robots.txt';
  $headers = get_headers($robotsTxtUrl);
  if ($headers && strpos($headers[0], '200') !== false) {
    return true; // robots.txt exists and returns a 200 status code
  }
  return false; // robots.txt does not exist or returns a non-200 status code
}
// Function to check if the nofollow meta tag exists
function hasNofollowTag($html)
{
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($html);
  libxml_use_internal_errors(false);

  $metaTags = $dom->getElementsByTagName('meta');
  foreach ($metaTags as $metaTag) {
    if ($metaTag->getAttribute('name') === 'robots' && $metaTag->getAttribute('content') === 'nofollow') {
      return true; // nofollow meta tag exists
    }
  }
  return false; // nofollow meta tag does not exist
}
// Function to check if the noindex meta tag exists
function hasNoindexTag($html)
{
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($html);
  libxml_use_internal_errors(false);

  $metaTags = $dom->getElementsByTagName('meta');
  foreach ($metaTags as $metaTag) {
    if ($metaTag->getAttribute('name') === 'robots' && $metaTag->getAttribute('content') === 'noindex') {
      return true; // noindex meta tag exists
    }
  }
  return false; // noindex meta tag does not exist
}
// Fetch the HTML content of the provided URL
$html = fetchHTML($url);
// Check if the Robots.txt nofollow, noindex.
$hasRobotsTxt = checkRobotsTxt($url);
$hasNofollow = hasNofollowTag($html);
$hasNoindex = hasNoindexTag($html);
// Check for URL redirects
$redirects = checkURLRedirects($url);
// Calculate the page size in bytes
$pageSize = strlen($html);

// Create a DOMDocument object and load the HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true); // Ignore any HTML parsing errors
$dom->loadHTML($html);
libxml_use_internal_errors(false);

// Create a DOMXPath object to query the DOM
$xpath = new DOMXPath($dom);

// language
$language = $dom->documentElement->getAttribute('lang');
// title
$titleNode = $xpath->query('//title')->item(0);
$title = $titleNode ? $titleNode->textContent : '';
// favicon
$faviconNode = $xpath->query('//link[@rel="icon" or @rel="shortcut icon"]/@href')->item(0);
$favicon = $faviconNode ? $faviconNode->textContent : '';
// Headings
$headings = ['h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => []];

foreach ($headings as $heading => &$value) {
  $headingNodes = $xpath->query("//{$heading}");

  foreach ($headingNodes as $headingNode) {
    $text = $headingNode ? preg_replace('/\s+/', ' ', trim($headingNode->textContent)) : '';

    $value[] = $text;
  }
}
// meta description
$descriptionNode = $xpath->query('//meta[@name="description"]/@content')->item(0);
$description = $descriptionNode ? $descriptionNode->textContent : '';

// Calculate the DOM size Function to count the nodes in the DOM
function countNodes($node)
{
  $count = 0;

  if ($node->nodeType === XML_ELEMENT_NODE) {
    $count++;
  }

  if ($node->hasChildNodes()) {
    $children = $node->childNodes;
    foreach ($children as $child) {
      $count += countNodes($child);
    }
  }

  return $count;
}
// Calculate the DOM size (number of nodes)
$domSize = countNodes($dom->documentElement);
// Checking for Doctype 
$hasDoctype = strpos($html, '<!DOCTYPE html>') !== false;
// Extract the server signature from the response headers
$headers = get_headers($url, 1);
$serverSignature = isset($headers['Server']) ? $headers['Server'] : null;
// Initialize totalImageCount
$totalImageCount = 0;
// Extract images without alt attribute text and total images used on the website
$imagesWithoutAltText = [];
$imageNodes = $xpath->query('//img');
foreach ($imageNodes as $imageNode) {
  $src = $imageNode->getAttribute('src');
  if (!empty($src)) {
    // Check if the alt attribute is empty or not present
    $alt = $imageNode->getAttribute('alt');
    if (empty($alt)) {
      $imagesWithoutAltText[] = $src;
    }
    $totalImageCount++;
  }
}
// Extract internal links with link text
$internalLinks = [];
$internalLinkUrls = [];
$internalLinkNodes = $xpath->query('//a[not(starts-with(@href, "#"))]');
foreach ($internalLinkNodes as $linkNode) {
  $href = $linkNode->getAttribute('href');
  $text = trim(preg_replace('/\s+/', ' ', $linkNode->textContent));

  if (!empty($href) && !empty($text)) {
    // Check if $href is an absolute URL and belongs to the same domain
    if (filter_var($href, FILTER_VALIDATE_URL)) {
      $parsedHref = parse_url($href);

      if (isset($parsedHref['host']) && $parsedHref['host'] === parse_url($url, PHP_URL_HOST)) {
        $fullUrl = $href;
      } else {
        continue; // Skip external URLs
      }
    } else {
      $base = rtrim($url, '/');
      $separator = '/';
      if (substr($href, 0, 1) === '/') {
        $separator = '';
      }
      $fullUrl = $base . $separator . $href;
    }

    $lowercaseUrl = strtolower($fullUrl);

    // Check if the lowercase URL has already been added to the array
    $isInternalLink = isset($internalLinkUrls[$lowercaseUrl]);

    if (!$isInternalLink) {
      $internalLinks[] = [
        'url' => $fullUrl,
        'text' => $text
      ];

      // Add the lowercase URL to the list of added URLs
      $internalLinkUrls[$lowercaseUrl] = true;
    }
  }
}

// Extract external links with link text
$externalLinks = [];
$externalLinkNodes = $xpath->query('//a[not(starts-with(@href, "/")) and not(starts-with(@href, "#"))]');
foreach ($externalLinkNodes as $linkNode) {
  $href = $linkNode->getAttribute('href');
  $text = trim(preg_replace('/\s+/', ' ', $linkNode->textContent));

  if (empty($href) || empty($text)) {
    continue; // Skip if href or text is empty
  }

  $linkParts = parse_url($href);

  // Skip if URL parsing failed
  if (!$linkParts || !isset($linkParts['host'])) {
    continue;
  }
  $linkDomain = $linkParts['host'];

  // Normalize the link domain and current domain for comparison
  $normalizedLinkDomain = rtrim(strtolower($linkDomain), '/');
  $normalizedCurrentDomain = rtrim(strtolower($domain), '/');

  if ($normalizedLinkDomain === $normalizedCurrentDomain) {
    continue; // Skip if link belongs to the same domain
  }

  $href = rtrim($href, '/');

  // Check if the link is already added to internal or external links
  $isDuplicate = false;
  foreach ($internalLinks as $link) {
    if ($link['url'] === $href) {
      $isDuplicate = true;
      break;
    }
  }
  foreach ($externalLinks as $link) {
    if ($link['url'] === $href) {
      $isDuplicate = true;
      break;
    }
  }

  if (!$isDuplicate) {
    $externalLinks[] = [
      'url' => $href,
      'text' => $text
    ];
  }
}
// Function to retrieve the character encoding declaration
function getCharacterEncoding($html)
{
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($html);
  libxml_use_internal_errors(false);
  $metaTags = $dom->getElementsByTagName('meta');
  foreach ($metaTags as $metaTag) {
    if ($metaTag->hasAttribute('charset')) {
      return $metaTag->getAttribute('charset'); // Return the character encoding
    }
  }
  return null; // No character encoding declaration found
}
$characterEncoding = getCharacterEncoding($html);
// Function to check viewport meta tag content
function getViewportContent($html)
{
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($html);
  libxml_use_internal_errors(false);
  $metaTags = $dom->getElementsByTagName('meta');
  foreach ($metaTags as $metaTag) {
    if ($metaTag->hasAttribute('name') && $metaTag->getAttribute('name') === 'viewport') {
      return $metaTag->getAttribute('content');
    }
  }
  return null; // Viewport meta tag does not exist or does not match the desired attributes
}
// Function to check if the viewport meta tag exists
$viewportContent = getViewportContent($html);
// Function to get the canonical URL
function getCanonicalUrl($html)
{
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($html);
  libxml_use_internal_errors(false);

  $linkTags = $dom->getElementsByTagName('link');
  foreach ($linkTags as $linkTag) {
    if ($linkTag->getAttribute('rel') === 'canonical') {
      return $linkTag->getAttribute('href'); // Return the canonical URL
    }
  }

  return false; // Canonical URL not found
}
// Check if the canonical URL exists
$hasCanonicalUrl = getCanonicalUrl($html);
// Function to check if a sitemap exists and follow redirects
function checkSitemap($url)
{
  $sitemapUrl = rtrim($url, '/') . '/sitemap.xml';

  $options = array(
    'http' => array(
      'method' => 'HEAD',
      'follow_location' => true // Follow redirects
    )
  );
  $context = stream_context_create($options);
  $headers = get_headers($sitemapUrl, 1, $context);

  if (isset($headers['Location'])) {
    if (is_array($headers['Location'])) {
      return $headers['Location'][count($headers['Location']) - 1]; // Return the final URL after following redirects
    } else {
      return $headers['Location'];
    }
  }

  if ($headers && strpos($headers[0], '200') !== false) {
    return $sitemapUrl; // Sitemap exists and returns a 200 status code
  }

  return false; // Sitemap does not exist or returns a non-200 status code
}
// Check if the sitemap exists
$sitemapUrl = checkSitemap($url);
// new code add here





// new code end

// Build the SEO report array
$report = [
  'url' => $url,
  'favicon' => $favicon,
  'language' => $language,
  'hasDoctype' => $hasDoctype,
  'sitemap' => $sitemapUrl,
  'characterEncoding' => $characterEncoding,
  'title' => $title,
  'description' => $description,
  'headings' => $headings,
  'hasNofollow' => $hasNofollow,
  'hasNoindex' => $hasNoindex,
  'hasRobotsTxt' => $hasRobotsTxt,
  'hasViewport' => $viewportContent,
  'hasCanonicalUrl' => $hasCanonicalUrl,
  'mostCommonKeywords' => $mostCommonKeywords,
  'redirects' => $redirects,
  'pageSize' => $pageSize,
  'domSize' => $domSize,
  'serverSignature' => $serverSignature,
  'imagesWithoutAlt' => $imagesWithoutAltText,
  'totalImageCount' => $totalImageCount,
  'internalLinks' => $internalLinks,
  'externalLinks' => $externalLinks
];

// Send the report as JSON response
echo json_encode($report);
?>