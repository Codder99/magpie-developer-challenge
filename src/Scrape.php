<?php

namespace App;

require 'vendor/autoload.php';

class Scrape
{
    private array $products = [];

    public function run(): void
    {
        $product = new Product();
        $this->products = $product->get();

        file_put_contents('output.json', json_encode($this->products, JSON_PRETTY_PRINT));
    }
}

$scrape = new Scrape();
$scrape->run();
