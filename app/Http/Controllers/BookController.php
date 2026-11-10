<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookController extends Controller
{
    /**
     * Display a listing of books.
     */
    public function index(Request $request): Response
    {
        $books = Book::query()
            ->orderBy('id', 'asc')
            ->get([
                'id',
                'title',
                'author',
                'details',
                'image_path',
                'image_url',
                'book_url',
                'description',
                'is_purchased',
                'created_at',
            ]);

        $stats = [
            'total' => $books->count(),
            'purchased' => $books->where('is_purchased', true)->count(),
            'unpurchased' => $books->where('is_purchased', false)->count(),
        ];

        return Inertia::render('Books/Index', [
            'books' => $books,
            'stats' => $stats,
        ]);
    }

    /**
     * Update the specified book's purchased status.
     */
    public function update(Request $request, Book $book): RedirectResponse
    {
        $validated = $request->validate([
            'is_purchased' => ['required', 'boolean'],
        ]);

        $book->update([
            'is_purchased' => $validated['is_purchased'],
        ]);

        return back();
    }

    /**
     * Toggle the purchased status of the specified book.
     */
    public function toggle(Book $book): RedirectResponse
    {
        $book->update([
            'is_purchased' => ! $book->is_purchased,
        ]);

        return back();
    }

    /**
     * Store a newly created book.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:255'],
            'is_purchased' => ['sometimes', 'boolean'],
        ]);

        Book::create($validated);

        return back()->with('success', 'Książka została dodana.');
    }
}
