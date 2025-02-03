<?php

namespace App;

use Symfony\Component\DomCrawler\Crawler;

class Product
{
    /**
     * This variable holds the site url for scrapping.
     * 
     * @var string
     */
    private string $siteUrl = 'https://www.magpiehq.com/developer-challenge/';

    /**
     * This variable holds the category of the product scrapping.
     * 
     * @var string
     */
    private string $category = 'smartphones';

    /**
     * This variable holds total product scrapped count.
     * 
     * @var string
     */
    private int $totalCount = 0;

    /**
     * This variable holds product scrapped count per page.
     * 
     * @var int
     */
    private int $perPageCount = 0;

    /**
     * This variable holds product list of array.
     * 
     * @var array
     */
    private array $products = [];

    /**
     * This variable holds unique product list of array only title.
     * 
     * @var array
     */
    private array $uniqueProductFound = [];

    /**
     * This variable holds duplicate product count.
     * 
     * @var int
     */
    private int $duplicateProductFound = 0;

    /**
     * This function returns a simple array with a list of product attributes
     * as strings.
     *
     * @return array An array of products.
     */
    public function get(): array
    {
        try {
            $document = ScrapeHelper::fetchDocument($this->siteUrl . $this->category);
            $page = $this->getPages($document);
            echo "Starting web scraping ...\n\n";
            $startTime = microtime(true);

            $this->executeScrappingForSinglePage($document);

            if ($page > 0) {
                $this->executeScrappingForMultiplePage($page);
            }

            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            echo "Script executed in: " . number_format($executionTime, 2) . " seconds.\n";
            echo "----------------------------------------------\n\n";

            $this->scrappingResult();

            return $this->products;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error scraping products: ' . $e->getMessage());
        }
    }

    /**
     * This function returns a simple array with a list of product attributes
     * as strings.
     * 
     * @param Crawler $crawler Document to be processed.
     * @return void
     */
    public function getAttributes(Crawler $crawler): void
    {
        $this->perPageCount = 0;

        $crawler->filter('.product')->each(function (Crawler $node) {

            $getNode = $this->parseNode($node);

            // Loop through each color and add the product array
            foreach ($getNode['colors'] as $color) {
                $this->totalCount = $this->totalCount + 1;
                $productTitle = $color . '-' . $getNode['title'] . ' ' . $getNode['capacity'];
                if (!isset($this->uniqueProductFound[$productTitle])) {
                    $this->perPageCount = $this->perPageCount + 1;
                    $this->uniqueProductFound[$productTitle] = true;
                    $this->products[] =  [
                        'title' => $getNode['title'] . ' ' . $getNode['capacity'],
                        'price' => $getNode['price'],
                        'imageUrl' => $getNode['imageUrl'],
                        'capacityMB' => $getNode['capacityMB'],
                        'colour' => $color,
                        'availabilityText' => $getNode['availabilityText'],
                        'isAvailable' => $getNode['isAvailable'],
                        'shippingText' => $getNode['shippingText'],
                        'shippingDate' => $getNode['shippingDate']
                    ];
                } else {
                    $this->duplicateProductFound++;
                }
            }
        });
    }

    /**
     * This function returns a product node.
     * 
     * @param Crawler $node Document to be processed.
     * @return Array
     */
    function parseNode(Crawler $node): array
    {
        return [
            'colors' => $this->getColour($node),
            'title' => $this->getTitle($node),
            'capacity' => $this->getCapacity($node),
            'price' => $this->getPrice($node),
            'imageUrl' => $this->getImageUrl($node),
            'capacityMB' => $this->getCapacityMB($node),
            'availabilityText' => $this->getAvailabilityText($node),
            'isAvailable' => $this->getIsAvailable($node),
            'shippingText' => $this->getShippingText($node),
            'shippingDate' => $this->getShippingDate($node)
        ];
    }

    /**
     * This function returns a product title.
     * 
     * @param Crawler $node Document to be processed.
     * @return string
     */
    function getTitle(Crawler $node): string
    {
        return $node->filter('.product-name')->text();
    }

    /**
     * This function returns a product price.
     * 
     * @param Crawler $node Document to be processed.
     * @return string 
     */
    function getPrice(Crawler $node): string
    {
        $price = $node->filter('.text-lg')->text();
        return preg_replace('/[^\d.]/', '', $price);
    }

    /**
     * This function returns a product image url.
     * 
     * @param Crawler $node Document to be processed.
     * @return string 
     */
    function getImageUrl(Crawler $node): string
    {
        $imageUrl = $node->filter('img')->attr('src');
        return str_replace('..', $this->siteUrl, $imageUrl);
    }

    /**
     * This function returns a product capacity.
     * 
     * @param Crawler $node Document to be processed.
     * @return string 
     */
    function getCapacity(Crawler $node): string
    {
        return str_replace(' ', '', $node->filter('.product-capacity')->text());
    }

    /**
     * This function returns a product capacity in MB.
     * 
     * @param Crawler $node Document to be processed.
     * @return string 
     */
    function getCapacityMB(Crawler $node): string
    {
        $capacity = $node->filter('.product-capacity')->text();
        preg_match('/(\d+)\s?(GB)/i', $capacity, $matches);

        if (!isset($matches[1])) {
            return preg_replace('/[^\d.]/', '', $capacity);
        }

        $gb = (int) $matches[1];

        // Convert GB to MB
        return $gb * 1024;
    }

    /**
     * This function returns a product colour.
     * 
     * @param Crawler $node Document to be processed.
     * @return array 
     */
    function getColour(Crawler $node): array
    {
        return $node->filter('[data-colour]')->each(function (Crawler $node) {
            return $node->attr('data-colour');
        });
    }

    /**
     * This function returns a product availability.
     * 
     * @param Crawler $node Document to be processed.
     * @return string 
     */
    function getAvailabilityText(Crawler $node): string
    {
        $availabilityText = $node->filter('.my-4.text-sm.block.text-center')->text();

        // Clean the extracted text to get the availability status
        preg_match('/Availability:\s*(.+)/i', $availabilityText, $matches);

        if (!isset($matches[1])) {
            return '';
        }

        $availabilityStatus = trim($matches[1]);

        if (strpos($availabilityStatus, 'In Stock') !== false)
            return 'In Stock';

        if (strpos($availabilityStatus, 'Out of Stock') !== false)
            return 'Out of Stock';

        return $availabilityStatus;
    }

    /**
     * This function returns a product availability status.
     * 
     * @param Crawler $node Document to be processed.
     * @return string 
     */
    function getIsAvailable(Crawler $node): string
    {
        return $this->getAvailabilityText($node) === 'Out of Stock' ? 'false' : 'true';
    }

    /**
     * This function returns a product shipping.
     * 
     * @param Crawler $node Document to be processed.
     * @return string 
     */
    function getShippingText(Crawler $node): string
    {
        if ($node->filter('div.my-4.text-sm.block.text-center')->count() !== 2) {
            return '';
        }

        return $node->filter('div.my-4.text-sm.block.text-center')->eq(1)->text();
    }

    /**
     * This function returns a product shipping date in Y-m-d format.
     * 
     * @param Crawler $node Document to be processed.
     * @return string 
     */
    function getShippingDate(Crawler $node): string
    {
        if ($node->filter('div.my-4.text-sm.block.text-center')->count() !== 2) {
            return '';
        }
        $shippingText = $node->filter('div.my-4.text-sm.block.text-center')->eq(1)->text();

        // Extract the date from the shipping text checking for "Delivery by Thursday 23rd Jan 2025" format.
        preg_match('/\d{1,2}(?:st|nd|rd|th)?\s\w+\s\d{4}/', $shippingText, $matches);

        // If match not found check for "2025-01-23" format
        if (!isset($matches[0])) {
            preg_match('/(\d{4}-\d{2}-\d{2})/', $shippingText, $matchesDate);
            if (!isset($matchesDate[0])) {
                return '';
            }
            return $matchesDate[0];
        }

        $dateString = $matches[0];

        // Convert the date string to the desired format (e.g., "2025-01-23")
        return date('Y-m-d', strtotime($dateString));
    }

    /**
     * This function returns a document last page number.
     * 
     * @param Crawler $node Document to be processed.
     * @return int return a int.
     */
    function getPages(Crawler $node): int
    {
        $pageNode = $node->filter('#pages a:last-child');
        $lastPage = (int) $pageNode->count();

        // Check if pages is exist or not if not found it set to 0.
        return $lastPage === 0 ? $lastPage : (int) $pageNode->text();
    }

    /**
     *  This function execute the scrapping for single page.
     * 
     * @param Crawler $node Document to be processed.
     * @return void
     */
    function executeScrappingForSinglePage(Crawler $document): void
    {
        $this->getAttributes($document);

        echo "Page: 1\n";
        echo "Product Scraped: " . $this->perPageCount . "\n\n";
    }

    /**
     * This function execute the scrapping for multiple pages.
     * 
     * @param int $page multiple pages to be processed.
     * @return void
     */
    function executeScrappingForMultiplePage(int $page): void
    {
        for ($i = 2; $i <= $page; $i++) {
            $document = ScrapeHelper::fetchDocument($this->siteUrl . $this->category . '?page=' . $i);

            $this->getAttributes($document);

            echo "Page: " . $i . "\n";
            echo "Product Scraped: " . $this->perPageCount . "\n\n";
        }
    }

    /**
     * This function show the scrapped data result.
     * 
     * @return void
     */
    function scrappingResult(): void
    {
        echo "Product Found: " . $this->totalCount . "\n";
        echo "Duplicate Product Found: " . $this->duplicateProductFound . "\n";
        echo "Product Scraped: " . count($this->uniqueProductFound) . "\n";
    }
}
