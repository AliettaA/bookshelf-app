<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $books = $request->user()
            ->favoriteBooks()
            ->orderByDesc('favorites.created_at')
            ->orderByDesc('favorites.id')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

    public function toggle(Request $request, Book $book): RedirectResponse
    {
        $result = $request->user()->favoriteBooks()->toggle($book->id);

        $message = $result['attached'] !== []
            ? 'お気に入りに追加しました。'
            : 'お気に入りを解除しました。';

        return redirect()
            ->back()
            ->with('success', $message);
    }
}
