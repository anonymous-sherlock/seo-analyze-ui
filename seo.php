<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$url = $_GET['url'];
// Validate and sanitize the URL input
if (!filter_var($url, FILTER_VALIDATE_URL)) {
  echo json_encode(['error' => 'Invalid URL']);
  exit;
}
// Function to fetch the HTML content of a URL
function fetchHTML($url)
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36');
  $html = curl_exec($ch);
  curl_close($ch);

  return $html;
}
// Fetch the HTML content of the provided URL
$html = fetchHTML($url);
$dom = new DOMDocument();
libxml_use_internal_errors(true); // Ignore any HTML parsing errors
$dom->loadHTML($html);
libxml_use_internal_errors(false);
// Create a DOMXPath object to query the DOM
$xpath = new DOMXPath($dom);

// Function to check if the nofollow meta tag exists
function hasMetaTag($html, $attribute, $values)
{
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($html);
  libxml_use_internal_errors(false);

  $metaTags = $dom->getElementsByTagName('meta');
  foreach ($metaTags as $metaTag) {
    $attrValue = $metaTag->getAttribute($attribute);
    if (in_array($attrValue, $values)) {
      return true; // Meta tag exists
    }
  }
  return false; // Meta tag does not exist
}


// Extract the domain from the provided URL
$urlParts = parse_url($url);
$domain = $urlParts['host'];

// Fetch necessary information
$title = $dom->getElementsByTagName('title')->item(0)->textContent;
$description = $xpath->evaluate('string(//meta[@name="description"]/@content)');
$description = $description ?: false;
// favicon
$favicon = $xpath->evaluate('string(//link[@rel="icon" or @rel="shortcut icon"]/@href)');
$favicon = $favicon ?: false;


$headings = [];
for ($i = 1; $i <= 6; $i++) {
  $headingNodes = $xpath->query("//h{$i}");
  foreach ($headingNodes as $headingNode) {
    $text = trim(preg_replace('/\s+/', ' ', $headingNode->textContent));
    $headings["h{$i}"][] = $text;
  }
}
// imaages
$imageNodes = $xpath->query('//img');
$totalImageCount = $imageNodes->length;
$imagesWithoutAltText = [];
foreach ($imageNodes as $imageNode) {
  $alt = $imageNode->getAttribute('alt');
  if (empty($alt)) {
    $src = $imageNode->getAttribute('src');
    if (!empty($src)) {
      $imagesWithoutAltText[] = $src;
    }
  }
}

// Server Signature
function getServerSignature($url)
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  $response = curl_exec($ch);

  $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $headers = substr($response, 0, $headerSize);

  $serverSignature = null;
  foreach (explode("\r\n", $headers) as $header) {
    if (stripos($header, 'Server:') !== false) {
      $serverSignature = trim(substr($header, strlen('Server:')));
      break;
    }
  }
  curl_close($ch);
  return $serverSignature;
}
// Check if the website allows crawling by checking the robots.txt file
function checkRobotsTxt($url)
{
  $robotsUrl = rtrim($url, '/') . '/robots.txt';
  $robotsContent = fetchHTML($robotsUrl);
  return !empty($robotsContent) && strpos($robotsContent, 'User-agent: *') !== false;
}

function getSPFRecord($domain)
{
  $spfRecords = dns_get_record($domain, DNS_TXT);
  foreach ($spfRecords as $record) {
    if (stripos($record['txt'], 'v=spf1') !== false) {
      return $record['txt'];
    }
  }

  return false; // SPF record not found
}





// all function call
$spfRecord = getSPFRecord($domain);
$redirects = checkURLRedirects($url);
$serverSignature = getServerSignature($url);
$hasNoindex = hasMetaTag($html, 'name', ['noindex', 'googlebot']);
$hasNofollow = hasMetaTag($html, 'name', ['nofollow']);


// test code here

function checkURLRedirects($url)
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);

  if ($response === false) {
    // Error occurred while making the request
    return false;
  }

  $redirectUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
  curl_close($ch);

  return $redirectUrl;
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

      // Check if the parsed URL matches any of the domain variations
      $parsedUrlHost = isset($parsedHref['host']) ? $parsedHref['host'] : '';
      $originalUrlHost = parse_url($url, PHP_URL_HOST);
      $wwwOriginalUrlHost = 'www.' . $originalUrlHost;
      $withoutWwwOriginalUrlHost = preg_replace('/^www\./', '', $originalUrlHost);

      if (
        $parsedUrlHost === $originalUrlHost ||
        $parsedUrlHost === $wwwOriginalUrlHost ||
        $parsedUrlHost === $withoutWwwOriginalUrlHost ||
        $wwwOriginalUrlHost === $parsedUrlHost ||
        $withoutWwwOriginalUrlHost === $parsedUrlHost
      ) {
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

// test code end here

// Prepare the response array
$response = [
  'url' => $url,
  'domain' => $domain,
  'redirects' => $redirects,
  'spfRecord' => $spfRecord,
  'title' => $title,
  'description' => $description,
  'favicon' => $favicon,
  'headings' => $headings,
  'totalImages' => $totalImageCount,
  'imagesWithoutAltText' => $imagesWithoutAltText,
  'internalLinks' => $internalLinks,
  'externalLinks' => $externalLinks,
  'hasRobotsTxt' => checkRobotsTxt($url),
  'hasNofollow' => $hasNofollow,
  'hasNoindex' => $hasNoindex,
  'serverSignature' => $serverSignature,
];

echo json_encode($response);
?>