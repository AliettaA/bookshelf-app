<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * レビューのいいねのテスト（ケースID L1〜L7）。
 */
class LikeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * L1【追加】レビューにいいねできる
     */
    public function test_l1_user_can_like_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $this->actingAs($user)
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book_id))
            ->assertSessionMissing('success')
            ->assertSessionMissing('error');

        $this->assertDatabaseHas('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
        $this->assertDatabaseCount('review_likes', 1);
    }

    /**
     * L2【取消】いいね済みで押すと取り消される
     */
    public function test_l2_pressing_again_removes_like(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $user->likedReviews()->attach($review->id);

        $this->actingAs($user)
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book_id))
            ->assertSessionMissing('success');

        $this->assertDatabaseMissing('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
    }

    /**
     * L3【切り替え】追加→取消→再追加が正しく切り替わる
     */
    public function test_l3_toggle_switches_between_like_unlike_and_like_again(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();
        $like = ['user_id' => $user->id, 'review_id' => $review->id];

        // 1回目：いいね
        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertDatabaseHas('review_likes', $like);

        // 2回目：取消
        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertDatabaseMissing('review_likes', $like);

        // 3回目：再いいね（重複せず1件になる）
        $this->actingAs($user)->post(route('reviews.like', $review));
        $this->assertDatabaseHas('review_likes', $like);
        $this->assertDatabaseCount('review_likes', 1);
    }

    /**
     * L4【表示】いいね数と状態が書籍詳細に表示される
     */
    public function test_l4_book_show_displays_like_count_and_state(): void
    {
        $liker = User::factory()->create();
        $otherUser = User::factory()->create();
        $review = Review::factory()->create();
        $liker->likedReviews()->attach($review->id);

        // いいねした本人：「いいね済み (1)」
        $this->actingAs($liker)->get(route('books.show', $review->book_id))
            ->assertOk()
            ->assertSee('いいね済み (1)')
            ->assertDontSee('いいね (1)');

        // 他のユーザー：「いいね (1)」
        $this->actingAs($otherUser)->get(route('books.show', $review->book_id))
            ->assertOk()
            ->assertSee('いいね (1)')
            ->assertDontSee('いいね済み');
    }

    /**
     * L5【追加】自分のレビューにもいいねできる
     */
    public function test_l5_user_can_like_own_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('reviews.like', $review))
            ->assertRedirect(route('books.show', $review->book_id));

        $this->assertDatabaseHas('review_likes', ['user_id' => $user->id, 'review_id' => $review->id]);
    }

    /**
     * L6【権限】ゲストはいいねできない
     */
    public function test_l6_guest_cannot_like_review(): void
    {
        $review = Review::factory()->create();

        $this->post(route('reviews.like', $review))
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('review_likes', 0);
    }

    /**
     * L7【異常系】存在しないレビューにはいいねできない
     */
    public function test_l7_cannot_like_nonexistent_review(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/reviews/9999/like')
            ->assertNotFound();

        $this->assertDatabaseCount('review_likes', 0);
    }
}
