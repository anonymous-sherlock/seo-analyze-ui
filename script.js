function showScreenshot(event) {
  event.preventDefault(); // Prevent form submission

  const url = document.getElementById("urlInput").value;
  const webCapture = document.getElementById("screen-shot");
  const reportDate = document.getElementById("generated-report-date");
  reportDate.innerHTML = generateReportDate();

  // Remove previously appended webCapture image
  webCapture.innerHTML = "";

  // Get the dimensions of the image based on the viewport
  const imgElement = document.querySelector(".mac-view");
  const { height: imgHeight, width: imgWidth } =
    imgElement.getBoundingClientRect();

  const img = document.createElement("img");
  img.src = `https://api.screenshotmachine.com/?key=f7ee5e&url=${encodeURIComponent(
    url
  )}&dimension=${imgWidth * 3}x${imgHeight * 3}`;
  img.classList.add("w-100");
  webCapture.appendChild(img);

  // Hide the loader after the image is loaded
  img.onload = () => {
    // Perform SEO analysis
    performSEOAnalysis(url);
  };
}

// report generated date
function generateReportDate() {
  const currentDate = new Date();
  const monthNames = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  const month = monthNames[currentDate.getMonth()];
  const day = currentDate.getDate();
  const year = currentDate.getFullYear();
  let hour = currentDate.getHours();
  const minute = currentDate.getMinutes();
  const period = hour >= 12 ? "pm" : "am";

  hour %= 12;
  hour = hour || 12;

  const formattedDate = `${month} ${day}, ${year} ${hour}:${minute
    .toString()
    .padStart(2, "0")} ${period}`;

  return `Report generated on ${formattedDate}`;
}
// performing seo analysis
async function performSEOAnalysis(url) {
  // Construct the URL with the URL parameter
  const endpoint = "seo_analysis.php"; // Update with the actual PHP script filename or endpoint
  const requestUrl = `${endpoint}?url=${encodeURIComponent(url)}`;

  try {
    const response = await fetch(requestUrl);
    if (response.ok) {
      const analysisResult = await response.json();
      // Process the analysis result as needed
      fillData(analysisResult);
    } else {
      throw new Error(`Error: ${response.status}`);
    }
  } catch (error) {
    // Handle errors
    console.log(`An error occurred: ${error.message}`);
  }
}
// format Page Size Bytes
function formatBytes(bytes) {
  if (bytes < 1024) {
    return `${bytes} B`;
  } else if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(2)} KB`;
  } else if (bytes < 1024 * 1024 * 1024) {
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  } else {
    return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} GB`;
  }
}
// render headings
async function renderHeadings({ headings: data }) {
  const headingContainer = document.getElementById("heading-container");
  for (const heading in data) {
    const headingData = data[heading];
    // Skip appending if data is empty or null
    if (!headingData || headingData.length === 0) {
      continue;
    }
    const headingTag = document.createElement("li");
    headingTag.classList.add("list-group-item");

    const headingDiv = document.createElement("div");
    headingDiv.classList.add("d-flex", "justify-content-between");
    headingDiv.setAttribute("data-bs-toggle", "collapse");
    headingDiv.setAttribute("href", `#multiCollapseH_${heading}`);
    headingDiv.setAttribute("role", "button");
    headingDiv.setAttribute("aria-expanded", "false");
    headingDiv.setAttribute("aria-controls", `multiCollapseH_${heading}`);

    const headingTitle = document.createElement("p");
    headingTitle.classList.add("mb-0", "text-uppercase");
    headingTitle.textContent = heading;

    const headingCount = document.createElement("span");
    headingCount.classList.add("badge", "badge-primary");
    headingCount.textContent = headingData.length.toString();

    headingDiv.appendChild(headingTitle);
    headingDiv.appendChild(headingCount);

    const collapseDiv = document.createElement("div");
    collapseDiv.classList.add("collapse");
    collapseDiv.setAttribute("id", `multiCollapseH_${heading}`);

    const hrElement = document.createElement("hr");

    const olElement = document.createElement("ol");
    olElement.classList.add("mb-0", "pb-2");

    for (const item of headingData) {
      const liElement = document.createElement("li");
      liElement.classList.add("py-1", "text-break", "heading-data");
      liElement.textContent = item;
      olElement.appendChild(liElement);
    }

    collapseDiv.appendChild(hrElement);
    collapseDiv.appendChild(olElement);

    headingTag.appendChild(headingDiv);
    headingTag.appendChild(collapseDiv);

    headingContainer.appendChild(headingTag);
  }
}

async function fillData(data) {
  const siteTitleElements = document.getElementsByClassName("site-title");
  const siteDescriptionElements =
    document.getElementsByClassName("site-description");
  const siteURLElements = document.getElementsByClassName("site-url");
  const siteURLAltElements = document.getElementsByClassName("site-url-alt");
  const imagesWithoutAltAttr = document.getElementById("imagesWithoutAltAttr");
  const totalImageCount = document.querySelector(".total-image-count");
  const pageSizeElements = document.querySelectorAll(".page-size");
  const siteLanguage = document.querySelector(".site-lang");
  const siteIcon = document.querySelector(".site-icon");
  const siteIconImg = document.querySelector(".site-icon-img");

  siteIcon.innerHTML = data?.favicon;
  siteIconImg.setAttribute("src", data?.favicon);
  const missingAltImageCount = document.querySelectorAll(
    ".missing-alt-image-count"
  );

  renderHeadings(data);
  renderSitemap(data);
  const inPageLinks = {
    "Internal Links": data.internalLinks,
    "External Links": data.externalLinks,
  };
  console.log(inPageLinks);
  renderInPageLink(inPageLinks);

  totalImageCount.innerHTML = data?.totalImageCount;
  siteLanguage.innerHTML = data?.language;

  for (let i = 0; i < pageSizeElements.length; i++) {
    const pageSize = pageSizeElements[i];
    pageSize.innerHTML = formatBytes(data?.pageSize);
  }

  for (let i = 0; i < missingAltImageCount.length; i++) {
    const missingAlt = missingAltImageCount[i];
    missingAlt.innerHTML = data?.imagesWithoutAlt.length;
  }

  for (let i = 0; i < siteTitleElements.length; i++) {
    const siteTitleElement = siteTitleElements[i];
    siteTitleElement.innerHTML = data?.title;
  }

  for (let i = 0; i < siteURLElements.length; i++) {
    const siteURLElement = siteURLElements[i];
    siteURLElement.innerHTML = data?.url;
    siteURLElement.setAttribute("href", data?.url);
  }

  for (let i = 0; i < siteURLAltElements.length; i++) {
    const siteURLAltElement = siteURLAltElements[i];
    siteURLAltElement.innerHTML = data?.url;
  }

  for (let i = 0; i < siteDescriptionElements.length; i++) {
    const siteDescriptionElement = siteDescriptionElements[i];
    siteDescriptionElement.innerHTML = data?.description;
  }

  let imagesWithoutAltHtml = "";
  if (data?.imagesWithoutAlt) {
    imagesWithoutAltHtml += '<ol class="mb-0 pb-2">';
    data.imagesWithoutAlt.forEach((image) => {
      imagesWithoutAltHtml += `<li class="py-1 text-break"><a href="${image}" target="_blank" rel="noopener noreferrer">${image}</a></li>`;
    });
    imagesWithoutAltHtml += "</ol>";
  }

  imagesWithoutAltAttr.innerHTML = imagesWithoutAltHtml;
}

async function renderSitemap(data) {
  const { sitemap: sitemapURL } = data;
  const sitemap = document.querySelector("[data-sitemap]");
  const checkSitemap = document.querySelector("[check-sitemap]");

  const sitemapHTML = `<li class="list-group-item">
    <div
      class="d-flex justify-content-between"
      data-bs-toggle="collapse"
      href="#seoReport_sitemaps"
      role="button"
      aria-expanded="false"
      aria-controls="seoReport_sitemaps"
      bis_skin_checked="1"
    >
      <p class="mb-0">Sitemaps</p>
      <span class="badge badge-primary">1</span>
    </div>
    <div
      class="collapse"
      id="seoReport_sitemaps"
      bis_skin_checked="1"
    >
      <hr />
      <ol class="mb-0 pb-2">
        <li class="py-1 text-break">
          <a
            href="${sitemapURL}"
            target="_blank"
            rel="noopener noreferrer"
          >${sitemapURL}</a>
        </li>
      </ol>
    </div>
  </li>`;

  if (sitemapURL) {
    sitemap.innerHTML = sitemapHTML;
  } else {
    checkSitemap.innerHTML = "No Sitemap Found.";
  }
}

// render in page links
function renderInPageLink(data) {
  const linkContainer = document.getElementById("link-container");
  linkContainer.innerHTML = "";
  const internalLinksCount = document.querySelector(".internal-links-count");
  internalLinksCount.innerHTML = data["Internal Links"].length.toString();

  for (const link in data) {
    const linkData = data[link];
    // Skip appending if data is empty or null
    if (!linkData || linkData.length === 0) {
      continue;
    }
    const linkTag = document.createElement("li");
    linkTag.classList.add("list-group-item");
    const linkTab = link.toLowerCase().replaceAll(" ", "");
    const linkDiv = document.createElement("div");
    linkDiv.classList.add("d-flex", "justify-content-between");
    linkDiv.setAttribute("data-bs-toggle", "collapse");
    linkDiv.setAttribute("href", `#multiCollapseH_${linkTab}`);
    linkDiv.setAttribute("role", "button");
    linkDiv.setAttribute("aria-expanded", "false");
    linkDiv.setAttribute("aria-controls", `multiCollapseH_${linkTab}`);

    const linkTitle = document.createElement("p");
    linkTitle.classList.add("mb-0");
    linkTitle.textContent =
      link.charAt(0).toUpperCase() + link.slice(1).toLowerCase(); // Capitalize the first letter and lowercase the rest

    const linkCount = document.createElement("span");
    linkCount.classList.add("badge", "badge-primary");
    linkCount.textContent = linkData.length.toString();

    linkDiv.appendChild(linkTitle);
    linkDiv.appendChild(linkCount);

    const collapseDiv = document.createElement("div");
    collapseDiv.classList.add("collapse");
    collapseDiv.setAttribute("id", `multiCollapseH_${linkTab}`);

    const hrElement = document.createElement("hr");

    const olElement = document.createElement("ol");
    olElement.classList.add("mb-0", "pb-2");

    for (const item of linkData) {
      const liElement = document.createElement("li");
      liElement.classList.add("py-1", "text-break");

      const linkAnchor = document.createElement("a");
      linkAnchor.href = item.url; // Use the "url" property of the link item
      linkAnchor.textContent = item.text; // Use the "text" property of the link item

      liElement.appendChild(linkAnchor);
      olElement.appendChild(liElement);
    }

    collapseDiv.appendChild(hrElement);
    collapseDiv.appendChild(olElement);

    linkTag.appendChild(linkDiv);
    linkTag.appendChild(collapseDiv);

    linkContainer.appendChild(linkTag);
  }
}
