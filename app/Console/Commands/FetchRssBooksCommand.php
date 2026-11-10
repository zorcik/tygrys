<?php

namespace App\Console\Commands;

use App\Models\Book;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class FetchRssBooksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'books:fetch-rss';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pobiera najnowsze książki z serii Żółty Tygrys z feedu RSS wydawnictwa Bellona i dodaje brakujące do bazy';

    /**
     * RSS feed URL.
     */
    protected string $feedUrl = 'https://www.bellona.pl/seria/tygrys/feed/';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Pobieranie feedu RSS: {$this->feedUrl}");

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(15)
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64)')
                ->get($this->feedUrl);

            if (! $response->successful()) {
                $this->error("Błąd pobierania RSS. Status HTTP: {$response->status()}");

                return Command::FAILURE;
            }

            $xml = @simplexml_load_string($response->body());
            if ($xml === false || ! isset($xml->channel->item)) {
                $this->error('Niepoprawny format XML w odpowiedzi feedu RSS.');

                return Command::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error("Wystąpił wyjątek podczas pobierania feedu: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $items = $xml->channel->item;
        $totalItems = count($items);
        $this->info("Znaleziono {$totalItems} pozycji w feedzie RSS.");

        $allBooks = Book::all();

        $normalize = function (string $text): string {
            $t = mb_strtolower(trim($text), 'UTF-8');
            $t = preg_replace('/[„”"\'’\-–—:]/u', '', $t);

            return trim(preg_replace('/\s+/u', ' ', $t));
        };

        $imageDir = public_path('images/books');
        if (! is_dir($imageDir)) {
            mkdir($imageDir, 0755, true);
        }

        $addedCount = 0;

        foreach ($items as $item) {
            $title = trim((string) $item->title);
            $link = trim((string) $item->link);

            if (empty($title)) {
                continue;
            }

            $normTitle = $normalize($title);
            $bookExists = false;

            foreach ($allBooks as $dbBook) {
                $normDbTitle = $normalize($dbBook->title);
                if ($normDbTitle === $normTitle || str_contains($normDbTitle, $normTitle) || str_contains($normTitle, $normDbTitle)) {
                    $bookExists = true;
                    break;
                }
            }

            if (! $bookExists) {
                $this->info("Znaleziono nową książkę w RSS: „{$title}” - dodawanie do bazy...");

                // Clean description
                $content = (string) ($item->children('content', true)->encoded ?? $item->description ?? '');
                $cleanDesc = preg_replace('/Artykuł.*?pochodzi z serwisu.*?$/us', '', strip_tags($content));
                $description = trim(html_entity_decode((string) $cleanDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // Fetch page to extract author and cover image if possible
                $author = 'Wydawnictwo Bellona';
                $imagePath = null;
                $imageUrl = null;

                if (! empty($link)) {
                    try {
                        $pageResponse = Http::withOptions(['verify' => false])
                            ->timeout(10)
                            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64)')
                            ->get($link);

                        if ($pageResponse->successful()) {
                            $html = $pageResponse->body();

                            // 1. Author
                            if (preg_match('/autor\/[^"\'>]+["\'][^>]*>([^<]+)<\/a>/iu', $html, $mAuthor)) {
                                $author = trim(html_entity_decode($mAuthor[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                            }

                            // 2. Cover image
                            if (preg_match('/<meta[^>]+property=[\'"]og:image[\'"][^>]+content=[\'"]([^\'"]+)[\'"]/i', $html, $mImg)) {
                                $imageUrl = $mImg[1];
                                $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
                                $imgFilename = 'rss_'.Str::slug($title).'.'.$ext;
                                $dest = $imageDir.'/'.$imgFilename;

                                $imgContent = @file_get_contents($imageUrl, false, stream_context_create([
                                    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                                    'http' => ['timeout' => 10, 'user_agent' => 'Mozilla/5.0'],
                                ]));

                                if ($imgContent !== false && ! empty($imgContent)) {
                                    file_put_contents($dest, $imgContent);
                                    $imagePath = '/images/books/'.$imgFilename;
                                }
                            }
                        }
                    } catch (Throwable $e) {
                        $this->warn("Nie udało się pobrać szczegółów podstrony dla „{$title}”: {$e->getMessage()}");
                    }
                }

                $newBook = Book::create([
                    'title' => $title,
                    'author' => $author,
                    'details' => now()->format('Y-m-d'),
                    'description' => $description ?: null,
                    'book_url' => $link ?: null,
                    'image_path' => $imagePath,
                    'image_url' => $imageUrl,
                    'is_purchased' => false,
                ]);

                $allBooks->push($newBook);
                $addedCount++;
                $this->info(" ✓ Dodano do bazy: „{$newBook->title}” (Autor: {$newBook->author}, Data w details: {$newBook->details})");
            }
        }

        if ($addedCount > 0) {
            $this->info("Zakończono! Dodano {$addedCount} nowych książek z feedu RSS.");
        } else {
            $this->info('Brak nowych książek w feedzie RSS (wszystkie pozycje są już w bazie).');
        }

        return Command::SUCCESS;
    }
}
