<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * 書籍番号は BookSeeder の並び（1〜11）に対応する。
     */
    public function run(): void
    {
        $bookIds = Book::orderBy('id')->pluck('id')->values();

        $favoriteBookNumbersByEmail = [
            'yamada@example.com' => [3, 6, 7, 10],
            'suzuki@example.com' => [1, 3, 8, 10, 11],
            'tanaka@example.com' => [3, 6, 9],
            'sato@example.com' => [2, 4, 6, 11],
            'takahashi@example.com' => [1, 4, 5, 10],
        ];

        foreach ($favoriteBookNumbersByEmail as $email => $bookNumbers) {
            $user = User::where('email', $email)->firstOrFail();

            $user->favoriteBooks()->syncWithoutDetaching(
                collect($bookNumbers)->map(fn (int $bookNumber) => $bookIds[$bookNumber - 1])->all()
            );
        }
    }
}
