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
$domain = extractDomainFromURL($url);

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

// Function to check for URL redirects and return the redirection path
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
$serverSignature = getServerSignature($url);
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
function getSitemapUrl($url)
{
  $sitemapUrls = [];

  // Remove 'www' from the URL if present
  $domain = preg_replace('/^www\./i', '', parse_url($url, PHP_URL_HOST));

  // Generate possible sitemap URLs
  $possibleSitemapUrls = [
    "https://{$domain}/sitemap.xml",
    "https://{$domain}/sitemap.txt",
    "https://{$domain}/sitemap",
    "https://{$domain}/sitemap_index.xml",
    "https://{$domain}/sitemap_index.txt",
    "https://{$domain}/sitemap_index",
    "https://{$domain}/sitemap_index.html",
    "https://{$domain}/sitemap.xml.gz",
    "https://{$domain}/sitemap.xml.zip",
    "https://{$domain}/sitemap.xml.tar",
    "https://{$domain}/sitemap.xml.rar",
    "https://{$domain}/sitemap.rss",
    "https://{$domain}/sitemap.res",
    "https://{$domain}/sitemap1.xml",
    // Add more variations as needed
  ];

  // Check the existence of each possible sitemap URL
  foreach ($possibleSitemapUrls as $possibleUrl) {
    $headers = get_headers($possibleUrl);
    if ($headers && strpos($headers[0], '200') !== false) {
      $sitemapUrls[] = $possibleUrl;
    }
  }

  // Check the robots.txt file for sitemap location
  $robotsUrl = "https://{$domain}/robots.txt";
  $robotsContent = @file_get_contents($robotsUrl);
  if ($robotsContent) {
    $matches = [];
    if (preg_match_all('/sitemap:\s*(.*)/i', $robotsContent, $matches)) {
      $robotsSitemapUrls = $matches[1];
      foreach ($robotsSitemapUrls as $sitemapUrl) {
        $sitemapUrl = trim($sitemapUrl);
        if (!in_array($sitemapUrl, $sitemapUrls)) {
          $sitemapUrls[] = $sitemapUrl;
        }
      }
    }
  }
  return $sitemapUrls;
}
// Check if the sitemap exists
$sitemapUrl = getSitemapUrl($url);
function extractTrackingID($html)
{
  $matches = [];
  $pattern = '/UA-\d{4,}-\d{1,}/';
  preg_match($pattern, $html, $matches);
  return isset($matches[0]) ? $matches[0] : null;
}
// Extract the Google Analytics tracking ID from the HTML
$trackingID = extractTrackingID($html);
function extractSocialMediaMetaTags($html)
{
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($html);
  libxml_clear_errors();

  $metaTags = $dom->getElementsByTagName('meta');
  $openGraphTags = array();
  $twitterCardTags = array();
  $facebookTags = array();
  $pinterestTags = array();
  $linkedinTags = array();
  $instagramTags = array();
  $googlePlusTags = array();

  foreach ($metaTags as $metaTag) {
    $property = $metaTag->getAttribute('property');
    $name = $metaTag->getAttribute('name');
    $content = $metaTag->getAttribute('content');

    if (strpos($property, 'og:') === 0) {
      $openGraphTags[$property] = $content;
    } elseif (strpos($name, 'twitter:') === 0) {
      $twitterCardTags[$name] = $content;
    } elseif (strpos($property, 'fb:') === 0) {
      $facebookTags[$property] = $content;
    } elseif ($name === 'pinterest-rich-pin') {
      $pinterestTags[$name] = $content;
    } elseif (strpos($property, 'linkedin:') === 0) {
      $linkedinTags[$property] = $content;
    } elseif ($name === 'instagram:app_id') {
      $instagramTags[$name] = $content;
    } elseif (strpos($name, 'google+:') === 0) {
      $googlePlusTags[$name] = $content;
    }
  }

  $socialMediaMetaTags = array(
    'openGraph' => $openGraphTags,
    'twitterCard' => $twitterCardTags,
    'facebook' => $facebookTags,
    'pinterest' => $pinterestTags,
    'linkedin' => $linkedinTags,
    'instagram' => $instagramTags,
    'googlePlus' => $googlePlusTags
  );

  foreach ($socialMediaMetaTags as $key => $value) {
    if (empty($value)) {
      $socialMediaMetaTags[$key] = false;
    }
  }

  return $socialMediaMetaTags;
}
// Extract the social media meta tags from the HTML
$socialMediaMetaTags = extractSocialMediaMetaTags($html);
// Function to check if a URL returns a 404 status code
function is404Page($url)
{
  $headers = get_headers($url);

  if ($headers && strpos($headers[0], '404') !== false) {
    return true; // Custom 404 page exists
  }

  return false; // No custom 404 page
}
// Construct the URL for a non-existent page (e.g., example.com/non-existent-page)
$nonExistentPageUrl = rtrim($url, '/') . '/non-existent-page';
// Check if the non-existent page returns a 404 status code
$hasCustom404Page = is404Page($nonExistentPageUrl);
function extractDomainFromURL($url)
{
  $parsedURL = parse_url($url);
  $host = $parsedURL['host'];

  // Remove www. prefix if present
  $host = preg_replace('/^www\./', '', $host);

  return $host;
}
function getSPFRecord($domain)
{
  $spfRecords = dns_get_record($domain, DNS_TXT);
  $spfRecord = '';

  foreach ($spfRecords as $record) {
    if (strpos($record['txt'], 'v=spf1') !== false) {
      $spfRecord = $record['txt'];
      break;
    }
  }
  return $spfRecord;
}
function getSocialMediaProfiles($url)
{
  $socialProfiles = [];

  // Create a new DOMDocument instance
  $dom = new DOMDocument();

  // Load the webpage HTML content
  $html = file_get_contents($url);
  $dom->loadHTML($html);

  // Create a new DOMXPath instance
  $xpath = new DOMXPath($dom);

  // Define the XPath expressions for social media links
  $expressions = [
    'facebook' => "//a[contains(@href, 'facebook.com')]",
    'twitter' => "//a[contains(@href, 'twitter.com')]",
    'instagram' => "//a[contains(@href, 'instagram.com')]",
    'linkedin' => "//a[contains(@href, 'linkedin.com')]",
    'youtube' => "//a[contains(@href, 'youtube.com')]",
    'pinterest' => "//a[contains(@href, 'pinterest.com')]",
    'snapchat' => "//a[contains(@href, 'snapchat.com')]",
    'tiktok' => "//a[contains(@href, 'tiktok.com')]",
    'reddit' => "//a[contains(@href, 'reddit.com')]",
    'tumblr' => "//a[contains(@href, 'tumblr.com')]",
    'github' => "//a[contains(@href, 'github.com')]",
    'wordpress' => "//a[contains(@href, 'wordpress.com')]",
    'soundcloud' => "//a[contains(@href, 'soundcloud.com')]",
    'pexels' => "//a[contains(@href, 'pexels.com')]",
    'behance' => "//a[contains(@href, 'behance.net')]",
    'dribbble' => "//a[contains(@href, 'dribbble.com')]",
    'deviantart' => "//a[contains(@href, 'deviantart.com')]",
    'flickr' => "//a[contains(@href, 'flickr.com')]",
    'vimeo' => "//a[contains(@href, 'vimeo.com')]",
    'twitch' => "//a[contains(@href, 'twitch.tv')]",
    'spotify' => "//a[contains(@href, 'spotify.com')]",
    'medium' => "//a[contains(@href, 'medium.com')]",
    'weibo' => "//a[contains(@href, 'weibo.com')]",
    'vk' => "//a[contains(@href, 'vk.com')]",
    'telegram' => "//a[contains(@href, 'telegram.org')]",
    'slack' => "//a[contains(@href, 'slack.com')]",
    'digg' => "//a[contains(@href, 'digg.com')]",
    'quora' => "//a[contains(@href, 'quora.com')]",
    // Add more social media platforms here
  ];


  // Extract social media profiles using XPath queries
  foreach ($expressions as $platform => $expression) {
    $nodes = $xpath->query($expression);
    if ($nodes->length > 0) {
      $socialProfiles[$platform] = $nodes[0]->getAttribute('href');
    }
  }

  return $socialProfiles;
}
$socialMediaProfiles = getSocialMediaProfiles($url);
//gpt new code add here 










function isCompressionEnabled($url)
{
  $headers = get_headers($url, 1);

  if (isset($headers['Content-Encoding'])) {
    $contentEncoding = $headers['Content-Encoding'];

    if (
      stripos($contentEncoding, 'gzip') !== false
      || stripos($contentEncoding, 'deflate') !== false
      || stripos($contentEncoding, 'br') !== false
    ) {
      return true;
    }
  }

  return false;
}

// Usage example:
$isCompressionEnabled = isCompressionEnabled($url);

require 'vendor/autoload.php';

use DonatelloZa\RakePlus\RakePlus;

$text = "Criteria of compatibility of a system of linear Diophantine equations, " .
  "strict inequations, and nonstrict inequations are considered. Upper bounds " .
  "for components of a minimal set of solutions and algorithms of construction " .
  "of minimal generating sets of solutions for all types of systems are given.";

$mostCommonKeywords = RakePlus::create($text)->keywords();



// new code end

// Build the SEO report array
$report = [
  'url' => $url,
  'socialMediaPresence' => $socialMediaProfiles,
  'domain' => $domain,
  'spfRecord' => getSPFRecord($domain),
  'isCompression' => $isCompressionEnabled,
  'googleTrackingID' => $trackingID,
  'hasCustom404Page' => $hasCustom404Page,
  'socialMetaTags' => $socialMediaMetaTags,
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