<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;

class EnrichBooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'books:enrich';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enrich books with images, descriptions and bookstore links from payload*.json';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $payloadFiles = glob(base_path('payload*.json'));

        if (empty($payloadFiles)) {
            $this->error('Nie znaleziono żadnych plików payload*.json');

            return Command::FAILURE;
        }

        sort($payloadFiles);
        $this->info('Znaleziono '.count($payloadFiles).' plików payload: '.implode(', ', array_map('basename', $payloadFiles)));

        $products = [];
        $seenSkus = [];

        foreach ($payloadFiles as $file) {
            $content = file_get_contents($file);
            $json = json_decode($content, true);
            $items = $json['data']['products']['items'] ?? [];

            foreach ($items as $item) {
                $sku = $item['sku'] ?? (string) ($item['id'] ?? uniqid());
                if (! isset($seenSkus[$sku])) {
                    $seenSkus[$sku] = true;
                    $products[] = $item;
                }
            }
        }

        if (empty($products)) {
            $this->warn('Brak produktów w plikach payload.');

            return Command::SUCCESS;
        }

        $imageDir = public_path('images/books');
        if (! is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }

        $this->info('Przetwarzanie '.count($products).' unikalnych produktów z księgarni...');

        $allBooks = Book::all();

        $normalize = function (string $text): string {
            $t = mb_strtolower(trim($text), 'UTF-8');
            $t = preg_replace('/[„”"\'’\-–—:]/u', '', $t);

            return trim(preg_replace('/\s+/u', ' ', $t));
        };

        $updatedCount = 0;
        $createdCount = 0;

        foreach ($products as $product) {
            $name = trim($product['name'] ?? '');
            $sku = $product['sku'] ?? (string) ($product['id'] ?? uniqid());

            // 1. Download image
            $imagePath = null;
            $imageUrl = $product['small_image']['url'] ?? null;

            if ($imageUrl) {
                $filename = "{$sku}.jpg";
                $destination = "{$imageDir}/{$filename}";

                if (! file_exists($destination) || filesize($destination) === 0) {
                    $ch = curl_init($imageUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                    $imgData = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if ($httpCode === 200 && ! empty($imgData)) {
                        file_put_contents($destination, $imgData);
                        $imagePath = "/images/books/{$filename}";
                    } else {
                        $this->warn("Nie udało się pobrać obrazka dla {$name} (HTTP {$httpCode})");
                    }
                } else {
                    $imagePath = "/images/books/{$filename}";
                }
            }

            // 2. Bookstore link
            $urlKey = ltrim($product['url'] ?? '', '/');
            $bookUrl = $urlKey ? "https://www.swiatksiazki.pl/{$urlKey}" : null;

            // 3. Description
            $rawDesc = $product['short_description']['html'] ?? '';
            $description = trim(html_entity_decode(strip_tags($rawDesc), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            // 4. Author
            $author = $product['dictionary']['authors'][0]['name'] ?? 'Autor nieznany';

            // 5. Match with existing DB books
            $normName = $normalize($name);
            $matchedBook = null;

            foreach ($allBooks as $dbBook) {
                $normDbTitle = $normalize($dbBook->title);
                if ($normDbTitle === $normName || str_contains($normDbTitle, $normName) || str_contains($normName, $normDbTitle)) {
                    $matchedBook = $dbBook;
                    break;
                }
            }

            if ($matchedBook) {
                $matchedBook->update([
                    'image_path' => $imagePath ?? $matchedBook->image_path,
                    'image_url' => $imageUrl ?? $matchedBook->image_url,
                    'book_url' => $bookUrl ?? $matchedBook->book_url,
                    'description' => $description ?: $matchedBook->description,
                ]);
                $this->line(" [Zaktualizowano] {$matchedBook->title}");
                $updatedCount++;
            } else {
                $newBook = Book::create([
                    'title' => $name,
                    'author' => $author,
                    'details' => '(wyd. Bellona)',
                    'image_path' => $imagePath,
                    'image_url' => $imageUrl,
                    'book_url' => $bookUrl,
                    'description' => $description,
                    'is_purchased' => false,
                ]);
                $allBooks->push($newBook);
                $this->info(" [Dodano nową pozycję] {$name} ({$author})");
                $createdCount++;
            }
        }

        $this->info("Zakończono! Zaktualizowano: {$updatedCount}, Dodano nowych: {$createdCount}");

        return Command::SUCCESS;
    }
}
