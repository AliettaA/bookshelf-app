<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        $genreIdsByName = Genre::pluck('id', 'name');

        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師の家に住みついた名もなき猫の視点から、明治時代の人間模様をユーモアと皮肉を込めて描いた夏目漱石のデビュー作。',
                'genres' => ['小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人間関係の基本原則を、豊富な実例とともに解説した自己啓発書の古典。批判しない、相手の立場に立つなど、今も通用する対人術を紹介する。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => '名前の付け方、コメントの書き方、制御フローの整理など、他の人が短時間で理解できる読みやすいコードを書くためのテクニックをまとめた実践的な一冊。',
                'genres' => ['技術書'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '「主体的である」「終わりを思い描くことから始める」など、人格を土台に成功を築くための7つの習慣を体系的に解説した世界的ベストセラー。',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '正義感が強く短気な江戸っ子の青年が、四国の中学校に数学教師として赴任し、同僚や生徒たちと巻き起こす騒動を痛快に描いた青春小説。',
                'genres' => ['小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => '認知革命、農業革命、科学革命という3つの転換点を軸に、ホモ・サピエンスが世界を支配するに至った歴史を大きな視点で捉え直す。',
                'genres' => ['歴史', '科学'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => '良いコードと悪いコードの違いを、命名・関数・クラス・テスト・リファクタリングなどの観点から具体例とともに示した、ソフトウェア開発者向けの定番書。',
                'genres' => ['技術書'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学の考え方を、悩める青年と哲人の対話形式でわかりやすく解説。課題の分離など、対人関係の悩みを軽くするヒントが得られる。',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => '売れない若手漫才師の徳永と、彼が師と仰ぐ先輩芸人・神谷との交流を通して、お笑いの世界で生きる人々の苦悩と情熱を描いた芥川賞受賞作。',
                'genres' => ['小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => '「分断本能」「ネガティブ本能」など10の思い込みを手がかりに、データに基づいて世界を正しく見るための習慣を紹介する。',
                'genres' => ['ビジネス', '科学'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_date' => '2007-01-18',
                'description' => '一つの金属製の箱「コンテナ」が、海運や港湾、そして世界経済のあり方をどのように変えたのかを描いた、物流の歴史を知るためのノンフィクション。',
                'genres' => ['ビジネス', '歴史'],
            ],
        ];

        foreach ($books as $index => $bookData) {
            $book = Book::firstOrCreate(
                ['isbn' => $bookData['isbn']],
                [
                    'user_id' => $user->id,
                    'title' => $bookData['title'],
                    'author' => $bookData['author'],
                    'published_date' => $bookData['published_date'],
                    'description' => $bookData['description'],
                    'image_url' => 'https://placehold.co/200x300/e2e8f0/475569?text='.($index + 1),
                ]
            );

            $book->genres()->sync($genreIdsByName->only($bookData['genres'])->values());
        }
    }
}
