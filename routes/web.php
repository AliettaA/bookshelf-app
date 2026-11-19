<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\BookIsbnController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [BookController::class, 'index']);

// ISBN 検索。/books/{book}/edit などと取り違えないよう、books の resource より前に定義する
Route::get('/books/isbn/{isbn}', BookIsbnController::class)
    ->middleware('auth')
    ->name('books.isbn');

// create を show より先に定義するため、認証必須のルートを先に登録する
Route::resource('books', BookController::class)
    ->except(['index', 'show'])
    ->middleware('auth');
Route::resource('books', BookController::class)
    ->only(['index', 'show']);

Route::resource('genres', GenreController::class)
    ->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])->name('reviews.edit');
    Route::put('/reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/books/{book}/favorites', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // show はなし。create / store / edit / update / destroy / complete のメソッドは未実装（ルートだけ定義）
    Route::resource('reading-plans', ReadingPlanController::class)->except(['show']);
    Route::post('/reading-plans/{reading_plan}/complete', [ReadingPlanController::class, 'complete'])->name('reading-plans.complete');
});

Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
