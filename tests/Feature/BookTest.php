<?php

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('renders the books list on home page with stats', function () {
    Book::factory()->count(3)->create(['is_purchased' => false]);
    Book::factory()->count(2)->purchased()->create();

    $response = $this->get('/');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Books/Index')
            ->has('books')
            ->has('stats', fn (Assert $stats) => $stats
                ->where('purchased', 2)
                ->etc()
            )
        );
});

test('can toggle a book purchased status', function () {
    $book = Book::factory()->create(['is_purchased' => false]);

    $response = $this->patch("/books/{$book->id}/toggle");

    $response->assertRedirect();
    expect($book->fresh()->is_purchased)->toBeTrue();

    $this->patch("/books/{$book->id}/toggle");
    expect($book->fresh()->is_purchased)->toBeFalse();
});

test('can explicitly update a book purchased status', function () {
    $book = Book::factory()->create(['is_purchased' => false]);

    $response = $this->patch("/books/{$book->id}", [
        'is_purchased' => true,
    ]);

    $response->assertRedirect();
    expect($book->fresh()->is_purchased)->toBeTrue();
});

test('can store a new book', function () {
    $response = $this->post('/books', [
        'title' => 'Cyrk Skalskiego',
        'author' => 'Bohdan Arct',
        'details' => '(wyd. Bellona) 2024',
        'is_purchased' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('books', [
        'title' => 'Cyrk Skalskiego',
        'author' => 'Bohdan Arct',
        'is_purchased' => true,
    ]);
});

test('books:enrich command enriches books with payload data', function () {
    Book::factory()->create([
        'title' => 'Na kursie wolność',
        'author' => 'Edward Obertyński',
    ]);

    $this->artisan('books:enrich')->assertSuccessful();

    $this->assertDatabaseHas('books', [
        'title' => 'Na kursie wolność',
    ]);

    $book = Book::where('title', 'Na kursie wolność')->first();
    expect($book->book_url)->not->toBeNull()
        ->and($book->description)->not->toBeNull();
});

test('books:fetch-rss adds new books from RSS feed and sets details to addition date', function () {
    $rssXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
<channel>
    <title>Seria: Żółty Tygrys | Wydawnictwo Bellona</title>
    <item>
        <title>Zupełnie Nowy Tom Tygrysa</title>
        <link>https://www.bellona.pl/tytul/zupelnie-nowy-tom/</link>
        <description><![CDATA[Krótki opis nowego tomu.]]></description>
        <content:encoded><![CDATA[<p>Szczegółowy opis nowego tomu w serii.</p><p>Artykuł pochodzi z serwisu Bellona.</p>]]></content:encoded>
    </item>
</channel>
</rss>
XML;

    Http::fake([
        'https://www.bellona.pl/seria/tygrys/feed/' => Http::response($rssXml, 200),
        'https://www.bellona.pl/tytul/zupelnie-nowy-tom/' => Http::response('<html><head><meta property="og:image" content="https://www.bellona.pl/img.jpg" /></head><body><a href="https://www.bellona.pl/autor/jan-kowalski/">Jan Kowalski</a></body></html>', 200),
    ]);

    $this->artisan('books:fetch-rss')->assertSuccessful();

    $this->assertDatabaseHas('books', [
        'title' => 'Zupełnie Nowy Tom Tygrysa',
        'author' => 'Jan Kowalski',
        'details' => now()->format('Y-m-d'),
        'book_url' => 'https://www.bellona.pl/tytul/zupelnie-nowy-tom/',
    ]);

    $book = Book::where('title', 'Zupełnie Nowy Tom Tygrysa')->first();
    expect($book->details)->toBe(now()->format('Y-m-d'))
        ->and($book->description)->toContain('Szczegółowy opis nowego tomu w serii.');
});
